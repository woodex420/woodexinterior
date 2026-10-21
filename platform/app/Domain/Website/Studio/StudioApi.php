<?php

declare(strict_types=1);

namespace App\Domain\Website\Studio;

use App\Domain\Bot\Models\BotKnowledge;
use App\Domain\Crm\Models\Lead;
use App\Domain\Sales\Enums\Unit;
use App\Domain\Sales\Models\EstimatorRate;
use App\Domain\System\Models\Automation;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\ContentLibrary;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageCache;
use App\Domain\Website\Services\PageCopier;
use App\Models\User;
use App\Settings\EstimatorSettings;
use App\Settings\NotificationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The server behind the ported v2.1 admin (docs/audit/16.7, 16.8).
 *
 * The admin's modules were written against one seam — `api(action, data)` posting `{action, ...}` and
 * expecting `{ok: true, ...}` — so this class is that seam and nothing else: a table of actions, each
 * mapped onto the model or service that already owns the data. It does not introduce a second content
 * model, a second media table or a second permission check.
 *
 * An action that has not been ported yet answers `{ok: false, error}` with the screen's name, because the
 * admin renders that as a plain message. Nothing here may pretend to have saved.
 */
final class StudioApi
{
    /** Actions implemented so far, and the method that serves each one. */
    private const array ACTIONS = [
        'status' => 'status',
        'me' => 'me',
        'logos_get' => 'logosGet',
        'logos_save' => 'logosSave',
        'media_upload' => 'mediaUpload',
        // The article and study editor (docs/audit/16.8): the list, the form, save, the Live switch,
        // delete and publish. `bapi_*` are the builder's three: preview shell, and the HTML snapshot.
        'cms_list' => 'cmsList',
        'cms_get' => 'cmsGet',
        'cms_save' => 'cmsSave',
        'cms_status' => 'cmsStatus',
        'cms_delete' => 'cmsDelete',
        'cms_published' => 'cmsPublished',
        // The Service pages screen (docs/audit/16.8): the page list, its types, and the two ways a new
        // service page is made — from an existing layout, or by duplicating a row.
        'pages' => 'pagesList',
        'cms_page_kinds' => 'pageKinds',
        // City pages: a new city is a draft copied from a template city, one at a time or several at once.
        'cms_city_new' => 'cityNew',
        'cms_city_bulk' => 'cityBulk',
        // The Forms screen reads the enquiry forms the public site posts, and the alert routing that is
        // already configured for all of them (there is no per-form store in the OS — see the screen's note).
        'cms_forms' => 'formsList',
        // The Estimator screen edits the public rate book (a file) and mirrors the rates into
        // estimator_rates, which the bot and the staff estimate builder price from (docs/audit/16.14).
        'cms_estimator' => 'estimatorBook',
        'cms_estimator_save' => 'estimatorSave',
        'cms_estimator_tpl' => 'estimatorTemplate',
        // The FAQs screen is the shared FAQ library every page's FAQ section can draw from (ContentEntry).
        'cms_faqs' => 'faqList',
        'cms_faq_save' => 'faqSave',
        'cms_faq_delete' => 'faqDelete',
        'cms_faq_order' => 'faqOrder',
        // The Testimonials screen is the shared review library the Reviews section draws from.
        'cms_testimonials' => 'testimonialList',
        'cms_testimonial_save' => 'testimonialSave',
        'cms_testimonial_delete' => 'testimonialDelete',
        'cms_testimonial_order' => 'testimonialOrder',
        'bapi_save' => 'builderSave',
        'bapi_page_new' => 'builderSave',
        'bapi_media' => 'builderMedia',
    ];

    /**
     * Actions the v2.1 admin had for screens Woodex OS 1 does not keep (step 18). They created or copied a
     * `pages` row, and a row stopped being a page when the renderer was retired (step 8) — the screen is
     * behind the door, so the action behind it refuses too, by name, and says where the work lives now.
     */
    private const array RETIRED = [
        'cms_page_new' => 'Service pages',
        'cms_page_duplicate' => 'Service pages',
    ];

    public function __construct(private readonly User $user) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $action, array $payload): array
    {
        if ($action === '') {
            throw new RuntimeException('No action was sent.');
        }

        $screen = self::RETIRED[$action] ?? null;

        if ($screen !== null) {
            throw new RuntimeException(
                "“{$screen}” is the old admin’s screen: it made a page row, and a row is not a page any more. "
                .'Pages are the site’s files — make and edit them in All pages and Page builder.'
            );
        }

        $method = self::ACTIONS[$action] ?? null;

        if ($method === null) {
            throw new RuntimeException("“{$action}” is not ported yet — this screen is still being moved from the v2.1 admin.");
        }

        /** @var array<string, mixed> $result */
        $result = $this->{$method}($payload);

        return $result;
    }

    /**
     * The signed-in staff member, in the shape the admin's shell expects.
     *
     * @return array{name: string, email: string, role: string, perms: list<string>}
     */
    public function user(): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->studioRole(),
            'perms' => [],
        ];
    }

    /** The admin's shell asks whether anyone is signed in before it draws anything. The OS session decides. */
    /** @param array<string, mixed> $payload */
    private function content(array $payload): StudioContent
    {
        return new StudioContent($this->user);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsList(array $payload): array
    {
        return $this->content($payload)->listing((string) ($payload['type'] ?? 'post'), (string) ($payload['q'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsGet(array $payload): array
    {
        return $this->content($payload)->get((int) ($payload['id'] ?? 0), $this->typeOf($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsSave(array $payload): array
    {
        return $this->content($payload)->save($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsStatus(array $payload): array
    {
        return $this->content($payload)->setStatus((int) ($payload['id'] ?? 0), (string) ($payload['status'] ?? 'draft'), $this->typeOf($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsDelete(array $payload): array
    {
        return $this->content($payload)->delete((int) ($payload['id'] ?? 0), $this->typeOf($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsPublished(array $payload): array
    {
        return $this->content($payload)->publish((int) ($payload['id'] ?? 0), $this->typeOf($payload));
    }

    /**
     * The pages the Service pages screen manages: everything except the article/study editors and the
     * city drafts, which have their own screens. `path` keeps the v2.1 shape (`foo/index.html`) because
     * the screen's own filters and labels were written for it; nothing on this server uses it as a file.
     *
     * @param  array<string, mixed>  $payload
     * @return array{pages: list<array{id: int, path: string, title: string, url: string, kind: string, status: string}>}
     */
    private function pagesList(array $payload): array
    {
        $pages = Page::query()
            ->whereNotIn('type', [PageType::Insight, PageType::Project])
            ->orderBy('type')
            ->orderBy('title')
            ->get()
            ->map(fn (Page $page): array => [
                'id' => $page->id,
                'path' => $this->pathOf($page),
                'title' => $page->title,
                'url' => $page->slug,
                'kind' => $page->type->value,
                'status' => $page->status->value,
            ])
            ->values()
            ->all();

        return ['pages' => $pages];
    }

    /**
     * The type badge the screen shows per page (v2.1 asked `cms_page_kinds` and got a path → kind map).
     *
     * @param  array<string, mixed>  $payload
     * @return array{kinds: array<string, string>}
     */
    private function pageKinds(array $payload): array
    {
        $kinds = [];

        foreach (Page::query()->get() as $page) {
            $kinds[$this->pathOf($page)] = $page->type->value;
        }

        return ['kinds' => $kinds];
    }

    /** @return array{id: int, title: string, url: string, status: string} */
    private function pageItem(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'url' => $page->slug,
            'status' => $page->status->value,
        ];
    }

    /**
     * The enquiry forms the v1 pages post, with what has actually arrived for each: submissions, the last
     * one, and the field names the leads carry. Nothing is written here — alert routing is configured once
     * for every form in Settings and Automations, and per-form routing would need a table the OS does not
     * have (docs/audit/16.13).
     *
     * @param  array<string, mixed>  $payload
     * @return array{forms: list<array<string, mixed>>, alerts: array<string, mixed>}
     */
    private function formsList(array $payload): array
    {
        $labels = [
            'brief' => '3D brief',
            'contact' => 'Contact form',
            'website' => 'Website form',
            'estimator' => 'Estimator',
            'booking' => 'Booking request',
            'book-a-visit' => 'Book a visit',
            'whatsapp' => 'WhatsApp widget',
        ];

        $forms = [];

        foreach ($labels as $key => $label) {
            $query = Lead::query()->where('source_detail', $key);

            $total = (clone $query)->count();
            $month = (clone $query)->where('created_at', '>=', now()->subDays(30))->count();
            $last = (clone $query)->max('created_at');

            /** @var list<string> $fields */
            $fields = [];

            foreach ((clone $query)->latest('id')->limit(200)->get(['meta']) as $lead) {
                /** @var array<string, mixed> $meta */
                $meta = (array) $lead->meta;

                foreach (array_keys((array) ($meta['fields'] ?? [])) as $field) {
                    $fields[(string) $field] = true;
                }
            }

            $forms[] = [
                'key' => $key,
                'label' => $label,
                'total' => $total,
                'month' => $month,
                'last' => $last === null ? null : (string) $last,
                'fields' => array_slice(array_keys($fields), 0, 30),
            ];
        }

        return ['forms' => $forms, 'alerts' => $this->alertRouting()];
    }

    /**
     * The shared testimonials library, with the pages already quoting each review.
     *
     * The shape is the one the site's Reviews section renders — text, name, when, source — so what is written
     * here is exactly what a visitor reads. v2.1 also had a per-review star rating and photo; the OS section
     * has neither, so this screen does not offer fields that would never appear (docs/audit/16.17).
     *
     * @param  array<string, mixed>  $payload
     * @return array{items: list<array<string, mixed>>, pages: int}
     */
    private function testimonialList(array $payload): array
    {
        $entries = ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->orderBy('position')->orderBy('id')->get();
        $used = $this->libraryUsage(ContentEntry::TESTIMONIAL);

        $items = [];

        foreach ($entries as $entry) {
            $items[] = [
                'id' => (int) $entry->id,
                'text' => (string) ($entry->data['text'] ?? ''),
                'name' => (string) ($entry->data['name'] ?? ''),
                'when' => (string) ($entry->data['when'] ?? ''),
                'source' => (string) ($entry->data['source'] ?? ''),
                'used_on' => $used[(int) $entry->id] ?? [],
            ];
        }

        return ['items' => $items, 'pages' => Page::query()->count()];
    }

    /**
     * Create or update one review. The words are quoted verbatim on the site, so nothing is rewritten here.
     *
     * @param  array<string, mixed>  $payload
     * @return array{item: array<string, mixed>}
     */
    private function testimonialSave(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = [
            'text' => trim((string) ($payload['text'] ?? '')),
            'name' => trim((string) ($payload['name'] ?? '')),
            'when' => trim((string) ($payload['when'] ?? '')),
            'source' => trim((string) ($payload['source'] ?? '')),
        ];

        if ($data['text'] === '') {
            throw new RuntimeException('Paste the client’s review — a review with no words cannot be shown.');
        }

        if (mb_strlen($data['text']) > 2000) {
            throw new RuntimeException('That review is over 2,000 characters — quote the part the client agreed to.');
        }

        if ($data['name'] === '') {
            throw new RuntimeException('Write the client’s name — an unsigned review reads as invented.');
        }

        if (mb_strlen($data['name']) > 120) {
            throw new RuntimeException('The client’s name is over 120 characters.');
        }

        if (mb_strlen($data['when']) > 40 || mb_strlen($data['source']) > 60) {
            throw new RuntimeException('Keep “When” under 40 characters and “Source” under 60.');
        }

        $entry = $id > 0
            ? ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->find($id)
            : null;

        if ($id > 0 && $entry === null) {
            throw new RuntimeException('That review is not in the list any more.');
        }

        if ($entry === null) {
            $entry = ContentEntry::query()->create([
                'kind' => ContentEntry::TESTIMONIAL,
                'data' => $data,
                'position' => (int) ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->max('position') + 1,
            ]);
        } else {
            $entry->data = $data;
            $entry->save();
        }

        ContentLibrary::flush();
        $this->revalidateLibraryPages('reviews');

        return ['item' => [
            'id' => (int) $entry->id,
            'text' => $data['text'],
            'name' => $data['name'],
            'when' => $data['when'],
            'source' => $data['source'],
            'used_on' => $this->libraryUsage(ContentEntry::TESTIMONIAL)[(int) $entry->id] ?? [],
        ]];
    }

    /**
     * Remove one review. As with the answers, the pages keep their blocks and the renderer skips a missing id.
     *
     * @param  array<string, mixed>  $payload
     * @return array{note: string}
     */
    private function testimonialDelete(array $payload): array
    {
        $entry = ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->find((int) ($payload['id'] ?? 0));

        if ($entry === null) {
            throw new RuntimeException('That review is already gone.');
        }

        $used = count($this->libraryUsage(ContentEntry::TESTIMONIAL)[(int) $entry->id] ?? []);
        $entry->delete();
        ContentLibrary::flush();
        $this->revalidateLibraryPages('reviews');

        return ['note' => $used === 0
            ? 'Deleted.'
            : "Deleted — {$used} reviews section(s) were showing it and no longer do."];
    }

    /**
     * Save the order the screen shows (up/down arrows post the whole list).
     *
     * @param  array<string, mixed>  $payload
     * @return array{note: string}
     */
    private function testimonialOrder(array $payload): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($payload['ids'] ?? []))));

        if ($ids === []) {
            throw new RuntimeException('The new order was empty.');
        }

        foreach ($ids as $position => $id) {
            ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->whereKey($id)->update(['position' => $position]);
        }

        ContentLibrary::flush();
        $this->revalidateLibraryPages('reviews');

        return ['note' => 'Order saved.'];
    }

    /**
     * Which pages show each library entry, read from the page blocks that pick it (never a stored list, so a
     * section edited by hand is counted too).
     *
     * @return array<int, list<array{id: int, title: string, url: string}>>
     */
    private function libraryUsage(string $kind): array
    {
        $blockType = $kind === ContentEntry::TESTIMONIAL ? 'reviews' : $kind;
        $usage = [];

        $blocks = PageBlock::query()
            ->where('type', $blockType)
            ->with(['page' => fn ($query) => $query->select('id', 'title', 'slug', 'type')])
            ->get();

        foreach ($blocks as $block) {
            $page = $block->page;

            if ($page === null) {
                continue;
            }

            foreach ((array) ($block->data['library_ids'] ?? []) as $id) {
                $usage[(int) $id][] = [
                    'id' => (int) $page->id,
                    'title' => (string) $page->title,
                    'url' => $page->path(),
                ];
            }
        }

        return $usage;
    }

    /**
     * The shared FAQ library, with the pages already using each answer.
     *
     * v2.1 kept named "FAQ groups"; the OS keeps one library and a page's FAQ section picks from it, which is
     * the same promise — write the answer once, reuse it anywhere — with one store instead of nested groups.
     * A named group would be a new table, so it is not pretended here (docs/audit/16.16).
     *
     * @param  array<string, mixed>  $payload
     * @return array{items: list<array<string, mixed>>, pages: int, bot: array<string, int>}
     */
    private function faqList(array $payload): array
    {
        $entries = ContentEntry::query()->ofKind(ContentEntry::FAQ)->orderBy('position')->orderBy('id')->get();
        $used = $this->faqUsage();

        $items = [];

        foreach ($entries as $entry) {
            $items[] = [
                'id' => (int) $entry->id,
                'question' => (string) ($entry->data['question'] ?? ''),
                'answer' => (string) ($entry->data['answer'] ?? ''),
                'used_on' => $used[(int) $entry->id] ?? [],
            ];
        }

        return [
            'items' => $items,
            'pages' => Page::query()->count(),
            'bot' => [
                'knowledge' => BotKnowledge::query()->where('is_active', true)->count(),
            ],
        ];
    }

    /**
     * Create or update one answer. The library is one ordered list, so a new entry goes to the end.
     *
     * @param  array<string, mixed>  $payload
     * @return array{item: array<string, mixed>}
     */
    private function faqSave(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $question = trim((string) ($payload['question'] ?? ''));
        $answer = trim((string) ($payload['answer'] ?? ''));

        if ($question === '') {
            throw new RuntimeException('Write the question first.');
        }

        if (mb_strlen($question) > 300) {
            throw new RuntimeException('The question is over 300 characters — split it into two.');
        }

        if ($answer === '') {
            throw new RuntimeException('Write the answer as well — a question on its own cannot be shown.');
        }

        if (mb_strlen($answer) > 5000) {
            throw new RuntimeException('The answer is over 5,000 characters.');
        }

        $entry = $id > 0
            ? ContentEntry::query()->ofKind(ContentEntry::FAQ)->find($id)
            : null;

        if ($id > 0 && $entry === null) {
            throw new RuntimeException('That question is not in the FAQ list any more.');
        }

        if ($entry === null) {
            $entry = ContentEntry::query()->create([
                'kind' => ContentEntry::FAQ,
                'data' => ['question' => $question, 'answer' => $answer],
                'position' => (int) ContentEntry::query()->ofKind(ContentEntry::FAQ)->max('position') + 1,
            ]);
        } else {
            $entry->data = ['question' => $question, 'answer' => $answer];
            $entry->save();
        }

        ContentLibrary::flush();
        $this->revalidateLibraryPages('faq');

        return ['item' => [
            'id' => (int) $entry->id,
            'question' => $question,
            'answer' => $answer,
            'used_on' => $this->faqUsage()[(int) $entry->id] ?? [],
        ]];
    }

    /**
     * Remove one answer. Pages keep their blocks; the renderer already skips an id that no longer exists, so
     * nothing is silently rewritten — the reply says how many pages were showing it.
     *
     * @param  array<string, mixed>  $payload
     * @return array{note: string}
     */
    private function faqDelete(array $payload): array
    {
        $entry = ContentEntry::query()->ofKind(ContentEntry::FAQ)->find((int) ($payload['id'] ?? 0));

        if ($entry === null) {
            throw new RuntimeException('That question is already gone.');
        }

        $used = count($this->faqUsage()[(int) $entry->id] ?? []);
        $entry->delete();
        ContentLibrary::flush();
        $this->revalidateLibraryPages('faq');

        return ['note' => $used === 0
            ? 'Deleted.'
            : "Deleted — {$used} page section(s) were showing it and no longer do."];
    }

    /**
     * Save the order the screen shows (up/down arrows post the whole list).
     *
     * @param  array<string, mixed>  $payload
     * @return array{note: string}
     */
    private function faqOrder(array $payload): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($payload['ids'] ?? []))));

        if ($ids === []) {
            throw new RuntimeException('The new order was empty.');
        }

        foreach ($ids as $position => $id) {
            ContentEntry::query()->ofKind(ContentEntry::FAQ)->whereKey($id)->update(['position' => $position]);
        }

        ContentLibrary::flush();
        $this->revalidateLibraryPages('faq');

        return ['note' => 'Order saved.'];
    }

    /**
     * A published page is rendered once and served from cache until the page itself changes. An answer in the
     * library is not part of that page's timestamp, so editing one would leave every page showing the old text
     * until somebody happened to edit the page — exactly the promise these screens make (“edit once, every page
     * updates”). Every page whose section of this type draws from the library is rendered again here.
     *
     * @param  'faq'|'reviews'  $blockType
     */
    private function revalidateLibraryPages(string $blockType): void
    {
        $pages = Page::query()
            ->whereHas('blocks', fn ($query) => $query->where('type', $blockType))
            ->with(['blocks' => fn ($query) => $query->where('type', $blockType)])
            ->get();

        foreach ($pages as $page) {
            foreach ($page->blocks as $block) {
                if ((array) ($block->data['library_ids'] ?? []) !== []) {
                    PageCache::invalidate($page);
                    break;
                }
            }
        }
    }

    /**
     * Which pages show each answer.
     *
     * @return array<int, list<array{id: int, title: string, url: string}>>
     */
    private function faqUsage(): array
    {
        return $this->libraryUsage(ContentEntry::FAQ);
    }

    /**
     * The rate book the public estimator is reading right now, plus the saved snapshots.
     *
     * @param  array<string, mixed>  $payload
     * @return array{book: array<string, mixed>, templates: list<array{name: string, at: string, services: int}>, page: string}
     */
    private function estimatorBook(array $payload): array
    {
        return [
            'book' => $this->rateBookForClient(RateBook::read()),
            'templates' => $this->rateBookTemplates(),
            'page' => '/estimator/',
        ];
    }

    /**
     * Save the rate book: the file the public page loads and the `estimator_rates` rows in one transaction.
     * If either side fails the file is put back, so the page can never show prices the bot does not use.
     *
     * @param  array<string, mixed>  $payload
     * @return array{book: array<string, mixed>, templates: list<array{name: string, at: string, services: int}>, saved: string}
     */
    private function estimatorSave(array $payload): array
    {
        $input = $payload['book'] ?? $payload;
        $book = RateBook::normalise(is_array($input) ? $input : []);
        $before = RateBook::read();

        // The screen never edits the catalogue: the 22 calculator add-ons stay exactly as they are.
        $book['catalog'] = $before['catalog'];

        try {
            DB::transaction(function () use ($book): void {
                RateBook::write($book);
                $this->mirrorEstimatorRates($book['services']);
            });
        } catch (Throwable $e) {
            // The public file is written inside the transaction; put it back if the rows did not commit.
            try {
                RateBook::write($before);
            } catch (Throwable) {
                // Nothing more can be done here — the error below still says the save failed.
            }

            throw $e;
        }

        return [
            'book' => $this->rateBookForClient($book),
            'templates' => $this->rateBookTemplates(),
            'saved' => 'The estimator page and the bot now use these rates.',
        ];
    }

    /**
     * Saved rate books: save the current prices under a name, delete one, or load one back into the editor.
     * Loading does not publish — the screen still has to press Save, as in v2.1.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function estimatorTemplate(array $payload): array
    {
        $op = (string) ($payload['op'] ?? '');
        $name = trim((string) ($payload['name'] ?? ''));
        $settings = EstimatorSettings::shelf();
        /** @var array<string, array{at: string, services: list<mixed>, finishes: list<mixed>, terms: list<mixed>}> $books */
        $books = $settings->rate_books;

        if ($op === 'delete') {
            if (! isset($books[$name])) {
                throw new RuntimeException('That rate book is not in the list.');
            }

            unset($books[$name]);
            $settings->rate_books = $books;
            $settings->save();

            return ['templates' => $this->rateBookTemplates(), 'note' => "“{$name}” deleted."];
        }

        if ($op === 'apply') {
            if (! isset($books[$name])) {
                throw new RuntimeException('That rate book is not in the list.');
            }

            $snapshot = $books[$name];

            return [
                'book' => $this->rateBookForClient([
                    'services' => $snapshot['services'],
                    'finishes' => $snapshot['finishes'],
                    'catalog' => RateBook::read()['catalog'],
                    'terms' => $snapshot['terms'],
                ]),
                'note' => "“{$name}” loaded into the editor — check it, then press Save & publish.",
            ];
        }

        if ($op !== 'save') {
            throw new RuntimeException('Unknown action for the saved rate books.');
        }

        if ($name === '' || mb_strlen($name) > 60) {
            throw new RuntimeException('Give the rate book a name of at most 60 characters.');
        }

        if (count($books) >= 12 && ! isset($books[$name])) {
            throw new RuntimeException('There are already 12 saved rate books — delete one first.');
        }

        $now = RateBook::read();
        $books[$name] = [
            'at' => now()->toIso8601String(),
            'services' => array_values($now['services']),
            'finishes' => array_values($now['finishes']),
            'terms' => array_values($now['terms']),
        ];

        // Newest first, so the list in the screen reads the same way the snapshots were taken.
        $books = array_reverse($books, true);

        $settings->rate_books = $books;
        $settings->save();

        return ['templates' => $this->rateBookTemplates(), 'note' => "“{$name}” saved."];
    }

    /**
     * The book as the screen edits it: catalogue entries are counted, not sent — the screen says they are
     * kept as they are. Finishes always arrive as the three the calculator uses, in order.
     *
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    private function rateBookForClient(array $book): array
    {
        $services = [];

        foreach ((array) ($book['services'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rates = is_array($row['rates'] ?? null) ? $row['rates'] : [];

            $services[] = [
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'hint' => (string) ($row['hint'] ?? ''),
                'unit' => (string) ($row['unit'] ?? 'sqft'),
                'unitLabel' => (string) ($row['unitLabel'] ?? ''),
                'rates' => [
                    'essential' => (int) ($rates['essential'] ?? 0),
                    'standard' => (int) ($rates['standard'] ?? 0),
                    'premium' => (int) ($rates['premium'] ?? 0),
                ],
            ];
        }

        $finishes = [];

        foreach (RateBook::FINISHES as $finish) {
            $label = ucfirst($finish);
            $hint = '';

            foreach ((array) ($book['finishes'] ?? []) as $row) {
                if (is_array($row) && (string) ($row['key'] ?? '') === $finish) {
                    $label = (string) ($row['label'] ?? $label);
                    $hint = (string) ($row['hint'] ?? '');
                    break;
                }
            }

            $finishes[] = ['key' => $finish, 'label' => $label, 'hint' => $hint];
        }

        $terms = [];

        foreach ((array) ($book['terms'] ?? []) as $term) {
            if (is_scalar($term) && trim((string) $term) !== '') {
                $terms[] = (string) $term;
            }
        }

        return [
            'services' => $services,
            'finishes' => $finishes,
            'terms' => $terms,
            'catalog_count' => count((array) ($book['catalog'] ?? [])),
        ];
    }

    /**
     * The saved snapshots for the screen, newest first.
     *
     * @return list<array{name: string, at: string, services: int}>
     */
    private function rateBookTemplates(): array
    {
        try {
            /** @var array<string, array{at?: string, services?: list<mixed>}> $books */
            $books = EstimatorSettings::shelf()->rate_books;
        } catch (Throwable) {
            return [];
        }

        $rows = [];

        foreach ($books as $name => $snapshot) {
            $rows[] = [
                'name' => (string) $name,
                'at' => (string) ($snapshot['at'] ?? ''),
                'services' => count((array) ($snapshot['services'] ?? [])),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return $rows;
    }

    /**
     * Keep the bot and the staff estimate builder on the same prices as the public page. Rows are never
     * deleted — a service taken off the website may still be referenced by an old estimate — they are
     * switched off instead.
     *
     * @param  list<array<string, mixed>>  $services
     */
    private function mirrorEstimatorRates(array $services): void
    {
        $keys = [];

        foreach ($services as $i => $service) {
            $key = (string) $service['key'];
            $keys[] = $key;
            $rates = (array) $service['rates'];

            EstimatorRate::updateOrCreate(['key' => $key], [
                'label' => (string) $service['label'],
                'hint' => $service['hint'] !== '' ? (string) $service['hint'] : null,
                'unit' => Unit::normalize((string) $service['unit'])->value,
                'unit_label' => (string) $service['unitLabel'],
                'essential_minor' => (int) $rates['essential'] * 100,
                'standard_minor' => (int) $rates['standard'] * 100,
                'premium_minor' => (int) $rates['premium'] * 100,
                'is_active' => true,
                'position' => $i,
            ]);
        }

        EstimatorRate::query()->whereNotIn('key', $keys)->update(['is_active' => false]);
    }

    /**
     * How the OS routes a new enquiry today. Read from the services that actually send it, so this screen
     * cannot drift from the behaviour it describes.
     *
     * @return array<string, mixed>
     */
    private function alertRouting(): array
    {
        try {
            $settings = app(NotificationSettings::class);

            return [
                'automation' => Automation::enabled('woodex:new-lead-alert'),
                'roles' => array_values((array) $settings->unassigned_lead_roles),
                'quiet' => [$settings->quiet_hours_start, $settings->quiet_hours_end],
                'telegram' => $settings->telegram_enabled,
            ];
        } catch (Throwable) {
            // Settings that have not been seeded yet must not break the screen.
            return ['automation' => null, 'roles' => [], 'quiet' => null, 'telegram' => null];
        }
    }

    /**
     * “New city”: a draft city page copied from a template city, with the template's city name swapped
     * for the new one (v2.1's cloneCity did the same against the HTML file).
     *
     * @param  array<string, mixed>  $payload
     * @return array{item: array{id: int, title: string, url: string, status: string}}
     */
    private function cityNew(array $payload): array
    {
        return ['item' => $this->createCity((string) ($payload['name'] ?? ''), (int) ($payload['from'] ?? 0))];
    }

    /**
     * “Bulk create”: one city per line, each a draft. A name that fails is reported and the rest continue.
     *
     * @param  array<string, mixed>  $payload
     * @return array{created: list<array{id: int, title: string, url: string, status: string}>, failed: list<array{name: string, error: string}>}
     */
    private function cityBulk(array $payload): array
    {
        $raw = $payload['names'] ?? [];
        $names = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n|,/', (string) $raw);
        $from = (int) ($payload['from'] ?? 0);

        $created = [];
        $failed = [];

        foreach (array_slice(array_values(array_filter(array_map(
            fn ($name): string => trim((string) $name),
            is_array($names) ? $names : []
        ))), 0, 25) as $name) {
            try {
                $created[] = $this->createCity($name, $from);
            } catch (Throwable $exception) {
                $failed[] = ['name' => $name, 'error' => $exception->getMessage()];
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * @return array{id: int, title: string, url: string, status: string}
     */
    private function createCity(string $name, int $from): array
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        if ($name === '') {
            throw new RuntimeException('A city name is needed.');
        }

        $source = $from > 0
            ? Page::query()->find($from)
            : Page::query()->where('type', PageType::City)->orderBy('id')->first();

        if ($source === null || $source->type !== PageType::City) {
            throw new RuntimeException('There is no city page to copy from yet.');
        }

        $slug = Page::normalizeSlug('/'.Str::slug($name).'/');

        if (Page::query()->where('slug', $slug)->exists()) {
            throw new RuntimeException("“{$name}” already has a page.");
        }

        $page = app(PageCopier::class)->copy($source, $name, $slug);
        $this->swapCityName($page, Str::headline(trim($source->slug, '/')), $name);

        PageCache::invalidate($page);
        Menus::flush();

        return $this->pageItem($page);
    }

    /**
     * Replace the template city's name inside the copied copy — the words, never a path or an image id,
     * so a copied page cannot end up pointing at the template's media.
     */
    private function swapCityName(Page $page, string $from, string $to): void
    {
        if ($from === '' || $from === $to) {
            return;
        }

        $pattern = '/(?<![\w-])'.preg_quote($from, '/').'(?![\w-])/u';

        $swap = function (mixed $value) use (&$swap, $pattern, $to) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = $swap($item);
                }

                return $value;
            }

            if (! is_string($value) || $value === '') {
                return $value;
            }

            // Addresses, media references and ids are copied as they are.
            if (preg_match('#^(/|https?:|mailto:|tel:|\#)#', $value) || str_contains($value, '/media/')) {
                return $value;
            }

            return (string) preg_replace($pattern, $to, $value);
        };

        foreach ($page->blocks as $block) {
            /** @var array<string, mixed> $data */
            $data = (array) $block->data;
            $block->forceFill(['data' => $swap($data)])->save();
        }
    }

    /** The v2.1 address shape the screen's filters were written for. No file is read or written. */
    private function pathOf(Page $page): string
    {
        return $page->slug === '/' ? 'index.html' : trim($page->slug, '/').'/index.html';
    }

    /**
     * The builder's `save` / `page_new`: the built HTML is kept as a page version — the rollback the static
     * file used to give. The site itself is rendered by the OS, so nothing is written to a public folder.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function builderSave(array $payload): array
    {
        $path = trim((string) ($payload['path'] ?? $payload['folder'].'/'.$payload['slug'].'/index.html'), '/');
        $html = (string) ($payload['html'] ?? '');

        $page = Page::query()->where('slug', '/'.preg_replace('#/index\.html$#', '/', $path))->first();

        if ($page === null) {
            throw new RuntimeException('Publish the page from the editor instead — the site renders it from its blocks.');
        }

        PageVersion::query()->create([
            'page_id' => $page->id,
            'user_id' => $this->user->id,
            'data' => ['html' => $html, 'path' => $path],
        ]);

        return ['path' => $path, 'mtime' => time()];
    }

    /**
     * The builder's `media`: the image picker's grid. The library's own images only — a picker that offered
     * a picture the page cannot render would be a trap.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function builderMedia(array $payload): array
    {
        // A row whose file is gone (an interrupted import, a deleted upload) must not be offered: the page
        // would render a broken image and nobody would know why.
        return ['media' => Media::query()->orderByDesc('id')->limit(500)->get()
            ->filter(fn (Media $media): bool => Storage::disk($media->disk)->exists($media->path))
            ->map(fn (Media $media): string => $media->url())
            ->values()
            ->all()];
    }

    /**
     * The content type of a row: the payload's own, or read from the page when the screen only sends an id.
     *
     * @param  array<string, mixed>  $payload
     */
    private function typeOf(array $payload): string
    {
        $type = (string) ($payload['type'] ?? '');

        if ($type !== '') {
            return $type;
        }

        $page = Page::query()->find((int) ($payload['id'] ?? 0));

        return $page?->type === PageType::Project ? 'study' : 'post';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function status(array $payload): array
    {
        return ['user' => $this->user()];
    }

    /** @return array<string, mixed> */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function me(array $payload): array
    {
        return ['token' => 'os-session', 'user' => $this->user()];
    }

    /**
     * The client logos, read from the home page's clients section — the section is the live record, so the
     * screen can never show something the site does not.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function logosGet(array $payload): array
    {
        $block = $this->clientsBlock();

        if ($block === null) {
            return ['data' => [
                'show' => false, 'kicker' => 'Trusted by', 'title' => '', 'mode' => 'grid', 'speed' => 30, 'grey' => true,
                'items' => [], 'missing' => true,
            ]];
        }

        $data = $block->data ?? [];
        $media = $this->mediaFor($data);

        $items = [];

        foreach ((array) ($data['library_ids'] ?? []) as $id) {
            $entry = ContentEntry::query()->where('kind', ContentEntry::LOGO)->find((int) $id);
            $image = isset($entry?->data['media_id']) ? ($media[(int) $entry->data['media_id']] ?? null) : null;

            if ($entry === null || $image === null) {
                continue;
            }

            $items[] = [
                'name' => (string) ($entry->data['name'] ?? $image->title ?? ''),
                'logo' => $image->url(),
                'url' => (string) ($entry->data['url'] ?? ''),
            ];
        }

        // Logos written straight onto the page (the section allows both) are shown after the library's.
        foreach ((array) ($data['logos'] ?? []) as $row) {
            $image = isset($row['id']) ? ($media[(int) $row['id']] ?? null) : null;

            if ($image !== null) {
                $items[] = ['name' => (string) ($row['alt'] ?? $image->title ?? ''), 'logo' => $image->url(), 'url' => (string) ($row['url'] ?? '')];
            }
        }

        return ['data' => [
            // The section counts as shown when it is visible on the page and has something in it: a heading
            // with no logos under it is not a client row.
            'show' => $block->visible && $items !== [],
            'kicker' => (string) ($data['heading'] ?? 'Trusted by'),
            'title' => (string) ($data['heading_strong'] ?? ''),
            'mode' => ($data['layout'] ?? 'grid') === 'slider' ? 'slider' : 'grid',
            'speed' => (int) ($data['speed'] ?? 30),
            'grey' => (bool) ($data['grey'] ?? true),
            'items' => $items,
        ]];
    }

    /**
     * Writes the screen's state back to the same block, so the public section changes with it.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function logosSave(array $payload): array
    {
        $incoming = $payload['data'] ?? null;

        if (! is_array($incoming)) {
            throw new RuntimeException('The logos were not sent.');
        }

        $block = $this->clientsBlock();

        // No section yet: the screen's job is to own the home page's client row, so it creates it — placed
        // before the closing form, where the v2.1 home page carries it, and invisible until it has a logo.
        if ($block === null) {
            $block = $this->createClientsBlock();
        }

        if ($block === null) {
            throw new RuntimeException('There is no published home page to put the client row on.');
        }

        $page = Page::query()->findOrFail($block->page_id);
        $rows = array_values(array_filter((array) ($incoming['items'] ?? []), 'is_array'));

        $data = $block->data ?? [];
        $data['heading'] = trim((string) ($incoming['kicker'] ?? 'Trusted by')) ?: 'Trusted by';
        $data['heading_strong'] = trim((string) ($incoming['title'] ?? ''));
        $data['layout'] = ($incoming['mode'] ?? 'grid') === 'slider' ? 'slider' : 'grid';
        $data['speed'] = max(10, min(90, (int) ($incoming['speed'] ?? 30)));
        $data['grey'] = (bool) ($incoming['grey'] ?? true);

        DB::transaction(function () use ($block, $data, $rows, $incoming): void {
            $ids = [];

            foreach ($rows as $position => $row) {
                $mediaId = $this->mediaIdFromUrl((string) ($row['logo'] ?? ''));

                if ($mediaId === null) {
                    continue;
                }

                $entry = ContentEntry::query()
                    ->where('kind', ContentEntry::LOGO)
                    ->where('data->media_id', $mediaId)
                    ->first() ?? new ContentEntry(['kind' => ContentEntry::LOGO]);

                $entry->fill([
                    'kind' => ContentEntry::LOGO,
                    'position' => $position,
                    'data' => [
                        'media_id' => $mediaId,
                        'alt' => (string) ($row['name'] ?? ''),
                        'name' => (string) ($row['name'] ?? ''),
                        'url' => (string) ($row['url'] ?? ''),
                    ],
                ])->save();

                $ids[] = $entry->id;
            }

            $data['library_ids'] = $ids;
            $data['logos'] = [];

            $block->update([
                'data' => $data,
                'visible' => (bool) ($incoming['show'] ?? true) && $ids !== [],
            ]);

            PageCache::invalidate(Page::query()->findOrFail($block->page_id));
            ContentLibrary::flush();
        });

        return $this->logosGet([]);
    }

    /**
     * One image, base64 (the admin sends files that way because the builder's uploader already did).
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mediaUpload(array $payload): array
    {
        $raw = (string) ($payload['data'] ?? '');

        if ($raw === '' || strlen($raw) > 8_000_000) {
            throw new RuntimeException('That image is empty or larger than 5 MB.');
        }

        $binary = base64_decode($raw, true);

        if ($binary === false) {
            throw new RuntimeException('That upload was not an image.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'wx21');

        if ($temporary === false) {
            throw new RuntimeException('The upload could not be written.');
        }

        file_put_contents($temporary, $binary);

        $file = new UploadedFile($temporary, (string) ($payload['name'] ?? 'image.png'), null, null, true);

        if (! in_array($file->extension(), ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize($temporary) === false) {
            @unlink($temporary);

            throw new RuntimeException('Only JPG, PNG or WebP images can be uploaded.');
        }

        $name = pathinfo((string) ($payload['name'] ?? 'image'), PATHINFO_FILENAME);
        $path = 'website/'.now()->format('Y/m').'/'.Str::slug($name).'-'.Str::lower(Str::random(6)).'.'.$file->extension();

        Storage::disk('public')->put($path, $binary);
        // Extension and contents were checked above; the size is a bonus for the library's columns.
        /** @var mixed $size */
        $size = @getimagesize($temporary);
        $width = is_array($size) ? (int) $size[0] : null;
        $height = is_array($size) ? (int) $size[1] : null;

        $media = Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'alt' => Str::headline($name),
            'title' => Str::headline($name),
            'width' => $width,
            'height' => $height,
            'folder' => 'website/'.now()->format('Y/m'),
            'uploaded_by' => $this->user->id,
        ]);

        @unlink($temporary);

        return ['url' => $media->url(), 'id' => $media->id];
    }

    /**
     * The studio's home page carries the client row just above the enquiry form; a new section goes there.
     * Its block key is stable and readable, so the page editor shows what created it.
     */
    private function createClientsBlock(): ?PageBlock
    {
        $home = $this->homePage();

        if ($home === null) {
            return null;
        }

        return DB::transaction(function () use ($home): PageBlock {
            $blocks = $home->blocks()->orderBy('position')->orderBy('id')->get();
            $before = $blocks->search(fn (PageBlock $block): bool => in_array($block->type->value, ['form', 'cta'], true));
            $at = $before === false ? $blocks->count() : $before;

            $block = $home->blocks()->create([
                'type' => 'clients',
                'block_key' => 'bclients01',
                'data' => ['heading' => 'Trusted by', 'library_ids' => [], 'logos' => []],
                'visible' => false,
                'position' => $at,
            ]);

            // Integer positions are shared with the blocks already there, and a tie is broken by id — which
            // would put the new row after the form. Renumber the whole page instead, so "before the form"
            // stays true however many sections there are.
            $order = $blocks->take($at)->push($block)->concat($blocks->slice($at));

            $order->values()->each(function (PageBlock $row, int $position): void {
                if ($row->position !== $position) {
                    $row->forceFill(['position' => $position])->save();
                }
            });

            return $block;
        });
    }

    private function homePage(): ?Page
    {
        return Page::query()
            ->where('type', PageType::Home)
            ->where('status', PageStatus::Published)
            ->orderBy('sort')
            ->orderBy('id')
            ->first()
            ?? Page::query()->where('slug', '/')->first();
    }

    private function clientsBlock(): ?PageBlock
    {
        return $this->homePage()?->blocks()->where('type', 'clients')->orderBy('position')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, Media>
     */
    private function mediaFor(array $data): array
    {
        $ids = array_values(array_filter(array_map('intval', array_merge(
            (array) ($data['library_ids'] ?? []),
            array_map(fn ($row): int => (int) ($row['id'] ?? 0), (array) ($data['logos'] ?? [])),
        ))));

        $mediaIds = ContentEntry::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (ContentEntry $entry): int => (int) ($entry->data['media_id'] ?? 0))
            ->all();

        return Media::query()
            ->whereIn('id', array_filter([...$mediaIds, ...$ids]))
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** `/media/12/logo.png?v=…` → 12. An outside URL has no media row, and is refused rather than faked. */
    private function mediaIdFromUrl(string $url): ?int
    {
        if (preg_match('~/media/(\d+)/~', $url, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /** The admin's role list, for its own labels. The real check is the permission on the route. */
    private function studioRole(): string
    {
        return $this->user->hasRole('owner') ? 'owner' : 'admin';
    }
}
