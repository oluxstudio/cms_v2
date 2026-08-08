<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ULID conversion — every app table's auto-increment PK becomes a ULID
 * (char 26, time-ordered, index-friendly), and every referencing column
 * follows. Sequential ids leaked table sizes and growth ("German tank
 * problem"); ULIDs are opaque while keeping B-tree insert locality.
 *
 * Introspection-driven: declared FKs (and their ON DELETE rules) are read
 * from information_schema, converted, and recreated identically. Known
 * UNDECLARED references and data-embedded ids (nodes.parent self-reference,
 * collection ids stored in node values, polymorphic activity entity_id)
 * are handled explicitly. Runs on populated AND fresh databases.
 *
 * Framework tables (jobs, cache, sessions, migrations, …) keep their ids —
 * they're never exposed. Pure pivots keep their own row ids too; only their
 * FK columns convert.
 */
return new class extends Migration
{
    /** Tables whose PRIMARY KEY becomes a ULID. */
    private const TABLES = [
        'users', 'sites', 'pages', 'components', 'nodes', 'posts',
        'collections', 'collection_items', 'media', 'forms', 'form_responses',
        'contacts', 'contact_submissions', 'activities', 'alerts', 'api_tokens',
        'account_members', 'account_subscriptions', 'roles', 'team_invitations',
        'bookings', 'booking_blocks', 'booking_types', 'services',
        'service_resources', 'service_departures', 'price_rules',
        'orders', 'order_items', 'products', 'invoices', 'donations',
        'subscriptions', 'estimates', 'estimators', 'estimator_calcs',
        'estimator_fields', 'messages', 'modules', 'page_attributes',
        'site_activity_logs', 'site_attributes', 'site_features',
        'site_github_settings', 'site_payment_settings', 'task_logs',
        'todos', 'todo_items', 'wireframes', 'wireframe_blocks',
        'block_batches', 'block_layouts', 'block_presets',
        'builder_templates', 'templates', 'template_versions',
        'template_purchases', 'template_ratings', 'template_submissions',
        'template_entitlements', 'site_templates',
    ];

    // NB: `blocks` keeps its native varchar(24) `blk_*` ids — never convert it.

    /** References with NO declared FK constraint: table.column => referenced table. */
    private const UNDECLARED = [
        ['components', 'site_id', 'sites'],
        ['nodes', 'component_id', 'components'],
        ['posts', 'user_id', 'users'],
        ['media', 'site_template_id', 'site_templates'],
        ['templates', 'latest_version_id', 'template_versions'],
        ['template_entitlements', 'purchase_id', 'template_purchases'],
        ['sessions', 'user_id', 'users'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return; // sqlite test rigs etc. rebuild from models directly
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 1 · Capture declared FKs (with delete rules) then drop the constraints.
        $declared = $this->declaredForeignKeys();
        foreach ($declared as $fk) {
            try {
                DB::statement("ALTER TABLE `{$fk->t}` DROP FOREIGN KEY `{$fk->name}`");
            } catch (Throwable) {
                // already dropped on a previous partial run
            }
        }

        // 2 · Mint a ULID per row of every converted table (ordered so
        //     relative age survives into the new key order).
        $maps = [];
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($this->alreadyConverted($table)) {
                $maps[$table] = []; // nothing left to remap for this table

                continue;
            }
            if (! Schema::hasColumn($table, '__ulid')) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `__ulid` CHAR(26) NULL");
            }
            foreach (DB::table($table)->whereNull('__ulid')->orderBy('id')->pluck('id') as $oldId) {
                DB::table($table)->where('id', $oldId)->update(['__ulid' => strtolower((string) Str::ulid())]);
            }
            $maps[$table] = DB::table($table)->pluck('__ulid', 'id')->all();
        }

        // 3 · Convert every referencing column (declared + undeclared).
        $refs = collect($declared)->map(fn ($fk) => [$fk->t, $fk->c, $fk->rt])
            ->concat(self::UNDECLARED)
            ->filter(fn ($r) => isset($maps[$r[2]]) && Schema::hasTable($r[0]));

        foreach ($refs as [$table, $column, $refTable]) {
            $nullable = $this->isNullable($table, $column);
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(26) ".($nullable ? 'NULL' : 'NOT NULL'));
            foreach ($maps[$refTable] as $oldId => $ulid) {
                DB::table($table)->where($column, (string) $oldId)->update([$column => $ulid]);
            }
        }

        // 4 · Data-embedded references.
        //     nodes.parent — self reference by value, '0' = root.
        if (Schema::hasTable('nodes')) {
            DB::statement("ALTER TABLE `nodes` MODIFY `parent` VARCHAR(26) NOT NULL DEFAULT '0'");
            foreach ($maps['nodes'] ?? [] as $oldId => $ulid) {
                DB::table('nodes')->where('parent', (string) $oldId)->update(['parent' => $ulid]);
            }
            //  collection-typed node values hold a Collection id.
            foreach ($maps['collections'] ?? [] as $oldId => $ulid) {
                DB::table('nodes')->where('type', 'collection')->where('value', (string) $oldId)
                    ->update(['value' => $ulid]);
            }
        }
        //     site_activity_logs.entity_id — polymorphic by entity_type.
        if (Schema::hasTable('site_activity_logs')) {
            DB::statement('ALTER TABLE `site_activity_logs` MODIFY `entity_id` VARCHAR(26) NULL');
            foreach (['form' => 'forms', 'form_response' => 'form_responses', 'media' => 'media', 'page' => 'pages',
                'post' => 'posts', 'booking' => 'bookings', 'invoice' => 'invoices', 'contact' => 'contacts',
                'estimate' => 'estimates', 'interest' => 'contacts'] as $type => $refTable) {
                foreach ($maps[$refTable] ?? [] as $oldId => $ulid) {
                    DB::table('site_activity_logs')->where('entity_type', $type)
                        ->where('entity_id', (string) $oldId)->update(['entity_id' => $ulid]);
                }
            }
        }

        // 5 · Swap the primary keys: drop auto-increment id, promote the ULID.
        foreach (self::TABLES as $table) {
            if (! isset($maps[$table]) || $this->alreadyConverted($table) || ! Schema::hasColumn($table, '__ulid')) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL"); // shed AUTO_INCREMENT
            DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, DROP COLUMN `id`, CHANGE `__ulid` `id` CHAR(26) NOT NULL, ADD PRIMARY KEY (`id`)");
        }

        // 6 · Recreate the declared FK constraints with their original rules.
        foreach ($declared as $fk) {
            $rule = in_array($fk->rule, ['CASCADE', 'SET NULL'], true) ? " ON DELETE {$fk->rule}" : '';
            DB::statement("ALTER TABLE `{$fk->t}` ADD CONSTRAINT `{$fk->name}` FOREIGN KEY (`{$fk->c}`) REFERENCES `{$fk->rt}` (`id`){$rule}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // One-way door: reconstructing auto-increment ids is not supported.
    }

    /** @return array<object{t: string, c: string, rt: string, name: string, rule: string}> */
    private function declaredForeignKeys(): array
    {
        $tables = "'".implode("','", self::TABLES)."'";

        return DB::select("
            SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt,
                   k.CONSTRAINT_NAME name, r.DELETE_RULE rule
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r
              ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IN ({$tables})
        ");
    }

    /** Has this table's PK already been swapped to char(26)? */
    private function alreadyConverted(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return true;
        }
        $type = DB::selectOne('
            SELECT DATA_TYPE dt, CHARACTER_MAXIMUM_LENGTH len FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = "id"
        ', [$table]);

        return $type?->dt === 'char' && (int) $type?->len === 26;
    }

    private function isNullable(string $table, string $column): bool
    {
        return DB::selectOne('
            SELECT IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ', [$table, $column])?->n === 'YES';
    }
};
