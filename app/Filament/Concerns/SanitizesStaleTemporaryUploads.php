<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

trait SanitizesStaleTemporaryUploads
{
    protected function reconcileTemporaryUploadState(
        string $statePath,
        ?string $orphanDirectory = null,
        int $orphanMaxAgeMinutes = 15,
    ): void {
        $state = data_get($this, $statePath);

        if (! is_array($state) || $state === []) {
            return;
        }

        $sanitized = [];
        $hadStaleTemp = false;

        foreach ($state as $key => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                try {
                    if ($file->exists()) {
                        $sanitized[$key] = $file;
                        continue;
                    }
                } catch (Throwable) {
                    // Treat unreadable temp uploads as stale.
                }

                $hadStaleTemp = true;

                continue;
            }

            if (is_string($file) && $file !== '') {
                $sanitized[$key] = $file;
            }
        }

        if ($hadStaleTemp && $sanitized === [] && filled($orphanDirectory)) {
            $orphan = $this->findRecentOrphanedUpload($orphanDirectory, $orphanMaxAgeMinutes);

            if ($orphan !== null) {
                $sanitized[(string) Str::uuid()] = $orphan;
            }
        }

        data_set($this, $statePath, $sanitized === [] ? null : $sanitized);
    }

    protected function findRecentOrphanedUpload(string $directory, int $maxAgeMinutes = 15): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($directory)) {
            return null;
        }

        $cutoff = now()->subMinutes($maxAgeMinutes)->timestamp;

        return collect($disk->files($directory))
            ->filter(fn (string $path): bool => $disk->lastModified($path) >= $cutoff)
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->first();
    }
}
