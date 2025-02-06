<?php

namespace Hazem\Zatca\Services;

use Hazem\Zatca\Models\ZatcaDevice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Helpers\Certificate;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;
use stdClass;


class ZatcaInvoiceSigner
{
    private ZatcaDevice $device;

    public function __construct(ZatcaDevice $device)
    {
        $this->device = $device;
    }



    /**
     * Sign invoice with ZATCA
     */
    public function signInvoice(
        string $xml,
        string $uuid,
        bool $isPos,
        float $total,
        float $tax,
        string $date
    ): array {
        try {


            $compliance = app(ComplianceService::class);
            $certificate = $this->createCertificate($this->device);

            $certInfo = $this->prepareCertificateInfo($certificate);

            // Generate compliance certificate
            $complianceCertificate = $this->generateComplianceCertificate($this->device->binary_security_token);



            // Calculate invoice hash
            $invoiceHash = $compliance->getInvoiceHash($xml);


            // Get certificate info
            $certDetails = $compliance->getCertificateInfo($complianceCertificate, $certInfo);
            $digitalSignature = $compliance->createInvoiceDigitalSignature(
                $invoiceHash,
                $this->device->private_key
            );


//            $qr = GenerateQrCode::fromArray([
//                new Seller($this->device->data['company_name']), // seller name
//                new TaxNumber($tax), // seller tax number
//                new InvoiceDate($date), // invoice date as Zulu ISO8601 @see https://en.wikipedia.org/wiki/ISO_8601
//                new InvoiceTotalAmount($total), // invoice total amount
//                new InvoiceTaxAmount($tax) // invoice tax amount
//                // .....
//            ])->toBase64();

//            // Generate QR code
            $qr = $compliance->generateQR([
                'invoice_xml' => $xml,
                'digital_signature' => $digitalSignature,
                'public_key' => $certDetails['public_key'],
                'certificate_signature' => $certificate->getCertificateSignature(),
                'company_name' => $this->device->data['company_name'],
                'vat_no' => $this->device->data['vat_no'],
                'total' => $total,
                'tax' => $tax,
                'date' => $date
            ]);
//            dd($qr);


            // Prepare signed properties
            $signedProperties = $this->prepareSignedProperties($certDetails , $certificate);

            // Generate UBL extensions
            $signedXml = $this->generateSignedXml(
                $xml,
                $invoiceHash,
                $digitalSignature,
                $certificate->getPlainCertificate(),
                $signedProperties,
                $qr
            );

            // Save XML file
            //$this->saveInvoiceXml($signedXml, $invoiceNo);

            // Send to ZATCA
            $response =  app(ZatcaAPI::class)->submitInvoice(
                $invoiceHash,
                $uuid,
                $signedXml,
                $this->device->binary_security_token,
                $this->device->secret,
                $isPos
            );


            return [
                'invoice_hash' => $invoiceHash,
                'qr_code' => $qr,
                'response' => $response,
            ];

        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to sign invoice: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Get device info from database
     */
    private function getDeviceInfo(int $branchId): stdClass
    {
        $deviceInfo = DB::table('device_infos')
            ->where('branch_id', $branchId)
            ->first();

        if (!$deviceInfo) {
            throw new \InvalidArgumentException("Device info not found for branch: {$branchId}");
        }

        return $deviceInfo;
    }

    /**
     * Create certificate instance
     */
    private function createCertificate(ZatcaDevice $deviceInfo): Certificate
    {
        return (new Certificate(
            base64_decode($deviceInfo->binary_security_token),
            $deviceInfo->private_key
        ))->setSecretKey($deviceInfo->secret);
    }

    /**
     * Prepare certificate information
     */
    private function prepareCertificateInfo(Certificate $certificate): array
    {
        $publicKey = str_replace([
            "-----BEGIN PUBLIC KEY-----\r\n",
            "\r\n-----END PUBLIC KEY-----",
            "\r\n"
        ], '', $certificate->getPublicKey()->toString('PKCS8'));

        return [
            'hash' => '',
            'issuer' => $certificate->getFormattedIssuerDN(),
            'pKey' => $publicKey,
            'signature' => $certificate->getCertificateSignature(),
            'serialNo' => $certificate->getCurrentCert()['tbsCertificate']['serialNumber']->toString(),
            'company_name' => $this->device->data['company_name'],
            'vat_no' => $this->device->data['vat_no']
        ];
    }

    /**
     * Generate compliance certificate
     */
    private function generateComplianceCertificate(string $token): string
    {
        return "-----BEGIN CERTIFICATE-----\n" .
            $token .
            "\n-----END CERTIFICATE-----";
    }

    /**
     * Prepare signed properties
     */
    private function prepareSignedProperties(array $certDetails , Certificate $certificate): array
    {

        $now = Carbon::now();
        return [
            'sign_timestamp' => $now->format('Y-m-d\TH:i:s\Z'),
            'certificate_hash' => $certificate->getHash(),
            'certificate_issuer' => $certDetails['issuer'],
            'certificate_serial_number' => $certDetails['serial_number']
        ];
    }

    /**
     * Generate final signed XML
     */
    private function generateSignedXml(
        string $xml,
        string $invoiceHash,
        string $digitalSignature,
        string $certificate,
        array $signedProperties,
        string $qr,
    ): string {
        $compliance = app(ComplianceService::class);
        // Generate signed properties XML
        $signedPropsXml = $compliance->defaultUBLExtensionsSignedProperties($signedProperties);
        $signedPropsXmlForSigning = $compliance->defaultUBLExtensionsSignedPropertiesForSigning($signedProperties);

        // Calculate signed properties hash
        $signedPropsHash = base64_encode(
            hash('sha256', $signedPropsXmlForSigning)
        );

        // Generate UBL extensions
        $ublExtensions = $compliance->defaultUBLExtensions(
            $invoiceHash,
            $signedPropsHash,
            $digitalSignature,
            $certificate,
            $signedPropsXml
        );

        // Replace placeholders
        $signedXml = str_replace(
            ['SET_UBL_EXTENSIONS_STRING', 'SET_QR_CODE_DATA'],
            [$ublExtensions, $qr],
            $xml
        );

        return $signedXml;
    }

    /**
     * Save invoice XML to file
     */
    private function saveInvoiceXml(string $xml, string $invoiceNo): void
    {
        $path = "certificate/invoice_xml_{$invoiceNo}.xml";
        if (file_put_contents($path, $xml) === false) {
            throw new \RuntimeException("Failed to save invoice XML file");
        }
    }
}
