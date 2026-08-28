<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePortfolioRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route middleware (permission:CREATE_PORTFOLIOS).
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
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', 'max:50'],
            'tech_stack' => ['nullable', 'array', 'max:30'],
            'tech_stack.*' => ['string', 'max:50'],
            'live_url' => ['nullable', 'url:http,https', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'is_public' => ['sometimes', 'boolean'],
            'cover_path' => ['nullable', 'string', 'max:500'],
            'video_path' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'media' => ['sometimes', 'array', 'max:50'],
            'media.*' => ['string', 'max:500'],
        ];
    }
}
