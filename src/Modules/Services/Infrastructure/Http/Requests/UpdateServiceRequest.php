<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route middleware (permission:UPDATE_SERVICES).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('services', 'slug')->ignore($this->route('uuid'), 'uuid')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
