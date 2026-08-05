<?php

namespace App\Services;

use App\Contracts\LlmDriverInterface;
use App\Models\Site;
use App\Models\User;

/**
 * LLM front-end for "prompting a site".
 *
 * Assembles the system prompt from the instruction files in resources/ai/ and
 * delegates the actual inference + tool-use loop to the bound LlmDriverInterface
 * (AnthropicDriver or OllamaDriver, selected by LLM_DRIVER env).
 *
 * SiteTools::execute() is passed as a callable so the driver can fire tool calls
 * without depending on SiteTools directly.
 */
class SiteAgent
{
    public function __construct(
        private readonly LlmDriverInterface $driver,
        private readonly SiteTools $tools,
    ) {}

    public static function configured(): bool
    {
        $driver = config('services.llm.driver', 'anthropic');

        return match ($driver) {
            'ollama'    => filled(config('services.ollama.base_url')),
            'deepseek'  => filled(config('services.deepseek.key')),
            default     => filled(config('services.anthropic.key')),
        };
    }

    /**
     * Quick TCP check — can the container actually reach the LLM endpoint right now?
     * Used to surface a friendly status in the UI without waiting for a full request.
     */
    public static function reachable(): bool
    {
        if (! static::configured()) return false;

        $driver = config('services.llm.driver', 'anthropic');

        if ($driver === 'ollama') {
            $url  = rtrim(config('services.ollama.base_url', ''), '/v1');
            $url  = rtrim($url, '/') . '/api/tags';
            $ch   = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_NOBODY         => false,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $res !== false && $code === 200;
        }

        // For Anthropic we assume reachable if the key is set.
        return true;
    }

    /**
     * Translate a raw exception into a user-friendly message.
     */
    private function friendlyError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'Failed to connect')) {
            $driver = config('services.llm.driver', 'anthropic');
            if ($driver === 'ollama') {
                return "Ollama is not running or not reachable.\n\n"
                    . "Please start Ollama on your machine, then try again.\n"
                    . "Expected at: " . config('services.ollama.base_url');
            }
            return "Could not connect to the AI service. Please check your API key and network connection.";
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'Operation timed out')) {
            return "The AI took too long to respond. "
                . (config('services.llm.driver') === 'ollama'
                    ? "Your Ollama model is running on CPU which can be slow. The request may still be processing — try a simpler prompt or wait a moment."
                    : "Please try again.");
        }

        if (str_contains($msg, 'does not support tools')) {
            return "The current Ollama model does not support tool-calling. "
                . "Please switch to qwen3:8b or qwen2.5:7b in your .env (OLLAMA_MODEL=qwen3:8b).";
        }

        return "The AI assistant encountered an error. Please try again.\n\nDetails: " . $msg;
    }

    /**
     * Read-only tools — running these does NOT count as "building" the site, so a
     * turn that only inspects (or only asks the user a question) won't trigger the
     * jump to the in-app preview.
     */
    private const READ_ONLY_TOOLS = [
        'site_info', 'site_status', 'list_pages', 'list_forms',
        'list_components', 'list_products', 'list_block_types', 'list_modules',
    ];

    /**
     * @param  array<int,array{role:string,text:string}>  $history  Prior plain-text turns.
     * @return array{ok:bool,text:string,built:bool,tools:array<int,string>}
     */
    public function ask(Site $site, User $user, string $prompt, array $history = []): array
    {
        $system   = $this->systemPrompt($site, $user);
        $toolDefs = $this->tools->definitions($site, $user);

        // Record which tools the model actually ran this turn, so the caller can
        // tell "asked a clarifying question" (no writes) from "built the app".
        $executed    = [];
        $executeTool = function (string $name, array $input) use ($site, $user, &$executed) {
            $executed[] = $name;
            return $this->tools->execute($site, $user, $name, $input);
        };

        $messages = [];
        foreach (array_slice($history, -8) as $turn) {
            $messages[] = [
                'role'    => $turn['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $turn['text'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $text = $this->driver->chat($system, $messages, $toolDefs, $executeTool);
        } catch (\Throwable $e) {
            return ['ok' => false, 'text' => $this->friendlyError($e), 'built' => false, 'page' => null, 'tools' => $executed];
        }

        $built = count(array_diff($executed, self::READ_ONLY_TOOLS)) > 0;

        // When something was built, point the preview at the page most likely just
        // worked on: the most-recently-updated published page.
        $page = $built
            ? $site->pages()->where('is_published', true)->orderByDesc('updated_at')->value('url')
            : null;

        return ['ok' => true, 'text' => $text, 'built' => $built, 'page' => $page, 'tools' => $executed];
    }

    // ── System prompt assembly ─────────────────────────────────────────

    private function systemPrompt(Site $site, User $user): string
    {
        $role     = $site->canManageTeam($user) ? 'owner/admin' : 'member';
        $features = collect(array_keys(config('features', [])))
            ->filter(fn ($f) => $site->hasFeature($f))->values()->join(', ') ?: 'none';

        // Local models (Ollama) have limited context windows — use compact prompt.
        if ($this->driver->prefersCompactPrompt()) {
            return $this->compactPrompt($site, $role, $features);
        }

        if ($composed = $this->loadInstructionSet($role, $features, $site)) {
            return $composed;
        }

        return $this->fallbackPrompt($site, $role, $features);
    }

    /**
     * Compact system prompt for local models with limited context (Ollama etc.).
     * Covers the full intent table and critical chaining rules without the heavy recipe files.
     */
    /**
     * Compact runtime prompt for local models (Ollama).
     *
     * When using the `cms-operator` custom model (built from Modelfile.cms), all CMS knowledge
     * is already baked in. This prompt only needs to supply the live session context so the
     * context window isn't wasted repeating static instructions every request.
     *
     * For generic models (deepseek-r1:7b, qwen3:8b) that haven't been built into cms-operator,
     * a brief reminder of the critical rules is included as a fallback.
     */
    private function compactPrompt(Site $site, string $role, string $features): string
    {
        $pageCount  = $site->pages()->count();
        $published  = $site->pages()->where('is_published', true)->count();
        $formCount  = $site->forms()->count();
        $isCmsModel = str_contains(strtolower(config('services.ollama.model', '')), 'cms-operator');

        $session = <<<TXT
        ## Session
        Site: "{$site->name}" | Role: {$role} | Enabled features: {$features}
        Pages: {$pageCount} total, {$published} published. Forms: {$formCount}.

        Act only on this site. Use tools to make real changes. Reply in 1-2 sentences.
        TXT;

        $session .= "\n\n" . $this->moduleContext($site);

        // cms-operator already knows the CMS — just inject live state.
        if ($isCmsModel) {
            return trim($session);
        }

        // For other local models, include a brief critical-rules reminder.
        return trim($session) . "\n\n" . <<<TXT

        ## Critical rules (follow these exactly)
        - "create page with content" → create_page, THEN create_component, THEN add_node (3-6 nodes with real values). NEVER stop after create_page.
        - "create content for X page" → list_pages first; if page exists skip create_page; then create_component + add_node.
        - "create form with fields" → create_form, THEN add_form_field for each. Infer types: email→email, phone→tel, message/notes/comment→textarea, date→date, else→text.
        - Questions (when created, page list, form count) → call site_info or list_pages or list_forms.
        TXT;
    }

    /**
     * One-block summary of the site's modules (built-in + declarative) so the agent
     * routes a capability request to an existing module instead of reinventing it.
     */
    private function moduleContext(Site $site): string
    {
        $installed = [];
        $available = [];
        foreach (\App\Modules\ModuleRegistry::capabilityMap($site) as $m) {
            $line = "{$m['key']} ({$m['name']})";
            $m['enabled'] ? ($installed[] = $line) : ($available[] = $line);
        }

        return "## Modules / capabilities\n"
            . 'Installed: ' . (implode(', ', $installed) ?: 'none') . "\n"
            . 'Available to enable: ' . (implode(', ', $available) ?: 'none') . "\n"
            . 'When the user asks for a capability, map it to one of these (call list_modules for the intent keywords). '
            . 'Prefer built-in features; only use create_module when nothing existing fits. See references/modules.md.';
    }

    /**
     * Assemble the system prompt from the persistent instruction files in resources/ai.
     * Returns null when files are missing so the caller falls back to the inline prompt.
     */
    private function loadInstructionSet(string $role, string $features, Site $site): ?string
    {
        $base  = resource_path('ai');
        $parts = [
            $base . '/System-Prompt.txt',
            $base . '/AI-Persona.md',
            $base . '/My-AI-Instructions.md',
            $base . '/Prompt-Handling.md',
            $base . '/skills/cms-operator/SKILL.md',
            $base . '/skills/cms-operator/references/node-types.md',
            $base . '/skills/cms-operator/references/crud-recipes.md',
            $base . '/skills/cms-operator/references/modules.md',
        ];

        $chunks = [];
        foreach ($parts as $path) {
            if (! is_file($path)) {
                return null;
            }
            $chunks[] = trim((string) file_get_contents($path));
        }

        $session = "## This session\nSelected site: \"{$site->name}\" (slug). "
            . "User role: {$role}. Enabled features: {$features}.\n"
            . 'Act only on this site. Use tools to make real changes; confirm in one or two plain sentences.'
            . "\n\n" . $this->moduleContext($site);

        return implode("\n\n---\n\n", [...$chunks, $session]);
    }

    private function fallbackPrompt(Site $site, string $role, string $features): string
    {
        return <<<TXT
        You are the in-app AI assistant for Olux CMS. You help the user operate ONE selected site
        through its CMS/CRM using the tools provided.

        ## Domain model
        A SITE has PAGES (ordered list of COMPONENTS). A COMPONENT has NODES (key/value fields with
        types: text, number, boolean, color, url, image). A FORM has FIELDS and collects RESPONSES.

        ## CRITICAL: when asked to create a page with content, always chain:
        1. create_page
        2. create_component (one or more)
        3. add_node (multiple nodes with real, topic-appropriate values)
        Never stop after create_page alone when content was requested.

        ## This session
        Selected site: "{$site->name}". User role: {$role}. Enabled features: {$features}.
        Use tools to make real changes. Confirm in one or two plain sentences.
        TXT;
    }
}
