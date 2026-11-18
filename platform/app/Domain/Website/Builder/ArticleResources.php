<?php

declare(strict_types=1);

namespace App\Domain\Website\Builder;

use App\Domain\Website\Studio\StudioResources;
use RuntimeException;

/** Explicit resource references, never legacy tpl/faqGroup IDs or implicit defaults. */
final class ArticleResources
{
    /** @param array<string, mixed> $data
     * @return array{data: array<string, mixed>, template: array<string, mixed>|null, state: list<array<string, mixed>>}
     */
    public static function resolve(array $data, string $type): array
    {
        $state = [];
        $template = null;
        foreach (['templateResource' => ['template', $type], 'faqResource' => ['faq_group', 'shared']] as $key => [$kind, $scope]) {
            $id = self::reference($data[$key] ?? null);
            if ($id === 0) {
                continue;
            }
            $snapshot = StudioResources::published($id, $kind, $scope);
            $state[] = ['id' => $id, 'kind' => $kind, 'scope' => $scope,
                'revision' => $snapshot['revision'], 'hash' => $snapshot['hash']];
            if ($kind === 'template') {
                $template = $snapshot['data'];
            } else {
                // A linked published group replaces rendered FAQs only. Local FAQs stay in the saved payload.
                $data['faqs'] = $snapshot['data']['items'];
            }
        }

        return ['data' => $data, 'template' => $template, 'state' => $state];
    }

    private static function reference(mixed $value): int
    {
        if (in_array($value, [null, '', 0], true)) {
            return 0;
        }
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException('Select a published resource using its numeric resource ID. The saved draft is safe.');
        }

        return $value;
    }
}
