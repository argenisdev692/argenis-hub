<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BulkDeleteBackupRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route middleware (permission:BULK_DELETE_BACKUPS).
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
            'uuids' => ['required', 'array', 'min:1', 'max:500'],
            'uuids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
