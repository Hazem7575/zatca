<?php

namespace Hazem\Zatca\Facades;

use Illuminate\Support\Facades\Facade;
use Hazem\Zatca\Services\DeviceRegistrationService;

/**
 * @method static \Hazem\Zatca\Models\ZatcaDevice register(string|int $businessId, string $otp, array $companyData)
 * @method static \Hazem\Zatca\Models\ZatcaDevice activate(string|int $businessId)
 * @method static bool hasDevice(string|int $businessId)
 * @method static \Hazem\Zatca\Models\ZatcaDevice|null getDevice(string|int $businessId)
 * @method static array getDeviceStatus(string|int $businessId)
 * @method static bool isDeviceActive(string|int $businessId)
 *
 * @see \Hazem\Zatca\Services\DeviceRegistrationService
 */
/**
 * @method static \Hazem\Zatca\Models\ZatcaDevice register(string|int $businessId, string $otp, array $companyData)
 *
 * @see \Hazem\Zatca\Services\DeviceRegistrationService
 */
class Device extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'zatca.device';
    }
}
