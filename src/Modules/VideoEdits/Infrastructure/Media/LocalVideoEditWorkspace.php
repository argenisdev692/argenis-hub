<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Media;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\Filesystem;
use Modules\VideoEdits\Domain\Exceptions\InsufficientWorkspaceException;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;
use RuntimeException;

/**
 * Workspace on the `video-edit-workspace` local disk — point its root at a
 * mounted volume on the worker (AD-12, R5).
 */
final readonly class LocalVideoEditWorkspace implements VideoEditWorkspacePort
{
    public function __construct(
        private Config $config,
        private FilesystemFactory $disks,
        private Filesystem $files,
    ) {}

    public function prepare(string $videoEditUuid, int $requiredBytes): string
    {
        $root = $this->root();
        $this->files->ensureDirectoryExists($root);

        $available = disk_free_space($root);

        if ($available !== false && $available < $requiredBytes) {
            throw InsufficientWorkspaceException::needs($requiredBytes, (int) $available);
        }

        $directory = $this->directory($videoEditUuid);
        $this->files->deleteDirectory($directory);

        if (! $this->files->makeDirectory($directory, 0755, true) && ! $this->files->isDirectory($directory)) {
            throw new RuntimeException('Could not create the video edit workspace.');
        }

        return $directory;
    }

    public function path(string $videoEditUuid, string $fileName): string
    {
        return $this->directory($videoEditUuid).DIRECTORY_SEPARATOR.basename($fileName);
    }

    public function wipe(string $videoEditUuid): void
    {
        $this->files->deleteDirectory($this->directory($videoEditUuid));
    }

    private function directory(string $videoEditUuid): string
    {
        return $this->root().DIRECTORY_SEPARATOR.basename($videoEditUuid);
    }

    private function root(): string
    {
        return rtrim(
            $this->disks->disk((string) $this->config->get('video-edit.workspace.disk'))->path(''),
            '/\\',
        );
    }
}
