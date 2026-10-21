<?php

declare(strict_types=1);

namespace App\Integrations\Mcp;

/**
 * The services this build cannot connect yet. Google data access is no longer listed here: it is a real
 * connector (`GoogleWorkspaceConnector`) with its own consent and scopes. These entries carry the exact
 * missing prerequisite and collect no credentials.
 */
final class IntegrationCatalog
{
    /** @return list<array<string, mixed>> */
    public static function planned(): array
    {
        $rows = [
            ['notion', 'Notion', 'workspace', 'book', 'Share approved knowledge and project notes.', 'A Notion integration, explicitly shared pages and encrypted credentials. No Notion pages are accessible through this release.', 'https://developers.notion.com/docs/getting-started'],
            ['slack', 'Slack', 'communication', 'message', 'Route approved updates to team channels.', 'A Slack app with bot OAuth scopes, workspace installation and an allowlist of channels. Message delivery is not implemented.', 'https://api.slack.com/start'],
            ['zoom', 'Zoom', 'communication', 'video', 'Schedule video calls from project workflows.', 'A Zoom OAuth app and meeting permissions. Scheduling and webhook signature verification are not implemented.', 'https://developers.zoom.us/docs/integrations/'],
            ['facebook', 'Facebook', 'social', 'megaphone', 'Capture enquiries from your business page.', 'A Meta app, page permissions, review where required and signed webhooks. No page or advertising account is connected by this hub.', 'https://developers.facebook.com/docs/'],
            ['instagram', 'Instagram', 'social', 'image', 'Bring business conversations into one place.', 'An eligible professional account, Meta permissions and signed webhooks. Messaging and publishing are not implemented here.', 'https://developers.facebook.com/docs/instagram-platform/'],
            ['linkedin', 'LinkedIn', 'social', 'building', 'Connect approved company-page workflows.', 'LinkedIn developer product access, company-page authorization and OAuth. Publishing and lead sync are not implemented.', 'https://learn.microsoft.com/en-us/linkedin/'],
        ];

        return array_map(fn (array $row): array => [
            'key' => $row[0], 'name' => $row[1], 'category' => $row[2], 'icon' => $row[3],
            'detail' => $row[4], 'requirements' => $row[5], 'docs' => $row[6],
            'phase' => 'Planned', 'configured' => false, 'last_ok' => null, 'last_error' => null,
        ], $rows);
    }
}
