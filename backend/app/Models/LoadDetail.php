<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadDetail extends Model
{
    protected $table = 'load_detail';
    protected $primaryKey = 'id_load_detail';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'tons' => 'decimal:2',
        'carrier_rate' => 'decimal:2',
        'carrier_pay' => 'decimal:2',
        'client_pay' => 'decimal:2',
        'margin' => 'decimal:2',
        'margin_total' => 'decimal:2',
        'source_amt' => 'decimal:2',
        'acc_amt' => 'decimal:2',
        'review_date' => 'datetime',
    ];

    public function auditLoad()
    {
        return $this->belongsTo(Load::class, 'id_load', 'id_load');
    }
}
