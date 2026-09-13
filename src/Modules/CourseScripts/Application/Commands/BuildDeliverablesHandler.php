<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Domain\Enums\DeliverableDocumentType;
use Modules\CourseScripts\Domain\Enums\DeliverableFormat;
use Modules\CourseScripts\Domain\Ports\DeliverableRendererPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Domain\Services\DocumentNameFactory;
use Modules\CourseScripts\Domain\ValueObjects\PracticeDocument;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDocument;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Shared\Domain\Ports\StoragePort;

/**
 * Renders and stores every file of a script version — script, prompts sheet,
 * practice document and each practice file, as Markdown and PDF (FR-45,
 * FR-46, FR-36e, FR-35a). Rebuilding replaces the previous files (FR-50).
 */
final readonly class BuildDeliverablesHandler
{
    public function __construct(
        private DeliverableRendererPort $renderer,
        private ScriptVersionRepositoryPort $versions,
        private StoragePort $storage,
        private DocumentNameFactory $names,
        private Config $config,
    ) {}

    public function handle(CourseScriptVersionEloquentModel $version): void
    {
        $version->loadMissing(['video.course:id,uuid,title,language', 'practice']);

        $video = $version->video;
        $course = $video->course;
        $directory = sprintf('%s/%s/deliverables/%s', (string) $this->config->get('course-scripts.uploads.disk_directory', 'course-scripts'), $course->uuid, $version->uuid);

        $script = $this->scriptDocument($version);
        $this->store($version->id, $directory, DeliverableDocumentType::Script, '', $script->fileName, $this->renderer->script($script));

        if ($script->prompts() !== []) {
            $this->store($version->id, $directory, DeliverableDocumentType::Prompts, '', $script->promptsFileName, $this->renderer->promptsSheet($script));
        }

        if ($version->practice !== null) {
            $practice = $this->practiceDocument($version, $course->language);
            $this->store($version->id, $directory, DeliverableDocumentType::Practice, '', $practice->documentName, $this->renderer->practice($practice));

            foreach ($practice->artifacts as $artifact) {
                $fileName = (string) $artifact['file_name'];
                $this->store($version->id, $directory.'/archivos', DeliverableDocumentType::PracticeFile, $fileName, $fileName, $this->renderer->practiceFile($practice, $fileName));
            }
        }
    }

    public function scriptDocument(CourseScriptVersionEloquentModel $version): ScriptDocument
    {
        $video = $version->video;
        $course = $video->course;

        return new ScriptDocument(
            language: $course->language,
            courseTitle: $course->title,
            videoNumber: $video->number,
            videoTitle: $video->title,
            fileName: $this->names->script($video->number),
            promptsFileName: $this->names->promptsSheet($video->number),
            version: $version->version,
            generatedOn: ($version->created_at ?? now())->format('d/m/Y'),
            technicalHeader: $version->technical_header,
            learningObjectives: $version->learning_objectives,
            continuityNote: $version->continuity_note,
            sections: $version->sections,
            summaryPoints: $version->summary_points,
            nextVideo: $version->next_video,
            recordingNotes: $version->recording_notes,
            verificationChecklist: $version->verification_checklist,
            practiceDocumentName: $version->practice?->document_name,
            isGrounded: $version->is_grounded,
            passedReview: $version->reviewed ? $version->passed_review : null,
        );
    }

    public function practiceDocument(CourseScriptVersionEloquentModel $version, string $language): PracticeDocument
    {
        $practice = $version->practice;

        return new PracticeDocument(
            language: $language,
            videoNumber: $version->video->number,
            videoTitle: $version->video->title,
            documentName: $practice->document_name,
            headerTitle: $practice->header_title,
            filesSummary: $practice->files_summary,
            setupInstruction: $practice->setup_instruction,
            usage: $practice->usage,
            instructorNote: $practice->instructor_note,
            designedContrasts: $practice->designed_contrasts,
            artifacts: $practice->artifacts,
        );
    }

    /**
     * @param  array{md: string, pdf: string}  $files
     */
    private function store(int $versionId, string $directory, DeliverableDocumentType $type, string $artifactFileName, string $name, array $files): void
    {
        foreach ([DeliverableFormat::Md, DeliverableFormat::Pdf] as $format) {
            $contents = $files[$format->value];
            $path = $this->storage->put($directory.'/'.$name.'.'.$format->value, $contents);

            $replaced = $this->versions->saveDeliverable($versionId, $type, $artifactFileName, $format, $path, strlen($contents), hash('sha256', $contents));

            if ($replaced !== null) {
                $this->storage->delete($replaced);
            }
        }
    }
}
