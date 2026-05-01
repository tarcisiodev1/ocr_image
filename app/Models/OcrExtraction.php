<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrExtraction extends Model
{
    protected $fillable = [
        'filename',
        'original_path',
        'extracted_text',
        'pdf_path',
        'status'
    ];

    protected $casts = [
        'extracted_text' => 'string',
        'pdf_path' => 'string',
        'status' => 'string'
    ];
}