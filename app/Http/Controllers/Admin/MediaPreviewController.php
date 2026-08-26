<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaStorage;
use App\Support\UploadedMediaPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MediaPreviewController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $path = UploadedMediaPath::normalize($request->query('path'));
        abort_if($path === null, 404);

        $disks = [];
        if (MediaStorage::usesR2Static()) {
            $disks[] = 'r2';
        }
        $disks[] = 'public';

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    return $disk->response($path);
                }
            } catch (Throwable) {
                continue;
            }
        }

        abort(404);
    }
}
