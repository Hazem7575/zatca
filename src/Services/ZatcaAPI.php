<?php

namespace Hazem\Zatca\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class ZatcaAPI
{
    private $live;
    private $baseUrl;

    public function __construct()
    {
        $this->live = config('zatca.live', false);
        $this->baseUrl = $this->live ?
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/core' :
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal';
    }

    public function submitCSR($csr, $otp)
    {

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'OTP' => $otp,
                'Accept-Version' => 'V2',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/compliance', [
                'csr' => base64_encode($csr)
            ]);

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to submit CSR: ' . $e->getMessage());
        }
    }

    public function generateCSID($requestId, $token, $secret)
    {
        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'Accept-Version' => 'V2',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($token . ':' . $secret)
            ])->post($this->baseUrl . '/production/csids', [
                'compliance_request_id' => $requestId
            ]);

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to generate CSID: ' . $e->getMessage());
        }
    }

    public function submitInvoice($invoiceHash, $uuid, $invoice, $token, $secret, $isPOS = false)
    {

        try {
            $endpoint = $isPOS ? 'invoices/reporting/single' : 'invoices/clearance/single';
            $response = Http::withHeaders([
                'Accept-Version' => 'V2',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'accept-language' => 'en',
                'Authorization' => 'Basic ' . base64_encode($token . ':' . $secret)
            ])->post($this->baseUrl . '/' . $endpoint, [
                'invoiceHash' => $invoiceHash,
                'uuid' => $uuid,
                'invoice' => base64_encode($invoice)
            ]);


            return $response->collect();
        } catch (Exception $e) {
            throw new Exception('Failed to submit invoice: ' . $e->getMessage());
        }
    }
}
