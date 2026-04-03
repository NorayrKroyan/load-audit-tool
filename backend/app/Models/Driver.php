<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $table = 'driver';
    protected $primaryKey = 'id_driver';
    public $timestamps = false;
    protected $guarded = [];
}
