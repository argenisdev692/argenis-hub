<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

/**
 * Provider payloads for a complete, valid script of video 22 (9 minutes, two
 * demos, the Heliantia practice pack) — shaped like the agents' schemas.
 */
final class CanonicalScriptFixture
{
    /**
     * @param  list<string>  $mandatoryContent
     * @return array<string, mixed>
     */
    public static function outlinePayload(array $mandatoryContent = ['Conectar Google Drive', 'Comparar dos documentos'], bool $withPractice = true, int $durationMinutes = 9): array
    {
        $plan = CanonicalPracticePackFixture::plan();
        $minutes = self::minutes($durationMinutes);

        return [
            'recording_format' => 'Grabación de pantalla con narración',
            'learning_objectives' => ['Conectar Google Drive a Claude', 'Resumir un documento de Drive', 'Comparar dos propuestas en una tabla'],
            'continuity_note' => 'Este vídeo abre el Bloque 4. Viene del Vídeo 21, donde se buscó información previa; el Vídeo 23 aplicará los conectores a Gmail y Calendar.',
            'uses_tool' => true,
            'taught_summary' => 'Cómo conectar Google Drive y trabajar con documentos reales: resumir y comparar propuestas en una tabla.',
            'sections' => [
                self::section('1', '', 'INTRODUCCIÓN', 'intro', $minutes[0]),
                self::section('2', '', 'CONECTAR GOOGLE DRIVE', 'concept', $minutes[1]),
                self::section('3', '', 'RESUMIR UN DOCUMENTO', 'demo', $minutes[2], 'DEMO 1'),
                self::section('4', '', 'COMPARAR DOS PROPUESTAS', 'comparison', $minutes[3], 'DEMO 2', $withPractice ? [CanonicalPracticePackFixture::FILE_A, CanonicalPracticePackFixture::FILE_B] : []),
                self::section('4.1', '4', 'Tabla comparativa', 'table', 0),
                self::section('5', '', 'CIERRE PRÁCTICO', 'closing', $minutes[4]),
            ],
            'coverage_map' => array_map(static fn (string $item, int $index): array => ['item' => $item, 'section_numbers' => [$index === 0 ? '2' : '4']], $mandatoryContent, array_keys($mandatoryContent)),
            'practice_warranted' => $withPractice,
            'practice_reason' => $withPractice ? $plan['reason'] : 'El vídeo es conceptual y no necesita material preparado.',
            'practice_topic' => $withPractice ? $plan['topic'] : '',
            'practice_files_summary' => $withPractice ? $plan['files_summary'] : '',
            'practice_setup_instruction' => $withPractice ? $plan['setup_instruction'] : '',
            'practice_instructor_note' => $withPractice ? $plan['instructor_note'] : '',
            'practice_artifacts' => $withPractice ? $plan['artifacts'] : [],
            'practice_contrasts' => $withPractice ? $plan['contrasts'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sectionPayload(string $sectionNumber, bool $withPrompts = true, bool $withPractice = true): array
    {
        $parts = [['section_number' => $sectionNumber, 'segments' => [
            self::segment('narration', text: '"Bienvenido. En esta sección vemos, paso a paso, cómo trabajar con documentos reales de Drive sin salir de Claude."'),
        ]]];

        if ($sectionNumber === '3' && $withPrompts) {
            $parts[0]['segments'][] = self::segment('on_screen_prompt', prompt: 'Resume en cinco puntos el documento "Plan comercial 2026" de mi Drive.');
            $parts[0]['segments'][] = self::segment('expected_result', text: 'Claude devuelve cinco puntos con la fuente enlazada.');
            $parts[0]['segments'][] = self::segment('on_screen_actions', items: ['Abrir claude.ai', 'Pegar el prompt']);
        }

        if ($sectionNumber === '4') {
            if ($withPractice) {
                $parts[0]['segments'][] = self::segment('show_on_screen', text: 'Las dos propuestas en Drive.', readAloud: false, practiceFile: CanonicalPracticePackFixture::FILE_A);
                $parts[0]['segments'][] = self::segment('show_on_screen', text: 'Segunda propuesta.', practiceFile: CanonicalPracticePackFixture::FILE_B);
            }

            if ($withPrompts) {
                $parts[0]['segments'][] = self::segment('on_screen_prompt', prompt: 'Compara las dos propuestas logísticas de mi Drive en una tabla: precio, cobertura y penalizaciones.');
            }

            $parts[] = ['section_number' => '4.1', 'segments' => [
                self::segment('on_screen_table', tableColumns: ['Dimensión', 'Proveedor A', 'Proveedor B'], tableRows: [['Precio', '28.856 €', '32.252 €']]),
                self::segment('presenter_note', items: ['Mantener la tabla 8 segundos en pantalla']),
            ]];
        }

        return ['parts' => $parts];
    }

    /**
     * @return array<string, mixed>
     */
    public static function closingPayload(bool $withPractice = true, bool $hasNext = true): array
    {
        return [
            'summary_points' => ['Drive se conecta en ajustes', 'Claude cita la fuente', 'Una tabla compara mejor que dos lecturas', 'Revisar siempre las cifras', 'Los permisos de Drive se respetan'],
            'next_video_handoff' => $hasNext ? 'En el Vídeo 23 aplicaremos lo mismo a Gmail y Calendar.' : '',
            'preparation' => $withPractice
                ? ['Subir '.CanonicalPracticePackFixture::FILE_A.' y '.CanonicalPracticePackFixture::FILE_B.' a Google Drive', 'Conector de Drive activo']
                : ['Conector de Drive activo'],
            'during_recording' => ['En la sección 4.1 mantener la tabla 8 segundos'],
            'tools_required' => ['Conector Google Drive'],
            'tools_none_reason' => '',
            'continuity' => 'Conecta con el Vídeo 21 y prepara el Vídeo 23.',
            'organisations_used' => $withPractice ? ['Heliantia Group', 'Transportes Meridional S.L.'] : ['Tecnoform S.A.'],
            'verification_checklist' => ['Se muestra la conexión de Drive', 'DEMO 1 resume un documento', 'DEMO 2 genera la tabla comparativa'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function artifactPayload(string $fileName): array
    {
        $artifact = CanonicalPracticePackFixture::artifacts()[$fileName];

        return [
            'content_blocks' => $artifact['content_blocks'],
            'organisations' => $artifact['organisations'],
            'characters' => $artifact['characters'],
        ];
    }

    /**
     * @return list<int> five top-level section minutes summing to the duration
     */
    private static function minutes(int $duration): array
    {
        $base = [1, 2, 2, 3, 1];
        $base[3] += $duration - 9;

        return $base;
    }

    /**
     * @param  list<string>  $files
     * @return array<string, mixed>
     */
    private static function section(string $number, string $parent, string $title, string $kind, int $minutes, string $demo = '', array $files = []): array
    {
        return [
            'number' => $number,
            'parent_number' => $parent,
            'title' => $title,
            'kind' => $kind,
            'minutes' => $minutes,
            'purpose' => 'Propósito de '.$title,
            'demo_label' => $demo,
            'demo_purpose' => $demo === '' ? '' : 'Demostrar '.$title,
            'practice_files' => $files,
        ];
    }

    /**
     * @param  list<string>  $items
     * @param  list<string>  $tableColumns
     * @param  list<list<string>>  $tableRows
     * @return array<string, mixed>
     */
    private static function segment(string $type, string $text = '', string $prompt = '', array $items = [], array $tableColumns = [], array $tableRows = [], bool $readAloud = false, string $practiceFile = ''): array
    {
        return [
            'type' => $type,
            'text' => $text,
            'prompt' => $prompt,
            'items' => $items,
            'table_columns' => $tableColumns,
            'table_rows' => $tableRows,
            'read_aloud' => $readAloud,
            'practice_file' => $practiceFile,
        ];
    }
}
