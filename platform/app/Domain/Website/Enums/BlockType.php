<?php

declare(strict_types=1);

namespace App\Domain\Website\Enums;

/**
 * The section library (docs/09 §4). One type = one Blade component in resources/views/site/sections.
 */
enum BlockType: string
{
    case Hero = 'hero';
    case Intro = 'intro';
    case Text = 'text';
    case Services = 'services';
    case Process = 'process';
    case Gallery = 'gallery';
    case Faq = 'faq';
    case Related = 'related';
    case Form = 'form';
    case Reviews = 'reviews';
    case Specs = 'specs';
    case PriceTable = 'price_table';
    case Author = 'author';
    case Map = 'map';
    case Clients = 'clients';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::Intro => 'Intro + key facts',
            self::Text => 'Text',
            self::Services => 'Services grid',
            self::Process => 'Process / stages',
            self::Gallery => 'Gallery',
            self::Faq => 'Questions & answers',
            self::Related => 'Related pages',
            self::Form => 'Enquiry form',
            self::Reviews => 'Reviews',
            self::Specs => 'Specifications table',
            self::PriceTable => 'Price bands',
            self::Author => 'Author',
            self::Map => 'Map + contact',
            self::Clients => 'Client logos',
        };
    }

    /**
     * One line that tells a non-technical editor what this section is for — the section library's blurb
     * (docs/12 §5.2). One declaration, two uses: the library panel and the editor header.
     */
    public function description(): string
    {
        return match ($this) {
            self::Hero => 'The opening: a big heading, a photo and the studio\'s first promise.',
            self::Intro => 'A short paragraph plus key figures — what this page is about.',
            self::Text => 'A heading and free text: the workhorse for telling any story.',
            self::Services => 'A grid of services with a line each.',
            self::Process => 'The studio\'s stages, numbered from first call to handover.',
            self::Gallery => 'A grid of photographs picked from the media library.',
            self::Faq => 'Questions and answers — they also feed Google\'s rich results.',
            self::Related => 'Cards linking to other pages: services, cities, insights.',
            self::Form => 'The enquiry form with consent, wired straight into the CRM.',
            self::Reviews => 'Client reviews with names, ratings and dates.',
            self::Specs => 'A specifications table: what is included, area, timeline.',
            self::PriceTable => 'Price bands with what each band includes.',
            self::Author => 'The person behind the studio: photo, bio, credentials.',
            self::Map => 'The studio\'s address on a map, with phone and hours.',
            self::Clients => 'Client logos — the quiet trust signal.',
        };
    }

    /**
     * The section library's groups (docs/12 §5.2): Hero first, then proof, content and the sections that
     * ask for the enquiry. `label()` names the section; this places it on the shelf.
     */
    public function category(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::Clients, self::Reviews, self::Gallery, self::Related, self::Author => 'Proof',
            self::Intro, self::Text, self::Services, self::Process, self::Specs, self::PriceTable, self::Faq => 'Content',
            self::Form, self::Map => 'Conversion',
        };
    }

    /**
     * Fields the editor shows, with the empty value used when a section is added.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Hero => ['eyebrow' => '', 'heading' => '', 'text' => '', 'image_id' => null, 'primary_label' => 'Start your project', 'primary_url' => '#enquiry', 'secondary_label' => '', 'secondary_url' => '', 'facts' => []],
            self::Intro => ['eyebrow' => '', 'heading' => '', 'body' => '', 'image_id' => null, 'facts' => []],
            self::Text => ['heading' => '', 'body' => ''],
            self::Services => ['heading' => '', 'items' => []],
            self::Process => ['heading' => '', 'stages' => []],
            self::Gallery => ['heading' => '', 'images' => []],
            self::Faq => ['heading' => 'Common questions', 'items' => []],
            self::Related => ['heading' => 'Related', 'page_ids' => []],
            self::Form => ['heading' => 'Start your project', 'text' => '', 'show_contact' => true],
            self::Reviews => ['heading' => 'What our clients say', 'items' => []],
            self::Specs => ['heading' => 'Materials & finishes', 'rows' => []],
            self::PriceTable => ['heading' => 'Indicative investment', 'rows' => [], 'note' => ''],
            self::Author => ['name' => '', 'role' => '', 'bio' => '', 'image_id' => null],
            self::Map => ['heading' => 'Studio', 'embed_url' => ''],
            self::Clients => ['heading' => 'Trusted by', 'logos' => []],
        };
    }

    /**
     * The fields the page editor shows for this section (docs/09 §4).
     *
     * @return list<array{key: string, label: string, type: string, rows?: int, fields?: list<array{key: string, label: string, type: string, rows?: int}>}>
     */
    public function schema(): array
    {
        $nameValue = [
            ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
            ['key' => 'value', 'label' => 'Value', 'type' => 'text'],
        ];

        return match ($this) {
            self::Hero => [
                ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                ['key' => 'heading', 'label' => 'Heading (the H1)', 'type' => 'textarea', 'rows' => 2],
                ['key' => 'text', 'label' => 'Intro', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'image_id', 'label' => 'Background photo', 'type' => 'image'],
                ['key' => 'primary_label', 'label' => 'Button label', 'type' => 'text'],
                ['key' => 'primary_url', 'label' => 'Button link', 'type' => 'text'],
                ['key' => 'secondary_label', 'label' => 'Second button label', 'type' => 'text'],
                ['key' => 'secondary_url', 'label' => 'Second button link', 'type' => 'text'],
                ['key' => 'facts', 'label' => 'Key figures', 'type' => 'repeater', 'fields' => $nameValue],
            ],
            self::Intro => [
                ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'body', 'label' => 'Text', 'type' => 'textarea', 'rows' => 6],
                ['key' => 'image_id', 'label' => 'Photo', 'type' => 'image'],
                ['key' => 'facts', 'label' => 'Key facts', 'type' => 'repeater', 'fields' => $nameValue],
            ],
            self::Text => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'body', 'label' => 'Text', 'type' => 'textarea', 'rows' => 8],
            ],
            self::Services => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'items', 'label' => 'Services', 'type' => 'repeater', 'fields' => [
                    ['key' => 'number', 'label' => 'Number', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['key' => 'copy', 'label' => 'Description', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'url', 'label' => 'Link', 'type' => 'text'],
                ]],
            ],
            self::Process => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'stages', 'label' => 'Stages', 'type' => 'repeater', 'fields' => [
                    ['key' => 'number', 'label' => 'Number', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['key' => 'copy', 'label' => 'Description', 'type' => 'textarea', 'rows' => 2],
                ]],
            ],
            self::Gallery => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'images', 'label' => 'Images', 'type' => 'repeater', 'fields' => [
                    ['key' => 'id', 'label' => 'Image', 'type' => 'image'],
                    ['key' => 'caption', 'label' => 'Caption', 'type' => 'text'],
                    ['key' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
                ]],
            ],
            self::Faq => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'items', 'label' => 'Questions', 'type' => 'repeater', 'fields' => [
                    ['key' => 'question', 'label' => 'Question', 'type' => 'text'],
                    ['key' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rows' => 3],
                ]],
            ],
            self::Related => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'page_ids', 'label' => 'Pages', 'type' => 'pages'],
            ],
            self::Form => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Intro', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'show_contact', 'label' => 'Show studio address, phone and hours', 'type' => 'bool'],
            ],
            self::Reviews => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'items', 'label' => 'Reviews', 'type' => 'repeater', 'fields' => [
                    ['key' => 'text', 'label' => 'Review', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'name', 'label' => 'Client name', 'type' => 'text'],
                    ['key' => 'when', 'label' => 'When', 'type' => 'text'],
                    ['key' => 'source', 'label' => 'Source (Google, …)', 'type' => 'text'],
                ]],
            ],
            self::Specs => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'rows', 'label' => 'Specifications', 'type' => 'repeater', 'fields' => $nameValue],
            ],
            self::PriceTable => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'rows', 'label' => 'Bands', 'type' => 'repeater', 'fields' => $nameValue],
                ['key' => 'note', 'label' => 'Note under the table', 'type' => 'textarea', 'rows' => 2],
            ],
            self::Author => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Role', 'type' => 'text'],
                ['key' => 'bio', 'label' => 'Short bio', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'image_id', 'label' => 'Photo', 'type' => 'image'],
            ],
            self::Map => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'embed_url', 'label' => 'Map embed URL', 'type' => 'text'],
            ],
            self::Clients => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'logos', 'label' => 'Logos', 'type' => 'repeater', 'fields' => [
                    ['key' => 'id', 'label' => 'Logo', 'type' => 'image'],
                    ['key' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
                ]],
            ],
        };
    }

    public function view(): string
    {
        return 'site.sections.'.str_replace('_', '-', $this->value);
    }
}
