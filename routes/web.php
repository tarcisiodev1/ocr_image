<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrController;

Route::get('/', [OcrController::class, 'index'])->name('ocr.index');
Route::post('/ocr/extract', [OcrController::class, 'upload'])->name('ocr.extract');
Route::get('/ocr/result/{id}', [OcrController::class, 'result'])->name('ocr.result');
Route::post('/ocr/clear-temp', [OcrController::class, 'clearTemp'])->name('ocr.clear');