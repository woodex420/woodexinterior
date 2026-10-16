<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\System\Models\LegacyRef;
use App\Domain\Website\Actions\SavePage;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Import\ImportAuthor;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the website back from the folder `woodex:export-website` wrote (docs/09 §13).
 *
 * This is how the pages reach the server: the old HTML lives on a laptop, so the pages and their photographs travel
 * in `database/data/website-bundle` instead. The import is idempotent through `legacy_refs` (one row per slug), so
 * it runs on every deploy that needs it and never overwrites a page the owner has since edited.
 *
 *   php artisan woodex:import-website [--path=database/data/website-bundle] [--force]
 */
final class ImportWebsite extends Command
{
    private const TABLE = 'website-bundle';

    protected $signature = 'woodex:import-website
        {--path=database/data/website-bundle : folder that holds pages.json and media/}
        {--force : import again even when this bundle was already imported}';

    protected $description = 'Import the pages, sections and images exported by woodex:export-website.';

    public function handle(SavePage $save, ImportAuthor $author): int
    {
        $base = (string) $this->option('path');
        $base = str_starts_with($base, '/') ? $base : base_path($base);
        $file = $base.'/pages.json';

        if (! is_file($file)) {
            $this->components->error('There is no export at '.$file.' (run woodex:export-website first).');

            return self::FAILURE;
        }

        /** @var array{pages: list<array<string, mixed>>, media: list<array<string, mixed>>, exported_at?: string} $bundle */
        $bundle = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $checksum = (string) hash_file('sha256', $file);
        $user = $author->first();

        if ($user === null) {
            $this->components->error('There is no user yet: install the app before importing pages.');

            return self::FAILURE;
        }

        $ref = LegacyRef::query()->where('legacy_table', self::TABLE)->where('legacy_id', 'pages.json')->first();

        if ($ref !== null && $ref->checksum === $checksum && ! $this->option('force')) {
            $this->components->info('This export is already imported. Use --force to import it again.');

            return self::SUCCESS;
        }

        $images = $this->importMedia($base, $bundle['media'] ?? []);
        $counts = ['created' => 0, 'updated' => 0, 'published' => 0];

        foreach ($bundle['pages'] as $row) {
            $slug = Page::normalizeSlug((string) $row['slug']);
            $page = Page::query()->firstWhere('slug', $slug);

            if ($page === null) {
                $page = new Page(['slug' => $slug]);
                $counts['created']++;
            } else {
                $counts['updated']++;
            }

            $publish = ($row['status'] ?? 'draft') === PageStatus::Published->value;
            $counts['published'] += $publish ? 1 : 0;

            $blocks = array_map(
                fn (array $block): array => [
                    'type' => (string) $block['type'],
                    'data' => $this->withImages((array) $block['data'], $images),
                    'visible' => (bool) ($block['visible'] ?? true),
                ],
                (array) $row['blocks'],
            );

            $page = $save(
                $page,
                [
                    'type' => PageType::tryFrom((string) $row['type']) ?? PageType::Listing,
                    'slug' => $slug,
                    'title' => (string) $row['title'],
                    'excerpt' => $row['excerpt'] ?? null,
                    'seo' => (array) ($row['seo'] ?? []),
                    'schema_type' => $row['schema_type'] ?? null,
                    'sort' => (int) ($row['sort'] ?? 0),
                ],
                $blocks,
                $publish,
                $user,
            );

            if ($publish && $page->published_at === null) {
                $page->forceFill(['published_at' => now()])->save();
            }
        }

        LegacyRef::query()->updateOrCreate(
            ['legacy_table' => self::TABLE, 'legacy_id' => 'pages.json'],
            ['model_type' => Page::class, 'model_id' => 0, 'checksum' => $checksum, 'imported_at' => now()],
        );

        $this->components->info(sprintf(
            '%d pages created · %d updated · %d published · %d images.',
            $counts['created'],
            $counts['updated'],
            $counts['published'],
            count($images),
        ));

        return self::SUCCESS;
    }

    /**
     * Copies the exported photographs into the media library and returns old id => new media id.
     *
     * @param  list<array<string, mixed>>  $media
     * @return array<int, int>
     */
    private function importMedia(string $base, array $media): array
    {
        $ids = [];

        foreach ($media as $row) {
            $file = $base.'/media/'.(string) $row['file'];

            if (! is_file($file)) {
                continue;
            }

            $hash = (string) hash_file('sha256', $file);
            $existing = LegacyRef::query()
                ->where('legacy_table', 'website-image')
                ->where('legacy_id', $hash)
                ->value('model_id');

            if ($existing !== null) {
                $ids[(int) $row['id']] = (int) $existing;

                continue;
            }

            $path = 'website/imported/'.date('Y/m').'/'.(string) $row['file'];
            $path = $this->uniquePath($path);

            Storage::disk('public')->put($path, (string) file_get_contents($file));

            $image = Media::query()->create([
                'disk' => 'public',
                'path' => $path,
                'alt' => (string) ($row['alt'] ?? ''),
                'title' => $row['title'] ?? null,
                'width' => $row['width'] ?? null,
                'height' => $row['height'] ?? null,
                'folder' => $row['folder'] ?? 'imported',
                'uploaded_by' => null,
            ]);

            LegacyRef::query()->create([
                'legacy_table' => 'website-image',
                'legacy_id' => $hash,
                'model_type' => Media::class,
                'model_id' => $image->id,
                'checksum' => $hash,
                'imported_at' => now(),
            ]);

            $ids[(int) $row['id']] = $image->id;
        }

        return $ids;
    }

    /** Two exports can carry different photographs with the same file name. */
    private function uniquePath(string $path): string
    {
        $candidate = $path;
        $suffix = 2;

        while (Storage::disk('public')->exists($candidate)) {
            $candidate = (string) preg_replace('/(\.\w+)$/', '-'.$suffix.'$1', $path);
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Puts the new media ids into a section, wherever the old ones were.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $images
     * @return array<string, mixed>
     */
    private function withImages(array $data, array $images): array
    {
        foreach ($data as $key => $value) {
            if (($key === 'image_id' || $key === 'id') && is_numeric($value)) {
                $data[$key] = $images[(int) $value] ?? null;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = array_map(
                    fn (mixed $row): mixed => is_array($row) ? $this->withImages($row, $images) : $row,
                    $value,
                );
            }
        }

        return $data;
    }
}
