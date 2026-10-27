<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationLog extends Model
{
    protected $fillable = [
        'representative_id',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Representative::class);
    }
}
