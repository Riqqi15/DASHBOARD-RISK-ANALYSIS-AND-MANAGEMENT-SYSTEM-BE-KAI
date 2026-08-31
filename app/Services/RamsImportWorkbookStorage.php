<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class RamsImportWorkbookStorage
{
    public function configuredDisk(): string
    {
        $disk = config('rams.imports.disk', 'local');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    public function store(UploadedFile $workbook, string $storedPath): string
    {
        $sourcePath = $workbook->getRealPath();
        if (! is_string($sourcePath) || $sourcePath === '') {
            throw new RuntimeException('Temporary file workbook tidak tersedia.');
        }

        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Workbook gagal dibuka untuk disimpan.');
        }

        try {
            $stored = Storage::disk($this->configuredDisk())->put($storedPath, $stream, [
                'visibility' => 'private',
            ]);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException('Workbook gagal disimpan ke penyimpanan private.');
        }

        return $storedPath;
    }

    public function exists(string $disk, string $storedPath): bool
    {
        return Storage::disk($disk)->exists($storedPath);
    }

    public function delete(string $disk, string $storedPath): void
    {
        Storage::disk($disk)->delete($storedPath);
    }

    public function withLocalCopy(string $disk, string $storedPath, Closure $callback): mixed
    {
        $source = Storage::disk($disk)->readStream($storedPath);
        if ($source === null || $source === false) {
            throw new RuntimeException('File workbook antrean tidak dapat dibaca.');
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'rams-import-');
        if ($temporaryBase === false) {
            fclose($source);

            throw new RuntimeException('File sementara workbook gagal dibuat.');
        }

        $extension = pathinfo($storedPath, PATHINFO_EXTENSION);
        $temporaryPath = $extension === '' ? $temporaryBase : $temporaryBase.'.'.$extension;

        try {
            if ($temporaryPath !== $temporaryBase && ! rename($temporaryBase, $temporaryPath)) {
                throw new RuntimeException('Ekstensi file sementara workbook gagal disiapkan.');
            }

            $target = fopen($temporaryPath, 'wb');
            if ($target === false) {
                throw new RuntimeException('File sementara workbook tidak dapat ditulis.');
            }

            try {
                if (stream_copy_to_stream($source, $target) === false) {
                    throw new RuntimeException('Workbook gagal disalin dari penyimpanan private.');
                }
            } finally {
                fclose($target);
            }

            return $callback($temporaryPath);
        } finally {
            fclose($source);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
            if ($temporaryPath !== $temporaryBase && is_file($temporaryBase)) {
                unlink($temporaryBase);
            }
        }
    }
}
