<?php

use App\Domain\Website\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * The privacy page said our data lived in Supabase and the site was served by Vercel (found by the audit of
 * 17 Oct 2026, docs/10). It does not: enquiries are stored in this application's own MySQL database and the
 * website is served from the studio's Hostinger account. A privacy notice is a promise, and a wrong promise on
 * the one page written to be trusted is the worst kind of small error.
 *
 * The rest of the imported wording is left as it is — the studio rewrites it when it humanizes the page.
 */
return new class extends Migration
{
    private const string FROM = 'Form and chat data is stored in our database, hosted by Supabase, and the website itself is served by Vercel.';

    private const string TO = 'Form and chat data is stored in our own database on the server that runs this website. We do not pass it to any other hosting or analytics provider.';

    public function up(): void
    {
        $this->replace(self::FROM, self::TO);
    }

    public function down(): void
    {
        $this->replace(self::TO, self::FROM);
    }

    private function replace(string $from, string $to): void
    {
        Page::query()->where('slug', '/privacy/')->first()?->blocks->each(function ($block) use ($from, $to): void {
            $data = $block->data;

            foreach (['body', 'text'] as $key) {
                if (isset($data[$key]) && is_string($data[$key]) && str_contains($data[$key], $from)) {
                    $data[$key] = str_replace($from, $to, $data[$key]);
                }
            }

            $block->data = $data;
            $block->save();
        });
    }
};
