<?php
namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InternalFileController extends Controller
{
    public function receive(Request $request)
    {
        $request->validate([
            "file" => "required|file",
            "path" => "required|string",
        ]);

        $relativePath = ltrim($request->input("path"), "/");
        $directory = public_path(dirname($relativePath));

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $request->file("file")->move($directory, basename($relativePath));

        return response()->json([
            "success" => true,
            "url" => asset($relativePath),
        ]);
    }
}
