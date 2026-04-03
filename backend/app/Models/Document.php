<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'document';
    protected $primaryKey = 'id_document';
    public $timestamps = false;
    protected $guarded = [];
}
