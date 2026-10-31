<?php

namespace App\Filesystem;

use Aws\S3\S3ClientInterface;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Throwable;

class R2FilesystemAdapter implements FilesystemAdapter
{
    private AwsS3V3Adapter $s3Adapter;
    private PathPrefixer $prefixer;

    public function __construct(
        private S3ClientInterface $client,
        private string $bucket,
        string $root = '',
    ) {
        $this->prefixer = new PathPrefixer($root);
        $this->s3Adapter = new AwsS3V3Adapter(
            $client,
            $bucket,
            $root,
            new PortableVisibilityConverter(Visibility::PRIVATE),
        );
    }

    public function fileExists(string $path): bool
    {
        return $this->s3Adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->s3Adapter->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->putObject($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->putObject($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->s3Adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->s3Adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->s3Adapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->s3Adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== Visibility::PRIVATE) {
            throw UnableToSetVisibility::atLocation(
                $path,
                'R2 visibility is controlled by keeping the bucket private.',
            );
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, Visibility::PRIVATE);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->s3Adapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->s3Adapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->s3Adapter->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->s3Adapter->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }

    private function putObject(string $path, mixed $contents, Config $config): void
    {
        $parameters = [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixer->prefixPath($path),
            'Body' => $contents,
        ];
        $contentType = $config->get('ContentType') ?? $config->get('mimetype');
        if (is_string($contentType) && $contentType !== '') {
            $parameters['ContentType'] = $contentType;
        }

        try {
            $this->client->putObject($parameters);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }
}
