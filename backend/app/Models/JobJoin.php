<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobJoin extends Model
{
    protected $table = 'join';
    protected $primaryKey = 'id_join';
    public $timestamps = false;
    protected $guarded = [];
}
