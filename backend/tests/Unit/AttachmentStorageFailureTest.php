<?php

namespace Tests\Unit;

use App\Domain\DomainError;
use App\Services\AttachmentStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/** 注入傳輸層timeout／TLS錯誤, 不向外輸出SDK exception或secret */
class AttachmentStorageFailureTest extends TestCase
{
    /**
     * put回安全502業務錯誤、read不暴露物件、delete保留重試而不假裝成功
     *
     * @return void
     */
    public function test_transport_failures_are_sanitized_and_deletes_retry(): void
    {
        config(['filesystems.disks.fault' => ['driver' => 'local', 'visibility' => 'private', 'serve' => false]]);
        foreach (['cURL error 28: timeout synthetic-secret', 'cURL error 60: TLS synthetic-secret'] as $reason) {
            $disk = Mockery::mock(Filesystem::class);
            $disk->shouldReceive('put')->once()->andThrow(new RuntimeException($reason));
            $disk->shouldReceive('readStream')->once()->andThrow(new RuntimeException($reason));
            $disk->shouldReceive('delete')->once()->andThrow(new RuntimeException($reason));
            $manager = Mockery::mock(FilesystemManager::class);
            $manager->shouldReceive('disk')->with('fault')->times(3)->andReturn($disk);
            $storage = new AttachmentStorage($manager);
            $path = tempnam(sys_get_temp_dir(), 'tt-r2-fault-');
            file_put_contents($path, 'synthetic');
            try {
                try {
                    $storage->put('fault', 'object', $path);
                    $this->fail('Expected storage failure');
                } catch (DomainError $error) {
                    $this->assertSame('STORAGE_ERROR', $error->errorCode);
                    $this->assertStringNotContainsString('synthetic-secret', $error->getMessage());
                }
                try {
                    $storage->read('fault', 'object');
                    $this->fail('Expected safe missing response');
                } catch (DomainError $error) {
                    $this->assertSame('NOT_FOUND', $error->errorCode);
                    $this->assertStringNotContainsString('synthetic-secret', $error->getMessage());
                }
                $this->assertFalse($storage->delete('fault', 'object'));
            } finally {
                unlink($path);
            }
        }
    }
}
