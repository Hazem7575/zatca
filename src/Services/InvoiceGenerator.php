<?php

namespace Hazem\Zatca\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class InvoiceGenerator
{
    private const DEFAULT_COUNTRY = 'SA';
    private const DEFAULT_EMAIL = 'm@g.com';
    private const DEFAULT_BRANCH_ID = 0;

    /** @var array */
    private $companyData;

    /**
     * @param array $companyData Company information
     * @throws InvalidArgumentException If company data is invalid
     */
    public function __construct(array $companyData)
    {
        $this->validateCompanyData($companyData);
        $this->companyData = $companyData;
    }

    /**
     * Generate invoice data
     *
     * @param array $invoiceData Raw invoice data
     * @return object Formatted invoice data
     * @throws InvalidArgumentException If invoice data is invalid
     */
    public function generate(array $invoiceData): object
    {

        $this->validateInvoiceData($invoiceData);
        $carbon = $this->parseDate($invoiceData['date']);

        return (object)[
            'branch_id' => self::DEFAULT_BRANCH_ID,
            'invoice_no' => $this->sanitizeString($invoiceData['invoice_number']),
            'total' => $this->calculateNetTotal($invoiceData),
            'grand_total' => $this->sanitizeAmount($invoiceData['total_amount']),
            'order_discount' => $this->getOrderDiscount($invoiceData),
            'product_tax' => $this->sanitizeAmount($invoiceData['vat_amount']),
            'is_pos' => (bool)$invoiceData['is_pos'],
            'is_invoice' => (bool)$invoiceData['is_invoice'],
            'date' => $this->formatDateTime($carbon),
           // 'seller' => $this->getSellerData(),
            'customer' => $this->getCustomerData($invoiceData)
        ];
    }

    public function items($invoiceData): array
    {
        $invoiceItems = [];
        foreach($invoiceData['items'] as $item){
            $invoiceItems[] = (object)[
                'product_id' => 100,
                'product_name' => $item['name'],
                'unit_quantity' => $item['quantity'],
                'net_unit_price' =>$item['price'],
                'discount' => 0,
                'item_tax' => $item['vat'],
                'subtotal' => $item['price'],
                'city_tax' => 0
            ];
        }
        return $invoiceItems;
    }
    /**
     * Validate company data
     */
    private function validateCompanyData(array $companyData): void
    {
        $requiredFields = ['company_name', 'vat_no', 'company_building', 'company_plot_identification', 'company_city', 'company_postal'];

        foreach ($requiredFields as $field) {
            if (empty($companyData[$field])) {
                throw new InvalidArgumentException("Missing required company field: {$field}");
            }
        }
    }

    /**
     * Validate invoice data
     */
    private function validateInvoiceData(array $invoiceData): void
    {
        $requiredFields = [
            'date',
            'invoice_number',
            'total_amount',
            'vat_amount',
            'buyer_name',
            'is_pos',
            'is_invoice'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($invoiceData[$field])) {
                throw new InvalidArgumentException("Missing required invoice field: {$field}");
            }
        }

        if ($invoiceData['total_amount'] < 0) {
            throw new InvalidArgumentException("Total amount cannot be negative");
        }

        if ($invoiceData['vat_amount'] < 0) {
            throw new InvalidArgumentException("VAT amount cannot be negative");
        }
    }

    /**
     * Get seller (company) data
     */
    private function getSellerData(): object
    {
        return (object)[
            'name' => $this->companyData['company_name'],
            'tax_number' => $this->companyData['vat_no'],
            'address' => $this->companyData['company_city'],
            'country' => $this->companyData['company_country']
        ];
    }

    /**
     * Get customer data
     */
    private function getCustomerData(array $invoiceData): object
    {
        return (object)[
            'name' => $this->sanitizeString($invoiceData['buyer_name']),
            'billing_address' => $this->sanitizeString($invoiceData['buyer_address']),
            'email' => self::DEFAULT_EMAIL,
            'tax_number' => $this->sanitizeString($invoiceData['buyer_tax_number']),
            'billing_country' => self::DEFAULT_COUNTRY,
            'billing_city' => $this->sanitizeString($invoiceData['buyer_city'] ?? ''),
            'billing_state' => $this->sanitizeString($invoiceData['buyer_state'] ?? ''),
            'billing_phone' => $this->sanitizeString($invoiceData['buyer_phone'] ?? ''),
            'billing_postal' => $this->sanitizeString($invoiceData['buyer_postal'] ?? ''),
            'billing_building' => $this->sanitizeString($invoiceData['buyer_building_no'] ?? '')
        ];
    }

    /**
     * Parse date string to Carbon instance
     */
    private function parseDate(string $date): Carbon
    {
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid date format: {$date}");
        }
    }

    /**
     * Format date time to ISO format
     */
    private function formatDateTime(Carbon $carbon): string
    {
        return $carbon->format('Y-m-d\TH:i:s').'Z';
    }

    /**
     * Calculate net total (total - VAT)
     */
    private function calculateNetTotal(array $invoiceData): float
    {
        return $this->sanitizeAmount($invoiceData['total_amount']) -
            $this->sanitizeAmount($invoiceData['vat_amount']);
    }

    /**
     * Get order discount with default 0
     */
    private function getOrderDiscount(array $invoiceData): float
    {
        return $this->sanitizeAmount($invoiceData['order_discount'] ?? 0);
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString(?string $value): string
    {
        return trim($value ?? '');
    }

    /**
     * Sanitize numeric amount
     */
    private function sanitizeAmount($amount): float
    {
        return (float)($amount ?? 0);
    }
}
