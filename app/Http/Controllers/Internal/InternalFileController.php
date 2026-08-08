<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @hideFromAPIDocumentation
 */
class InternalFileController extends Controller
{
    public function receive(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'path' => 'required|string',
        ]);

        $relativePath = ltrim($request->input('path'), '/');
        $directory = public_path(dirname($relativePath));

        if (! file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $request->file('file')->move($directory, basename($relativePath));

        return response()->json([
            'success' => true,
            'url' => asset($relativePath),
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $relativePath = ltrim($request->input('path'), '/');

        // Safety: hanya boleh di dalam folder file/ dan tanpa path traversal
        if (
            ! str_starts_with($relativePath, 'file/') ||
            str_contains($relativePath, '..')
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Path tidak valid.',
                ],
                422,
            );
        }

        $abs = public_path($relativePath);

        if (is_file($abs)) {
            @unlink($abs);
        }

        // Bersihkan folder kosong (bottom-up sampai public/file)
        $dir = dirname($abs);
        $base = public_path('file');

        while ($dir !== $base && str_starts_with($dir, $base) && @rmdir($dir)) {
            $dir = dirname($dir);
        }

        return response()->json(['success' => true]);
    }
}
