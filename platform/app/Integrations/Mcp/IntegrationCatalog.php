<?php

declare(strict_types=1);

namespace App\Integrations\Mcp;

/** Roadmap entries, not pretend OAuth connections. No credentials are collected for these services. */
final class IntegrationCatalog
{
    /** @return list<array<string, mixed>> */
    public static function planned(): array
    {
        $rows = [
            ['google-drive', 'Google Drive', 'workspace', 'folder', 'Keep project files and shared folders together.', 'A Google Cloud OAuth app, a verified redirect URI and least-privilege Drive scopes. Shared business account consent and encrypted refresh-token storage are not implemented yet.', 'https://developers.google.com/drive/api/guides/about-sdk'],
            ['google-sheets', 'Google Sheets', 'workspace', 'chart', 'Move approved reports into business spreadsheets.', 'Google OAuth consent and Sheets scopes, spreadsheet selection and an explicit data-sharing policy. Read/write synchronization is not implemented.', 'https://developers.google.com/workspace/sheets/api/guides/concepts'],
            ['gmail', 'Gmail', 'workspace', 'mail', 'Bring business email into your workspace.', 'Google OAuth and Gmail scopes, consent verification where required, encrypted token storage and approval rules for sending. SMTP email is separate from Gmail OAuth.', 'https://developers.google.com/workspace/gmail/api/guides'],
            ['google-calendar', 'Google Calendar', 'workspace', 'calendar', 'Coordinate appointments and team availability.', 'Google OAuth, calendar selection, timezone handling and consent for calendar access. Calendar synchronization is not implemented.', 'https://developers.google.com/workspace/calendar/api/guides/overview'],
            ['google-meet', 'Google Meet', 'workspace', 'video', 'Add meeting links to approved appointments.', 'Google Workspace permissions and OAuth. Meeting creation or Calendar conference data needs a dedicated implementation; connecting Drive does not connect Meet.', 'https://developers.google.com/workspace/meet/api/guides/overview'],
            ['notion', 'Notion', 'workspace', 'book', 'Share approved knowledge and project notes.', 'A Notion integration, explicitly shared pages and encrypted credentials. No Notion pages are accessible through this release.', 'https://developers.notion.com/docs/getting-started'],
            ['whatsapp', 'WhatsApp Business', 'communication', 'whatsapp', 'Connect customer messaging to the team inbox.', 'Meta business setup, a verified phone number, private Cloud API credentials and signed webhooks. This hub does not yet provide a guided connection flow.', 'https://developers.facebook.com/docs/whatsapp/cloud-api/'],
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
