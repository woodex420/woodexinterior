<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Models\DocumentDesign;

/** A value copy, including logo bytes. Documents never follow the mutable design master at render time. */
final class DocumentStyles
{
    /** @return array<string, mixed> */
    public function copy(BusinessLine $line): array
    {
        $design = DocumentDesign::query()->where('business_line', $line === BusinessLine::Furniture ? 'furniture' : 'interior')->firstOrFail();

        return $design->only(['id', 'business_line', 'revision', 'brand_name', 'accent_color', 'header_text', 'footer_text', 'logo_data_uri', 'logo_sha256']);
    }
}
