<?php

namespace App\Services\Modules;

use App\Models\Collection;
use App\Models\Module;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Provisions declarative modules: an entity (Collection + JSON field schema) plus a
 * Module record describing its capabilities and frontend. No code is generated or
 * executed — a module is data. Records are stored as CollectionItems and served by
 * ModuleController; the frontend renders via the generic `module` wireframe block.
 */
class DeclarativeModuleEngine
{
    /**
     * Create a declarative module from a name + normalized fields + capabilities.
     *
     * @param  array<int,array{key:string,label:string,type:string,required:bool,options:array}>  $fields
     * @param  array{public_list:bool,public_submit:bool}  $caps
     */
    public function provision(Site $site, string $name, array $fields, array $caps, ?User $user = null): Module
    {
        $slug = Str::slug($name);
        $publicList   = (bool) ($caps['public_list'] ?? false);
        $publicSubmit = (bool) ($caps['public_submit'] ?? true);

        $collection = $site->collections()->create([
            'name'         => $name,
            'slug'         => $slug,
            'type'         => $publicList ? 'grid' : 'list',
            'description'  => "Entries for the {$name} module.",
            'fields'       => $fields,
            'is_public'    => $publicList,
            'allow_submit' => $publicSubmit,
        ]);

        $capabilities = [
            'list'   => $publicList,
            'get'    => $publicList,
            'submit' => $publicSubmit,
        ];

        return $site->modules()->create([
            'key'           => $slug,
            'name'          => $name,
            'description'   => $this->describe($name, $capabilities),
            'icon'          => 'puzzle',
            'collection_id' => $collection->id,
            'schema'        => $fields,
            'capabilities'  => $capabilities,
            'frontend'      => ['block' => 'module', 'variant' => $publicList ? 'grid' : 'list', 'title' => $name],
            'intents'       => $this->intentsFor($name),
            'created_by'    => $user?->id,
            'enabled'       => true,
        ]);
    }

    /** Frontend block descriptor for attaching this module to a wireframe. */
    public function blockDescriptor(Module $module): array
    {
        return [
            'type'    => $module->frontend['block'] ?? 'module',
            'variant' => $module->frontend['variant'] ?? 'grid',
        ];
    }

    private function describe(string $name, array $caps): string
    {
        $verbs = [];
        if ($caps['submit'] ?? false) $verbs[] = 'collect';
        if ($caps['list'] ?? false)   $verbs[] = 'list';

        return $verbs ? (ucfirst(implode(' + ', $verbs)) . ' ' . Str::lower($name)) : $name;
    }

    /** Keyword intents derived from the module name (for LLM routing / dedup). */
    private function intentsFor(string $name): array
    {
        $words = collect(preg_split('/\s+/', Str::lower(trim($name))))->filter()->values();

        return $words
            ->merge([Str::lower($name), Str::singular(Str::lower($name))])
            ->merge($words->map(fn ($w) => Str::singular($w)))
            ->unique()->values()->all();
    }
}
