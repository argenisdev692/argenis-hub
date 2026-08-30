<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Export;

use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Shared\Domain\Ports\ExportPort;

/**
 * Maps a {@see PostEloquentModel} row to export columns so CSV, Excel and PDF
 * stay consistent with the on-screen list. The module ships only this
 * transformer — the writer / streamer / PDF renderer live behind the Shared
 * {@see ExportPort} (BACKEND-PHP §8). Soft-delete state is `Status`
 * (Active/Suspended from `deleted_at`); editorial lifecycle stays in
 * `Publication Status`. Cover image and full body are intentionally omitted.
 */
final readonly class PostExportTransformer
{
    /**
     * @return array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }
     */
    #[\NoDiscard]
    public static function transformForTable(PostEloquentModel $post): array
    {
        return $post
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }
     */
    #[\NoDiscard]
    public static function transformForPdf(PostEloquentModel $post): array
    {
        return self::transformForTable($post);
    }

    /**
     * @return array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function extractBaseData(PostEloquentModel $post): array
    {
        return [
            'Title' => $post->post_title,
            'Category' => $post->category?->blog_category_name ?? '',
            'Author' => self::authorLabel($post),
            'Publication Status' => ucfirst($post->post_status->value),
            'SEO Score' => $post->seo_score !== null ? (string) $post->seo_score : '',
            'Created' => $post->created_at?->toIso8601String() ?? '',
            'Status' => $post->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @param  array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }  $data
     * @return array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function formatDates(array $data): array
    {
        if ($data['Created'] !== '') {
            try {
                $data['Created'] = (new \DateTimeImmutable($data['Created']))->format('F j, Y');
            } catch (\Exception) {
                // Keep original value if parsing fails.
            }
        }

        return $data;
    }

    /**
     * @param  array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }  $data
     * @return array{
     *     Title: string,
     *     Category: string,
     *     Author: string,
     *     Publication Status: string,
     *     SEO Score: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }

    private static function authorLabel(PostEloquentModel $post): string
    {
        $name = trim(sprintf('%s %s', $post->user?->first_name ?? '', $post->user?->last_name ?? ''));

        return $name !== '' ? $name : 'System';
    }
}
