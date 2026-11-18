<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Studio\PublicationJournal;
use App\Domain\Website\Studio\StudioContent;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->site = storage_path('framework/testing/recovery-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/shell', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/shell/index.html', '<!doctype html><html><head><title>Shell</title></head><body><header>Keep</header><main><h1>Shell</h1></main><footer>Keep</footer></body></html>');
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head><title>Listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    $this->app->usePublicPath($this->site);
    $this->studio = new StudioContent($this->userWithRole('owner'));
});

afterEach(function (): void {
    Event::forget('website.studio.publication.checkpoint');
    (new Filesystem)->deleteDirectory(PublicationJournal::root());
    (new Filesystem)->deleteDirectory($this->site);
});

function recoverySave(string $type): array
{
    return test()->studio->save(['type' => $type, 'title' => 'Saved synthetic draft', 'slug' => 'recover-subject', 'seo' => [],
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Saved synthetic body']]]])['item'];
}

it('compensates actual Studio exceptions and preserves saved drafts at every precommit boundary', function (string $type, string $point, bool $withdraw): void {
    $item = recoverySave($type);
    if ($withdraw) {
        $item = $this->studio->publish($item['id'], $type, $item)['item'];
        $item = $this->studio->save(array_replace($item, ['title' => 'Pending private title']))['item'];
    }
    $folder = $type === 'post' ? 'insights' : 'projects';
    $article = $folder.'/recover-subject/index.html';
    $beforeArticle = PublicationJournal::readPublic($article);
    $beforeListing = PublicationJournal::readPublic($folder.'/index.html');
    $draft = StudioDraft::where('page_id', $item['id'])->firstOrFail()->getAttributes();
    $page = Page::findOrFail($item['id'])->getAttributes();
    $receipts = DB::table('website_studio_publication_receipts')->count();
    Event::listen('website.studio.publication.checkpoint', function (string $at) use ($point): void {
        if ($at === $point) {
            throw new RuntimeException('Controlled synthetic interruption');
        }
    });
    expect(fn () => $withdraw ? $this->studio->setStatus($item['id'], 'draft', $type, $item) : $this->studio->publish($item['id'], $type, $item))
        ->toThrow(RuntimeException::class, 'Controlled synthetic interruption');
    Event::forget('website.studio.publication.checkpoint');
    expect(PublicationJournal::readPublic($article))->toBe($beforeArticle)
        ->and(PublicationJournal::readPublic($folder.'/index.html'))->toBe($beforeListing)
        ->and(StudioDraft::where('page_id', $item['id'])->firstOrFail()->getAttributes())->toBe($draft)
        ->and(Page::findOrFail($item['id'])->getAttributes())->toBe($page)
        ->and(DB::table('website_studio_publication_receipts')->count())->toBe($receipts);
    PublicationJournal::exclusive(fn () => null);
    expect(count(PublicationJournal::status()))->toBe($receipts);
})->with(['post', 'study'])->with(['prepared', 'sql_projected', 'article_written', 'listing_written', 'receipt_written', 'before_commit'])->with([false, true]);

it('does not roll back a transaction whose receipt is visible after acknowledgement failure', function (string $type): void {
    $item = recoverySave($type);
    Event::listen('website.studio.publication.checkpoint', function (string $at): void {
        if ($at === 'after_commit') {
            throw new RuntimeException('Lost confirmation');
        }
    });
    expect(fn () => $this->studio->publish($item['id'], $type, $item))->toThrow(RuntimeException::class, 'Publication was committed');
    expect(Page::findOrFail($item['id'])->status)->toBe(PageStatus::Published)
        ->and(StudioDraft::where('page_id', $item['id'])->exists())->toBeFalse()
        ->and(DB::table('website_studio_publication_receipts')->count())->toBe(1);
})->with(['post', 'study']);

it('preflights all unknown bytes before rollback and blocks subsequent private saves without losing drafts', function (): void {
    $item = recoverySave('post');
    $original = PublicationJournal::readPublic('insights/index.html');
    Event::listen('website.studio.publication.checkpoint', function (string $at): void {
        if ($at === 'listing_written') {
            file_put_contents(test()->site.'/insights/index.html', 'NEWER EXTERNAL EDIT');
            throw new RuntimeException('Interrupted with external conflict');
        }
    });
    expect(fn () => $this->studio->publish($item['id'], 'post', $item))->toThrow(RuntimeException::class, 'recovery is pending');
    Event::forget('website.studio.publication.checkpoint');
    $article = PublicationJournal::readPublic('insights/recover-subject/index.html');
    expect($article)->not->toBeNull();
    expect(fn () => recoverySave('post'))->toThrow(RuntimeException::class, 'blocked by newer');
    expect(PublicationJournal::readPublic('insights/index.html'))->toBe('NEWER EXTERNAL EDIT')
        ->and(PublicationJournal::readPublic('insights/recover-subject/index.html'))->toBe($article)
        ->and(StudioDraft::where('page_id', $item['id'])->exists())->toBeTrue();
    // An operator restores the VERIFIED preimage, not a force-recovery flag.
    file_put_contents($this->site.'/insights/index.html', $original);
    PublicationJournal::exclusive(fn () => null);
    PublicationJournal::exclusive(fn () => null);
    expect(PublicationJournal::readPublic('insights/recover-subject/index.html'))->toBeNull()
        ->and(PublicationJournal::status())->toBe([]);
});

it('rejects symlinked public paths and corrupted private images without overwrites', function (): void {
    $listing = PublicationJournal::readPublic('insights/index.html');
    $plan = [
        ['path' => 'insights/recover-subject/index.html', 'before' => null, 'after' => 'AFTER ARTICLE'],
        ['path' => 'insights/index.html', 'before' => $listing, 'after' => 'AFTER LISTING'],
    ];
    $j = PublicationJournal::prepare(123, 'publish', $plan);
    $j->apply(0);
    chmod(PublicationJournal::root(), 0000);
    try {
        expect(fn () => PublicationJournal::exclusive(fn () => null))->toThrow(RuntimeException::class, 'cannot be inspected safely');
    } finally {
        chmod(PublicationJournal::root(), 0700);
    }
    file_put_contents(PublicationJournal::root().'/'.$j->id.'/1-before.html', 'CORRUPTED');
    expect(fn () => PublicationJournal::exclusive(fn () => null))->toThrow(RuntimeException::class, 'integrity check');
    expect(PublicationJournal::readPublic('insights/recover-subject/index.html'))->toBe('AFTER ARTICLE')
        ->and(PublicationJournal::readPublic('insights/index.html'))->toBe($listing);
    (new Filesystem)->deleteDirectory(PublicationJournal::root());
    unlink($this->site.'/insights/recover-subject/index.html');
    rmdir($this->site.'/insights/recover-subject');
    symlink($this->site.'/projects', $this->site.'/insights/recover-subject');
    expect(fn () => PublicationJournal::prepare(123, 'publish', $plan))->toThrow(RuntimeException::class, 'Symlinks');
});

it('refuses arbitrary manifest paths and receipt mismatches and supports read-only operator status', function (): void {
    $listing = PublicationJournal::readPublic('insights/index.html');
    $j = PublicationJournal::prepare(123, 'publish', [
        ['path' => 'insights/recover-subject/index.html', 'before' => null, 'after' => 'ARTICLE'],
        ['path' => 'insights/index.html', 'before' => $listing, 'after' => 'LISTING'],
    ]);
    expect($this->artisan('woodex:studio-recover'))->toBe(0)
        ->and(count(PublicationJournal::status()))->toBe(1)
        ->and(PublicationJournal::readPublic('insights/index.html'))->toBe($listing);
    DB::table('website_studio_publication_receipts')->insert(['id' => $j->id, 'manifest_hash' => str_repeat('0', 64), 'created_at' => now()]);
    expect($this->artisan('woodex:studio-recover --apply'))->toBe(1)
        ->and(count(PublicationJournal::status()))->toBe(1);
    DB::table('website_studio_publication_receipts')->where('id', $j->id)->delete();
    $manifest = PublicationJournal::root().'/'.$j->id.'/manifest.json';
    $data = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $data['files'][0]['path'] = '../outside/index.html';
    file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR));
    expect($this->artisan('woodex:studio-recover --apply'))->toBe(1)
        ->and(PublicationJournal::readPublic('insights/index.html'))->toBe($listing);
});

it('recovers a real SIGKILL in a separate SQL connection and is repeat-safe', function (string $type, string $point, string $operation): void {
    $run = storage_path('framework/testing/publication-process-'.bin2hex(random_bytes(8)));
    mkdir($run, 0700, true);
    $command = [PHP_BINARY, base_path('tests/Support/studio-publication-process.php'), $run, 'interrupt', $type, $point, $operation];
    $process = new Process($command, base_path());
    $process->setTimeout(45);
    $process->setInput(new InputStream);
    try {
        $process->start();
        $ready = $process->waitUntil(fn (string $stream, string $output): bool => str_contains($output, 'READY'));
        expect($ready)->toBeTrue($process->getOutput().$process->getErrorOutput());
        $probeCommand = $command;
        $probeCommand[3] = 'lock-probe';
        $probe = new Process($probeCommand, base_path());
        $probe->setTimeout(15);
        $probe->mustRun();
        expect(json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe(['locked' => true]);
        $process->signal(9);
        try {
            $process->wait();
        } catch (ProcessSignaledException) {
            // Expected: this is not an exit/exception/shutdown simulation.
        }
        expect($process->hasBeenSignaled())->toBeTrue()->and($process->getTermSignal())->toBe(9);
        if ($point === 'before_commit') {
            // Kill reconciliation after the listing has rolled back but before the article is restored.
            $command[3] = 'recover-interrupt';
            $rollback = new Process($command, base_path());
            $rollback->setTimeout(30);
            $rollback->setInput(new InputStream);
            $rollback->start();
            try {
                expect($rollback->waitUntil(fn (string $stream, string $output): bool => str_contains($output, 'READY')))->toBeTrue($rollback->getOutput().$rollback->getErrorOutput());
                $rollback->signal(9);
                try {
                    $rollback->wait();
                } catch (ProcessSignaledException) {
                }
                expect($rollback->getTermSignal())->toBe(9);
            } finally {
                if ($rollback->isRunning()) {
                    $rollback->stop(0, 9);
                }
            }
        }
        $command[3] = 'recover';
        $recover = new Process($command, base_path());
        $recover->setTimeout(30);
        $recover->mustRun();
        $result = json_decode(trim($recover->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        expect($result)->toBe(['files_match' => true, 'sql_match' => true, 'idempotent' => true, 'pending' => 0, 'committed' => $point === 'after_commit']);
    } finally {
        if ($process->isRunning()) {
            $process->stop(0, 9);
        }
        (new Filesystem)->deleteDirectory($run);
    }
})->with(['post', 'study'])->with(['prepared', 'sql_projected', 'article_written', 'listing_written', 'receipt_written', 'before_commit', 'after_commit', 'ambient_returned'])->with(['publish', 'withdraw']);

it('acknowledges committed receipts without replaying stale output over a newer file edit', function (): void {
    $listing = PublicationJournal::readPublic('insights/index.html');
    $j = PublicationJournal::prepare(123, 'publish', [
        ['path' => 'insights/recover-subject/index.html', 'before' => null, 'after' => 'COMMITTED ARTICLE'],
        ['path' => 'insights/index.html', 'before' => $listing, 'after' => 'COMMITTED LISTING'],
    ]);
    $j->apply(0);
    $j->apply(1);
    $j->receipt();
    file_put_contents($this->site.'/insights/index.html', 'NEWER COMMITTED EDIT');
    expect($j->reconcile())->toBe('committed')
        ->and(PublicationJournal::readPublic('insights/index.html'))->toBe('NEWER COMMITTED EDIT')
        ->and(PublicationJournal::readPublic('insights/recover-subject/index.html'))->toBe('COMMITTED ARTICLE');
});

it('refuses preparation from stale bytes and fails closed without the additive receipt migration', function (): void {
    $item = recoverySave('post');
    expect(fn () => PublicationJournal::prepare($item['id'], 'publish', [
        ['path' => 'insights/recover-subject/index.html', 'before' => 'STALE', 'after' => 'ARTICLE'],
        ['path' => 'insights/index.html', 'before' => 'STALE', 'after' => 'LISTING'],
    ]))->toThrow(RuntimeException::class, 'changed while preparing');
    Schema::drop('website_studio_publication_receipts');
    expect(fn () => $this->studio->publish($item['id'], 'post', $item))->toThrow(RuntimeException::class, 'additive Studio receipt migration')
        ->and(StudioDraft::where('page_id', $item['id'])->exists())->toBeTrue()
        ->and(PublicationJournal::readPublic('insights/recover-subject/index.html'))->toBeNull()
        ->and(PublicationJournal::status())->toBe([]);
});
