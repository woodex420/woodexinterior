<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Every page asks for the next step (audit of 16 Oct 2026, docs/10).
 *
 * Forty of the 89 published pages — all of the cost guides — ended with an answer and then nothing: no form, no
 * link, no way to carry on. A guide is the page that brings a stranger in from Google; ending it on a full stop
 * throws away the only reason it exists.
 *
 * Each of those pages gets an enquiry form block at the end (the same block the city and project pages already
 * use), with a heading written for that kind of page. Runs once; anything the studio arranges afterwards in
 * Website -> Pages is left alone.
 */
return new class extends Migration
{
    /** @return array<string, string> heading by page type */
    private const array HEADINGS = [
        PageType::Insight->value => 'Ask us about your own numbers',
        PageType::Project->value => 'Start a project like this',
        PageType::City->value => 'Design in your city',
        PageType::Area->value => 'Design in your area',
        PageType::Service->value => 'Start your project',
        PageType::Listing->value => 'Tell us about your space',
        PageType::Home->value => 'Tell us about your space',
    ];

    public function up(): void
    {
        Page::query()
            ->where('status', PageStatus::Published->value)
            ->get()
            ->each(function (Page $page): void {
                if ($page->blocks()->where('type', BlockType::Form->value)->exists()) {
                    return;
                }

                $page->blocks()->create([
                    'type' => BlockType::Form->value,
                    'position' => ((int) $page->blocks()->max('position')) + 1,
                    'visible' => true,
                    'data' => [
                        'heading' => self::HEADINGS[$page->type->value] ?? 'Tell us about your space',
                        'text' => 'Share the location, size and timeline. We reply within one working day.',
                        'show_contact' => true,
                    ],
                ]);

                // The rendered page is cached against updated_at (PageCache); without this the old HTML stays.
                $page->touch();
            });
    }

    public function down(): void
    {
        Page::query()
            ->where('status', PageStatus::Published->value)
            ->get()
            ->each(function (Page $page): void {
                $page->blocks()
                    ->where('type', BlockType::Form->value)
                    ->where('data->heading', self::HEADINGS[$page->type->value] ?? 'Tell us about your space')
                    ->delete();

                $page->touch();
            });
    }
};
