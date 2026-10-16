<?php

declare(strict_types=1);

namespace App\Integrations\WhatsApp\Contracts;

use App\Integrations\WhatsApp\SendResult;

/**
 * WhatsApp boundary (D-23, WAP-001…WAP-005). Real client: WhatsAppCloudApi. Tests: FakeWhatsAppGateway.
 *
 * Everything the app knows about WhatsApp goes through here, so P4 can be tested without Meta, and Meta's
 * payload shapes stay in one file.
 */
interface WhatsAppGateway
{
    /** Credentials present, in the dashboard or in the server .env? Nothing is sent when they are not. */
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

    /**
     * What Meta says about the phone number we send from — the "Test connection" button (WAP-001).
     *
     * @return array{ok: bool, display_phone_number: ?string, verified_name: ?string, quality_rating: ?string, error: ?string}
     */
    public function accountInfo(): array;

    /**
     * Every template the business account holds, approved or not (WAP-005).
     *
     * @return list<array{id: string, name: string, language: string, category: string, status: string, components: list<array<string, mixed>>, rejected_reason: ?string}>
     */
    public function templates(): array;

    /**
     * Submit a template for Meta's approval (WAP-005). Nothing can be sent with it until Meta says approved.
     *
     * @param  list<string>  $exampleValues  One example per {{1}}…{{n}} in the body: Meta needs to see what the variables look like
     * @return array{ok: bool, id: ?string, status: ?string, error: ?string}
     */
    public function submitTemplate(string $name, string $language, string $category, string $body, array $exampleValues = []): array;
}
