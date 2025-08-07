# Hazem/Zatca

ZATCA (Fatoora) e-invoicing implementation for Saudi Arabia.

## Contents
- [Installation](#installation)
- [Configuration](#configuration)
- [Service Provider](#service-provider)
- [Basic Usage](#basic-usage)
- [Submitting Invoices](#submitting-invoices)
- [Customizing Data](#customizing-data)
- [Checking Invoice Status](#checking-invoice-status)
- [Database Structure](#database-structure)
- [Testing](#testing)
- [API Reference](#api-reference)

## Installation

You can install the package via Composer:

```bash
composer require hazem/zatca
```

## Configuration

1. Publish configuration and migration files:

```bash
php artisan vendor:publish --provider="Hazem\Zatca\ZatcaServiceProvider"
```

2. Run migrations:

```bash
php artisan migrate
```

3. Set environment variables in your `.env` file:

```env
ZATCA_LIVE=false
```

## Service Provider

The package automatically registers the `ZatcaServiceProvider` in your application. The provider registers the following services:

### Registered Services

- `zatca` - Main ZATCA service singleton
- `zatca.device` - Device registration service
- `Hazem\Zatca\Facades\Zatca` - ZATCA facade
- `Hazem\Zatca\Facades\Device` - Device facade

### Manual Registration (Laravel 11+)

For Laravel 11+ applications, you need to manually register the provider in `bootstrap/providers.php`:

```php
<?php

return [
    // end line
    Hazem\Zatca\ZatcaServiceProvider::class,
];
```

### Laravel 10 and Below

For Laravel 10 and below, the provider is automatically registered via `composer.json` autoload.

## Basic Usage

### Adding Traits to Your Model

Add the `HasZatcaDevice` and `HasZatcaInvoice` traits to your model:

```php
use Hazem\Zatca\Traits\HasZatcaDevice;
use Hazem\Zatca\Traits\HasZatcaInvoice;

class Order extends Model
{
    use HasZatcaInvoice;
}
```

### Registering a New Device

```php
$user = User::find(1);
$device = $user->registerZatcaDevice($otp, [
    'vat_no' => '399999999900003', // this number for sandbox
    'ci_no' => '1234567891',
    'company_name' => 'Your Company',
    'company_address' => 'Riyadh',
    'company_building' => '1234',
    'company_plot_identification' => '1234',
    'company_city_subdivision' => '1234',
    'company_city' => 'Riyadh',
    'company_postal' => '12345',
    'company_country' => 'SA',
    'solution_name' => 'Your Solution',
    'common_name' => 'Your Common Name',
]);
```

## Submitting Invoices

### Basic Method

```php
$order = Order::find(1);
$result = $order->submitToZatca();
```

### Customizing Data

```php
$result = $order->submitToZatca([
    'invoice_number' => 'INV-001',
    'buyer_name' => 'Customer',
    'buyer_vat' => '1234567890',
    'total_amount' => 100,
    'vat_amount' => 15,
    'items' => [
        [
            'name' => 'Product 1',
            'quantity' => 1,
            'price' => 100,
            'vat' => 15
        ]
    ]
]);
```

### Using Zatca Facade

You can also use the Zatca facade for direct invoice submission:

```php
use Hazem\Zatca\Facades\Zatca;

// Submit standard invoice
$result = Zatca::submitStandardInvoice($businessId, $invoiceData);

// Submit simplified invoice
$result = Zatca::submitSimplifiedInvoice($businessId, $invoiceData);

// Submit credit note
$result = Zatca::submitCreditNote($businessId, $invoiceData);
```

### Customizing Data Preparation

You can override the default methods in your model:

```php
class Order extends Model
{
    use HasZatcaInvoice;

    protected function prepareZatcaInvoiceData()
    {
        $items = $this->prepareZatcaItems();
        return [
            // Required fields
            'invoice_number' => rand(100000, 999999),
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
            'is_pos' => false,
            'is_invoice' => false,
            'is_refund' => true,
            'items' => $items,
            'date' => now()->format('Y-m-d H:i:s'),
            'buyer_name' => 'Testing',
            // Optional buyer information
            'buyer_tax_number' => null,
            'buyer_address' => null,
            'buyer_city' => null,
            'buyer_state' => null,
            'buyer_postal' => null,
            'buyer_building_no' => null
        ];
    }

    protected function prepareZatcaItems()
    {
        return $this->orderItems->map(function($item) {
            return [
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'vat' => $item->tax_amount
            ];
        })->toArray();
    }
}
```

## Invoice Types and Subtypes

The package supports different invoice types and subtypes:

### Invoice Types (InvoiceTypeCode enum)
- `STANDARD_TAX_INVOICE` (388) - Standard Tax Invoice (B2B)
- `DEBIT_NOTE` (383) - Tax Invoice Debit Note
- `CREDIT_NOTE` (381) - Tax Invoice Credit Note
- `PREPAYMENT_INVOICE` (386) - Prepayment Invoice

### Invoice Subtypes (InvoiceSubtype enum)
- `STANDARD` (01) - Standard/Tax Invoice (B2B, B2G)
- `SIMPLIFIED` (02) - Simplified Invoice (B2C)

## Checking Invoice Status

```php
// Check if invoice was submitted
if ($order->isSubmittedToZatca()) {
    // Invoice was submitted
}

// Get invoice status
$status = $order->getZatcaStatus();

// Check for errors
if ($order->hasZatcaErrors()) {
    $errors = $order->getZatcaErrors();
}
```

## Database Structure

### `hazem_devices_zatca` Table

Stores registered device information:

- `id` - Serial identifier
- `deviceable_type` - Related model type
- `deviceable_id` - Related model ID
- `request_id` - ZATCA request ID
- `disposition_message` - Status message
- `binary_security_token` - Security token
- `secret` - Secret key
- `errors` - Errors (JSON)
- `private_key` - Private key
- `public_key` - Public key
- `csr_content` - CSR content

### `hazem_orders_zatca` Table

Stores submitted invoice information:

- `id` - Serial identifier
- `orderable_type` - Related model type
- `orderable_id` - Related model ID
- `invoice_number` - Invoice number
- `uuid` - Unique identifier
- `invoice_hash` - Invoice hash
- `signed_invoice_xml` - Signed XML
- `status` - Invoice status
- `is_reported` - Reported status
- `is_cleared` - Cleared status
- `warnings` - Warnings (JSON)
- `errors` - Errors (JSON)
- `response` - Complete response (JSON)
- `submitted_at` - Submission timestamp

## Testing

The package includes comprehensive unit tests for all major components:

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Unit/ZatcaControllerTest.php

# Run with coverage
php artisan test --coverage
```

### Test Structure

- `tests/Unit/ZatcaControllerTest.php` - Controller tests
- `tests/Unit/OrderTest.php` - Order model tests
- `tests/Unit/UserTest.php` - User model tests

### Example Test

```php
class ZatcaControllerTest extends TestCase
{
    public function test_device_registration()
    {
        $user = User::factory()->create();
        
        $device = $user->registerZatcaDevice(123456, [
            'vat_no' => '399999999900003',
            'company_name' => 'Test Company',
            // ... other data
        ]);
        
        $this->assertNotNull($device);
        $this->assertTrue($user->hasZatcaDevice());
    }
}
```

## API Reference

### Available Methods

#### HasZatcaDevice Trait

- `zatcaDevice()` - Relationship with ZATCA device
- `registerZatcaDevice($otp, $data)` - Register new device
- `hasZatcaDevice()` - Check if device exists
- `getLatestZatcaDevice()` - Get latest device

#### HasZatcaInvoice Trait

- `zatcaOrders()` - Relationship with ZATCA invoices
- `latestZatcaOrder()` - Latest invoice
- `submitToZatca($data = null)` - Submit invoice
- `isSubmittedToZatca()` - Check submission status
- `getZatcaStatus()` - Get invoice status
- `hasZatcaErrors()` - Check for errors
- `getZatcaErrors()` - Get errors

#### Zatca Facade

- `submitInvoice($businessId, $data)` - Submit invoice with automatic type detection
- `submitSimplifiedInvoice($businessId, $data)` - Submit simplified invoice
- `submitStandardInvoice($businessId, $data)` - Submit standard invoice
- `submitCreditNote($businessId, $data)` - Submit credit note

#### Device Facade

- `register($otp, $data)` - Register new device
- `getStatus($deviceId)` - Get device status
- `getCertificate($deviceId)` - Get device certificate

### Services

The package includes several services for different functionalities:

- `ComplianceService` - Handles compliance checks
- `DeviceRegistrationService` - Handles device registration
- `InvoiceGenerator` - Generates invoice data
- `InvoiceService` - Main invoice service
- `ZatcaAPI` - ZATCA API communication
- `ZatcaXMLGenerator` - Generates XML for invoices
- `CSRGenerator` - Generates Certificate Signing Requests
- `InvoiceSigner` - Signs invoices with certificates

### Configuration Options

```php
// config/zatca.php
return [
    'live' => env('ZATCA_LIVE', false),
];
```

## Error Handling

The package provides comprehensive error handling:

```php
try {
    $result = $order->submitToZatca();
} catch (\Hazem\Zatca\Exceptions\ZatcaException $e) {
    // Handle ZATCA specific errors
    Log::error('ZATCA Error: ' . $e->getMessage());
} catch (\Exception $e) {
    // Handle general errors
    Log::error('General Error: ' . $e->getMessage());
}
```

## Contributing

We welcome contributions! Please send pull requests to the GitHub repository.

## License

Licensed under MIT License.
