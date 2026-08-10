<?php

namespace App\Templates;

class TemplateRegistry
{
    /**
     * All available templates: the hard-coded class templates plus any on-disk
     * split-file packages under resources/templates/. A package overrides a class
     * template that shares its key (so a package is the canonical source).
     *
     * @return TemplateContract[]
     */
    public static function all(): array
    {
        $classes = [
            new BlankTemplate,
        ];

        $byKey = [];
        foreach ($classes as $t) {
            $byKey[$t->key()] = $t;
        }
        foreach (TemplatePackage::discover() as $pkg) {
            $byKey[$pkg->key()] = $pkg; // packages win on key collision
        }

        return array_values($byKey);
    }

    public static function find(string $key): ?TemplateContract
    {
        return collect(static::all())->first(fn (TemplateContract $t) => $t->key() === $key);
    }
}
