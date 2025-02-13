<?php

namespace Hazem\Zatca\Facades;

use Illuminate\Support\Facades\Facade;
use Hazem\Zatca\ZatcaService;

/**
 * @method static \Hazem\Zatca\Services\ZatcaInvoiceService prepare()
 * @method static array submitSimplifiedInvoice(string|int $businessId, array $invoiceData)
 * @method static array submitStandardInvoice(string|int $businessId, array $invoiceData)
 * @method static array submitCreditNote(string|int $businessId, array $invoiceData)
 * @method static array submitDebitNote(string|int $businessId, array $invoiceData)
 * @method static array getInvoiceStatus(string|int $businessId, string $invoiceNumber)
 * @method static array getInvoiceReport(string|int $businessId, string $invoiceNumber)
 * @method static array clearInvoice(string|int $businessId, string $invoiceNumber)
 * @method static array reportInvoice(string|int $businessId, string $invoiceNumber)
 * @method static \Hazem\Zatca\Models\ZatcaDevice registerDevice(string|int $businessId, string $otp, array $companyData)
 * @method static bool validateInvoice(array $invoiceData)
 * @method static array generateQRCode(array $invoiceData)
 * @method static array submitInvoice(string|int $businessId, array $invoiceData , $prev_hash = null)
 */
class Zatca extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'zatca';
    }
}
