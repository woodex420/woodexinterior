<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Settings\BusinessSettings;
use App\Support\Phone;
use Illuminate\Contracts\View\View;

/**
 * The public home page (website phase P6, first release). Content is still in the view; the page builder and the
 * 142 legacy URLs arrive with the rest of the phase. The enquiry form posts to the same public lead API as the
 * holding page did, so every enquiry lands in the CRM.
 */
final class HomeController
{
    public function __invoke(BusinessSettings $business): View
    {
        return view('site.home', [
            'business' => $business,
            'phone' => Phone::display($business->phone),
            'phoneUrl' => Phone::telUrl($business->phone),
            'whatsapp' => Phone::whatsappUrl(
                $business->whatsapp,
                'Assalam o Alaikum, I would like to discuss a project with Woodex Interior.',
            ),
            'canonical' => url('/'),
            'ogImage' => url('/img/og-home.jpg'),
            'services' => self::services(),
            'stages' => self::stages(),
            'studies' => self::studies(),
            'reviews' => self::reviews(),
            'clients' => self::clients(),
            'faqs' => self::faqs(),
        ]);
    }

    /**
     * The six disciplines, in the order used everywhere (PDF, client page, website).
     *
     * @return list<array{number: string, name: string, copy: string}>
     */
    public static function services(): array
    {
        return [
            ['number' => '01', 'name' => 'Interior Design', 'copy' => 'Space planning, layouts, materials and lighting resolved into one clear design, so every room works the way you live or do business.'],
            ['number' => '02', 'name' => 'Fit-Out', 'copy' => 'One team runs the whole site, from shell and services to ceilings, joinery and finishes, on one programme and one set of drawings.'],
            ['number' => '03', 'name' => 'Renovation', 'copy' => 'Homes, offices and shops reworked in planned phases, with dust, noise and downtime controlled while you keep using the space.'],
            ['number' => '04', 'name' => 'Architecture', 'copy' => 'Building concepts, elevations and construction-ready drawings that join the inside and outside into one considered form.'],
            ['number' => '05', 'name' => '3D Studio', 'copy' => 'Photorealistic renders, walkthroughs and virtual tours made in house at 4K, with two revision rounds, so you approve the space before work begins.'],
            ['number' => '06', 'name' => 'Furniture', 'copy' => 'Custom kitchens, wardrobes and office furniture made to the drawing and fitted to the millimetre on site.'],
        ];
    }

    /**
     * @return list<array{number: string, name: string, copy: string}>
     */
    public static function stages(): array
    {
        return [
            ['number' => '01', 'name' => 'Brief & survey', 'copy' => 'We visit, measure and record how the space must work, your budget and your timeline.'],
            ['number' => '02', 'name' => 'Design & 3D', 'copy' => 'Layouts, materials and lighting resolved, then shown in photoreal 3D before you commit.'],
            ['number' => '03', 'name' => 'Build & make', 'copy' => 'Site trades and joinery teams deliver to one programme and one set of drawings.'],
            ['number' => '04', 'name' => 'Handover', 'copy' => 'Snagging, final finishes and a walkthrough, with the space ready to use on the agreed date.'],
        ];
    }

    /**
     * Selected studies shown on the home page. Photography comes from the current live website; the owner replaces it
     * with real project photographs when the project pages are built (P6).
     *
     * @return list<array{image: string, alt: string, category: string, title: string, note: string}>
     */
    public static function studies(): array
    {
        return [
            ['image' => 'img/work/work-1.webp', 'alt' => 'Contemporary office interior with workstations and warm timber finishes', 'category' => 'Commercial', 'title' => 'Workplace planning study', 'note' => 'Fit-out'],
            ['image' => 'img/work/work-2.webp', 'alt' => 'Home interior during a phased Woodex renovation', 'category' => 'Renovation', 'title' => 'Phased home upgrade', 'note' => 'Renovation'],
            ['image' => 'img/work/work-3.webp', 'alt' => 'Photoreal 3D study of a contemporary living room', 'category' => '3D', 'title' => 'Living room visualisation', 'note' => '3D Studio'],
            ['image' => 'img/work/work-4.webp', 'alt' => 'Bedroom with custom fitted wardrobes and layered lighting', 'category' => 'Residential', 'title' => 'Bedroom and joinery study', 'note' => 'Furniture'],
            ['image' => 'img/work/work-5.webp', 'alt' => 'Retail showroom interior visualisation study', 'category' => 'Commercial', 'title' => 'Showroom study', 'note' => 'Fit-out'],
            ['image' => 'img/work/work-6.webp', 'alt' => 'Restaurant dining interior study', 'category' => 'Commercial', 'title' => 'Dining room study', 'note' => 'Fit-out'],
        ];
    }

    /**
     * Published Google reviews, as shown on the current live website.
     *
     * @return list<array{name: string, when: string, text: string}>
     */
    public static function reviews(): array
    {
        return [
            ['name' => 'Amna Zafar', 'when' => '5 months ago', 'text' => 'Excellent work on our 1 kanal house interior design project. The team was professional, creative and easy to work with from the first meeting to handover.'],
            ['name' => 'Shahroz Ahmed', 'when' => '8 months ago', 'text' => 'The design was practical and modern, and the execution was handled professionally from start to finish.'],
            // Verbatim from the live website's Google reviews widget (odd phrasing and all): never rewrite a testimonial.
            ['name' => 'Shah Nawaz', 'when' => '6 months ago', 'text' => 'One of the best designers, specially Arishma ma’am.'],
        ];
    }

    /**
     * @return list<array{name: string, logo: string}>
     */
    public static function clients(): array
    {
        return [
            ['name' => 'Shell', 'logo' => 'img/clients/shell.webp'],
            ['name' => 'PSO', 'logo' => 'img/clients/pso.webp'],
            ['name' => 'Total Parco', 'logo' => 'img/clients/total-parco.webp'],
            ['name' => 'Attock Petroleum', 'logo' => 'img/clients/attock.webp'],
            ['name' => 'MG Motor', 'logo' => 'img/clients/mg.webp'],
            ['name' => 'Xiaomi', 'logo' => 'img/clients/xiaomi.webp'],
        ];
    }

    /**
     * Questions clients ask first. Each answer is written to stand on its own: search engines and AI assistants
     * quote these lines directly (FAQPage structured data below).
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(): array
    {
        return [
            ['question' => 'What services does Woodex Interior provide?', 'answer' => 'Interior design, fit-out, renovation, architecture, 3D visualisation and custom furniture, all coordinated by one team from our Lahore studio.'],
            ['question' => 'Can Woodex manage a project from design to handover?', 'answer' => 'Yes. Appoint us for one service, or let one accountable team handle design, quantities, site work, furniture and the final handover.'],
            ['question' => 'Does Woodex only work in Lahore?', 'answer' => 'Lahore is our base. We also deliver selected projects in Islamabad, Karachi and other cities across Pakistan.'],
            ['question' => 'How is the cost of a project worked out?', 'answer' => 'After a site visit we prepare an itemised quotation based on the scope, area, materials and site condition, so you know what each part costs before work starts.'],
            ['question' => 'Will I see the design before work begins?', 'answer' => 'Yes. Layouts, materials and lighting are shown in photoreal 3D, and we revise them with you until the design is approved.'],
            ['question' => 'How do we begin a project?', 'answer' => 'Share the location, approximate area, intended use, timeline and any drawings you already have. We will confirm the right next step within one working day.'],
        ];
    }
}
