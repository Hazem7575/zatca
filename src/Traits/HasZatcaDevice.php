<?php

namespace Hazem\Zatca\Traits;

use Hazem\Zatca\Models\ZatcaDevice;
use Hazem\Zatca\Facades\Device;
use Hazem\Zatca\Services\ComplianceService;

trait HasZatcaDevice
{
    /**
     * Get the model's ZATCA device.
     */
    public function zatcaDevice()
    {
        return $this->morphOne(ZatcaDevice::class, 'deviceable');
    }

    /**
     * Register a new ZATCA device for this model.
     */
    public function registerZatcaDevice($otp, array $companyData)
    {
        if($this->hasZatcaDevice()) {
            return $this->zatcaDevice();
        }

        // Delete existing device if any
        $this->zatcaDevice()->delete();

        // Add model type and ID to company data
        $companyData = array_merge($companyData, [
            'model_type' => get_class($this),
            'model_id' => $this->id
        ]);

        // Register new device
        $device = Device::register(
            $this->id,
            $otp,
            $companyData
        );

        return $device;
    }

    /**
     * Activate the device by sending compliance samples
     */


    /**
     * Check if model has a ZATCA device.
     */
    public function hasZatcaDevice()
    {
        return !is_null($this->zatcaDevice);
    }

    /**
     * Get the latest ZATCA device for this model.
     */
    public function getLatestZatcaDevice()
    {
        return $this->zatcaDevice()->latest()->first();
    }
}
