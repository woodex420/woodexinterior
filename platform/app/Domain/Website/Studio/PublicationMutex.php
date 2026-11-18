<?php

declare(strict_types=1);

namespace App\Domain\Website\Studio;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use WeakReference;

/** Single-host cooperating-writer lock. An ambient SQL transaction must not outlive its file lock. */
final class PublicationMutex
{
    /** @var resource|null */
    private $handle = null;

    private ?Connection $connection = null;

    private int $depth = 0;

    private bool $publication = false;

    public function __construct()
    {
        $weak = WeakReference::create($this);
        Event::listen([TransactionCommitted::class, TransactionRolledBack::class], static function ($event) use ($weak): void {
            $mutex = $weak->get();
            if ($mutex !== null && $mutex->depth === 0 && $mutex->connection === $event->connection && $event->connection->transactionLevel() === 0) {
                $mutex->release();
            }
        });
    }

    public function __destruct()
    {
        $this->release();
    }

    public function retainUntilTransactionEnd(): void
    {
        if ($this->handle !== null) {
            $this->publication = true;
        }
    }

    public function run(callable $work): mixed
    {
        if ($this->handle === null) {
            $folder = storage_path('app/studio-private');
            $cursor = $folder;
            while (dirname($cursor) !== $cursor) {
                if (is_link($cursor)) {
                    throw new RuntimeException('The Studio publication lock directory is unsafe.');
                }
                $cursor = dirname($cursor);
            }
            if (! is_dir($folder) && ! mkdir($folder, 0700, true) && ! is_dir($folder)) {
                throw new RuntimeException('The Studio publication lock directory could not be created.');
            }
            if (is_link($folder.'/publisher.lock')) {
                throw new RuntimeException('The Studio publication lock is unsafe.');
            }
            $handle = fopen($folder.'/publisher.lock', 'c');
            if ($handle === false) {
                throw new RuntimeException('The Studio publication lock could not be opened.');
            }
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);
                throw new RuntimeException('Another editor is saving or recovering. Try again.');
            }
            $this->handle = $handle;
            $this->connection = DB::connection();
        } elseif ($this->connection !== DB::connection()) {
            throw new RuntimeException('Studio cannot change SQL connections while holding a publication lock.');
        }
        $this->depth++;
        try {
            PublicationJournal::recover();

            return $work();
        } finally {
            $this->depth--;
            // Retain the lease for a real outer transaction (including test transactions). Other processes
            // must not mistake its not-yet-visible receipt for a dead writer. OS exit also releases flock.
            if ($this->depth === 0 && (DB::transactionLevel() === 0 || ! $this->publication)) {
                $this->release();
            }
        }
    }

    private function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
            $this->connection = null;
            $this->publication = false;
        }
    }
}
