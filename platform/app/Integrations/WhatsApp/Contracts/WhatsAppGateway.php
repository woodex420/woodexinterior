<?php

declare(strict_types=1);

namespace App\Integrations\WhatsApp\Contracts;

use App\Integrations\WhatsApp\SendResult;

/**
 * WhatsApp boundary (D-23, WAP-001…WAP-004). Real client: WhatsAppCloudApi. Tests: FakeWhatsAppGateway.
 *
 * Everything the app knows about WhatsApp goes through here, so P4 can be tested without Meta, and Meta's
 * payload shapes stay in one file.
 */
interface WhatsAppGateway
{
    /** Credentials present in the server .env? Nothing is sent (and no screen promises anything) when they are not. */
    public function isConfigured(): bool;

    /** A free-form message, only valid inside the 24-hour window (WAP-004). */
    public function sendText(string $to, string $body): SendResult;

    /**
     * An approved template, for outside the window (WAP-004).
     *
     * @param  list<array<string, mixed>>  $components  Body/button components as Meta expects them
     */
    public function sendTemplate(string $to, string $template, string $language = 'en', array $components = []): SendResult;

    /**
     * Where a piece of inbound media can be fetched from (WAP-003).
     *
     * @return array{url: string, mime_type: string, sha256: string, file_size: int}|null
     */
    public function media(string $mediaId): ?array;
}
