<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Odai extends Model
{
    protected $fillable = ['title', 'image_path', 'source_url', 'used_at'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }
}
