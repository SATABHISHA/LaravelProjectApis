<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'file' => 'required|file',
            'category' => 'nullable|string|max:100',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads', $fileName, 'public');

            $fileRecord = DB::table('files')->insertGetId([
                'user_id' => $validated['user_id'],
                'fileName' => $fileName,
                'filePath' => $filePath,
                'category' => $validated['category'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'File has been uploaded successfully',
                'file_id' => $fileRecord,
                'file_path' => $filePath
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No file uploaded'
            ], 400);
        }
    }
}
