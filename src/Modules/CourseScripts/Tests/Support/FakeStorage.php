<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use DateTimeInterface;
use RuntimeException;
use Shared\Domain\Exceptions\StorageObjectNotFoundException;
use Shared\Domain\Ports\StoragePort;
use SplFileInfo;

/**
 * In-memory StoragePort for Course Scripts tests: no network, deterministic
 * fake signed URLs, and a record of every deleted path.
 */
final class FakeStorage implements StoragePort
{
    /** @var array<string, string> object key → contents */
    public array $objects = [];

    /** @var list<string> */
    public array $deleted = [];

    public static function install(): self
    {
        $storage = new self;
        app()->instance(StoragePort::class, $storage);

        return $storage;
    }

    public function get(string $path): ?string
    {
        return $this->objects[$this->key($path)] ?? null;
    }

    /**
     * @return list<string>
     */
    public function pathsUnder(string $prefix): array
    {
        return array_values(array_filter(
            array_keys($this->objects),
            static fn (string $key): bool => str_starts_with($key, ltrim($prefix, '/')),
        ));
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
