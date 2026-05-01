<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ocr_extractions', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_path');
            $table->text('extracted_text')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocr_extractions');
    }
};