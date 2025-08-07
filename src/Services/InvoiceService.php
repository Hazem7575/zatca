<?php

namespace Hazem\Zatca\Services;

use Exception;
use Illuminate\Support\Str;
use Salla\ZATCA\Helpers\Certificate;

class InvoiceService
{
    private $zatcaAPI;
    private $complianceService;
    private $live;
    private $defaultCompanyData = [
        'vat_no' => '399999999900003',
        'ci_no' => '4030283037',
        'company_name' => 'shadow dimensions ltd',
        'company_address' => 'Al Modinah Al Monawarah Branch Rd, 8575, 2111, Al Faisaliyah Dist, 23442',
        'company_building' => '8575',
        'company_plot_identification' => '2111',
        'company_city_subdivision' => 'Al Faisaliyah Dist',
        'company_city' => 'jeddah',
        'company_postal' => '23442',
        'company_country' => 'SA',
        'company_state' => 'Makkah',
        'company_phone' => '0126082030',
        'company_email' => 'info@shadowd.com.sa'
    ];

    public function __construct(ZatcaAPI $zatcaAPI, ComplianceService $complianceService, $live = false)
    {
        $this->zatcaAPI = $zatcaAPI;
        $this->complianceService = $complianceService;
        $this->live = $live;
    }

    /**
     * Prepare and submit invoice data to ZATCA
     */
    public function prepareInvoiceData($invoiceObj)
    {
        try {

            // Create invoice object with customer details
            $invoice = $this->createInvoiceObject($invoiceObj);

            // Process invoice items
            $invoiceItems = $this->processInvoiceItems($invoiceObj['items']);

            // Get customer details
            $customer = $invoice->customer;

            // Generate UUID for this invoice
            $UUID = (string) Str::uuid();

            // Prepare invoice properties
            $props = $this->prepareInvoiceProperties($invoice, $customer, $invoiceItems, $UUID);

            // Generate XML invoice
            $xmlInvoice = $this->complianceService->getDefaultSimplifiedTaxInvoice($props);
            // Sign and submit invoice
            $result = $this->signAndSubmitInvoice(
                $xmlInvoice,
                $UUID,
                $invoice->is_pos,
                $invoice->invoice_no,
                $invoice->branch_id,
                $invoice->total,
                $invoice->product_tax,
                $invoice->date
            );

            return $result;

        } catch (Exception $e) {
            throw new Exception('Failed to prepare invoice: ' . $e->getMessage());
        }
    }

    /**
     * Create invoice object from input data
     */
    private function createInvoiceObject($invoiceObj)
    {
        return (object)[
            'branch_id' => 0,
            'invoice_no' => $invoiceObj['invoice_number'],
            'total' => $invoiceObj['total_amount'] - $invoiceObj['vat_amount'],
            'grand_total' => $invoiceObj['total_amount'],
            'order_discount' => 0,
            'product_tax' => $invoiceObj['vat_amount'],
            'is_pos' => $invoiceObj['is_pos'],
            'is_invoice' => $invoiceObj['is_invoice'],
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => $invoiceObj['buyer_name'],
                'billing_address' => $invoiceObj['buyer_address'],
                'email' => 'm@g.com',
                'tax_number' => $invoiceObj['buyer_tax_number'],
                'billing_country' => 'SA',
                'billing_city' => $invoiceObj['buyer_city'],
                'billing_state' => $invoiceObj['buyer_state'],
                'billing_phone' => '',
                'billing_postal' => $invoiceObj['buyer_postal'],
                'billing_building' => $invoiceObj['buyer_building_no']
            ]
        ];
    }

    /**
     * Process invoice items
     */
    private function processInvoiceItems($items)
    {
        $invoiceItems = [];
        foreach ($items as $item) {
            $invoiceItems[] = (object)[
                'product_id' => 100,
                'product_name' => $item['name'],
                'unit_quantity' => $item['quantity'],
                'net_unit_price' => $item['price'],
                'discount' => 0,
                'item_tax' => $item['vat'],
                'subtotal' => $item['price'],
                'city_tax' => 0
            ];
        }
        return $invoiceItems;
    }

    /**
     * Prepare invoice properties for XML generation
     */
    private function prepareInvoiceProperties($invoice, $customer, $invoiceItems, $UUID)
    {
        return [
            'invoice_serial_number' => $invoice->invoice_no,
            'uuid' => $UUID,
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'invoice_type' => $invoice->is_pos ? '0211010' : '0111010',
            'invoice_type_no' => $invoice->is_invoice ? '388' : '381',
            'details' => $invoiceItems,
            'sale' => $invoice,
            'total' => $invoice->total,
            'grand_total' => $invoice->grand_total,
            'product_tax' => $invoice->product_tax,

            // Company details
            'VAT_number' => $this->defaultCompanyData['vat_no'],
            'VAT_name' => $this->defaultCompanyData['company_name'],
            'CRN_number' => $this->defaultCompanyData['vat_no'],
            'street' => $this->defaultCompanyData['company_address'],
            'building' => $this->defaultCompanyData['company_building'],
            'plot_identification' => $this->defaultCompanyData['company_plot_identification'],
            'city_subdivision' => $this->defaultCompanyData['company_city_subdivision'],
            'city' => $this->defaultCompanyData['company_city'],
            'postal' => $this->defaultCompanyData['company_postal'],

            // Customer details
            'CRN_number_CUSTOMER' => $customer->tax_number,
            'street_CUSTOMER' => $customer->billing_address,
            'building_CUSTOMER' => $customer->billing_building ?: '0000',
            'plot_identification_CUSTOMER' => $customer->billing_address,
            'city_subdivision_CUSTOMER' => '0000',
            'city_CUSTOMER' => $customer->billing_city,
            'postal_CUSTOMER' => $customer->billing_postal ?: '00000'
        ];
    }

    /**
     * Sign and submit invoice to ZATCA
     */
    private function signAndSubmitInvoice($xmlInvoice, $UUID, $isPos, $invoiceNo, $branchId, $total, $tax, $date)
    {
        try {
            // Get device info
            $deviceInfo = $this->getDeviceInfo($branchId);
            if (!$deviceInfo) {
                throw new Exception('Device information not found');
            }

            // Sign invoice
            $signedData = $this->signInvoice($deviceInfo, $xmlInvoice, $total, $tax, $date);

            // Submit to ZATCA
            $response = $this->submitInvoice($deviceInfo, $signedData, $UUID, $isPos);

            // Process response
            return $this->processResponse($response, $invoiceNo, $isPos);

        } catch (Exception $e) {
            throw new Exception('Failed to sign and submit invoice: ' . $e->getMessage());
        }
    }

    /**
     * Get device information from database
     */
    private function getDeviceInfo($branchId)
    {
        $deviceInfo = \DB::table('device_infos')
            ->where('branch_id', $branchId)
            ->first();

        if (!$deviceInfo) {
            return null;
        }

        $deviceInfo->token = json_decode($deviceInfo->token, true);
        return $deviceInfo;
    }

    /**
     * Sign invoice with device credentials
     */
    private function signInvoice($deviceInfo, $xmlInvoice, $total, $tax, $date)
    {
        $certificate = new Certificate(
            base64_decode($deviceInfo->token['binarySecurityToken']),
            $deviceInfo->private_key
        );
        $certificate->setSecretKey($deviceInfo->token['secret']);

        return $this->complianceService->signInvoice(
            $xmlInvoice,
            $total,
            $tax,
            $date,
            $certificate
        );
    }

    /**
     * Submit signed invoice to ZATCA
     */
    private function submitInvoice($deviceInfo, $signedData, $UUID, $isPos)
    {
        $endpoint = $isPos ? 'invoices/reporting/single' : 'invoices/clearance/single';

        return $this->zatcaAPI->submitInvoice(
            $signedData['invoice_hash'],
            $UUID,
            $signedData['final'],
            $deviceInfo->token['binarySecurityToken'],
            $deviceInfo->token['secret'],
            $isPos
        );
    }

    /**
     * Process ZATCA response
     */
    private function processResponse($response, $invoiceNo, $isPos)
    {
        $resultJSON = json_decode($response);

        $errors = $resultJSON->validationResults->errorMessages ?? [];
        $warnings = $resultJSON->validationResults->warningMessages ?? [];
        $status = $isPos ?
            ($resultJSON->reportingStatus ?? '') :
            ($resultJSON->clearanceStatus ?? '');

        // Save response to database
        \DB::table('zatca_response')->insert([
            'invoice_id' => $invoiceNo,
            'full_response' => $resultJSON,
            'warnings' => $warnings,
            'errors' => $errors,
            'status' => $status
        ]);

        return $status;
    }
}
