<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bluebook extends Model
{
    protected $fillable = [
        'title', 'authors', 'year', 'department', 'program',
        'keywords', 'abstract', 'adviser', 'status',
        'uploaded_by', 'uploaded_by_name', 'pages', 'views', 'date_added',
        'file_path', 'file_original_name', 'file_size',
        'ocr_status', 'ocr_text', 'ocr_error', 'ocr_engine', 'ocr_rasterizer', 'ocr_processed_at',
    ];

    protected $casts = [
        'authors'          => 'array',
        'keywords'         => 'array',
        'views'            => 'integer',
        'year'             => 'integer',
        'pages'            => 'integer',
        'ocr_processed_at' => 'datetime',
    ];
}
