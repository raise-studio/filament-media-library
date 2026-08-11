<?php

namespace RaiseStudio\FilamentMediaLibrary\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Support\MediaUploader;

class UploadController extends Controller
{
    public function __invoke(Request $request)
    {
        // auth 中间件已拦截匿名请求；此处仅做防御性兜底。
        abort_unless($request->user() !== null, 401);

        $allowed = implode(',', Config::allowedMimes());

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:'.$allowed],
            'directory' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()?->getKey();

        $media = MediaUploader::store($data['file'], $userId, [
            'directory' => $data['directory'] ?? null,
            'name' => $data['name'] ?? null,
        ]);

        if (! $media) {
            return response()->json(['error' => 'upload_failed'], 422);
        }

        return response()->json([
            'id' => $media->getKey(),
            'url' => $media->url(),
            'name' => $media->name,
            'isImage' => $media->isImage(),
        ]);
    }
}
