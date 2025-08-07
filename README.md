# Hazem/Zatca

ZATCA (Fatoora) e-invoicing implementation for Saudi Arabia.

## المحتويات
- [التثبيت](#التثبيت)
- [الإعداد](#الإعداد)
- [الاستخدام الأساسي](#الاستخدام-الأساسي)
- [إرسال الفواتير](#إرسال-الفواتير)
- [تخصيص البيانات](#تخصيص-البيانات)
- [التحقق من حالة الفواتير](#التحقق-من-حالة-الفواتير)
- [هيكل قاعدة البيانات](#هيكل-قاعدة-البيانات)

## التثبيت

يمكنك تثبيت الحزمة عبر Composer:

```bash
composer require hazem/zatca
```

## الإعداد

1. نشر ملفات الإعداد والترحيل:

```bash
php artisan vendor:publish --provider="Hazem\Zatca\ZatcaServiceProvider"
```

2. تشغيل الترحيلات:

```bash
php artisan migrate
```

3. إعداد متغيرات البيئة في ملف `.env`:

```env
ZATCA_LIVE=false
ZATCA_VAT_NO=
ZATCA_COMPANY_NAME=
# ... باقي الإعدادات
```

## الاستخدام الأساسي

### إضافة السمات للنموذج

أضف السمات `HasZatcaDevice` و `HasZatcaInvoice` إلى النموذج الخاص بك:

```php
use Hazem\Zatca\Traits\HasZatcaDevice;
use Hazem\Zatca\Traits\HasZatcaInvoice;

class Order extends Model
{
    use HasZatcaDevice, HasZatcaInvoice;
}
```

### تسجيل جهاز جديد

```php
$order = Order::find(1);
$device = $order->registerZatcaDevice($otp, [
    'vat_no' => '123456789',
    'company_name' => 'شركتي',
    // ... باقي البيانات
]);
```

## إرسال الفواتير

### الطريقة الأساسية

```php
$order = Order::find(1);
$result = $order->submitToZatca();
```

### تخصيص البيانات

```php
$result = $order->submitToZatca([
    'invoice_number' => 'INV-001',
    'buyer_name' => 'عميل',
    'buyer_vat' => '1234567890',
    'total_amount' => 100,
    'vat_amount' => 15,
    'items' => [
        [
            'name' => 'منتج 1',
            'quantity' => 1,
            'price' => 100,
            'vat' => 15
        ]
    ]
]);
```

### تخصيص إعداد البيانات

يمكنك تجاوز الطرق الافتراضية في النموذج الخاص بك:

```php
class Order extends Model
{
    use HasZatcaInvoice;

    protected function prepareZatcaInvoiceData()
    {
        return [
            'invoice_number' => $this->custom_number,
            'buyer_name' => $this->client->name,
            // ... باقي البيانات المخصصة
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

## التحقق من حالة الفواتير

```php
// التحقق من إرسال الفاتورة
if ($order->isSubmittedToZatca()) {
    // تم الإرسال
}

// الحصول على حالة الفاتورة
$status = $order->getZatcaStatus();

// التحقق من وجود أخطاء
if ($order->hasZatcaErrors()) {
    $errors = $order->getZatcaErrors();
}
```

## هيكل قاعدة البيانات

### جدول `hazem_devices_zatca`

يخزن معلومات الأجهزة المسجلة:

- `id` - معرف تسلسلي
- `deviceable_type` - نوع النموذج المرتبط
- `deviceable_id` - معرف النموذج المرتبط
- `request_id` - معرف الطلب من ZATCA
- `disposition_message` - رسالة الحالة
- `binary_security_token` - رمز الأمان
- `secret` - المفتاح السري
- `errors` - الأخطاء (JSON)
- `private_key` - المفتاح الخاص
- `public_key` - المفتاح العام
- `csr_content` - محتوى CSR

### جدول `hazem_orders_zatca`

يخزن معلومات الفواتير المرسلة:

- `id` - معرف تسلسلي
- `orderable_type` - نوع النموذج المرتبط
- `orderable_id` - معرف النموذج المرتبط
- `invoice_number` - رقم الفاتورة
- `uuid` - معرف فريد
- `invoice_hash` - هاش الفاتورة
- `signed_invoice_xml` - XML الموقع
- `status` - حالة الفاتورة
- `is_reported` - تم الإبلاغ
- `is_cleared` - تم المقاصة
- `warnings` - التحذيرات (JSON)
- `errors` - الأخطاء (JSON)
- `response` - الرد الكامل (JSON)
- `submitted_at` - وقت الإرسال

## الدوال المتاحة

### HasZatcaDevice Trait

- `zatcaDevice()` - علاقة مع جهاز ZATCA
- `registerZatcaDevice()` - تسجيل جهاز جديد
- `hasZatcaDevice()` - التحقق من وجود جهاز
- `getLatestZatcaDevice()` - الحصول على آخر جهاز

### HasZatcaInvoice Trait

- `zatcaOrders()` - علاقة مع فواتير ZATCA
- `latestZatcaOrder()` - آخر فاتورة
- `submitToZatca()` - إرسال فاتورة
- `isSubmittedToZatca()` - التحقق من الإرسال
- `getZatcaStatus()` - حالة الفاتورة
- `hasZatcaErrors()` - التحقق من الأخطاء
- `getZatcaErrors()` - الحصول على الأخطاء

## المساهمة

نرحب بالمساهمات! يرجى إرسال pull requests إلى مستودع GitHub.

## الترخيص

مرخص تحت MIT License.