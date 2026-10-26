<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use Illuminate\Support\Facades\Validator;

/** Safe furniture presentation and commercial text; never accepts arbitrary CSS or HTML. */
final class FurnitureDocument
{
    public const TERMS = "Advance requested and remainder are shown in the totals above.\nMaterials, finish and fabric are subject to client approval.\nDelivery starts after advance confirmation and material approval.\nQuotation validity is shown above.";

    /** @return array<string, string> */
    public static function style(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $defaults = ['use_default_logo' => 'yes', 'header_align' => 'center', 'footer_align' => 'center', 'secondary_color' => '#c8ccca', 'quotation_title' => 'Quotation', 'invoice_title' => 'Invoice'];
        foreach ($defaults as $key => $default) {
            $candidate = $value[$key] ?? null;
            if (! is_string($candidate)) {
                continue;
            }
            $valid = $key === 'use_default_logo' ? in_array($candidate, ['yes', 'no'], true) : (str_ends_with($key, '_align') ? in_array($candidate, ['left', 'center', 'right'], true)
                : ($key === 'secondary_color' ? preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) === 1 : mb_strlen($candidate) <= 60 && trim($candidate) !== ''));
            if ($valid) {
                $defaults[$key] = trim($candidate);
            }
        }

        return $defaults;
    }

    /** @return array<string, string> */
    public static function text(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $defaults = ['document_title' => '', 'delivery_text' => '', 'transport_text' => 'Outstation charges depend on city, quantity and load.', 'tax_note' => '', 'po_reference' => ''];
        foreach ($defaults as $key => $default) {
            if (is_string($value[$key] ?? null)) {
                $defaults[$key] = mb_substr(trim($value[$key]), 0, $key === 'document_title' ? 60 : 300);
            }
        }

        return $defaults;
    }

    /** @param array<string, mixed> $value
     * @return array<string, string>
     */
    public static function validatedText(array $value): array
    {
        Validator::make($value, ['document_title' => ['nullable', 'string', 'max:60'], 'delivery_text' => ['nullable', 'string', 'max:300'], 'transport_text' => ['nullable', 'string', 'max:300'], 'tax_note' => ['nullable', 'string', 'max:300'], 'po_reference' => ['nullable', 'string', 'max:300']])->validate();

        return self::text($value);
    }
}
