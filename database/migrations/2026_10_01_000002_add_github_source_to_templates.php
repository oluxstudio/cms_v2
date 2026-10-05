<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remember the GitHub repo a store template is built from, so a super admin
 * can push to the repo and press "Update from GitHub" to build a new version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('source_repo', 300)->nullable()->after('builtin_key');
            $table->string('source_branch', 100)->nullable()->after('source_repo');
        });
        Schema::table('template_uploads', function (Blueprint $table) {
            $table->string('repo_url', 300)->nullable()->after('original_filename');
            $table->string('repo_branch', 100)->nullable()->after('repo_url');
        });

        // Backfill: GitHub imports recorded "url#branch" as their filename.
        foreach (DB::table('template_uploads')->where('original_filename', 'like', 'https://github.com/%')->orderBy('created_at')->get() as $u) {
            [$url, $branch] = array_pad(explode('#', (string) $u->original_filename, 2), 2, null);
            $url = preg_replace('#(\.git)?/?$#', '', trim((string) $url));
            DB::table('template_uploads')->where('id', $u->id)->update(['repo_url' => $url, 'repo_branch' => $branch ?: null]);
            $templateId = $u->template_id ?: $u->replaces_template_id;
            if ($templateId) {
                DB::table('templates')->where('id', $templateId)->update(['source_repo' => $url, 'source_branch' => $branch ?: null]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('templates', fn (Blueprint $table) => $table->dropColumn(['source_repo', 'source_branch']));
        Schema::table('template_uploads', fn (Blueprint $table) => $table->dropColumn(['repo_url', 'repo_branch']));
    }
};
