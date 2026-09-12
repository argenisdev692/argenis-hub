<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Repositories;

use Modules\VideoEdits\Domain\Ports\ScriptProviderPort;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditScriptEloquentModel;

/**
 * Reads the extracted script text attached to an edit (EX-9).
 */
final readonly class EloquentScriptProvider implements ScriptProviderPort
{
    public function forEdit(int $videoEditId): ?ScriptDocument
    {
        $script = VideoEditScriptEloquentModel::query()
            ->where('video_edit_id', $videoEditId)
            ->first();

        if ($script === null || $script->extracted_text === null || trim($script->extracted_text) === '') {
            return null;
        }

        return new ScriptDocument($script->original_name, $script->extracted_text);
    }
}
