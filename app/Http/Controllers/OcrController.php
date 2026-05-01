<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\OcrExtraction;
use App\Jobs\ProcessOcrJob;

class OcrController extends Controller
{
    public function upload(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:png,jpeg,jpg|max:10240'
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $validator->errors()->first('image')
                ], 422);
            }
            return redirect('/')->withErrors($validator)->withInput();
        }

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        $tempDir = storage_path('app/private/temp');
        $tempPath = $tempDir . '/' . $filename;
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $file->move($tempDir, $filename);

        $ocrExtraction = OcrExtraction::create([
            'filename' => $file->getClientOriginalName(),
            'original_path' => $tempPath,
            'status' => 'pending'
        ]);

        ProcessOcrJob::dispatch($ocrExtraction);

        return response()->json(['id' => $ocrExtraction->id, 'job_id' => $ocrExtraction->id]);
    }

    public function result(int $id): JsonResponse
    {
        $ocrExtraction = OcrExtraction::findOrFail($id);

        return response()->json([
            'id' => $ocrExtraction->id,
            'status' => $ocrExtraction->status,
            'extracted_text' => $ocrExtraction->extracted_text
        ]);
    }

    public function clearTemp(Request $request): JsonResponse
    {
        $keepId = $request->input('keep_id');
        $tempDir = storage_path('app/private/temp');

        if (!is_dir($tempDir)) {
            return response()->json(['success' => true, 'deleted' => 0]);
        }

        $files = glob($tempDir . '/*');
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                // Se tem keep_id, verifica se é o arquivo correto
                if ($keepId) {
                    $ocr = OcrExtraction::where('original_path', $file)->first();
                    if ($ocr && $ocr->id != $keepId) {
                        unlink($file);
                        $deleted++;
                    }
                } else {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    public function index()
    {
        return view('ocr.upload');
    }
}