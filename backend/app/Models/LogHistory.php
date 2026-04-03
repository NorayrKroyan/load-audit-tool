<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogHistory extends Model
{
    protected $table = 'log_history';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'action_time' => 'datetime',
    ];
}
