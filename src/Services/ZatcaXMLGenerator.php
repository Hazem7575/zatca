<?php

namespace Hazem\Zatca\Services;

use Carbon\Carbon;
use Hazem\Zatca\Enums\InvoiceTypeCode;
use Hazem\Zatca\Models\ZatcaDevice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

class ZatcaXMLGenerator
{
    private const POS_INVOICE_TYPE = '0211010';
    private const STANDARD_INVOICE_TYPE = '0111010';
    private const INVOICE_TYPE_388 = '388';  // فاتورة
    private const INVOICE_TYPE_383 = '383';  // إشعار
    private const DEFAULT_CITY_SUBDIVISION = '0000';
    private const DEFAULT_POSTAL = '00000';

    /** @var array */
    private $companyData;

    /** @var string|null */
    private $previousInvoiceHash;

    private ZatcaDevice $device;

    /**
     * @param array $companyData Company information for XML generation
     * @param string|null $previousInvoiceHash Previous invoice hash for chain
     */
    public function __construct(ZatcaDevice $device, ?string $previousInvoiceHash = null)
    {

        $this->validateCompanyData($device->data);
        $this->companyData = $device->data;
        $this->device = $device;
        $this->previousInvoiceHash = $previousInvoiceHash ??
            'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';
    }

    /**
     * Generate XML and sign invoice with ZATCA
     *
     * @param stdClass $invoice Invoice basic data
     * @param stdClass $mInvoice Modified invoice data
     * @param stdClass $customer Customer information
     * @param array $invoiceItems Invoice line items
     * @return array ZATCA response status
     * @throws InvalidArgumentException If required data is missing
     */
    public function generateAndSignInvoice(
        stdClass $invoice,
        stdClass $mInvoice,
        stdClass $customer,
        array $invoiceItems
    ): array {
        $this->validateInputs($invoice, $mInvoice, $customer, $invoiceItems);
        $customerData = $this->prepareCustomerData($customer);
        $sale = $this->prepareSaleData($mInvoice);


        $uuid = $this->generateUUID();
        $xmlProperties = $this->prepareXMLProperties(
            $invoice,
            $mInvoice,
            $customerData,
            $sale,
            $invoiceItems,
            $uuid
        );


        $invoiceXML = app(ComplianceService::class)->getDefaultSimplifiedTaxInvoice($xmlProperties);
        $signer = new ZatcaInvoiceSigner($this->device);
        $signatureResponse = $signer->signInvoice(
            $invoiceXML,
            $uuid,
            $mInvoice->is_pos,
            $sale->total,
            $sale->product_tax,
            $sale->date
        );
        return $signatureResponse;
    }

    /**
     * Validate company data
     */
    private function validateCompanyData(array $data): void
    {
        $requiredFields = [
            'vat_no',
            'company_address',
            'company_building',
            'company_plot_identification',
            'company_city_subdivision',
            'company_city',
            'company_postal',
            'company_name'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Missing required company field: {$field}");
            }
        }
    }

    /**
     * Prepare customer data
     */
    private function prepareCustomerData(stdClass $customer): stdClass
    {
        return (object)[
            'name' => empty($customer->billing_name) ? $customer->name : $customer->billing_name,
            'address' => $customer->billing_address,
            'email' => $customer->email,
            'tax_number' => $customer->tax_number,
            'country' => $customer->billing_country,
            'city' => $customer->billing_city,
            'state' => $customer->billing_state,
            'phone' => $customer->billing_phone,
            'postal' => $customer->billing_postal ?: self::DEFAULT_POSTAL,
            'building' => $customer->billing_building ?: self::DEFAULT_CITY_SUBDIVISION
        ];
    }

    /**
     * Prepare sale data object
     */
    private function prepareSaleData(stdClass $mInvoice): stdClass
    {
        $now = Carbon::now();

        return (object)[
            'id' => $mInvoice->invoice_no,
            'total' => $mInvoice->total,
            'grand_total' => $mInvoice->grand_total,
            'order_discount' => 0, // Future implementation
            'product_tax' => $mInvoice->product_tax,
            'date' => $mInvoice->date
        ];
    }

    /**
     * Generate UUID v4
     */
    private function generateUUID(): string
    {
        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex(random_bytes(16)), 4)
        );
    }

    /**
     * Prepare XML properties
     */
    private function prepareXMLProperties(
        stdClass $invoice,
        stdClass $mInvoice,
        stdClass $customer,
        stdClass $sale,
        array $invoiceItems,
        string $uuid
    ): array {
        $now = Carbon::parse($invoice->date);
        return [
            'invoice_serial_number' => $mInvoice->invoice_no,
            'uuid' => $uuid,
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('H:i:s').'Z',
            'previous_invoice_hash' => $this->previousInvoiceHash,
            'invoice_counter_number' => 1,
            'CRN_number' => $this->device->data['vat_no'],
            'street' => $this->device->data['company_address'],
            'building' => $this->device->data['company_building'],
            'plot_identification' => $this->device->data['company_plot_identification'],
            'city_subdivision' => $this->device->data['company_city_subdivision'],
            'city' => $this->device->data['company_city'],
            'postal' => $this->device->data['company_postal'],
            'VAT_number' => $this->device->data['vat_no'],
            'VAT_name' => $this->device->data['company_name'],
            'details' => $invoiceItems,
            'sale' => $sale,
            'invoice_type' => $invoice->is_pos ? self::POS_INVOICE_TYPE : self::STANDARD_INVOICE_TYPE,
            'invoice_type_no' => $this->getTypeInvoice($invoice),
            'CRN_number_CUSTOMER' => $customer->tax_number,
            'street_CUSTOMER' => $customer->address,
            'building_CUSTOMER' => $customer->building,
            'plot_identification_CUSTOMER' => $customer->address,
            'city_subdivision_CUSTOMER' => self::DEFAULT_CITY_SUBDIVISION,
            'city_CUSTOMER' => $customer->city,
            'postal_CUSTOMER' => $customer->postal,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];
    }

    private function  getTypeInvoice(object $invoice): string
    {
        if(isset($invoice->is_refund) AND $invoice->is_refund) return InvoiceTypeCode::CREDIT_NOTE->value;
        if(isset($invoice->is_invoice) AND $invoice->is_invoice) {
            return InvoiceTypeCode::STANDARD_TAX_INVOICE->value;
        }
        return InvoiceTypeCode::DEBIT_NOTE->value;
    }
    /**
     * Process and save ZATCA response
     */
    private function processAndSaveResponse(string $response, stdClass $mInvoice): string
    {
        $resultJSON = json_decode($response);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON response from ZATCA');
        }

        $status = $mInvoice->is_pos ?
            $resultJSON->reportingStatus :
            $resultJSON->clearanceStatus;

        DB::table('zatca_response')->insert([
            'invoice_id' => $mInvoice->invoice_no,
            'full_response' => $resultJSON,
            'warnings' => $resultJSON->validationResults->warningMessages,
            'errors' => $resultJSON->validationResults->errorMessages,
            'status' => $status
        ]);

        return $status;
    }

    /**
     * Validate input parameters
     */
    private function validateInputs(
        stdClass $invoice,
        stdClass $mInvoice,
        stdClass $customer,
        array $invoiceItems
    ): void {

//        if (empty($invoice->is_pos) || !isset($invoice->is_invoice)) {
//            throw new InvalidArgumentException('Invoice type information is missing');
//        }

        if (empty($mInvoice->invoice_no) || empty($mInvoice->total) ||
            empty($mInvoice->grand_total)) {
            throw new InvalidArgumentException('Required invoice details are missing');
        }

        if (empty($customer->name)) {
            throw new InvalidArgumentException('Required customer details are missing');
        }

        if (empty($invoiceItems)) {
            throw new InvalidArgumentException('Invoice items cannot be empty');
        }
    }

    /**
     * Get default simplified tax invoice template
     * @codeCoverageIgnore This method should be implemented based on your requirements
     */
    protected function getDefaultSimplifiedTaxInvoice(array $props): string
    {
        // Implementation needed based on your XML template
        // This should return the XML string based on the properties
        throw new \RuntimeException('Method not implemented');
    }

    /**
     * Sign invoice with ZATCA
     * @codeCoverageIgnore This method should be implemented based on your requirements
     */
    protected function signInvoice(
        string $xml,
        string $uuid,
        bool $isPos,
        string $invoiceNo,
        int $branchId,
        float $total,
        float $tax,
        string $date
    ): string {
        // Implementation needed based on your ZATCA integration
        // This should return the JSON response from ZATCA
        throw new \RuntimeException('Method not implemented');
    }
}
