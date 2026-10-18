<?php

declare(strict_types=1);

namespace App\Domain\Website\Seo;

use App\Settings\BotSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The SEO agent's second half (V2-3 F8, docs/14 §5a): with the studio's model key in place (the one
 * key, from the Chat Bot screen — the BOT pattern), the agent proposes a title and a meta
 * description for a page. It proposes; the owner reads and clicks Apply — a proposal never lands
 * on the site by itself.
 *
 * Keyless (provider "none" or empty key): propose() returns null and the screen shows the unlock
 * note instead. Everything else on the SEO screen stays fully keyless.
 */
final class ProposeSeoCopy
{
    public function __construct(private readonly BotSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->provider !== 'none' && trim((string) $this->settings->api_key) !== '';
    }

    /**
     * A proposal for one page, or null when the model is not funded or answers nonsense.
     *
     * @return array{title: string, description: string}|null
     */
    public function propose(string $title, string $content): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $system = <<<'TXT'
            You write search-engine metadata for an interior design studio's website.
            Answer with JSON only, no markdown: {"title": string, "description": string}.
            Rules: title at most 60 characters, plain words, no clickbait, keep the page's subject;
            description one or two sentences, at most 155 characters, says what the page offers.
            TXT;

        $user = "Page title: {$title}\n\nPage content:\n{$content}";

        $text = match ($this->settings->provider) {
            'openai' => $this->openai($system, $user),
            'gemini' => $this->gemini($system, $user),
            default => $this->anthropic($system, $user),
        };

        if ($text === null) {
            return null;
        }

        $json = json_decode(trim($text, " \t\n\r\0\x0B\"`"), true);

        if (! is_array($json) || ! isset($json['title'], $json['description'])) {
            return null;
        }

        return ['title' => mb_substr(trim((string) $json['title']), 0, 65), 'description' => mb_substr(trim((string) $json['description']), 0, 160)];
    }

    private function openai(string $system, string $user): ?string
    {
        try {
            $response = Http::timeout(20)->connectTimeout(5)
                ->withToken((string) $this->settings->api_key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => trim((string) $this->settings->model) !== '' ? trim((string) $this->settings->model) : 'gpt-4o-mini',
                    'max_tokens' => 300,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? (string) $response->json('choices.0.message.content', '') : null;
    }

    private function anthropic(string $system, string $user): ?string
    {
        try {
            $response = Http::timeout(20)->connectTimeout(5)
                ->withHeaders(['x-api-key' => (string) $this->settings->api_key])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => trim((string) $this->settings->model) !== '' ? trim((string) $this->settings->model) : 'claude-3-5-haiku-latest',
                    'max_tokens' => 300,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ]);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? (string) $response->json('content.0.text', '') : null;
    }

    private function gemini(string $system, string $user): ?string
    {
        try {
            $response = Http::timeout(20)->connectTimeout(5)
                ->withQueryParameters(['key' => (string) $this->settings->api_key])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.(trim((string) $this->settings->model) !== '' ? trim((string) $this->settings->model) : 'gemini-1.5-flash').':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['parts' => [['text' => $user]]]],
                ]);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? (string) $response->json('candidates.0.content.parts.0.text', '') : null;
    }
}
