<?php

namespace Hazem\Zatca;

use Exception;
use Hazem\Zatca\Models\ZatcaDevice;
use Hazem\Zatca\Services\ZatcaAPI;
use Hazem\Zatca\Services\CSRGenerator;
use Hazem\Zatca\Services\InvoiceSigner;
use Hazem\Zatca\Services\InvoiceGenerator;
use Hazem\Zatca\Services\ZatcaXMLGenerator;

class ZatcaService
{
    private $live;
    private $api;

    public function __construct()
    {
        $this->live = config('zatca.live', false);
        $this->api = new ZatcaAPI($this->live);
    }

    public function registerDevice($businessId, $otp)
    {
        try {
            // Generate CSR
            $csrGenerator = new CSRGenerator($this->companyData, $this->live);
            $csr = $csrGenerator->generate();

            // Submit CSR to ZATCA
            $response = $this->api->submitCSR($csr['csr'], $otp);

            // Store device information
            $device = ZatcaDevice::create([
                'business_id' => $businessId,
                'request_id' => $response['requestID'],
                'disposition_message' => $response['dispositionMessage'],
                'binary_security_token' => $response['binarySecurityToken'],
                'secret' => $response['secret'],
                'errors' => $response['errors']
            ]);

            // Store private key securely
            // Implementation needed for secure storage

            return $device;
        } catch (Exception $e) {
            throw new Exception('Failed to register device: ' . $e->getMessage());
        }
    }

    public function submitInvoice($device, $invoiceData , $prev_hash = null)
    {

        try {
            $generator = new InvoiceGenerator($device->data);
            $invoice = $generator->generate($invoiceData);
            $items = $generator->items($invoiceData);
            $xml = new ZatcaXMLGenerator($device , $prev_hash);
            return  $xml->generateAndSignInvoice($invoice, $invoice , $invoice->customer , $items);
        } catch (Exception $e) {
            throw new Exception('Failed to submit invoice: ' . $e->getMessage());
        }
    }
}
