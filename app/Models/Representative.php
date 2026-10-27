<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Representative extends Model
{
    protected $fillable = [
        'name',
        'employee_code',
    ];

    public function locationLogs(): HasMany
    {
        return $this->hasMany(LocationLog::class);
    }
}
