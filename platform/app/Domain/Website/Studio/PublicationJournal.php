<?php

declare(strict_types=1);

namespace App\Domain\Website\Studio;

use App\Domain\Website\Builder\BuilderFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Private immutable before/after images plus a transaction-bound SQL commit receipt. No HTTP failpoints. */
final class PublicationJournal
{
    /** @param array<string, mixed> $manifest */
    private function __construct(public readonly string $id, private readonly string $directory, private readonly string $digest, private readonly array $manifest) {}

    public static function root(): string
    {
        // Never replay one public tree's journal into another (including an isolated test/restore copy).
        return storage_path('app/private/studio-publications/'.hash('sha256', rtrim(public_path(), '/')));
    }

    /** The same lock for normal Studio actions and explicit recovery; not a distributed/external-builder lock. */
    public static function exclusive(callable $work): mixed
    {
        app()->singletonIf(PublicationMutex::class);

        return app(PublicationMutex::class)->run($work);
    }

    /** @param list<array{path: string, before: ?string, after: ?string}> $files */
    public static function prepare(int $pageId, string $operation, array $files): self
    {
        if (! in_array($operation, ['publish', 'withdraw'], true) || count($files) !== 2) {
            throw new RuntimeException('Unsupported publication recovery plan.');
        }
        if (! DB::getSchemaBuilder()->hasTable('website_studio_publication_receipts')) {
            throw new RuntimeException('Apply the additive Studio receipt migration before publishing.');
        }
        self::directory(self::root());
        $id = (string) Str::uuid7();
        $directory = self::root().'/'.$id;
        self::directory($directory);
        $entries = [];
        foreach ($files as $i => $file) {
            self::path($file['path']);
            if (self::readPublic($file['path']) !== $file['before']) {
                throw new RuntimeException('A public file changed while preparing publication. Reopen and Preview again.');
            }
            $entry = ['path' => $file['path']];
            foreach (['before', 'after'] as $side) {
                $bytes = $file[$side];
                $entry[$side] = $bytes === null ? null : hash('sha256', $bytes);
                if ($bytes !== null) {
                    self::atomic($directory.'/'.$i.'-'.$side.'.html', $bytes, 0600);
                }
            }
            $entries[] = $entry;
        }
        self::validatePaths($entries);
        $manifest = ['version' => 1, 'id' => $id, 'root' => rtrim(public_path(), '/'), 'page' => $pageId, 'operation' => $operation, 'files' => $entries];
        $bytes = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        // The manifest is the activation marker. A crash before it cannot have changed public files.
        self::atomic($directory.'/manifest.json', $bytes, 0600);
        app(PublicationMutex::class)->retainUntilTransactionEnd();

        return new self($id, $directory, hash('sha256', $bytes), $manifest);
    }

    /** Write only a plan's after image, after comparing its exact preimage. */
    public function apply(int $index): void
    {
        $entry = $this->manifest['files'][$index] ?? throw new RuntimeException('Unknown publication file.');
        $before = $this->image($index, 'before');
        if (self::readPublic($entry['path']) !== $before) {
            throw new RuntimeException('A public file changed during publication. Recovery will not overwrite a newer edit.');
        }
        $after = $this->image($index, 'after');
        if ($before !== $after) {
            self::writePublic($entry['path'], $after);
        }
        self::checkpoint($index === 0 ? 'article_written' : 'listing_written', $this->id);
    }

    /** Preserve the existing withdrawal archive as well as the immutable recovery before image. */
    public function archiveRetired(): void
    {
        $bytes = $this->image(0, 'before');
        if ($bytes !== null) {
            $folder = storage_path('app/studio-retired/'.dirname($this->manifest['files'][0]['path']));
            self::directory($folder);
            self::atomic($folder.'/'.$this->id.'.html', $bytes, 0600);
        }
    }

    /** Called at the end of the SAME SQL transaction as Page/blocks/draft/listing changes. */
    public function receipt(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('A publication receipt requires its SQL transaction.');
        }
        foreach ($this->manifest['files'] as $i => $entry) {
            if (self::readPublic($entry['path']) !== $this->image($i, 'after')) {
                throw new RuntimeException('Publication output changed before commit. Recovery requires verification.');
            }
        }
        DB::table('website_studio_publication_receipts')->insert(['id' => $this->id, 'manifest_hash' => $this->digest, 'created_at' => now()]);
        self::checkpoint('receipt_written', $this->id);
    }

    /** Never resolve an ambient transaction early. The next locked action reconciles after its real outcome. */
    public function committed(): void
    {
        if (DB::transactionLevel() === 0) {
            $this->reconcile();
        }
    }

    /** Recovery never guesses across unknown bytes. A committed receipt proves both after images were verified. */
    public function reconcile(): string
    {
        // A locking/current read also avoids stale receipt visibility in an ambient MySQL snapshot.
        $receipt = DB::table('website_studio_publication_receipts')->where('id', $this->id)->lockForUpdate()->first();
        if ($receipt !== null) {
            if (! hash_equals($this->digest, (string) $receipt->manifest_hash)) {
                throw new RuntimeException('Publication receipt integrity mismatch. Manual verification is required.');
            }
            // Do not replay an old committed after image over a subsequent legitimate publication/edit.
            if (DB::transactionLevel() === 0) {
                $this->resolve('committed');
            }

            return 'committed';
        }
        $restore = [];
        foreach ($this->manifest['files'] as $i => $entry) {
            $before = $this->image($i, 'before');
            $after = $this->image($i, 'after');
            $current = self::readPublic($entry['path']);
            if ($current !== $before && $current !== $after) {
                throw new RuntimeException('Publication recovery is blocked by newer or unknown public bytes. Nothing will overwrite that edit.');
            }
            $restore[] = [$entry['path'], $before, $current];
        }
        // All files must pass preflight before restoring ANY file. Repeat-safe after interruption mid-rollback.
        foreach (array_reverse($restore) as [$path, $before, $current]) {
            if (self::readPublic($path) !== $current) {
                throw new RuntimeException('A public file changed during recovery. Verify it before retrying.');
            }
            if ($current !== $before) {
                self::writePublic($path, $before);
                self::checkpoint('recovery_file_restored', $this->id);
            }
        }
        $this->resolve('rolled_back');

        return 'rolled_back';
    }

    /** Called only under exclusive(). Newest uncommitted plans must roll back before older ones. */
    public static function recover(): void
    {
        $paths = self::manifests();
        rsort($paths);
        foreach ($paths as $path) {
            $journal = self::load($path);
            $result = $journal->directory.'/resolved.json';
            if (is_file($result)) {
                $resolved = json_decode(self::privateRead($result), true, flags: JSON_THROW_ON_ERROR);
                if (($resolved['hash'] ?? '') !== $journal->digest || ! in_array($resolved['result'] ?? '', ['committed', 'rolled_back'], true)) {
                    throw new RuntimeException('A recovery result is invalid. Manual verification is required.');
                }

                continue;
            }
            $journal->reconcile();
        }
    }

    /**
     * Read-only status. Does not recover or expose page content, tokens, filesystem paths or credentials.
     *
     * @return list<array{id: string, page: int, operation: string}>
     */
    public static function status(): array
    {
        $rows = [];
        foreach (self::manifests() as $path) {
            $j = self::load($path);
            if (! is_file($j->directory.'/resolved.json')) {
                $rows[] = ['id' => $j->id, 'page' => $j->manifest['page'], 'operation' => $j->manifest['operation']];
            }
        }

        return $rows;
    }

    /** Internal event only. Tests may throw/terminate here; no HTTP/env/config failpoint switches exist. */
    public static function checkpoint(string $point, string $id): void
    {
        event('website.studio.publication.checkpoint', [$point, $id]);
    }

    public static function readPublic(string $path): ?string
    {
        $absolute = self::path($path);
        if (! file_exists($absolute)) {
            return null;
        }
        if (! is_file($absolute)) {
            throw new RuntimeException('A publication target is not a regular file.');
        }
        $bytes = file_get_contents($absolute);
        if ($bytes === false) {
            throw new RuntimeException('A publication file could not be read.');
        }

        return $bytes;
    }

    /** @return list<string> */
    private static function manifests(): array
    {
        $root = self::root();
        if (is_link($root) || (file_exists($root) && (! is_dir($root) || ! is_readable($root)))) {
            throw new RuntimeException('Private publication storage cannot be inspected safely.');
        }
        if (! is_dir($root)) {
            return [];
        }
        // GLOB_ERR prevents an unreadable journal directory from masquerading as no pending work.
        $paths = glob($root.'/*/manifest.json', GLOB_ERR);
        if ($paths === false) {
            throw new RuntimeException('Private publication journals could not all be inspected.');
        }

        return $paths;
    }

    private static function writePublic(string $path, ?string $bytes): void
    {
        $absolute = self::path($path);
        if ($bytes === null) {
            if (file_exists($absolute) && ! unlink($absolute)) {
                throw new RuntimeException('The publication file could not be retired.');
            }
        } else {
            self::directory(dirname($absolute), 0755);
            self::atomic($absolute, $bytes, 0644);
        }
    }

    private function resolve(string $result): void
    {
        self::atomic($this->directory.'/resolved.json', json_encode(['hash' => $this->digest, 'result' => $result], JSON_THROW_ON_ERROR), 0600);
    }

    private function image(int $index, string $side): ?string
    {
        $hash = $this->manifest['files'][$index][$side];
        if ($hash === null) {
            return null;
        }
        $bytes = self::privateRead($this->directory.'/'.$index.'-'.$side.'.html');
        if (! is_string($hash) || ! hash_equals($hash, hash('sha256', $bytes))) {
            throw new RuntimeException('A recovery snapshot failed its integrity check. Nothing was overwritten.');
        }

        return $bytes;
    }

    private static function load(string $path): self
    {
        $bytes = self::privateRead($path);
        $m = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($m) || ($m['version'] ?? null) !== 1 || ($m['root'] ?? '') !== rtrim(public_path(), '/')
            || ! is_string($m['id'] ?? null) || ! Str::isUuid($m['id']) || basename(dirname($path)) !== $m['id']
            || ! is_int($m['page'] ?? null) || ! in_array($m['operation'] ?? '', ['publish', 'withdraw'], true)
            || ! is_array($m['files'] ?? null) || ! array_is_list($m['files']) || count($m['files']) !== 2) {
            throw new RuntimeException('A publication recovery manifest is invalid. Nothing was overwritten.');
        }
        self::validatePaths($m['files']);
        foreach ($m['files'] as $entry) {
            foreach (['before', 'after'] as $side) {
                if (! array_key_exists($side, $entry) || ($entry[$side] !== null && (! is_string($entry[$side]) || preg_match('/^[a-f0-9]{64}$/D', $entry[$side]) !== 1))) {
                    throw new RuntimeException('A publication recovery manifest has invalid file hashes.');
                }
            }
        }

        return new self($m['id'], dirname($path), hash('sha256', $bytes), $m);
    }

    /** @param list<array<string, mixed>> $files */
    private static function validatePaths(array $files): void
    {
        $article = $files[0]['path'] ?? '';
        $listing = $files[1]['path'] ?? '';
        if (! is_string($article) || ! is_string($listing) || preg_match('~^(insights|projects)/[a-z0-9][a-z0-9-]*/index\.html$~D', $article, $match) !== 1 || $listing !== $match[1].'/index.html') {
            throw new RuntimeException('The recovery plan is outside the article/listing boundary.');
        }
    }

    private static function path(string $path): string
    {
        if (preg_match('~^(insights|projects)/(?:[a-z0-9][a-z0-9-]*/)?index\.html$~D', $path) !== 1) {
            throw new RuntimeException('That recovery path is unsupported.');
        }
        if (is_link(rtrim(public_path(), '/'))) {
            throw new RuntimeException('A symlinked publication root is unsafe.');
        }
        $absolute = BuilderFile::resolve($path);
        $cursor = $absolute;
        while ($cursor !== rtrim(public_path(), '/') && dirname($cursor) !== $cursor) {
            if (is_link($cursor)) {
                throw new RuntimeException('Symlinks are not allowed in publication recovery targets.');
            }
            $cursor = dirname($cursor);
        }

        return $absolute;
    }

    private static function privateRead(string $path): string
    {
        $cursor = $path;
        $root = rtrim(storage_path(), '/');
        while ($cursor !== $root && dirname($cursor) !== $cursor) {
            if (is_link($cursor)) {
                throw new RuntimeException('Symlinked recovery storage is unsafe.');
            }
            $cursor = dirname($cursor);
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('A private recovery record could not be read.');
        }

        return $bytes;
    }

    private static function directory(string $path, int $mode = 0700): void
    {
        $cursor = $path;
        while (dirname($cursor) !== $cursor) {
            if (is_link($cursor)) {
                throw new RuntimeException('Symlinked publication storage is unsafe.');
            }
            $cursor = dirname($cursor);
        }
        if (is_link($path) || (! is_dir($path) && ! mkdir($path, $mode, true) && ! is_dir($path))) {
            throw new RuntimeException('Publication storage could not be created safely.');
        }
    }

    /** File fsync precedes atomic rename. This bounds process-interruption recovery, not hardware power loss. */
    private static function atomic(string $path, string $bytes, int $mode): void
    {
        if (is_link($path)) {
            throw new RuntimeException('A publication storage target is unsafe.');
        }
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(8));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('A publication staging file could not be opened.');
        }
        try {
            if (! chmod($temporary, $mode)) {
                throw new RuntimeException('Publication file permissions could not be set.');
            }
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Publication storage write failed.');
                }
                $offset += $written;
            }
            if (! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('Publication storage could not be synchronized.');
            }
        } finally {
            fclose($handle);
        }
        if (! rename($temporary, $path)) {
            throw new RuntimeException('A publication staging file could not be promoted.');
        }
    }
}
