<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\DocumentDesign;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SaveDocumentDesign
{
    /** @param array<string, mixed> $values */
    public function handle(User $by, string $line, array $values, int $expectedRevision, ?UploadedFile $logo = null, bool $removeLogo = false): DocumentDesign
    {
        $by = User::query()->findOrFail($by->id);
        abort_unless($by->is_active, 403);
        Gate::forUser($by)->authorize('settings.manage');
        foreach (['brand_name', 'header_text', 'footer_text', 'accent_color'] as $field) {
            if (is_string($values[$field] ?? null)) {
                $values[$field] = trim($values[$field]);
            }
        }
        $data = Validator::make(array_merge($values, ['business_line' => $line, 'logo' => $logo]), [
            'business_line' => ['required', Rule::in(['interior', 'furniture'])],
            'brand_name' => ['required', 'string', 'max:35'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_text' => ['present', 'string', 'max:100'],
            'footer_text' => ['present', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:96', 'dimensions:min_width=8,min_height=8,max_width=600,max_height=240'],
        ])->validate();
        unset($data['logo'], $data['business_line']);
        if ($logo !== null) {
            $bytes = $logo->get();
            $data['logo_data_uri'] = 'data:'.$logo->getMimeType().';base64,'.base64_encode($bytes);
            $data['logo_sha256'] = hash('sha256', $bytes);
        } elseif ($removeLogo) {
            $data['logo_data_uri'] = null;
            $data['logo_sha256'] = null;
        }

        return DB::transaction(function () use ($by, $line, $data, $expectedRevision): DocumentDesign {
            $design = DocumentDesign::query()->where('business_line', $line)->lockForUpdate()->firstOrFail();
            abort_unless($design->revision === $expectedRevision, 409, 'This design changed. Reload before saving.');
            $design->fill($data);
            $design->revision++;
            $design->save();
            activity('sales-library')->causedBy($by)->performedOn($design)->withProperties(['revision' => $design->revision, 'logo_sha256' => $design->logo_sha256])->log('Document design saved for future copies');

            return $design->refresh();
        });
    }
}
