<?php
namespace Hazem\Zatca\Enums;

enum InvoiceTypeCode: string
{
    case STANDARD_TAX_INVOICE = '388';           // Standard Tax Invoice (B2B)
    case DEBIT_NOTE = '383';                     // Tax Invoice Debit Note
    case CREDIT_NOTE = '381';                    // Tax Invoice Credit Note
    case PREPAYMENT_INVOICE = '386';             // Prepayment Invoice

}
