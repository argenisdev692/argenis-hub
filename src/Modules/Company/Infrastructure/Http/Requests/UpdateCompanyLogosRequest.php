<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Modules\Company\Domain\Enums\LogoVariant;
use SplFileInfo;

/**
 * Validates a brand-mark upload.
 *
 * A FormRequest rather than a Spatie Data object because the payload is
 * multipart: files are the one input shape where the framework validator, with
 * its file/image/mimes/dimensions rules, is strictly better than property
 * attributes — and Scramble reads rules() for the request body just as happily.
 *
 * All three variants are optional, but at least one must be present: the form
 * lets the operator replace a single mark without re-uploading the other two.
 *
 * SVG is refused deliberately. It is XML, it can carry script, and it cannot be
 * flattened by a raster re-encode, so accepting it would mean serving
 * attacker-controlled markup from the same origin as the brand assets. The
 * mimes rule checks the sniffed type, not the filename.
 */
final class UpdateCompanyLogosRequest extends FormRequest
{
    /** Marks are small; a 4 MB ceiling is already generous for a logo. */
    private const int MAX_KILOBYTES = 4096;

    private const int MAX_DIMENSION = 4000;

    /**
     * Authorization is enforced by the route middleware (permission:UPDATE_COMPANY_DATA).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (LogoVariant::cases() as $variant) {
            $rules[$variant->value] = [
                'nullable',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:'.self::MAX_KILOBYTES,
                'dimensions:max_width='.self::MAX_DIMENSION.',max_height='.self::MAX_DIMENSION,
                'required_without_all:'.$this->otherVariants($variant),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required_without_all' => 'Upload at least one brand mark.',
        ];
    }

    /**
     * The uploaded marks, keyed by {@see LogoVariant} value.
     *
     * @return array<string, SplFileInfo>
     */
    public function logos(): array
    {
        $files = [];

        foreach (LogoVariant::cases() as $variant) {
            $file = $this->file($variant->value);

            if ($file instanceof UploadedFile) {
                $files[$variant->value] = $file;
            }
        }

        return $files;
    }

    private function otherVariants(LogoVariant $variant): string
    {
        $others = array_filter(
            LogoVariant::cases(),
            static fn (LogoVariant $case): bool => $case !== $variant,
        );

        return implode(',', array_map(static fn (LogoVariant $case): string => $case->value, $others));
    }
}
