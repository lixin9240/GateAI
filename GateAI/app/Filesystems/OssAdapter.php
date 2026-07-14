<?php

namespace App\Filesystems;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use OSS\OssClient;

class OssAdapter implements FilesystemAdapter
{
    public function __construct(
        protected OssClient $client,
        protected string $bucket,
        protected string $endpoint,
    ) {}

    public function getUrl(string $path): string
    {
        return 'https://' . $this->bucket . '.' . $this->endpoint . '/' . $path;
    }

    /**
     * 生成带签名的临时访问URL（用于私有bucket）
     */
    public function signUrl(string $path, int $timeout = 3600): string
    {
        return $this->client->signUrl($this->bucket, $path, $timeout, OssClient::OSS_HTTP_GET);
    }

    public function fileExists(string $path): bool
    {
        return $this->client->doesObjectExist($this->bucket, $path);
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->client->putObject($this->bucket, $path, $contents);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        try {
            return $this->client->getObject($this->bucket, $path);
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        $content = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }

    public function delete(string $path): void
    {
        $this->client->deleteObject($this->bucket, $path);
    }

    public function deleteDirectory(string $path): void
    {
    }

    public function createDirectory(string $path, Config $config): void
    {
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->client->getObjectMeta($this->bucket, $path);
        return new FileAttributes($path, null, null, null, $meta['content-type'] ?? null);
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->client->getObjectMeta($this->bucket, $path);
        $ts = strtotime($meta['last-modified'] ?? '');
        return new FileAttributes($path, null, null, $ts ?: null);
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->client->getObjectMeta($this->bucket, $path);
        return new FileAttributes($path, (int) ($meta['content-length'] ?? 0));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $result = $this->client->listObjects($this->bucket, ['prefix' => $path]);
        $objects = $result->getObjectList();
        $prefixes = $result->getPrefixList();

        foreach ($prefixes ?? [] as $prefix) {
            yield new DirectoryAttributes(rtrim($prefix->getPrefix(), '/'));
        }

        foreach ($objects ?? [] as $object) {
            yield new FileAttributes(
                $object->getKey(),
                (int) $object->getSize(),
                null,
                strtotime($object->getLastModified())
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->client->copyObject($this->bucket, $source, $this->bucket, $destination);
    }

    public function setVisibility(string $path, string $visibility): void
    {
    }
}
