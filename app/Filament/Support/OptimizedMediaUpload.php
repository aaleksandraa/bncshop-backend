<?php

namespace App\Filament\Support;

use App\Services\Media\MediaStorage;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class OptimizedMediaUpload
{
    public static function configure(BaseFileUpload $upload, string|\Closure $directory): BaseFileUpload
    {
        return $upload
            ->disk(MediaStorage::resolveDiskName())
            ->directory($directory)
            ->fetchFileInformation(false)
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($directory): string {
                $resolvedDirectory = is_string($directory)
                    ? $directory
                    : (string) $component->evaluate($directory);

                $mediaStorage = app(MediaStorage::class);
                $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $baseName = Str::slug($baseName) ?: (string) Str::uuid();
                $contents = self::readUploadedFile($file);

                if (str_ends_with(strtolower($file->getClientOriginalName()), '.svg')) {
                    $key = trim($resolvedDirectory, '/').'/'.$baseName.'.svg';
                    $stored = $mediaStorage->storeOptimized($key, $contents);

                    return $stored->key;
                }

                $stored = $mediaStorage->storeFromBinary($contents, $resolvedDirectory, $baseName);

                return $stored->key;
            });
    }

    private static function readUploadedFile(TemporaryUploadedFile $file): string
    {
        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_readable($realPath)) {
            throw new RuntimeException('Upload nije moguće pročitati. Pokušajte ponovo odabrati sliku.');
        }

        $contents = file_get_contents($realPath);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Upload je prazan. Pokušajte ponovo odabrati sliku.');
        }

        return $contents;
    }
}
