<?php
namespace Hazem\Zatca\Enums;
enum InvoiceSubtype: string
{
    case STANDARD = '01';        // Standard/Tax Invoice (B2B, B2G)
    case SIMPLIFIED = '02';      // Simplified Invoice (B2C)


    public function getSubtypeName(): string
    {
        return match($this) {
            self::STANDARD => '0100000',
            self::SIMPLIFIED => '0200000'
        };
    }
}
