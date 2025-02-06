<?php

namespace Hazem\Zatca\Services;

use DOMDocument;
use Exception;

class InvoiceSigner
{
    private $privateKey;
    private $certificate;
    private $secret;

    public function __construct($privateKey, $certificate, $secret)
    {
        $this->privateKey = $privateKey;
        $this->certificate = $certificate;
        $this->secret = $secret;
    }

    public function sign($invoice)
    {
        $invoiceHash = $this->generateInvoiceHash($invoice);
        $digitalSignature = $this->createDigitalSignature($invoiceHash);
        $qr = $this->generateQR($invoice, $digitalSignature);
        
        return [
            'signed_invoice' => $this->appendSignature($invoice, $digitalSignature, $qr),
            'hash' => $invoiceHash
        ];
    }

    private function generateInvoiceHash($invoice)
    {
        $doc = new DOMDocument;
        $doc->loadXML($invoice);
        $canonicalXml = $doc->C14N();
        return base64_encode(hash('sha256', $canonicalXml, true));
    }

    private function createDigitalSignature($hash)
    {
        $hashBytes = base64_decode($hash);
        openssl_sign($hashBytes, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    private function generateQR($invoice, $signature)
    {
        // Implementation of QR code generation
        // This would include the TLV (Tag-Length-Value) encoding
        return '';
    }

    private function appendSignature($invoice, $signature, $qr)
    {
        // Implementation of XML signature appending
        return $invoice;
    }
}