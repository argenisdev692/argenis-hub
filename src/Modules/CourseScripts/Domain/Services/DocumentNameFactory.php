<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * Predictable deliverable names (FR-37, D17), reusing the author's own
 * conventions from GUIDE/MODULE-VIDEOS: `Guion_Video_39`,
 * `Practica_Reunion_Guion_39`, `Propuesta_Logistica_ProveedorA_2026`.
 */
final readonly class DocumentNameFactory
{
    #[\NoDiscard]
    public function script(int $videoNumber): string
    {
        return 'Guion_Video_'.$this->number($videoNumber);
    }

    #[\NoDiscard]
    public function promptsSheet(int $videoNumber): string
    {
        return 'Prompts_Video_'.$this->number($videoNumber);
    }

    #[\NoDiscard]
    public function practiceDocument(string $topic, int $videoNumber): string
    {
        $topic = $this->words($topic, 3);

        return 'Practica_'.($topic === '' ? 'Video' : $topic).'_Guion_'.$this->number($videoNumber);
    }

    /**
     * A writer-proposed artifact file name, made safe but still descriptive.
     * The extension, if any, is dropped: every file is rendered as .md and .pdf.
     */
    #[\NoDiscard]
    public function artifactFile(string $proposed): string
    {
        $base = preg_replace('/\.(pdf|md|docx?|xlsx?|txt)$/i', '', trim($proposed)) ?? '';
        $safe = $this->words(str_replace(['_', '-'], ' ', $base), 8);

        return $safe === '' ? 'Documento_Practica' : mb_substr($safe, 0, 80);
    }

    /**
     * A folder slug: `bloque-07-seguridad-colaboracion`.
     */
    #[\NoDiscard]
    public function folder(string $prefix, int $number, ?string $title): string
    {
        $slug = strtolower($this->ascii((string) $title))
            |> (static fn (string $value): string => preg_replace('/[^a-z0-9]+/', '-', $value) ?? '')
            |> (static fn (string $value): string => trim($value, '-'))
            |> (static fn (string $value): string => implode('-', array_slice(explode('-', $value), 0, 5)));

        return sprintf('%s-%02d', $prefix, $number).($slug === '' ? '' : '-'.$slug);
    }

    #[\NoDiscard]
    public function courseFolder(string $title): string
    {
        $slug = strtolower($this->ascii($title))
            |> (static fn (string $value): string => preg_replace('/[^a-z0-9]+/', '-', $value) ?? '')
            |> (static fn (string $value): string => trim($value, '-'));

        return $slug === '' ? 'curso' : mb_substr($slug, 0, 60);
    }

    private function number(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    private function words(string $text, int $max): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $this->ascii($text)) ?: [];
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));

        return implode('_', array_map(
            // Keep the author's inner capitals ("ProveedorA", "IVA"); title-case the rest.
            static fn (string $word): string => preg_match('/[A-Z0-9]/', substr($word, 1)) === 1 ? ucfirst($word) : ucfirst(strtolower($word)),
            array_slice($words, 0, $max),
        ));
    }

    private function ascii(string $text): string
    {
        $ascii = TextNormaliser::ascii($text);

        return $ascii;
    }
}
