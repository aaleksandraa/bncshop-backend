<?php

namespace App\Filament\Support;

use App\Services\Media\MediaStorage;
use App\Support\UploadedMediaPath;
use Filament\Facades\Filament;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

class OptimizedMediaUpload
{
    public static function configure(BaseFileUpload $upload, string|\Closure $directory): BaseFileUpload
    {
        return $upload
            ->disk(MediaStorage::resolveDiskName())
            ->directory($directory)
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(function (BaseFileUpload $component, string $file): ?array {
                $path = UploadedMediaPath::normalize($file);
                if ($path === null) {
                    return null;
                }

                return [
                    'name' => basename($path),
                    'size' => 0,
                    'type' => null,
                    'url' => self::previewUrl($path),
                ];
            })
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($directory): string {
                $resolvedDirectory = is_string($directory)
                    ? $directory
                    : (string) $component->evaluate($directory);

                try {
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
                } catch (Throwable $exception) {
                    report($exception);

                    throw ValidationException::withMessages([
                        $component->getStatePath() => 'Sliku nije moguće obraditi. Pokušajte JPG, PNG ili WebP, ili manju datoteku.',
                    ]);
                }
            });
    }

    private static function previewUrl(string $path): string
    {
        $panelId = Filament::getCurrentPanel()?->getId();
        $routeName = $panelId === 'b2b-admin'
            ? 'filament.b2b-admin.media-preview'
            : 'filament.admin.media-preview';

        if (Route::has($routeName)) {
            return route($routeName, ['path' => $path]);
        }

        return url('/admin/media-preview').'?path='.rawurlencode($path);
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
