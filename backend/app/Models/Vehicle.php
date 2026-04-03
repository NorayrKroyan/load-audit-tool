<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = 'vehicle';
    protected $primaryKey = 'id_vehicle';
    public $timestamps = false;
    protected $guarded = [];
}
