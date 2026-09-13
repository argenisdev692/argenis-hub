<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Queries;

use Modules\CourseScripts\Application\Commands\BuildDeliverablesHandler;
use Modules\CourseScripts\Domain\Enums\DeliverableDocumentType;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\NothingGeneratedException;
use Modules\CourseScripts\Domain\Ports\CourseBundlePort;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Domain\Services\DocumentNameFactory;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;
use Modules\CourseScripts\Infrastructure\Rendering\RenderVocabulary;

/**
 * The course ZIP (FR-48, D17) — or one video's (FR-48a):
 *
 *   {curso}/00-curso/{README.md, Biblia.md, Indice.md}
 *   {curso}/bloque-NN-slug/video-NN-slug/{Guion, Prompts, Practica}.{md,pdf}
 *   {curso}/bloque-NN-slug/video-NN-slug/archivos/{practice files}.{md,pdf}
 *
 * Only accepted versions are bundled; the README lists every video's status.
 */
final readonly class BuildCourseBundleHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private ScriptVersionRepositoryPort $versions,
        private BuildDeliverablesHandler $deliverables,
        private CourseBundlePort $bundles,
        private DocumentNameFactory $names,
    ) {}

    /**
     * @return array{path: string, filename: string}
     *
     * @throws CourseNotFoundException
     * @throws NothingGeneratedException
     */
    public function handle(string $courseUuid, int $userId, ?string $videoUuid = null): array
    {
        $course = $this->courses->findOwnedWithStructure($courseUuid, $userId) ?? throw new CourseNotFoundException;
        $video = null;

        if ($videoUuid !== null) {
            $video = $course->videos->firstWhere('uuid', $videoUuid) ?? throw new CourseNotFoundException;
        }

        $versions = $this->versions->acceptedForCourse($course->id, $video?->id);

        if ($versions === []) {
            throw new NothingGeneratedException;
        }

        $root = $this->names->courseFolder($course->title);
        $stored = [];

        foreach ($versions as $version) {
            if ($version->deliverables->isEmpty()) {
                $this->deliverables->handle($version);
                $version->load('deliverables:id,uuid,course_script_version_id,document_type,artifact_file_name,format,path,size_bytes');
            }

            $folder = $root.'/'.$this->videoFolder($version->video);

            foreach ($version->deliverables as $deliverable) {
                $stored[$folder.'/'.$this->fileName($version, $deliverable)] = $deliverable->path;
            }
        }

        $generated = $video === null ? [
            $root.'/00-curso/README.md' => $this->readme($course, $versions),
            $root.'/00-curso/Biblia.md' => $this->bible($course),
            $root.'/00-curso/Indice.md' => $this->index($course),
        ] : [];

        $filename = $video === null
            ? $root.'.zip'
            : $root.'-'.$this->names->script($video->number).'.zip';

        return ['path' => $this->bundles->build($generated, $stored), 'filename' => $filename];
    }

    private function videoFolder(CourseVideoEloquentModel $video): string
    {
        $videoFolder = $this->names->folder('video', $video->number, $video->title);

        return $video->block === null
            ? $videoFolder
            : $this->names->folder('bloque', $video->block->number, $video->block->title).'/'.$videoFolder;
    }

    private function fileName(CourseScriptVersionEloquentModel $version, CourseDeliverableEloquentModel $deliverable): string
    {
        $extension = $deliverable->format->value;

        return match ($deliverable->document_type) {
            DeliverableDocumentType::Script => $this->names->script($version->video->number).'.'.$extension,
            DeliverableDocumentType::Prompts => $this->names->promptsSheet($version->video->number).'.'.$extension,
            DeliverableDocumentType::Practice => ($version->practice?->document_name ?? 'Practica').'.'.$extension,
            DeliverableDocumentType::PracticeFile => 'archivos/'.$deliverable->artifact_file_name.'.'.$extension,
        };
    }

    /**
     * @param  list<CourseScriptVersionEloquentModel>  $versions
     */
    private function readme(CourseEloquentModel $course, array $versions): string
    {
        $t = RenderVocabulary::for($course->language);
        $byVideo = [];

        foreach ($versions as $version) {
            $byVideo[$version->course_video_id] = $version;
        }

        $lines = [
            '# '.$course->title,
            '',
            $course->language === 'en' ? 'Generated course material.' : 'Material del curso generado.',
            '',
            '| Nº | '.($course->language === 'en' ? 'Video | Status | Writer | Research | Second review | Practice' : 'Vídeo | Estado | Redactor | Investigación | Segunda revisión | Práctica').' |',
            '| --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($course->videos as $video) {
            $version = $byVideo[$video->id] ?? null;
            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s | %s | %s |',
                $video->number,
                str_replace('|', '\|', $video->title),
                $video->script_status->value,
                $version?->writer_provider ?? '—',
                $version === null ? '—' : ($version->is_grounded ? 'sí' : 'no'),
                $version === null || ! $version->reviewed ? '—' : ($version->passed_review ? 'superada' : 'no superada'),
                $version?->practice?->document_name ?? '—',
            );
        }

        return implode("\n", [...$lines, '', '> '.$t['fictional'], '']);
    }

    private function bible(CourseEloquentModel $course): string
    {
        $bible = (array) ($course->bible ?? []);
        $lines = ['# '.($course->language === 'en' ? 'Course bible' : 'Biblia del curso').' — '.$course->title, ''];

        foreach ((array) ($bible['organisations'] ?? []) as $organisation) {
            $lines[] = sprintf('- **%s**%s — %s (%s)', $organisation['name'], ($organisation['is_primary'] ?? false) ? ' ★' : '', $organisation['role'] ?? '', $organisation['sector'] ?? '');
        }

        $lines[] = '';

        foreach ((array) ($bible['characters'] ?? []) as $character) {
            $lines[] = sprintf('- %s — %s', $character['name'], $character['role'] ?? '');
        }

        return implode("\n", [...$lines, '', '**Audiencia:** '.($bible['audience'] ?? ''), '', '**Tono:** '.($bible['tone'] ?? ''), '', '**Herramienta:** '.($bible['taught_tool'] ?? '—'), '']);
    }

    private function index(CourseEloquentModel $course): string
    {
        $lines = ['# '.$course->title, ''];
        $blocks = $course->blocks->keyBy('id');
        $currentBlock = null;

        foreach ($course->videos as $video) {
            if ($video->course_block_id !== $currentBlock && $video->course_block_id !== null) {
                $currentBlock = $video->course_block_id;
                $block = $blocks->get($currentBlock);
                $lines[] = '';
                $lines[] = sprintf('## %d. %s', $block?->number, $block?->title);
                $lines[] = '';
            }

            $lines[] = sprintf('%d. %s (%d min)', $video->number, $video->title, $video->declared_duration_minutes ?? $course->default_video_minutes);
        }

        return implode("\n", $lines)."\n";
    }
}
