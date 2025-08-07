<?php

namespace Hazem\Zatca\Traits;

use Hazem\Zatca\Models\ZatcaOrder;
use Hazem\Zatca\Facades\Zatca;
use Illuminate\Support\Str;

/**
 * Provides ZATCA device functionality to models.
 *
 * @property-read \Hazem\Zatca\Models\ZatcaDevice|null $zatcaDevice The associated ZATCA device
 *
 * @method \Illuminate\Database\Eloquent\Relations\MorphOne zatcaDevice() Get the model's ZATCA device relationship
 * @method \Hazem\Zatca\Models\ZatcaDevice registerZatcaDevice(string $otp, array $companyData) Register a new ZATCA device
 * @method \Hazem\Zatca\Models\ZatcaDevice active() Activate the device by sending compliance samples
 * @method bool hasZatcaDevice() Check if model has a ZATCA device
 * @method \Hazem\Zatca\Models\ZatcaDevice|null getLatestZatcaDevice() Get the latest ZATCA device
 */
trait HasZatcaInvoice
{
    /**
     * Get all ZATCA orders for this model.
     */
    public function zatcaOrders()
    {
        return $this->morphMany(ZatcaOrder::class, 'orderable');
    }

    public function zatca()
    {
        return $this->morphOne(ZatcaOrder::class, 'orderable');
    }

    /**
     * Scope query to only include models with active ZATCA devices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasZatca($query)
    {
        return $query->whereHas('zatcaDevice', function($q) {
            $q->where('status', 1);
        });
    }

    /**
     * Scope query to only include models without active ZATCA devices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDoesntHaveZatca($query)
    {
        return $query->whereDoesntHave('zatcaDevice', function($q) {
            $q->where('status', 1);
        });
    }
    /**
     * Get the latest ZATCA order for this model.
     */
    public function latestZatcaOrder()
    {
        return $this->zatcaOrders()->latest()->first();
    }

    /**
     * Prepare invoice data for ZATCA submission.
     * Override this method to customize the invoice data structure.
     */
    protected function prepareZatcaInvoiceData()
    {
        return [
            'invoice_number' => $this->invoice_number ?? $this->number ?? $this->id,
            'buyer_name' => $this->customer_name ?? $this->buyer_name ?? '',
            'buyer_vat' => null,
            'buyer_address' => null,
            'buyer_city' => null,
            'buyer_state' => null,
            'buyer_postal' => null,
            'buyer_building_no' => null,
            'total_amount' => $this->total ?? $this->amount ?? 0,
            'vat_amount' => $this->vat ?? $this->tax ?? 0,
            'is_pos' => $this->is_pos ?? false,
            'is_invoice' => $this->is_invoice ?? true,
            'is_refund' => $this->is_refund ?? false,
            'items' => $this->prepareZatcaItems()
        ];
    }

    /**
     * Prepare items data for ZATCA submission.
     * Override this method to customize the items structure.
     */
    protected function prepareZatcaItems()
    {
        // Try to get items from common relationships/attributes
        $items = $this->items ?? $this->order_items ?? $this->invoice_items ?? [];

        return collect($items)->map(function($item) {
            return [
                'name' => $item->name ?? $item->product_name ?? '',
                'quantity' => $item->quantity ?? $item->qty ?? 1,
                'price' => $item->price ?? $item->unit_price ?? 0,
                'vat' => $item->vat ?? $item->tax ?? 0
            ];
        })->toArray();
    }

    /**
     * Submit invoice to ZATCA.
     * @param array $customData Optional custom data to override default values
     */


    public function submitToZatca(array $customData = [])
    {

        if(!$this->device()->hasZatcaDevice()) {
            return false;
        }


        $uuid = Str::uuid()->toString();
        $invoiceData = array_merge($this->prepareZatcaInvoiceData(), $customData);


        try {
            $device = $this->device()->getLatestZatcaDevice();
            if (!$device) {
                throw new \Exception('No ZATCA device registered for this model');
            }


            $result = Zatca::submitInvoice(
                $device,
                array_merge($invoiceData, ['uuid' => $uuid ]),
                $this->last_hash()
            );
            dd($result);

            if(!$invoiceData['is_pos']) {
                $order = $this->zatcaOrders()->updateOrCreate([
                    'orderable_type' => self::class,
                    'orderable_id' => $this->id,
                ],[
                    'invoice_number' => $invoiceData['invoice_number'],
                    'uuid' => $uuid,
                    'status' => 1,
                    'is_reported' => 1,
                    'qr_code' => $result['qr_code'],
                    'invoice_hash' => $result['invoice_hash'],
                    'response' => [],
                    'submitted_at' => now(),
                    'is_cleared' => true,
                ]);

                if(method_exists($this, 'update_last_hash')) {
                    $this->update_last_hash($result['invoice_hash']);
                }

                return $order;
            }

            if(isset($result['response']['reportingStatus']) AND $result['response']['reportingStatus'] === 'REPORTED') {
                $order = $this->zatcaOrders()->updateOrCreate([
                    'orderable_type' => self::class,
                    'orderable_id' => $this->id,
                ],[
                    'invoice_number' => $invoiceData['invoice_number'],
                    'uuid' => $uuid,
                    'status' => 1,
                    'is_reported' => 1,
                    'qr_code' => $result['qr_code'],
                    'invoice_hash' => $result['invoice_hash'],
                    'response' => $result['response'],
                    'submitted_at' => now(),
                ]);

                if(method_exists($this, 'update_last_hash')) {
                    $this->update_last_hash($result['invoice_hash']);
                }

                return $order;
            }else{
                throw new \Exception('فشل ارسال الفاتورة للهئية');
            }

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Check if invoice was submitted to ZATCA.
     */
    public function isSubmittedToZatca()
    {
        return $this->zatcaOrders()->where('status', '!=', 'failed')->exists();
    }

    /**
     * Get submission status for invoice.
     */
    public function getZatcaStatus()
    {
        $order = $this->latestZatcaOrder();
        return $order ? $order->status : null;
    }

    /**
     * Check if invoice has ZATCA submission errors.
     */
    public function hasZatcaErrors()
    {
        $order = $this->latestZatcaOrder();
        return $order ? $order->hasErrors() : false;
    }

    /**
     * Get ZATCA submission errors.
     */
    public function getZatcaErrors()
    {
        $order = $this->latestZatcaOrder();
        return $order ? $order->errors : null;
    }
}
