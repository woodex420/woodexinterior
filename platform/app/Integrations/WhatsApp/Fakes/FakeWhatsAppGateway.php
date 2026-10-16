<?php

declare(strict_types=1);

namespace App\Integrations\WhatsApp\Fakes;

use App\Integrations\WhatsApp\Contracts\WhatsAppGateway;
use App\Integrations\WhatsApp\SendResult;

/**
 * Test double for the WhatsApp gateway: remembers what was sent and can be told to fail, so P4 can be tested
 * without Meta and without network.
 */
final class FakeWhatsAppGateway implements WhatsAppGateway
{
    /** @var list<array{to: string, body: ?string, template: ?string, language: string, components: list<array<string, mixed>>}> */
    public array $sent = [];

    /** @var list<array<string, mixed>> */
    public array $submitted = [];

    /** @var list<array<string, mixed>> */
    private array $templates = [];

    public function __construct(
        private bool $configured = true,
        private bool $succeeds = true,
        private string $error = 'Meta refused the message.',
    ) {}

    public function configured(bool $configured = true): self
    {
        $this->configured = $configured;

        return $this;
    }

    public function fails(string $error = 'Meta refused the message.'): self
    {
        $this->succeeds = false;
        $this->error = $error;

        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function sendText(string $to, string $body): SendResult
    {
        $this->sent[] = ['to' => $to, 'body' => $body, 'template' => null, 'language' => 'en', 'components' => []];

        return $this->result();
    }

    public function sendTemplate(string $to, string $template, string $language = 'en', array $components = []): SendResult
    {
        $this->sent[] = ['to' => $to, 'body' => null, 'template' => $template, 'language' => $language, 'components' => $components];

        return $this->result();
    }

    /**
     * @return array{url: string, mime_type: string, sha256: string, file_size: int}
     */
    public function media(string $mediaId): array
    {
        return [
            'url' => 'https://lookaside.fbsbx.com/whatsapp/'.$mediaId,
            'mime_type' => 'image/jpeg',
            'sha256' => 'abc',
            'file_size' => 1024,
        ];
    }

    public function accountInfo(): array
    {
        if (! $this->configured) {
            return ['ok' => false, 'display_phone_number' => null, 'verified_name' => null, 'quality_rating' => null, 'error' => 'WhatsApp is not connected yet.'];
        }

        return [
            'ok' => true,
            'display_phone_number' => '+92 300 1234567',
            'verified_name' => 'Woodex Interior',
            'quality_rating' => 'GREEN',
            'error' => null,
        ];
    }

    public function templates(): array
    {
        return $this->templates;
    }

    public function submitTemplate(string $name, string $language, string $category, string $body, array $exampleValues = []): array
    {
        $this->submitted[] = ['name' => $name, 'language' => $language, 'category' => $category, 'body' => $body, 'examples' => $exampleValues];

        return ['ok' => true, 'id' => 'meta-'.bin2hex(random_bytes(4)), 'status' => 'PENDING', 'error' => null];
    }

    /**
     * What "Test connection" and the template sync will see.
     *
     * @param  list<array<string, mixed>>  $templates
     */
    public function withTemplates(array $templates): self
    {
        $this->templates = $templates;

        return $this;
    }

    private function result(): SendResult
    {
        if (! $this->configured) {
            return SendResult::failed('WhatsApp is not configured in the server .env.');
        }

        return $this->succeeds
            ? SendResult::sent('wamid.'.bin2hex(random_bytes(8)))
            : SendResult::failed($this->error, 400);
    }
}
