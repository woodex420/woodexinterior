<?php

declare(strict_types=1);

namespace App\Domain\Website\Studio;

use App\Domain\Website\Models\StudioResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Shared Studio resources, not public page writes. No legacy adapters, automatic defaults or fan-out.
 * All writes are explicit column assignments. Existing-row writes use a revision/token CAS as well as
 * a row lock; Publish promotes saved data and appends history in the same SQL transaction.
 */
final class StudioResources
{
    public function __construct(private readonly User $user) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function listing(array $input): array
    {
        $this->authorize();
        [$kind, $scope] = $this->context($input);
        $valid = Validator::make($input, ['page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'view' => ['sometimes', Rule::in(['draft', 'published'])]])->validate();
        $published = ($valid['view'] ?? 'draft') === 'published';
        $rows = StudioResource::query()->where('kind', $kind)->where('scope', $scope)
            ->when($published, fn ($query) => $query->whereNotNull('published_payload'))
            ->orderBy('id')->paginate(25, ['*'], 'page', (int) ($valid['page'] ?? 1));

        return ['items' => $rows->getCollection()->map(function (StudioResource $row) use ($published): array {
            $data = $published ? $row->published_payload : ($row->draft_payload ?? $row->published_payload);

            // Summary only: lists must not return up to 25 entire 100-question groups.
            return ['id' => $row->id, 'kind' => $row->kind, 'scope' => $row->scope,
                'name' => $data['name'] ?? '', 'has_draft' => $row->draft_payload !== null,
                'is_published' => $row->published_payload !== null];
        })->all(), 'page' => $rows->currentPage(), 'total' => $rows->total(), 'per_page' => 25];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function get(array $input): array
    {
        $this->authorize();
        [$kind, $scope] = $this->context($input);

        return ['item' => $this->item($this->find($this->id($input), $kind, $scope))];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $this->authorize();
        [$kind, $scope] = $this->context($input);
        $data = self::clean($kind, $input['data'] ?? null);
        $id = $this->id($input, true);
        if ($id === 0) {
            if (isset($input['revision']) || isset($input['token'])) {
                throw new RuntimeException('A new resource must not reuse an opened revision.');
            }
            $row = StudioResource::query()->create(['kind' => $kind, 'scope' => $scope, 'draft_payload' => $data,
                'revision' => 1, 'token' => (string) Str::uuid(), 'updated_by' => $this->user->id]);

            return ['item' => $this->item($row)];
        }

        return $this->change($input, $kind, $scope, fn (StudioResource $row): array => ['draft_payload' => $data]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function discard(array $input): array
    {
        $this->authorize();
        [$kind, $scope] = $this->context($input);

        // Retain identity and rotate the token even for an unpublished resource: no stale resurrection.
        return $this->change($input, $kind, $scope, fn (StudioResource $row): array => ['draft_payload' => null]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function publish(array $input): array
    {
        $this->authorize(true);
        [$kind, $scope] = $this->context($input);

        return $this->change($input, $kind, $scope, function (StudioResource $row) use ($input, $kind): array {
            if ($row->draft_payload === null) {
                throw new RuntimeException('There is no saved resource draft to publish.');
            }
            $data = self::clean($kind, $row->draft_payload);
            $hash = self::hash($data);
            $expected = $input['draft_hash'] ?? null;
            if (! is_string($expected) || ! hash_equals($hash, $expected)) {
                throw new RuntimeException('The saved resource draft changed. Reopen it before publishing.');
            }

            // No Page, block, cache, menu, listing or file is touched. Referring pages need explicit Publish.
            return ['draft_payload' => null, 'published_payload' => $data,
                'published_revision' => $row->revision + 1, 'published_hash' => $hash];
        }, true);
    }

    /**
     * Published-only renderer seam, context-bound and integrity-checked, without private edit metadata.
     * Cooperating resource writers wait for shared row locks held during article publication transactions.
     *
     * @return array<string, mixed>
     */
    public static function published(int $id, string $kind, string $scope): array
    {
        self::validateContext($kind, $scope);
        $row = StudioResource::query()->whereKey($id)->where('kind', $kind)->where('scope', $scope)
            ->whereNotNull('published_payload')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->sharedLock())->first();
        if ($row === null) {
            throw new RuntimeException('That shared resource has no published version in this context.');
        }

        $data = self::clean($kind, $row->published_payload);
        if (! is_string($row->published_hash) || ! hash_equals(self::hash($data), $row->published_hash) || $row->published_revision === null || $row->published_revision < 1) {
            throw new RuntimeException('That published resource snapshot is invalid. Reopen and explicitly publish a valid resource first.');
        }

        return ['id' => $row->id, 'kind' => $kind, 'scope' => $scope, 'data' => $data,
            'revision' => $row->published_revision, 'hash' => $row->published_hash];
    }

    private function authorize(bool $publish = false): void
    {
        abort_unless($this->user->can('website.edit') && (! $publish || $this->user->can('website.publish')), 403);
    }

    /** @param array<string, mixed> $input
     * @return array{string, string}
     */
    private function context(array $input): array
    {
        $valid = Validator::make($input, ['kind' => ['required', 'string', Rule::in(['template', 'faq_group'])],
            'scope' => ['required', 'string', Rule::in(['post', 'study', 'shared'])]])->validate();
        self::validateContext($valid['kind'], $valid['scope']);

        return [$valid['kind'], $valid['scope']];
    }

    private static function validateContext(string $kind, string $scope): void
    {
        if (! (($kind === 'template' && in_array($scope, ['post', 'study'], true)) || ($kind === 'faq_group' && $scope === 'shared'))) {
            throw new RuntimeException('Choose a Blog/Portfolio template or a shared FAQ group.');
        }
    }

    /** @param array<string, mixed> $input */
    private function id(array $input, bool $create = false): int
    {
        $valid = Validator::make(['id' => $input['id'] ?? ($create ? 0 : null)],
            ['id' => ['required', 'integer', 'min:'.($create ? 0 : 1)]])->validate();

        return (int) $valid['id'];
    }

    private function find(int $id, string $kind, string $scope, bool $lock = false): StudioResource
    {
        return StudioResource::query()->whereKey($id)->where('kind', $kind)->where('scope', $scope)
            ->when($lock, fn ($query) => $query->lockForUpdate())->first()
            ?? throw new RuntimeException('That resource is not in this list.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  callable(StudioResource): array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function change(array $input, string $kind, string $scope, callable $attributes, bool $publish = false): array
    {
        $id = $this->id($input);
        $expected = Validator::make($input, ['revision' => ['required', 'integer', 'min:1'], 'token' => ['required', 'string', 'uuid']])->validate();

        return DB::transaction(function () use ($id, $kind, $scope, $expected, $attributes, $publish): array {
            $row = $this->find($id, $kind, $scope, true);
            $revision = $row->revision;
            $token = $row->token;
            if ($revision !== (int) $expected['revision'] || ! hash_equals($token, $expected['token'])) {
                throw new RuntimeException('Another resource change won. Reopen it before retrying.');
            }
            $row->fill($attributes($row));
            $row->fill(['revision' => $revision + 1, 'token' => (string) Str::uuid(),
                'updated_by' => $this->user->id, 'updated_at' => now()]);
            // getDirty uses the model's JSON casts; mass request assignment is never used.
            $affected = StudioResource::query()->whereKey($id)->where('revision', $revision)->where('token', $token)->update($row->getDirty());
            if ($affected !== 1) {
                throw new RuntimeException('Another resource change won. Reopen it before retrying.');
            }
            if ($publish) {
                DB::table('website_studio_resource_versions')->insert(['resource_id' => $id,
                    'revision' => $row->published_revision, 'payload' => json_encode($row->published_payload, JSON_THROW_ON_ERROR),
                    'payload_hash' => $row->published_hash, 'published_by' => $this->user->id, 'created_at' => now()]);
            }

            return ['item' => $this->item($row->refresh())];
        });
    }

    /** @return array<string, mixed> */
    private function item(StudioResource $row): array
    {
        return ['id' => $row->id, 'kind' => $row->kind, 'scope' => $row->scope,
            'revision' => $row->revision, 'token' => $row->token,
            'has_draft' => $row->draft_payload !== null, 'data' => $row->draft_payload ?? $row->published_payload,
            'draft_hash' => $row->draft_payload !== null ? self::hash($row->draft_payload) : null,
            'published' => $row->published_payload, 'published_revision' => $row->published_revision,
            'published_hash' => $row->published_hash];
    }

    /** @param array<string, mixed> $data */
    private static function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Bounded structured data only; no code/URL fields, auto-default flags or fan-out commands.
     *
     * @return array<string, mixed>
     */
    private static function clean(string $kind, mixed $data): array
    {
        $rules = $kind === 'template' ? [
            'data' => ['required', 'array:name,description,hero,toc,meta,facts,sections'],
            'data.hero' => ['required', 'string', Rule::in(['image', 'navy', 'media'])],
            'data.toc' => ['required', 'boolean'], 'data.meta' => ['required', 'boolean'], 'data.facts' => ['required', 'boolean'],
            'data.sections' => ['required', 'array', 'list', 'size:5'],
            'data.sections.*' => ['required', 'array:k,on'],
            'data.sections.*.k' => ['required', 'string', 'distinct:strict', Rule::in(['body', 'summary', 'faqs', 'quote', 'related'])],
            'data.sections.*.on' => ['required', 'boolean'],
        ] : [
            'data' => ['required', 'array:name,description,items'],
            'data.items' => ['present', 'array', 'list', 'max:100'],
            'data.items.*' => ['required', 'array:q,a'],
            'data.items.*.q' => ['required', 'string', 'max:500'],
            'data.items.*.a' => ['required', 'string', 'max:5000'],
        ];
        $rules += ['data.name' => ['required', 'string', 'max:120'], 'data.description' => ['nullable', 'string', 'max:1000']];
        $valid = Validator::make(['data' => $data], $rules)->validate()['data'];
        if ($kind === 'template') {
            foreach (['toc', 'meta', 'facts'] as $key) {
                if (! is_bool($valid[$key])) {
                    throw new RuntimeException('Template switches must be JSON true or false.');
                }
            }
            foreach ($valid['sections'] as $section) {
                if (! is_bool($section['on'])) {
                    throw new RuntimeException('Section switches must be JSON true or false.');
                }
            }
        }
        // Fixed key order makes hashes independent of JSON object's input key order, but preserves list order.
        $out = ['name' => trim($valid['name']), 'description' => trim($valid['description'] ?? '')];
        if ($out['name'] === '') {
            throw new RuntimeException('Add a resource name.');
        }
        if ($kind === 'template') {
            return $out + ['hero' => $valid['hero'], 'toc' => $valid['toc'], 'meta' => $valid['meta'], 'facts' => $valid['facts'],
                'sections' => array_map(static fn (array $s): array => ['k' => $s['k'], 'on' => $s['on']], $valid['sections'])];
        }
        $items = array_map(static fn (array $item): array => ['q' => trim($item['q']), 'a' => trim($item['a'])], $valid['items']);
        foreach ($items as $item) {
            if ($item['q'] === '' || $item['a'] === '') {
                throw new RuntimeException('Every FAQ needs a question and answer.');
            }
        }

        return $out + ['items' => $items];
    }
}
