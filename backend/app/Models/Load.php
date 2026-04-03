<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Load extends Model
{
    protected $table = 'load';
    protected $primaryKey = 'id_load';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'delivery_time' => 'datetime',
        'entered_at' => 'datetime',
        'audited_at' => 'datetime',
        'is_deleted' => 'integer',
        'is_finished' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'id_load';
    }

    public function detail()
    {
        return $this->hasOne(LoadDetail::class, 'id_load', 'id_load');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    public function auditUser()
    {
        return $this->belongsTo(User::class, 'audit_user_id', 'id');
    }

    public function jobJoin()
    {
        return $this->belongsTo(JobJoin::class, 'id_join', 'id_join');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'id_contact', 'id_contact');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'id_vehicle', 'id_vehicle');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'id_owner', 'id_load')
            ->where('is_deleted', 0);
    }
}
