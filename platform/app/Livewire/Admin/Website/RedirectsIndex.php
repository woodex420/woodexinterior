<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The 301 map from the 142 URLs of the previous website (docs/09 §3). Every old URL keeps its visitors: an old
 * link that answers 404 loses the ranking it earned over years.
 */
#[Layout('layouts::admin')]
#[Title('SEO & Redirects')]
class RedirectsIndex extends Component
{
    use InteractsWithAdmin;

    public string $search = '';

    public string $fromPath = '';

    public string $toPath = '';

    public int $code = 301;

    public string $note = '';

    public string $csv = '';

    public string $result = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function add(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate([
            'fromPath' => ['required', 'string', 'max:190', 'starts_with:/', 'not_regex:#\s#'],
            'toPath' => ['required', 'string', 'max:500', 'starts_with:/'],
            'code' => ['required', 'in:301,302'],
        ], [], ['fromPath' => 'old URL', 'toPath' => 'new URL']);

        $this->store($this->fromPath, $this->toPath, $this->code, $this->note);

        $this->fromPath = '';
        $this->toPath = '';
        $this->note = '';
        $this->toast('Redirect added.');
    }

    public function remove(int $redirectId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        Redirect::query()->findOrFail($redirectId)->delete();
        $this->toast('Redirect removed.');
    }

    /**
     * Import a CSV of old,new[,code] lines — the export of the previous sitemap, or a spreadsheet column pair.
     * Existing rows are updated, so re-running an import is safe.
     */
    public function importCsv(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $lines = preg_split('/\r\n|\r|\n/', trim($this->csv)) ?: [];
        $added = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $row = str_getcsv(trim($line));

            if (count($row) < 2) {
                $skipped++;

                continue;
            }

            [$from, $to] = array_map(fn (string $cell): string => trim($cell), [$row[0], $row[1]]);

            if (! str_starts_with($from, '/') || ! str_starts_with($to, '/')) {
                $skipped++;

                continue;
            }

            if (str_starts_with(strtolower($from), '/http')) {
                $skipped++;

                continue;
            }

            $code = isset($row[2]) && (int) $row[2] === 302 ? 302 : 301;
            $this->store($from, $to, $code, $row[3] ?? null);
            $added++;
        }

        $this->csv = '';
        $this->result = $added.' redirect(s) imported'.($skipped > 0 ? ', '.$skipped.' line(s) skipped.' : '.');
        $this->toast($this->result);
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $rows = Redirect::query()->orderBy('from_path')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['old_url', 'new_url', 'code', 'hits', 'note']);

            foreach ($rows as $redirect) {
                fputcsv($out, [$redirect->from_path, $redirect->to_path, $redirect->code, $redirect->hits, $redirect->note]);
            }

            fclose($out);
        }, 'woodex-redirects-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        return view('livewire.admin.website.redirects-index', [
            'redirects' => Redirect::query()
                ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('from_path', 'like', '%'.$this->search.'%')
                    ->orWhere('to_path', 'like', '%'.$this->search.'%')))
                ->orderByDesc('hits')
                ->orderBy('from_path')
                ->limit(500)
                ->get(),
            'total' => Redirect::query()->count(),
            'visits' => (int) Redirect::query()->sum('hits'),
            'pages' => Page::query()->orderBy('slug')->get(['id', 'slug', 'title']),
        ]);
    }

    private function store(string $from, string $to, int $code, ?string $note): void
    {
        $from = Redirect::normalize($from);
        $to = Redirect::normalize($to);

        if ($from === $to || $from === '/') {
            return;
        }

        DB::table('redirects')->updateOrInsert(
            ['from_path' => $from],
            ['to_path' => $to, 'code' => $code, 'note' => Str::limit((string) $note, 255, ''), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
