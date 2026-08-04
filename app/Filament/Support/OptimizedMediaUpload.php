<?php

namespace App\Filament\Support;

use App\Services\Media\MediaStorage;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OptimizedMediaUpload
{
    public static function configure(BaseFileUpload $upload, string $directory): BaseFileUpload
    {
        $mediaStorage = app(MediaStorage::class);

        return $upload
            ->disk($mediaStorage->diskName())
            ->directory($directory)
            ->visibility(null)
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) use ($directory, $mediaStorage): string {
                $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $baseName = Str::slug($baseName) ?: (string) Str::uuid();

                if (str_ends_with(strtolower($file->getClientOriginalName()), '.svg')) {
                    $key = trim($directory, '/').'/'.$baseName.'.svg';
                    $contents = (string) file_get_contents($file->getRealPath());
                    $stored = $mediaStorage->storeOptimized($key, $contents);

                    return $stored->key;
                }

                $stored = $mediaStorage->storeFromBinary(
                    (string) file_get_contents($file->getRealPath()),
                    $directory,
                    $baseName,
                );

                return $stored->key;
            });
    }
}
