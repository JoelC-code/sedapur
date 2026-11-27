<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FridgeScan extends Model
{
        protected $fillable = [
        'user_id',
        'household_id',
        'status',
        'image_path',
        'result',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }
}
