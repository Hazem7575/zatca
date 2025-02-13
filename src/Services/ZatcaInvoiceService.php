<?php

namespace Hazem\Zatca\Services;

use Illuminate\Support\Collection;

class ZatcaInvoiceService
{
    protected $required_fields = [
        'invoice_number',
        'total_amount',
        'vat_amount',
        'is_pos',
        'is_invoice',
        'items',
        'date'
    ];

    protected $invoice_number;
    protected $total_amount;
    protected $vat_amount;
    protected $buyer_name;
    protected $buyer_tax_number = null;
    protected $buyer_address = null;
    protected $buyer_city = null;
    protected $buyer_state = null;
    protected $buyer_postal = null;
    protected $buyer_building_no = null;
    protected $is_pos = true;
    protected $is_invoice = true;
    protected $items = [];
    protected $date;

    /**
     * Create a new invoice
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Set invoice number
     */
    public function setInvoiceNumber($number)
    {
        $this->invoice_number = $number;
        return $this;
    }

    /**
     * Set total amount
     */
    public function setTotalAmount($amount)
    {
        $this->total_amount = round($amount, 2);
        return $this;
    }

    /**
     * Set VAT amount directly
     */
    public function setVatAmount($amount)
    {
        $this->vat_amount = round($amount, 2);
        return $this;
    }

    /**
     * Set buyer name
     */
    public function setBuyerName($name)
    {
        $this->buyer_name = $name;
        return $this;
    }

    /**
     * Set buyer tax number
     */
    public function setBuyerTaxNumber($taxNumber)
    {
        $this->buyer_tax_number = $taxNumber;
        return $this;
    }

    /**
     * Set buyer address
     */
    public function setBuyerAddress($address)
    {
        $this->buyer_address = $address;
        return $this;
    }

    /**
     * Set buyer city
     */
    public function setBuyerCity($city)
    {
        $this->buyer_city = $city;
        return $this;
    }

    /**
     * Set buyer state
     */
    public function setBuyerState($state)
    {
        $this->buyer_state = $state;
        return $this;
    }

    /**
     * Set buyer postal code
     */
    public function setBuyerPostal($postal)
    {
        $this->buyer_postal = $postal;
        return $this;
    }

    /**
     * Set buyer building number
     */
    public function setBuyerBuildingNumber($buildingNo)
    {
        $this->buyer_building_no = $buildingNo;
        return $this;
    }

    /**
     * Set invoice date
     */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    /**
     * Set invoice items
     */
    public function setItems(Collection $items)
    {
        $this->items = $items->map(function ($item) {
            return [
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'price' => round($item->unit_price, 2),
                'vat' => round(round($item->unit_price, 2) * 0.15, 2)
            ];
        })->toArray();

        $this->vat_amount = collect($this->items)->sum(function ($item) {
            return round($item['vat'] * $item['quantity'], 2);
        });

        return $this;
    }

    /**
     * Add single item to invoice
     */
    public function addItem($name, $quantity, $price, $vat = null)
    {
        $vat = $vat ?? round($price * 0.15, 2);

        $this->items[] = [
            'name' => $name,
            'quantity' => $quantity,
            'price' => round($price, 2),
            'vat' => round($vat, 2)
        ];

        // Recalculate total VAT
        $this->vat_amount = collect($this->items)->sum(function ($item) {
            return round($item['vat'] * $item['quantity'], 2);
        });

        return $this;
    }

    /**
     * Set if invoice is POS
     */
    public function isPOS($value = true)
    {
        $this->is_pos = $value;
        return $this;
    }

    /**
     * Set if document is invoice
     */
    public function isInvoice($value = true)
    {
        $this->is_invoice = $value;
        return $this;
    }

    public function toArray()
    {
        $this->validateRequiredFields();
        return [
            'invoice_number' => $this->invoice_number,
            'total_amount' => $this->total_amount,
            'vat_amount' => $this->vat_amount,
            'buyer_name' => $this->buyer_name,
            'buyer_tax_number' => $this->buyer_tax_number,
            'buyer_address' => $this->buyer_address,
            'buyer_city' => $this->buyer_city,
            'buyer_state' => $this->buyer_state,
            'buyer_postal' => $this->buyer_postal,
            'buyer_building_no' => $this->buyer_building_no,
            'is_pos' => $this->is_pos,
            'is_invoice' => $this->is_invoice,
            'items' => $this->items,
            'date' => $this->date
        ];
    }

    /**
     * Validate that all required fields are present
     *
     * @throws \Exception
     */
    protected function validateRequiredFields()
    {
        $missing = [];

        foreach ($this->required_fields as $field) {
            if (empty($this->{$field})) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \Exception('Missing required ZATCA fields: ' . implode(', ', $missing));
        }

        // Validate items structure
        if (empty($this->items)) {
            throw new \Exception('Invoice must have at least one item');
        }

        foreach ($this->items as $index => $item) {
            if (empty($item['name']) || !isset($item['quantity']) || !isset($item['price']) || !isset($item['vat'])) {
                throw new \Exception("Invalid item at index {$index}. Each item must have name, quantity, price, and vat");
            }
        }
    }
}
