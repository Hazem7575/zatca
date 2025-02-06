<?php

namespace Hazem\Zatca\Services;

use Hazem\Zatca\Models\ZatcaDevice;
use Exception;

class DeviceRegistrationService
{
    private $csrGenerator;
    private $api;
    private $live;

    public function __construct(CSRGenerator $csrGenerator, ZatcaAPI $api, $live = false)
    {
        $this->csrGenerator = $csrGenerator;
        $this->api = $api;
        $this->live = config('zatca.live', false);
    }

    public function register($businessId, $otp, array $companyData)
    {
        try {
            // Validate required company data
            $this->validateCompanyData($companyData);

            // Prepare CSR data
            $csrData = [
                'organization_identifier' => $companyData['vat_no'],
                'solution_name' => $companyData['solution_name'],
                'version' => $companyData['solution_version'] ?? '1.0',
                'serial_number' => $this->generateSerialNumber(),
                'common_name' => $companyData['common_name'],
                'organization_name' => $companyData['company_name'],
                'organization_unit_name' => $companyData['ci_no'],
                'registered_address' => $companyData['company_address'],
                'business_category' => $companyData['business_category'] ?? 'Contracting',
                'current_env' => $this->live ? 'production' : 'sandbox'
            ];

            // Generate CSR
            $csr = $this->csrGenerator->generate($csrData, $companyData);

            // Submit CSR to ZATCA
            $response = $this->api->submitCSR($csr['csr'], $otp);

            // Store device information
            return ZatcaDevice::create([
                'deviceable_type' => $companyData['model_type'] ?? null,
                'deviceable_id' => $companyData['model_id'] ?? $businessId,
                'request_id' => $response['requestID'],
                'disposition_message' => $response['dispositionMessage'],
                'binary_security_token' => $response['binarySecurityToken'],
                'secret' => $response['secret'],
                'errors' => $response['errors'],
                'public_key' => $csr['public_key'],
                'private_key' => $csr['private_key'],
                'csr_content' => $csr['csr'],
                'data' => collect($companyData)->except('model_type' , 'model_id')
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to register device: ' . $e->getMessage());
        }
    }

    private function validateCompanyData($companyData)
    {
        $required = [
            'vat_no' => 'VAT number',
            'ci_no' => 'Commercial registration number',
            'company_name' => 'Company name',
            'company_address' => 'Company address',
            'company_building' => 'Building number',
            'company_plot_identification' => 'Plot identification',
            'company_city_subdivision' => 'City subdivision',
            'company_city' => 'City',
            'company_postal' => 'Postal code',
            'company_country' => 'Country'
        ];

        foreach ($required as $field => $label) {
            if (empty($companyData[$field])) {
                throw new Exception("Missing required company data: {$label}");
            }
        }
    }

    private function generateSerialNumber()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
