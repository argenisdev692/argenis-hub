<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Rendering;

use Modules\CourseScripts\Domain\Ports\CourseBundlePort;
use RuntimeException;
use Shared\Domain\Ports\StoragePort;
use ZipArchive;

/**
 * {@see CourseBundlePort} with `ext-zip` (research §6). Stored files are copied
 * to a scratch directory first — private objects are read server-side, never
 * through a public URL.
 */
final readonly class ZipCourseBundleBuilder implements CourseBundlePort
{
    public function __construct(
        private StoragePort $storage,
    ) {}

    public function build(array $generatedFiles, array $storedFiles): string
    {
        $scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'course-bundle-'.bin2hex(random_bytes(8));
        $zipPath = $scratch.'.zip';

        if (! mkdir($scratch, 0700, true) && ! is_dir($scratch)) {
            throw new RuntimeException('Could not create the bundle workspace.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the bundle archive.');
        }

        try {
            foreach ($generatedFiles as $entry => $contents) {
                $zip->addFromString($this->entry($entry), $contents);
            }

            $index = 0;

            foreach ($storedFiles as $entry => $storagePath) {
                $local = $scratch.DIRECTORY_SEPARATOR.($index++).'.bin';
                $this->storage->copyToLocal($storagePath, $local);
                $zip->addFile($local, $this->entry($entry));
            }

            $zip->close();
        } finally {
            foreach (glob($scratch.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($scratch);
        }

        return $zipPath;
    }

    /**
     * Entry names are built by the module, but are normalised anyway so no
     * entry can escape the archive root (zip-slip).
     */
    private function entry(string $name): string
    {
        $parts = array_filter(explode('/', str_replace('\\', '/', $name)), static fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..');

        return implode('/', $parts);
    }
}
