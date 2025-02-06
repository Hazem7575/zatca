<?php

namespace Hazem\Zatca\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Salla\ZATCA\GenerateCSR;
use Salla\ZATCA\Models\CSRRequest;

class CSRGenerator
{
    private $live;

    public function __construct()
    {
        $this->live = config('zatca.live', false);
    }

    public function generate($csrData, $companyData)
    {
        try {
            $data = CSRRequest::make()
                ->setUID($companyData['vat_no'])
                ->setSerialNumber(
                    $csrData['solution_name'] ?? 'POSIT',
                    $csrData['version'] ?? '1.0',
                    $this->generateSerialNumber()
                )
                ->setCommonName($csrData['common_name'])
                ->setCountryName('SA')
                ->setOrganizationName($companyData['company_name'])
                ->setOrganizationalUnitName($companyData['ci_no'])
                ->setRegisteredAddress($companyData['company_address'])
                ->setInvoiceType(true, true)
                ->setCurrentZatcaEnv($this->live ? 'production' : 'sandbox')
                ->setBusinessCategory($csrData['business_category'] ?? 'Contracting');

            $CSR = GenerateCSR::fromRequest($data)->initialize()->generate();

            // Get private key and CSR content
            $privateKey = $CSR->getPrivateKey();
            $csrContent = $CSR->getCsrContent();

            $baseStoragePath = public_path('certificate');
            $fileName = 'private_' . rand(100000, 99999999) . '_key.pem';
            $filePath = $baseStoragePath . DIRECTORY_SEPARATOR . $fileName;

            // إنشاء المجلد إذا لم يكن موجوداً
            if (!File::exists($baseStoragePath)) {
                File::makeDirectory($baseStoragePath, 0755, true);
            }

            // التحقق من صلاحيات الكتابة
            if (!is_writable($baseStoragePath)) {
                throw new Exception("Directory not writable: {$baseStoragePath}");
            }

            // إنشاء المفتاح الخاص
            if (!openssl_pkey_export_to_file($privateKey, $filePath, null)) {
                throw new Exception('Failed to export private key: ' . openssl_error_string());
            }

            // قراءة المفتاح الخاص
            if (!File::exists($filePath)) {
                throw new Exception("Private key file not created: {$filePath}");
            }

            $privateKeyContent = File::get($filePath);

            // الحصول على المفتاح العام
            $publicKeyDetails = openssl_pkey_get_details($privateKey);
            if ($publicKeyDetails === false) {
                throw new Exception('Failed to get public key details: ' . openssl_error_string());
            }

            // حذف الملف المؤقت
            File::delete($filePath);

            return [
                'private_key' => $privateKeyContent,
                'public_key'  => $publicKeyDetails['key'],
                'csr'        => $csrContent
            ];

        } catch (Exception $e) {
            throw new Exception("Failed to generate CSR: " . $e->getMessage());
        }
    }

    private function generateSerialNumber()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
