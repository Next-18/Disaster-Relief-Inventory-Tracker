<?php

namespace Tests\Unit;

use App\Filesystem\R2FilesystemAdapter;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use League\Flysystem\Config;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class R2FilesystemAdapterTest extends TestCase
{
    public function test_uploads_content_without_an_unsupported_acl_header(): void
    {
        $request = null;
        $client = new S3Client([
            'region' => 'auto',
            'version' => 'latest',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => function (CommandInterface $command, ?RequestInterface $httpRequest) use (&$request) {
                $request = $httpRequest;

                return Create::promiseFor(new Result());
            },
        ]);
        $adapter = new R2FilesystemAdapter($client, 'private-qr-bucket');

        $adapter->write(
            'beneficiary-qr/'.str_repeat('a', 64).'.svg',
            '<svg></svg>',
            new Config(['ContentType' => 'image/svg+xml']),
        );

        $this->assertNotNull($request);
        $this->assertFalse($request->hasHeader('x-amz-acl'));
        $this->assertSame('image/svg+xml', $request->getHeaderLine('Content-Type'));
    }
}
