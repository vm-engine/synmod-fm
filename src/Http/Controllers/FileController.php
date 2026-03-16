<?php

declare(strict_types=1);

namespace VmEngine\Fm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Enums\FmAction;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\FileManagerService;

class FileController extends Controller
{
    public function __construct(
        private readonly FileManagerService $service
    ) {}

    /**
     * Handle file upload via API.
     */
    public function upload(Request $request): JsonResponse
    {
        $uploadConfig = FmConfig::getUploadConfig();
        $maxKb = (int) ($uploadConfig['max_size_kb'] ?? 10240);
        $extensions = implode(',', $uploadConfig['allowed_extensions'] ?? []);

        $request->validate([
            'file' => "required|file|max:{$maxKb}|mimes:{$extensions}",
            'folder_path' => 'required|string',
            'sub_path' => 'nullable|string',
        ]);

        $user = $request->user();

        if (! $user || ! FmConfig::canUserDo($user, $request->input('folder_path'), FmAction::Upload)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $fmFile = $this->service->upload(
            $request->file('file'),
            $request->input('folder_path'),
            $request->input('sub_path', ''),
            $user->getAuthIdentifier()
        );

        return response()->json([
            'id' => $fmFile->id,
            'filename' => $fmFile->filename,
            'url' => $fmFile->getUrl(),
            'thumbnail_url' => $fmFile->getThumbnailUrl(),
            'size' => $fmFile->size,
            'human_size' => $fmFile->getHumanSize(),
            'mime_type' => $fmFile->mime_type,
        ]);
    }

    /**
     * Stream a file by ID (for private/protected access).
     */
    public function stream(int $id): Response
    {
        $file = FmFile::active()->findOrFail($id);

        $user = request()->user();

        if (! $user || ! FmConfig::canUserDo($user, $file->folder_path, FmAction::Read)) {
            abort(403);
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->getStoragePath())) {
            abort(404);
        }

        return response(
            $disk->get($file->getStoragePath()),
            200,
            [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => 'inline; filename="'.$file->filename.'"',
            ]
        );
    }
}
