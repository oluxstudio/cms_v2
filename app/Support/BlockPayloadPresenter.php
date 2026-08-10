<?php

namespace App\Support;

/**
 * Shapes a template payload block (plain arrays from a template.json / payload) into
 * the SAME renderable payload the live site API emits (see SiteContentController::
 * blockPayload), so a template PREVIEW renders with identical fidelity to an
 * installed site — type, variant, catalog shape, nodes and nested children.
 */
class BlockPayloadPresenter
{
    /**
     * @param  array<string,mixed>  $block
     * @param  string  $siteName  substituted for the {site_name} placeholder in node values
     */
    public static function fromArray(array $block, string $siteName = ''): array
    {
        $type = (string) ($block['type'] ?? '');
        $variant = $block['variant'] ?? 'default';

        return [
            'type' => $type,
            'variant' => $variant,
            'shape' => '',
            'name' => $block['name'] ?? ($def['name'] ?? $type),
            'component' => $block['name'] ?? null,
            'settings' => is_array($block['settings'] ?? null) ? $block['settings'] : null,
            'nodes' => self::nodes($block['nodes'] ?? [], $siteName),
            'children' => array_map(
                fn ($c) => self::fromArray(is_array($c) ? $c : [], $siteName),
                is_array($block['children'] ?? null) ? $block['children'] : []
            ),
        ];
    }

    /** @param array<int,mixed> $nodes */
    private static function nodes(array $nodes, string $siteName): array
    {
        return array_values(array_map(fn ($n) => [
            'label' => (string) ($n['label'] ?? ''),
            'type' => (string) ($n['type'] ?? 'text'),
            'value' => str_replace('{site_name}', $siteName, (string) ($n['value'] ?? '')),
            'order' => (int) ($n['order'] ?? 0),
        ], $nodes));
    }
}
