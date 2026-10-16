<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
| The pages reach the server as an export, not as the old HTML (docs/09 §13): `woodex:export-website` writes a
| folder with pages.json and the photographs they use, `woodex:import-website` reads it back, and the migration
| that ships it runs with the owner's one-click "Database update". A second import must change nothing.
*/
it('exports the website and imports it back', function () {
    Storage::fake('public');
    User::factory()->create(); // the import writes on behalf of a user: pages have an author and a version
    $dir = sys_get_temp_dir().'/website-bundle-'.bin2hex(random_bytes(4));
    mkdir($dir, 0o777, true);

    $image = Media::query()->create([
        'disk' => 'public',
        'path' => 'website/imported/hero.webp',
        'alt' => 'A living room in Model Town, Lahore',
        'width' => 1600,
        'height' => 900,
        'folder' => 'imported',
    ]);
    Storage::disk('public')->put($image->path, 'not-really-an-image');

    $page = Page::factory()->published()->withSlug('/interior-design/')->create([
        'title' => 'Interior Design in Lahore',
        'excerpt' => 'Design, drawings and site supervision.',
    ]);
    PageBlock::factory()->for($page)->asType(BlockType::Hero, [
        'heading' => 'Interior Design in Lahore',
        'image_id' => $image->id,
    ])->create(['position' => 0]);

    try {
        Artisan::call('woodex:export-website', ['--to' => $dir]);

        expect(is_file($dir.'/pages.json'))->toBeTrue()
            ->and(is_file($dir.'/media/hero.webp'))->toBeTrue();

        // The database is emptied, as it would be on a fresh installation.
        PageBlock::query()->forceDelete();
        Page::query()->forceDelete(); // pages are soft deleted: force, so the URL is free again
        Media::query()->delete();
        Storage::disk('public')->deleteDirectory('website');

        Artisan::call('woodex:import-website', ['--path' => $dir]);

        $imported = Page::query()->where('slug', '/interior-design/')->firstOrFail();

        expect($imported->status)->toBe(PageStatus::Published)
            ->and($imported->title)->toBe('Interior Design in Lahore')
            ->and($imported->excerpt)->toBe('Design, drawings and site supervision.')
            ->and($imported->blocks)->toHaveCount(1)
            ->and($imported->blocks->first()->data['heading'])->toBe('Interior Design in Lahore');

        $media = Media::query()->firstOrFail();

        expect($media->alt)->toBe('A living room in Model Town, Lahore')
            ->and($imported->blocks->first()->data['image_id'])->toBe($media->id);

        // Importing the same bundle again leaves the pages alone.
        Artisan::call('woodex:import-website', ['--path' => $dir]);

        expect(Page::query()->count())->toBe(1)
            ->and(PageBlock::query()->count())->toBe(1)
            ->and(Media::query()->count())->toBe(1);
    } finally {
        array_map('unlink', glob($dir.'/media/*') ?: []);
        @rmdir($dir.'/media');
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);
    }
});
