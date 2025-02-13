# Hazem/Zatca

ZATCA (Fatoora) e-invoicing implementation for Saudi Arabia.

please visit our [documentation](https://raanh-1.gitbook.io/hazem-package).

## Table of Contents

- [Installation](#installation)
- [Requirements](#requirements)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
    - [Device Registration](#device-registration)
    - [Invoice Creation](#invoice-creation)
    - [Invoice Submission](#invoice-submission)
    - [Invoice Status](#invoice-status)
- [Customization](#customization)
- [API Reference](#api-reference)
- [Database Schema](#database-schema)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Installation

Install the package via Composer:

```bash
composer require hazem7575/zatca
```

## Requirements

- PHP ^7.4|^8.0
- Laravel ^8.0|^9.0|^10.0
- OpenSSL Extension
- JSON Extension
- DOM Extension
- cURL Extension

## Configuration

1. Publish configuration and migrations:

```bash
php artisan vendor:publish --provider="Hazem\Zatca\ZatcaServiceProvider"
```

2. Run migrations:

```bash
php artisan migrate
```

3. Configure your `.env` file:

```env
ZATCA_LIVE=false
```

## Basic Usage

### Device Registration

Add the `HasZatcaDevice` trait to your model:

```php
use Hazem\Zatca\Traits\HasZatcaDevice;

class Order extends Model
{
    use HasZatcaDevice;
}
```

Register a new device:

```php
$order = Order::find(1);
$device = $order->registerZatcaDevice($otp, [
    'vat_no' => '123456789',
    'company_name' => 'My Company',
    // ... other company data
]);
```

### Invoice Creation

Add the `HasZatcaInvoice` trait to your model:

```php
use Hazem\Zatca\Traits\HasZatcaInvoice;

class Order extends Model
{
    use HasZatcaInvoice;

    /**
     * Required fields for ZATCA invoice
     */
    protected function prepareZatcaInvoiceData()
    {
        $items = $this->prepareZatcaItems();
        return [
            // Required fields
            'invoice_number' => $this->invoice_no,
            'total_amount' => round($this->final_total, 2),
            'vat_amount' => collect($items)->sum(function ($item) {
                return round($item['vat'] * $item['quantity'], 2);
            }),
            'is_pos' => true,
            'is_invoice' => $this->type === 'sell',
            'items' => $items,
            'date' => $this->transaction_date,
            
            // Optional buyer information
            'buyer_name' => $this->contact->name ?? null,
            'buyer_tax_number' => null,
            'buyer_address' => null,
            'buyer_city' => null,
            'buyer_state' => null,
            'buyer_postal' => null,
            'buyer_building_no' => null
        ];
    }

    /**
     * Prepare invoice items
     */
    protected function prepareZatcaItems()
    {
        return $this->sell_lines->map(function($item) {
            return [
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'price' => round($item->unit_price, 2),
                'vat' => round(round($item->unit_price, 2) * 0.15, 2)
            ];
        })->toArray();
    }

    /**
     * Get device relationship
     */
    protected function device()
    {
        return $this->business;
    }

    /**
     * Update last invoice hash
     */
    public function update_last_hash($hash)
    {
        $this->business()->update([
            'last_hash' => $hash
        ]);
    }

    /**
     * Get last invoice hash
     */
    public function last_hash()
    {
        $last_hash = $this->business->last_hash;
        if(isset($last_hash)) {
            return $last_hash;
        }
        return 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';
    }
}
```


```php
use Hazem\Zatca\Traits\HasZatcaInvoice;

class Order extends Model
{
    use HasZatcaInvoice;
}
```

Create an invoice using the fluent interface:

```php
$invoice = Zatca::prepare()
    ->setInvoiceNumber('INV-001')
    ->setTotalAmount(115.00)
    ->setVatAmount(15.00)
    ->setBuyerName('John Doe')              // Optional
    ->setBuyerTaxNumber('1234567890')       // Optional
    ->setBuyerAddress('123 Main St')        // Optional
    ->setBuyerCity('Riyadh')               // Optional
    ->setBuyerState('Riyadh')              // Optional
    ->setBuyerPostal('12345')              // Optional
    ->setBuyerBuildingNumber('1234')       // Optional
    ->isPOS()                              // For POS invoices
    ->isInvoice(true)                      // For standard invoices
    ->setDate(now())
    ->addItem('Product 1', 1, 100.00, 15.00);
```

### Invoice Submission

Submit the invoice:

```php
$result = $order->submitToZatca($invoice->toArray());
```

Or submit with custom data:

```php
$result = $order->submitToZatca([
    'invoice_number' => 'INV-001',
    'total_amount' => 115.00,
    'vat_amount' => 15.00,
    'items' => [
        [
            'name' => 'Product 1',
            'quantity' => 1,
            'price' => 100.00,
            'vat' => 15.00
        ]
    ]
]);
```

### Invoice Status

Check invoice status:

```php
// Check if submitted
if ($order->isSubmittedToZatca()) {
    // Get status
    $status = $order->getZatcaStatus();
    
    // Check for errors
    if ($order->hasZatcaErrors()) {
        $errors = $order->getZatcaErrors();
    }
}
```

## API Reference

### Device Facade

```php
Device::register(string|int $businessId, string $otp, array $companyData);
Device::activate(string|int $businessId);
Device::hasDevice(string|int $businessId);
Device::getDevice(string|int $businessId);
Device::getDeviceStatus(string|int $businessId);
Device::isDeviceActive(string|int $businessId);
```

### Zatca Facade

```php
Zatca::prepare();
Zatca::submitInvoice(string|int $businessId, array $invoiceData);
Zatca::submitSimplifiedInvoice(string|int $businessId, array $invoiceData);
Zatca::submitStandardInvoice(string|int $businessId, array $invoiceData);
Zatca::submitCreditNote(string|int $businessId, array $invoiceData);
Zatca::submitDebitNote(string|int $businessId, array $invoiceData);
Zatca::getInvoiceStatus(string|int $businessId, string $invoiceNumber);
Zatca::getInvoiceReport(string|int $businessId, string $invoiceNumber);
Zatca::clearInvoice(string|int $businessId, string $invoiceNumber);
Zatca::reportInvoice(string|int $businessId, string $invoiceNumber);
Zatca::registerDevice(string|int $businessId, string $otp, array $companyData);
Zatca::validateInvoice(array $invoiceData);
Zatca::generateQRCode(array $invoiceData);
```

### HasZatcaDevice Trait Methods

```php
device();                    // Get device relationship
registerZatcaDevice();       // Register new device
active();                    // Activate device
hasZatcaDevice();           // Check device existence
getLatestZatcaDevice();     // Get latest device
scopeHasZatca();            // Query scope for models with active devices
scopeDoesntHaveZatca();     // Query scope for models without active devices
```

### HasZatcaInvoice Trait Methods

```php
// Basic Methods
order();                      // Get order relationship
prepareZatcaInvoiceData();   // Prepare invoice data (required fields)
prepareZatcaItems();         // Prepare items data
submitToZatca();             // Submit invoice
isSubmittedToZatca();        // Check submission status
getZatcaStatus();            // Get invoice status
hasZatcaErrors();            // Check for errors
getZatcaErrors();            // Get error details

// Hash Management Methods
update_last_hash($hash);     // Update the last invoice hash
last_hash();                 // Get the last invoice hash (with default)

// Required Fields for prepareZatcaInvoiceData():
// - invoice_number
// - total_amount
// - vat_amount
// - is_pos
// - is_invoice
// - items
// - date

// Optional Fields:
// - buyer_name
// - buyer_tax_number
// - buyer_address
// - buyer_city
// - buyer_state
// - buyer_postal
// - buyer_building_no
```


## Database Schema

### devices_zatca Table

- `id` - Primary key
- `deviceable_type` - Model type
- `deviceable_id` - Model ID
- `request_id` - ZATCA request ID
- `status` - Device status
- `disposition_message` - Status message
- `binary_security_token` - Security token
- `secret` - Device secret
- `errors` - Error messages (JSON)
- `private_key` - Private key
- `public_key` - Public key
- `csr_content` - CSR content
- `timestamps`

### orders_zatca Table

- `id` - Primary key
- `orderable_type` - Model type
- `orderable_id` - Model ID
- `invoice_number` - Invoice number
- `uuid` - Unique identifier
- `invoice_hash` - Invoice hash
- `signed_invoice_xml` - Signed XML
- `status` - Invoice status
- `is_reported` - Reporting status
- `is_cleared` - Clearance status
- `warnings` - Warning messages (JSON)
- `errors` - Error messages (JSON)
- `response` - Full response (JSON)
- `submitted_at` - Submission timestamp
- `timestamps`

### company_data Table

- `id` - Primary key
- `solution_name` - Solution name
- `solution_version` - Solution version
- `business_category` - Business category
- `vat_no` - VAT number
- `ci_no` - Commercial registration number
- `company_name` - Company name
- `company_address` - Company address
- `company_building` - Building number
- `company_plot_identification` - Plot ID
- `company_city_subdivision` - City subdivision
- `company_city` - City
- `company_postal` - Postal code
- `company_country` - Country code
- `company_state` - State
- `company_phone` - Phone number
- `company_email` - Email address
- `timestamps`

## Security

- Row Level Security (RLS) enabled for all tables
- Hash chaining for invoice integrity
- Secure storage of private keys and secrets
- Authentication required for all operations
- Data access controlled through policies

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the MIT license.
