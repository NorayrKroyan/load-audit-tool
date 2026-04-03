<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contact';
    protected $primaryKey = 'id_contact';
    public $timestamps = false;
    protected $guarded = [];

    public function driver()
    {
        return $this->hasOne(Driver::class, 'id_contact', 'id_contact');
    }
}
