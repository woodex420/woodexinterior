<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Website\Studio\PublicationJournal;
use Illuminate\Console\Command;
use Throwable;

final class StudioPublicationRecovery extends Command
{
    protected $signature = 'woodex:studio-recover {--apply : Reconcile pending journals under the Studio writer lock}';

    protected $description = 'Show pending private publication journals, or conservatively reconcile them with --apply.';

    public function handle(): int
    {
        try {
            if ($this->option('apply')) {
                PublicationJournal::exclusive(fn () => null);
                $this->info('Recovery completed. Committed operations were acknowledged; uncommitted file projections were rolled back.');
            }
            $rows = PublicationJournal::status();
            $this->table(['Operation ID', 'Page ID', 'Action'], $rows);
            $this->line(count($rows).' pending publication journal(s). No private content is displayed.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Recovery is blocked. Preserve the saved drafts, SQL receipts, private journals and public files. Verify conflicts and storage integrity before retrying; no force-overwrite option is provided.');

            return self::FAILURE;
        }
    }
}
