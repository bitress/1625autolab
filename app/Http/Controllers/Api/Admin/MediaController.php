<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadStorageService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private readonly UploadStorageService $uploadService) {}

    public function upload(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file provided.',
            ], 422);
        }

        $file = $request->file('file');
        $folder = $request->input('folder', 'general/');

        try {
            UploadStorageService::assertImageFile($file, ['image/jpeg', 'image/png', 'image/webp'], 5);
            $url = $this->uploadService->upload($file, (string) $folder);

            return response()->json([
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
