<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\OcrExtraction;
use App\Actions\ExtractTextFromImage;

class ProcessOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public OcrExtraction $ocrExtraction
    ) {}

    public function handle(ExtractTextFromImage $extractTextFromImage): void
    {
        $this->ocrExtraction->update(['status' => 'processing']);

        try {
            $extractedText = $extractTextFromImage($this->ocrExtraction->original_path);

            $this->ocrExtraction->update([
                'extracted_text' => $extractedText,
                'status' => 'completed'
            ]);
        } catch (\Exception $e) {
            Log::error('OCR processing failed: ' . $e->getMessage());

            $this->ocrExtraction->update([
                'status' => 'failed'
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('OCR job failed permanently: ' . $exception->getMessage());

        $this->ocrExtraction->update([
            'status' => 'failed'
        ]);
    }
}