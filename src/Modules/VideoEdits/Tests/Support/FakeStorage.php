<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Tests\Support;

use DateTimeInterface;
use RuntimeException;
use Shared\Domain\Exceptions\StorageObjectNotFoundException;
use Shared\Domain\Ports\StoragePort;
use SplFileInfo;

/**
 * In-memory StoragePort for Video Edits tests: no network, deterministic fake
 * signed URLs, and a record of every deleted path.
 */
final class FakeStorage implements StoragePort
{
    /** @var array<string, string> object key → contents */
    public array $objects = [];

    /** @var list<string> */
    public array $deleted = [];

    public function seed(string $path, int $bytes): void
    {
        $this->objects[$this->key($path)] = str_repeat('x', $bytes);
    }

    public function put(string $path, string $contents, string $visibility = 'private'): string
    {
        $this->objects[$this->key($path)] = $contents;

        return $this->key($path);
    }

    public function putFile(string $directory, SplFileInfo $file, string $visibility = 'private'): string
    {
        return $this->put(rtrim($directory, '/').'/'.$file->getFilename(), (string) file_get_contents($file->getPathname()));
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return 'https://storage.test/'.$this->key($path).'?signature=download&expires='.$expiresAt->getTimestamp();
    }

    public function temporaryUploadUrl(string $path, DateTimeInterface $expiresAt): array
    {
        return [
            'upload_url' => 'https://storage.test/'.$this->key($path).'?signature=upload&expires='.$expiresAt->getTimestamp(),
            'headers' => ['Content-Type' => 'application/octet-stream'],
        ];
    }

    public function publicUrl(string $path): string
    {
        return 'https://public.storage.test/'.$this->key($path);
    }

    public function copyToLocal(string $path, string $localPath): void
    {
        $key = $this->key($path);

        if (! array_key_exists($key, $this->objects)) {
            throw new RuntimeException("Failed to open storage object [{$key}].");
        }

        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        file_put_contents($localPath, $this->objects[$key]);
    }

    public function putFromPath(string $path, string $localPath, string $visibility = 'private'): string
    {
        return $this->put($path, (string) file_get_contents($localPath), $visibility);
    }

    public function size(string $path): int
    {
        $key = $this->key($path);

        if (! array_key_exists($key, $this->objects)) {
            throw StorageObjectNotFoundException::forPath($key);
        }

        return strlen($this->objects[$key]);
    }

    public function delete(string $path): bool
    {
        $key = $this->key($path);
        $this->deleted[] = $key;
        $existed = array_key_exists($key, $this->objects);
        unset($this->objects[$key]);

        return $existed;
    }

    public function exists(string $path): bool
    {
        return array_key_exists($this->key($path), $this->objects);
    }

    private function key(string $path): string
    {
        return ltrim($path, '/');
    }
}
