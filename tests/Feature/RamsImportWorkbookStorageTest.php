<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RamsImportWorkbookStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class RamsImportWorkbookStorageTest extends TestCase
{
    public function test_it_materializes_remote_bytes_and_removes_the_temporary_copy(): void
    {
        config()->set('rams.imports.disk', 'rams-import-test');
        Storage::fake('rams-import-test');
        $storage = app(RamsImportWorkbookStorage::class);
        $workbook = UploadedFile::fake()->createWithContent('rams.xlsx', 'workbook bytes');
        $storedPath = $storage->store($workbook, 'rams-imports/example.xlsx');
        $temporaryPath = null;

        $result = $storage->withLocalCopy(
            'rams-import-test',
            $storedPath,
            function (string $path) use (&$temporaryPath): string {
                $temporaryPath = $path;
                $this->assertFileExists($path);
                $this->assertSame('workbook bytes', file_get_contents($path));

                return 'processed';
            },
        );

        $this->assertSame('processed', $result);
        $this->assertIsString($temporaryPath);
        $this->assertFileDoesNotExist($temporaryPath);
        Storage::disk('rams-import-test')->assertExists($storedPath);
    }

    public function test_it_removes_the_temporary_copy_when_processing_throws(): void
    {
        config()->set('rams.imports.disk', 'rams-import-test');
        Storage::fake('rams-import-test');
        $storage = app(RamsImportWorkbookStorage::class);
        $workbook = UploadedFile::fake()->createWithContent('rams.xlsx', 'workbook bytes');
        $storedPath = $storage->store($workbook, 'rams-imports/example.xlsx');
        $temporaryPath = null;

        try {
            $storage->withLocalCopy(
                'rams-import-test',
                $storedPath,
                function (string $path) use (&$temporaryPath): never {
                    $temporaryPath = $path;

                    throw new RuntimeException('processing failed');
                },
            );
            $this->fail('Expected processing to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('processing failed', $exception->getMessage());
        }

        $this->assertIsString($temporaryPath);
        $this->assertFileDoesNotExist($temporaryPath);
        Storage::disk('rams-import-test')->assertExists($storedPath);
    }
}
