<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnanasProductType extends Model
{
    protected $fillable = [
        'name',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }
}
