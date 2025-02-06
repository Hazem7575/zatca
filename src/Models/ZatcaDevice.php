<?php

namespace Hazem\Zatca\Models;

use Hazem\Zatca\Services\ComplianceService;
use Illuminate\Database\Eloquent\Model;

class ZatcaDevice extends Model
{
    protected $table = 'hazem_devices_zatca';

    protected $fillable = [
        'deviceable_type',
        'deviceable_id',
        'request_id',
        'status',
        'disposition_message',
        'binary_security_token',
        'secret',
        'errors',
        'public_key',
        'private_key',
        'data',
        'csr_content'
    ];

    protected $casts = [
        'errors' => 'array',
        'data' => 'array',
    ];

    protected $hidden = [
        'private_key',
        'secret'
    ];

    /**
     * Get the parent deviceable model.
     */
    public function deviceable()
    {
        return $this->morphTo();
    }

    /**
     * Check if the device has errors.
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Get the device's status.
     */
    public function getStatus()
    {
        return $this->disposition_message;
    }

    /**
     * Check if the device is active.
     */
    public function isActive()
    {
        return $this->status === 1;
    }

    /**
     * Get the device's credentials for API calls.
     */
    public function getCredentials()
    {
        return [
            'token' => $this->binary_security_token,
            'secret' => $this->secret
        ];
    }

    public function active()
    {
        $device = $this;

        if($this->status == 1) {
            return $this;
        }
        if (!$device) {
            throw new \Exception('No ZATCA device registered for this model');
        }

        // Use compliance service to activate device
        app(ComplianceService::class)->activate($device);

        return $device;
    }
}
