<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/*
| Woodex OS 1 (docs/audit/16.19): the database copies of pages that exist as v1 files are deleted with
| their blocks and versions — and only those. The command reports first, keeps a JSON backup, and the
| other page types are untouched.
*/

beforeEach(function () {
    $this->backup = sys_get_temp_dir().'/woodex-retire-'.bin2hex(random_bytes(4));

    $this->shadow = collect([
        PageType::Service, PageType::City, PageType::Area, PageType::Project,
    ])->map(function (PageType $type) {
        $page = Page::factory()->create(['type' => $type, 'slug' => '/'.$type->value.'-one/']);

        PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'A section'])->create(['position' => 0]);
        PageVersion::query()->create([
            'page_id' => $page->id,
            'data' => ['canvas_html' => '<html><body>old</body></html>'],
        ]);

        return $page;
    });

    $this->kept = Page::factory()->create([
        'type' => PageType::Insight,
        'slug' => '/insight-kept/',
        'status' => PageStatus::Published,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->backup);
});

it('only reports what it would delete until --force is given', function () {
    expect($this->artisan('website:retire-shadow-pages', ['--backup-dir' => $this->backup]))->toBe(0)
        ->and(Artisan::output())->toContain('Nothing was deleted');

    expect(Page::query()->count())->toBe(5)
        ->and(PageBlock::query()->count())->toBe(4)
        ->and(PageVersion::query()->count())->toBe(4)
        ->and(is_dir($this->backup) ? File::files($this->backup) : [])->toBe([]);
});

it('deletes the shadow pages, keeps the rest, and backs up everything it deleted', function () {
    expect($this->artisan('website:retire-shadow-pages', ['--force' => true, '--backup-dir' => $this->backup]))->toBe(0)
        ->and(Artisan::output())->toContain('Deleted 4 pages');

    expect(Page::query()->pluck('id')->all())->toBe([$this->kept->id])
        ->and(PageBlock::query()->count())->toBe(0)
        ->and(PageVersion::query()->count())->toBe(0);

    $files = File::files($this->backup);

    expect($files)->toHaveCount(1);

    $backup = json_decode((string) file_get_contents($files[0]->getPathname()), true);

    expect($backup['types'])->toBe(['service', 'city', 'area', 'project'])
        ->and($backup['pages'])->toHaveCount(4)
        ->and(array_column($backup['pages'], 'slug'))->toContain('/service-one/', '/city-one/', '/area-one/', '/project-one/')
        ->and($backup['pages'][0]['blocks'])->toHaveCount(1)
        ->and($backup['pages'][0]['versions'])->toHaveCount(1);
});

it('leaves the home row alone unless --home is given, then deletes it too', function () {
    $home = Page::factory()->create(['type' => PageType::Home, 'slug' => '/', 'title' => 'Home']);
    $home->blocks()->create([
        'type' => BlockType::Slider,
        'block_key' => 'bhome001',
        'position' => 0,
        'visible' => true,
        'data' => ['slides' => [['heading' => 'The home opening']]],
    ]);

    expect($this->artisan('website:retire-shadow-pages', ['--force' => true, '--backup-dir' => $this->backup]))->toBe(0)
        ->and(Artisan::output())->toContain('Deleted 4 pages');

    expect(Page::query()->whereKey($home->id)->exists())->toBeTrue();

    $files = File::files($this->backup);
    $first = json_decode((string) file_get_contents($files[0]->getPathname()), true);
    expect($first['types'])->toBe(['service', 'city', 'area', 'project']);

    expect($this->artisan('website:retire-shadow-pages', [
        '--force' => true, '--home' => true, '--backup-dir' => $this->backup,
    ]))->toBe(0)->and(Artisan::output())->toContain('Deleted 1 pages');

    expect(Page::query()->whereKey($home->id)->exists())->toBeFalse()
        ->and(Page::query()->count())->toBe(1);

    $files = File::files($this->backup);
    $second = json_decode((string) file_get_contents($files[count($files) - 1]->getPathname()), true);

    expect($second['types'])->toBe(['service', 'city', 'area', 'project', 'home'])
        ->and($second['pages'])->toHaveCount(1)
        ->and($second['pages'][0]['slug'])->toBe('/')
        ->and($second['pages'][0]['blocks'])->toHaveCount(1);
});

it('says so instead of failing when there is nothing to retire', function () {
    Page::query()->whereKeyNot($this->kept->id)->delete();

    expect($this->artisan('website:retire-shadow-pages', ['--force' => true, '--backup-dir' => $this->backup]))->toBe(0)
        ->and(Artisan::output())->toContain('Nothing to do');

    expect(Page::query()->count())->toBe(1);
});
