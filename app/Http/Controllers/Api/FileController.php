<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\File;

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

            $fileRecord = File::create([
                'user_id' => $validated['user_id'],
                'fileName' => $fileName,
                'filePath' => $filePath,
                'category' => $validated['category'] ?? null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'File has been uploaded successfully',
                'file_id' => $fileRecord->id,
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
