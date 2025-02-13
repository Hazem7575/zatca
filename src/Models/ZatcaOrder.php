<?php

namespace Hazem\Zatca\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaOrder extends Model
{
    protected $table = 'hazem_orders_zatca';

    protected $fillable = [
        'orderable_type',
        'orderable_id',
        'invoice_number',
        'uuid',
        'invoice_hash',
        'signed_invoice_xml',
        'status',
        'is_reported',
        'is_cleared',
        'warnings',
        'errors',
        'response',
        'submitted_at',
        'qr_code'
    ];

    protected $casts = [
        'warnings' => 'array',
        'errors' => 'array',
        'response' => 'array',
        'is_reported' => 'boolean',
        'is_cleared' => 'boolean',
        'submitted_at' => 'datetime'
    ];

    public function orderable()
    {
        return $this->morphTo();
    }

    public function isSubmitted()
    {
        return !is_null($this->submitted_at);
    }

    public function hasErrors()
    {
        return !empty($this->errors);
    }

    public function hasWarnings()
    {
        return !empty($this->warnings);
    }

    public function isSuccessful()
    {
        return in_array($this->status, ['REPORTED', 'CLEARED']);
    }
}
