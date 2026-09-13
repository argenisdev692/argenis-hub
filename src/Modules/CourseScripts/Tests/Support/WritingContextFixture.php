<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Modules\CourseScripts\Domain\ValueObjects\ContinuityContext;
use Modules\CourseScripts\Domain\ValueObjects\NotesExcerpt;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;

/**
 * A writing context for video 22 of the reference course.
 */
final class WritingContextFixture
{
    public static function video22(?string $taughtTool = 'Claude', bool $hasNext = true): VideoWritingContext
    {
        return new VideoWritingContext(
            courseUuid: '0190a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
            courseTitle: 'Claude para usuarios',
            language: 'es',
            bible: [
                'organisations' => [
                    ['key' => 'tecnoform', 'name' => 'Tecnoform S.A.', 'role' => 'Empresa del alumno', 'sector' => 'Industria', 'is_primary' => true],
                    ['key' => 'heliantia', 'name' => 'Heliantia Group', 'role' => 'Cliente', 'sector' => 'Industria', 'is_primary' => false],
                ],
                'characters' => [['name' => 'Marta', 'role' => 'Operaciones', 'organisation_key' => 'tecnoform']],
                'audience' => 'Profesionales no técnicos',
                'tone' => 'Cercano',
                'taught_tool' => $taughtTool,
                'forbidden_phrasings' => [],
            ],
            videoNumber: 22,
            videoTitle: 'Integración con Google Workspace: documentos y Drive',
            topic: 'Google',
            durationMinutes: 9,
            brief: [
                'objective' => 'Mostrar cómo usar documentos de Drive con Claude.',
                'learning_areas' => [],
                'audience_objectives' => [],
                'mandatory_content' => ['Conectar Google Drive', 'Comparar dos documentos'],
                'errors_to_avoid' => ['Explicar sin demostrar'],
                'expected_result' => null,
            ],
            notes: [new NotesExcerpt('video_notes', 'Apuntes del autor para este vídeo', 'Usar dos propuestas de proveedores logísticos.')],
            courseResearch: [new ResearchFinding('tavily', 'claude drive', 'https://source.test/a', 'Fuente', 'Contenido', 0.9, false, 1)],
            videoResearch: [],
            continuity: new ContinuityContext(
                videoNumber: 22,
                blockTitle: 'Conectores y trabajo con información real',
                blockNumber: 4,
                positionInBlock: 1,
                videosInBlock: 6,
                isFirstOfCourse: false,
                isFirstOfBlock: true,
                predecessors: [['number' => 21, 'title' => 'Búsqueda web', 'taught' => 'Buscar información previa.', 'from_script' => true]],
                nextVideoNumber: $hasNext ? 23 : null,
                nextVideoTitle: $hasNext ? 'Uso real con Gmail y Calendar' : null,
                isProvisional: false,
                sourceVideoIds: [21],
            ),
        );
    }
}
