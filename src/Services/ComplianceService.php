<?php

namespace Hazem\Zatca\Services;

use DOMDocument;
use Hazem\Zatca\Models\ZatcaDevice;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Helpers\Certificate;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;

class ComplianceService
{
    private $zatcaAPI;
    private $device;
    private $live;
    private $companyData = [];

    public function __construct(ZatcaAPI $zatcaAPI)
    {
        $this->zatcaAPI = $zatcaAPI;
        $this->device = config('zatca.company_data', []);
        $this->live = config('zatca.live', false);
    }

    /**
     * Send compliance samples for device registration
     *
     * @param ZatcaDevice $device
     * @return array|bool
     */
    public function activate(ZatcaDevice $device)
    {
        if (empty($device->binary_security_token) || empty($device->secret)) {
            throw new Exception('Device is not in active state');
        }

        $this->SendComplianceSamples($device);
    }
    public function SendComplianceSamples(ZatcaDevice $device)
    {
        $this->device = $device;
        $this->sample_pos();
        $this->sample_a4();
        $this->sample_pos_credit();
        $this->sample_a4_credit();
        $this->sample_pos_debit();
        $this->sample_a4_debit();
        return $this->generateCSID();
    }


    public function sample_pos(){

        $invoice = (object)[
            'invoice_no' => '0001',
            'total'=> 10,
            'grand_total'=> 11.5,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> 1.5,
            'is_pos' => true,
            'is_invoice'=>true,
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => 'Mahmoud',
                'billing_address'=> 'Alriad',
                'email' => 'm@g.com',
                'tax_number' => '300000000000003',
                'billing_country' => 'SA',
                'billing_city' => 'Alriad',
                'billing_state' => 'Alriad',
                'billing_phone' => '5123456',
                'billing_postal' => '00000',
                'billing_building' => '0000'
            ]
        ];

        $invoiceItems[] = (object)[
            'product_id' => 100,
            'product_name' => 'Item A',
            'unit_quantity' => 1,
            'net_unit_price' => 10,
            'discount' => 0,
            'item_tax' => 1.5,
            'subtotal' => 10,
            'city_tax' => 0
        ];

        $customer = $invoice->customer;
        $customer_name=empty($customer->billing_name)? $customer->name : $customer->billing_name ;
        $customer_address=$customer->billing_address;
        $customer_email = $customer->email;
        $customer_tax_number = $customer->tax_number;
        $customer_country = $customer->billing_country;
        $customer_city = $customer->billing_city;
        $customer_state = $customer->billing_state;
        $customer_phone = $customer->billing_phone;
        $customer_postal = $customer->billing_postal;
        $customer_building = $customer->billing_building;

        $id = $invoice->invoice_no;
        $vatNo = $this->device->data['vat_no'];
        $address = $this->device->data['company_address'];
        $building = $this->device->data['company_building'];
        $plot_identification = $this->device->data['company_plot_identification'];
        $city_subdivision = $this->device->data['company_city_subdivision'];
        $city = $this->device->data['company_city'];
        $postal = $this->device->data['company_postal'];
        $companyName = $this->device->data['company_name'];
        $invoiceType = $invoice->is_pos ? '0211010' : '0111010';
        $invoiceTypeNo = $invoice->is_invoice ? '388' : '381';

        $customer_vatNo = $customer_tax_number;
        $building_customer = $customer_building;
        $plot_identification_customer = $customer_address;
        $city_subdivision_customer = '0000';
        $city_customer = $customer_city;
        $postal_customer = $customer_postal;


        $sale = (object)[
            'id' => $invoice->invoice_no,
            'total'=> $invoice->total,
            'grand_total'=> $invoice->grand_total,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> $invoice->product_tax,
            'date' => date('Y-m-d').'T'.date('H:i:s')
        ];

        $details = $invoiceItems;
        $UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $props = [
            'invoice_serial_number' => $id,
            'uuid' => $UUID,//'3cf5ee18-ee25-44ea-a444-2c37ba7f28be',// vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'CRN_number' => $vatNo,
            'street' => $address,
            'building' => $building,
            'plot_identification' => $plot_identification,
            'city_subdivision' => $city_subdivision,
            'city' => $city,
            'postal' => $postal,
            'VAT_number' => $vatNo,
            'VAT_name' => $companyName,
            'details' => $details,
            'sale' => $sale,
            'invoice_type' => $invoiceType,
            'invoice_type_no' => $invoiceTypeNo,

            'CRN_number_CUSTOMER' => $customer_vatNo,
            'street_CUSTOMER' => $address,
            'building_CUSTOMER' => $building_customer,
            'plot_identification_CUSTOMER' => $plot_identification_customer,
            'city_subdivision_CUSTOMER' => $city_subdivision_customer,
            'city_CUSTOMER' => $city_customer,
            'postal_CUSTOMER' => $postal_customer,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];

        $xmlInvoice = $this->getDefaultSimplifiedTaxInvoice($props);

        $private_key = $this->device->private_key;

        $token = $this->device->binary_security_token;

        $secret = $this->device->secret;


        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($token),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);


        $data = $this->signSingleInvoice($xmlInvoice,$sale->total,$sale->product_tax,$sale->date);

        //$invoice = (new InvoiceSign($xmlInvoice, $certificate))->sign();

        $invoiceHash = $data['invoice_hash'];

        $invoiceSignedXML = ($data['final']);
        //dd($invoiceSignedXML);
        //save to file
        $directory = public_path('certificate');
        $filename = 'invoice_pos_' . $id . '.xml';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        // إنشاء المجلد إذا لم يكن موجوداً
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // التحقق من صلاحيات الكتابة
        if (!is_writable($directory)) {
            throw new Exception("Directory not writable: {$directory}");
        }

        // حفظ الملف
        if (file_put_contents($fullPath, $invoiceSignedXML) === false) {
            throw new Exception("Failed to write file: {$fullPath}");
        }


        $token = $this->device->binary_security_token;

        $secret = $this->device->secret;

        $body = [
            "invoiceHash" => $invoiceHash,
            "uuid" => $UUID,
            "invoice" => base64_encode($invoiceSignedXML)
        ];


        $body = json_encode($body);

        $headers = [
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Accept: application/json',
            'accept-language: en',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ];

        $ch = curl_init();

        $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        if($this->live){
            $url = str_replace('developer-portal','core',$url);
        }

        curl_setopt($ch, CURLOPT_URL,$url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($ch, CURLOPT_USERPWD, "$token:$secret");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result=curl_exec ($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code

        //dd($result , $status_code);
        //log_message('error',$status_code);
        if (curl_errno($ch)) {
            $result = curl_error($ch);
        }
        curl_close ($ch);

        $filename = 'compliance_invoice_pos_'.$id.'.json';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        // إنشاء المجلد إذا لم يكن موجوداً
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // التحقق من صلاحيات الكتابة
        if (!is_writable($directory)) {
            throw new Exception("Directory not writable: {$directory}");
        }

        // حفظ الملف
        if (file_put_contents($fullPath, $result) === false) {
            throw new Exception("Failed to write file: {$fullPath}");
        }



    }

    public  function sample_a4(){
        $invoice = (object)[
            'invoice_no' => '0001',
            'total'=> 10,
            'grand_total'=> 11.5,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> 1.5,
            'is_pos' => false,
            'is_invoice'=>true,
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => 'Mahmoud',
                'billing_address'=> 'Alriad',
                'email' => 'm@g.com',
                'tax_number' => '300000000000003',
                'billing_country' => 'SA',
                'billing_city' => 'Alriad',
                'billing_state' => 'Alriad',
                'billing_phone' => '5123456',
                'billing_postal' => '00000',
                'billing_building' => '0000'
            ]
        ];

        $invoiceItems[] = (object)[
            'product_id' => 100,
            'product_name' => 'Item A',
            'unit_quantity' => 1,
            'net_unit_price' => 10,
            'discount' => 0,
            'item_tax' => 1.5,
            'subtotal' => 10,
            'city_tax' => 0
        ];

        $customer = $invoice->customer;
        $customer_name=empty($customer->billing_name)? $customer->name : $customer->billing_name ;
        $customer_address=$customer->billing_address;
        $customer_email = $customer->email;
        $customer_tax_number = $customer->tax_number;
        $customer_country = $customer->billing_country;
        $customer_city = $customer->billing_city;
        $customer_state = $customer->billing_state;
        $customer_phone = $customer->billing_phone;
        $customer_postal = $customer->billing_postal;
        $customer_building = $customer->billing_building;

        $id = $invoice->invoice_no;
        $vatNo = $this->device->data['vat_no'];
        $address = $this->device->data['company_address'];
        $building = $this->device->data['company_building'];
        $plot_identification = $this->device->data['company_plot_identification'];
        $city_subdivision = $this->device->data['company_city_subdivision'];
        $city = $this->device->data['company_city'];
        $postal = $this->device->data['company_postal'];
        $companyName = $this->device->data['company_name'];
        $invoiceType = $invoice->is_pos ? '0211010' : '0111010';
        $invoiceTypeNo = $invoice->is_invoice ? '388' : '383';

        $customer_vatNo = $customer_tax_number;
        $building_customer = $customer_building;
        $plot_identification_customer = $customer_address;
        $city_subdivision_customer = '0000';
        $city_customer = $customer_city;
        $postal_customer = $customer_postal;


        $sale = (object)[
            'id' => $invoice->invoice_no,
            'total'=> $invoice->total,
            'grand_total'=> $invoice->grand_total,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> $invoice->product_tax,
            'date' => date('Y-m-d').'T'.date('H:i:s')
        ];

        $details = $invoiceItems;
        $UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $props = [
            'invoice_serial_number' => $id,
            'uuid' => $UUID,//'3cf5ee18-ee25-44ea-a444-2c37ba7f28be',// vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'CRN_number' => $vatNo,
            'street' => $address,
            'building' => $building,
            'plot_identification' => $plot_identification,
            'city_subdivision' => $city_subdivision,
            'city' => $city,
            'postal' => $postal,
            'VAT_number' => $vatNo,
            'VAT_name' => $companyName,
            'details' => $details,
            'sale' => $sale,
            'invoice_type' => $invoiceType,
            'invoice_type_no' => $invoiceTypeNo,

            'CRN_number_CUSTOMER' => $customer_vatNo,
            'street_CUSTOMER' => $address,
            'building_CUSTOMER' => $building_customer,
            'plot_identification_CUSTOMER' => $plot_identification_customer,
            'city_subdivision_CUSTOMER' => $city_subdivision_customer,
            'city_CUSTOMER' => $city_customer,
            'postal_CUSTOMER' => $postal_customer,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];

        $xmlInvoice = $this->getDefaultSimplifiedTaxInvoice($props);


        $private_key = $this->device->private_key;

        $token = $this->device->binary_security_token;

        $secret = $this->device->secret;

        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($token),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);


        $data = $this->signSingleInvoice($xmlInvoice,$sale->total,$sale->product_tax,$sale->date);

        //$invoice = (new InvoiceSign($xmlInvoice, $certificate))->sign();

        $invoiceHash = $data['invoice_hash'];

        $invoiceSignedXML = ($data['final']);

        $directory = public_path('certificate');
        $filename = 'invoice_a4_'.$id.'.xml';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        // إنشاء المجلد إذا لم يكن موجوداً
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // التحقق من صلاحيات الكتابة
        if (!is_writable($directory)) {
            throw new Exception("Directory not writable: {$directory}");
        }

        // حفظ الملف
        if (file_put_contents($fullPath, $invoiceSignedXML) === false) {
            throw new Exception("Failed to write file: {$fullPath}");
        }



        $body = [
            "invoiceHash" => $invoiceHash,
            "uuid" => $UUID,
            "invoice" => base64_encode($invoiceSignedXML)
        ];


        $body = json_encode($body);

        $headers = [
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Accept: application/json',
            'accept-language: en',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ];

        $ch = curl_init();

        $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        if($this->live){
            $url = str_replace('developer-portal','core',$url);
        }

        curl_setopt($ch, CURLOPT_URL,$url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($ch, CURLOPT_USERPWD, "$token:$secret");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result=curl_exec ($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        //log_message('error',$status_code);
        if (curl_errno($ch)) {
            $result = curl_error($ch);
        }
        curl_close ($ch);

        //  file_put_contents('certificate/compliance_invoice_a4_'.$id.'.json', $result);


    }
    function signSingleInvoice($invoiceXML,$total,$tax,$date){

        $private_key = $this->device->private_key;
        $binarySecurityToken = $this->device->binary_security_token;
        $secret = $this->device->secret;
        $token = base64_decode($binarySecurityToken);



        $compliance_certificate = '-----BEGIN CERTIFICATE-----
        '.$token.'
        -----END CERTIFICATE-----';

        $invoice_hash = $this->getInvoiceHash($invoiceXML);

        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($binarySecurityToken),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);

        $public_key = str_replace([
            "-----BEGIN PUBLIC KEY-----\r\n",
            "\r\n-----END PUBLIC KEY-----", "\r\n"
        ], '', $certificate->getPublicKey()->toString('PKCS8'));

        $certificate_signature = $certificate->getCertificateSignature();

        $certInfo = [
            'hash' => '',
            'issuer' => $certificate->getFormattedIssuerDN(),
            'pKey' => $public_key,
            'signature' => $certificate_signature,
            'serialNo' => $certificate->getCurrentCert()['tbsCertificate']['serialNumber']->toString(),
            'company_name' => $this->device->data['company_name'],
            'vat_no' => $this->device->data['vat_no']
        ];
        $cert_info = $this->getCertificateInfo($compliance_certificate,$certInfo);

        $digital_signature = $this->createInvoiceDigitalSignature($invoice_hash, $private_key);

        $qr = $this->generateQR([
            'invoice_xml' => $invoiceXML,
            'digital_signature' => $digital_signature,
            'public_key'=> $cert_info['public_key'],
            'certificate_signature'=> $certificate_signature,
            'company_name' => $certInfo['company_name'],
            'vat_no' => $certInfo['vat_no'],
            'total' => $total,
            'tax' => $tax,
            'date' => $date
        ]);

        $signed_properties_props = [
            'sign_timestamp' => date('Y-m-d').'T'.date('H:i:s').'Z',
            'certificate_hash'=> $cert_info['hash'],
            'certificate_issuer'=> $cert_info['issuer'],
            'certificate_serial_number'=>$cert_info['serial_number']
        ];

        $ubl_signature_signed_properties_xml_string_for_signing = $this->defaultUBLExtensionsSignedPropertiesForSigning($signed_properties_props);
        $ubl_signature_signed_properties_xml_string = $this->defaultUBLExtensionsSignedProperties($signed_properties_props);


        // 5: Get SignedProperties hash
        $signed_properties_hash = base64_encode(hash('sha256', $ubl_signature_signed_properties_xml_string_for_signing));


        // UBL Extensions
        $ubl_signature_xml_string = $this->defaultUBLExtensions(
            $invoice_hash,
            $signed_properties_hash,
            $digital_signature,
            $this->cleanUpCertificateString($compliance_certificate),
            $ubl_signature_signed_properties_xml_string
        );

        $filanl = str_replace('SET_UBL_EXTENSIONS_STRING',$ubl_signature_xml_string,$invoiceXML);
        $filanl = str_replace('SET_QR_CODE_DATA',$qr,$filanl);

        $data = [
            'final' =>$filanl,
            'invoice_hash' => $invoice_hash
        ];
        return $data;

    }
    function defaultUBLExtensionsSignedPropertiesForSigning($signed_properties_props){

        $populated_template = $data = '<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">'."\n" .
            '                                    <xades:SignedSignatureProperties>'."\n" .
            '                                        <xades:SigningTime>SET_SIGN_TIMESTAMP</xades:SigningTime>'."\n" .
            '                                        <xades:SigningCertificate>'."\n" .
            '                                            <xades:Cert>'."\n" .
            '                                                <xades:CertDigest>'."\n" .
            '                                                    <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'."\n" .
            '                                                    <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">SET_CERTIFICATE_HASH</ds:DigestValue>'."\n" .
            '                                                </xades:CertDigest>'."\n" .
            '                                                <xades:IssuerSerial>'."\n" .
            '                                                    <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">SET_CERTIFICATE_ISSUER</ds:X509IssuerName>'."\n" .
            '                                                    <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">SET_CERTIFICATE_SERIAL_NUMBER</ds:X509SerialNumber>'."\n" .
            '                                                </xades:IssuerSerial>'."\n" .
            '                                            </xades:Cert>'."\n" .
            '                                        </xades:SigningCertificate>'."\n" .
            '                                    </xades:SignedSignatureProperties>'."\n" .
            '                                </xades:SignedProperties>';


        $populated_template = str_replace("SET_SIGN_TIMESTAMP", $signed_properties_props['sign_timestamp'],$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE_HASH", $signed_properties_props['certificate_hash'],$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE_ISSUER", $signed_properties_props['certificate_issuer'],$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE_SERIAL_NUMBER", $signed_properties_props['certificate_serial_number'],$populated_template);
        return $populated_template;
    }
    function defaultUBLExtensions($invoice_hash,$signed_properties_hash,$digital_signature,$certificate_string,$signed_properties_xml){

        $populated_template = /* XML */'
    <ext:UBLExtension>
        <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
        <ext:ExtensionContent>
            <sig:UBLDocumentSignatures
                    xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2"
                    xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2"
                    xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2">
                <sac:SignatureInformation>
                    <cbc:ID>urn:oasis:names:specification:ubl:signature:1</cbc:ID>
                    <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>
                    <ds:Signature Id="signature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                        <ds:SignedInfo>
                            <ds:CanonicalizationMethod
                                    Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                            <ds:SignatureMethod
                                    Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>
                            <ds:Reference Id="invoiceSignedData" URI="">
                                <ds:Transforms>
                                    <ds:Transform
                                            Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform
                                            Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::cac:Signature)</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform
                                            Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID="QR"])</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform
                                            Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                </ds:Transforms>
                                <ds:DigestMethod
                                        Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue>SET_INVOICE_HASH</ds:DigestValue>
                            </ds:Reference>
                            <ds:Reference
                                    Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties"
                                    URI="#xadesSignedProperties">
                                <ds:DigestMethod
                                        Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue>SET_SIGNED_PROPERTIES_HASH</ds:DigestValue>
                            </ds:Reference>
                        </ds:SignedInfo>
                        <ds:SignatureValue>SET_DIGITAL_SIGNATURE</ds:SignatureValue>
                        <ds:KeyInfo>
                            <ds:X509Data>
                                <ds:X509Certificate>SET_CERTIFICATE</ds:X509Certificate>
                            </ds:X509Data>
                        </ds:KeyInfo>
                        <ds:Object>
                            <xades:QualifyingProperties Target="signature"
                                                        xmlns:xades="http://uri.etsi.org/01903/v1.3.2#">
                                SET_SIGNED_PROPERTIES_XML
                            </xades:QualifyingProperties>
                        </ds:Object>
                    </ds:Signature>
                </sac:SignatureInformation>
            </sig:UBLDocumentSignatures>
        </ext:ExtensionContent>
    </ext:UBLExtension>';
        $populated_template = str_replace("SET_INVOICE_HASH", $invoice_hash,$populated_template);
        $populated_template = str_replace("SET_SIGNED_PROPERTIES_HASH", $signed_properties_hash,$populated_template);
        $populated_template = str_replace("SET_DIGITAL_SIGNATURE", $digital_signature,$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE", $certificate_string,$populated_template);
        $populated_template = str_replace("SET_SIGNED_PROPERTIES_XML", $signed_properties_xml,$populated_template);
        return $populated_template;
    }

    function defaultUBLExtensionsSignedProperties($signed_properties_props){
        $populated_template = '<xades:SignedProperties Id="xadesSignedProperties">'."\n".
            '                                    <xades:SignedSignatureProperties>'."\n".
            '                                        <xades:SigningTime>SET_SIGN_TIMESTAMP</xades:SigningTime>'."\n".
            '                                        <xades:SigningCertificate>'."\n".
            '                                            <xades:Cert>'."\n".
            '                                                <xades:CertDigest>'."\n".
            '                                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'."\n".
            '                                                    <ds:DigestValue>SET_CERTIFICATE_HASH</ds:DigestValue>'."\n".
            '                                                </xades:CertDigest>'."\n".
            '                                                <xades:IssuerSerial>'."\n".
            '                                                    <ds:X509IssuerName>SET_CERTIFICATE_ISSUER</ds:X509IssuerName>'."\n".
            '                                                    <ds:X509SerialNumber>SET_CERTIFICATE_SERIAL_NUMBER</ds:X509SerialNumber>'."\n".
            '                                                </xades:IssuerSerial>'."\n".
            '                                            </xades:Cert>'."\n".
            '                                        </xades:SigningCertificate>'."\n".
            '                                    </xades:SignedSignatureProperties>'."\n".
            '                                </xades:SignedProperties>';


        $populated_template = str_replace("SET_SIGN_TIMESTAMP", $signed_properties_props['sign_timestamp'],$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE_HASH", $signed_properties_props['certificate_hash'],$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE_ISSUER", $signed_properties_props['certificate_issuer'],$populated_template);
        $populated_template = str_replace("SET_CERTIFICATE_SERIAL_NUMBER", $signed_properties_props['certificate_serial_number'],$populated_template);
        return $populated_template;
    }

    public function sample_pos_credit(){
        $invoice = (object)[
            'invoice_no' => '0001',
            'total'=> 10,
            'grand_total'=> 11.5,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> 1.5,
            'is_pos' => true,
            'is_invoice'=>false,
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => 'Mahmoud',
                'billing_address'=> 'Alriad',
                'email' => 'm@g.com',
                'tax_number' => '300000000000003',
                'billing_country' => 'SA',
                'billing_city' => 'Alriad',
                'billing_state' => 'Alriad',
                'billing_phone' => '5123456',
                'billing_postal' => '00000',
                'billing_building' => '0000'
            ]
        ];

        $invoiceItems[] = (object)[
            'product_id' => 100,
            'product_name' => 'Item A',
            'unit_quantity' => 1,
            'net_unit_price' => 10,
            'discount' => 0,
            'item_tax' => 1.5,
            'subtotal' => 10,
            'city_tax' => 0
        ];

        $customer = $invoice->customer;
        $customer_name=empty($customer->billing_name)? $customer->name : $customer->billing_name ;
        $customer_address=$customer->billing_address;
        $customer_email = $customer->email;
        $customer_tax_number = $customer->tax_number;
        $customer_country = $customer->billing_country;
        $customer_city = $customer->billing_city;
        $customer_state = $customer->billing_state;
        $customer_phone = $customer->billing_phone;
        $customer_postal = $customer->billing_postal;
        $customer_building = $customer->billing_building;

        $id = $invoice->invoice_no;
        $vatNo = $this->device->data['vat_no'];
        $address = $this->device->data['company_address'];
        $building = $this->device->data['company_building'];
        $plot_identification = $this->device->data['company_plot_identification'];
        $city_subdivision = $this->device->data['company_city_subdivision'];
        $city = $this->device->data['company_city'];
        $postal = $this->device->data['company_postal'];
        $companyName = $this->device->data['company_name'];
        $invoiceType = $invoice->is_pos ? '0211010' : '0111010';
        $invoiceTypeNo = $invoice->is_invoice ? '388' : '381';

        $customer_vatNo = $customer_tax_number;
        $building_customer = $customer_building;
        $plot_identification_customer = $customer_address;
        $city_subdivision_customer = '0000';
        $city_customer = $customer_city;
        $postal_customer = $customer_postal;


        $sale = (object)[
            'id' => $invoice->invoice_no,
            'total'=> $invoice->total,
            'grand_total'=> $invoice->grand_total,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> $invoice->product_tax,
            'date' => date('Y-m-d').'T'.date('H:i:s')
        ];

        $details = $invoiceItems;
        $UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $props = [
            'invoice_serial_number' => $id,
            'uuid' => $UUID,//'3cf5ee18-ee25-44ea-a444-2c37ba7f28be',// vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'CRN_number' => $vatNo,
            'street' => $address,
            'building' => $building,
            'plot_identification' => $plot_identification,
            'city_subdivision' => $city_subdivision,
            'city' => $city,
            'postal' => $postal,
            'VAT_number' => $vatNo,
            'VAT_name' => $companyName,
            'details' => $details,
            'sale' => $sale,
            'invoice_type' => $invoiceType,
            'invoice_type_no' => $invoiceTypeNo,

            'CRN_number_CUSTOMER' => $customer_vatNo,
            'street_CUSTOMER' => $address,
            'building_CUSTOMER' => $building_customer,
            'plot_identification_CUSTOMER' => $plot_identification_customer,
            'city_subdivision_CUSTOMER' => $city_subdivision_customer,
            'city_CUSTOMER' => $city_customer,
            'postal_CUSTOMER' => $postal_customer,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];

        $xmlInvoice = $this->getDefaultSimplifiedTaxInvoice($props);

        $private_key = $this->device->private_key;
        $token = $this->device->binary_security_token;
        $secret = $this->device->secret;


        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($token),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);


        $data = $this->signSingleInvoice($xmlInvoice,$sale->total,$sale->product_tax,$sale->date);

        //$invoice = (new InvoiceSign($xmlInvoice, $certificate))->sign();

        $invoiceHash = $data['invoice_hash'];

        $invoiceSignedXML = ($data['final']);


        $binarySecurityToken = $this->device->binary_security_token;
        $secret = $this->device->secret;



        $body = [
            "invoiceHash" => $invoiceHash,
            "uuid" => $UUID,
            "invoice" => base64_encode($invoiceSignedXML)
        ];


        $body = json_encode($body);

        $headers = [
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Accept: application/json',
            'accept-language: en',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ];

        $ch = curl_init();

        $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        if($this->live){
            $url = str_replace('developer-portal','core',$url);
        }else{
            $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        }

        curl_setopt($ch, CURLOPT_URL,$url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($ch, CURLOPT_USERPWD, "$token:$secret");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result=curl_exec ($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        //log_message('error',$status_code);
        if (curl_errno($ch)) {
            $result = curl_error($ch);
        }
        curl_close ($ch);

        // file_put_contents('certificate/compliance_invoice_pos_credit_'.$id.'.json', $result);


    }

    public function sample_a4_credit(){
        $invoice = (object)[
            'invoice_no' => '0001',
            'total'=> 10,
            'grand_total'=> 11.5,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> 1.5,
            'is_pos' => false,
            'is_invoice'=>false,
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => 'Mahmoud',
                'billing_address'=> 'Alriad',
                'email' => 'm@g.com',
                'tax_number' => '300000000000003',
                'billing_country' => 'SA',
                'billing_city' => 'Alriad',
                'billing_state' => 'Alriad',
                'billing_phone' => '5123456',
                'billing_postal' => '00000',
                'billing_building' => '0000'
            ]
        ];

        $invoiceItems[] = (object)[
            'product_id' => 100,
            'product_name' => 'Item A',
            'unit_quantity' => 1,
            'net_unit_price' => 10,
            'discount' => 0,
            'item_tax' => 1.5,
            'subtotal' => 10,
            'city_tax' => 0
        ];

        $customer = $invoice->customer;
        $customer_name=empty($customer->billing_name)? $customer->name : $customer->billing_name ;
        $customer_address=$customer->billing_address;
        $customer_email = $customer->email;
        $customer_tax_number = $customer->tax_number;
        $customer_country = $customer->billing_country;
        $customer_city = $customer->billing_city;
        $customer_state = $customer->billing_state;
        $customer_phone = $customer->billing_phone;
        $customer_postal = $customer->billing_postal;
        $customer_building = $customer->billing_building;

        $id = $invoice->invoice_no;
        $vatNo = $this->device->data['vat_no'];
        $address = $this->device->data['company_address'];
        $building = $this->device->data['company_building'];
        $plot_identification = $this->device->data['company_plot_identification'];
        $city_subdivision = $this->device->data['company_city_subdivision'];
        $city = $this->device->data['company_city'];
        $postal = $this->device->data['company_postal'];
        $companyName = $this->device->data['company_name'];
        $invoiceType = $invoice->is_pos ? '0211010' : '0111010';
        $invoiceTypeNo = $invoice->is_invoice ? '388' : '381';

        $customer_vatNo = $customer_tax_number;
        $building_customer = $customer_building;
        $plot_identification_customer = $customer_address;
        $city_subdivision_customer = '0000';
        $city_customer = $customer_city;
        $postal_customer = $customer_postal;


        $sale = (object)[
            'id' => $invoice->invoice_no,
            'total'=> $invoice->total,
            'grand_total'=> $invoice->grand_total,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> $invoice->product_tax,
            'date' => date('Y-m-d').'T'.date('H:i:s')
        ];

        $details = $invoiceItems;
        $UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $props = [
            'invoice_serial_number' => $id,
            'uuid' => $UUID,//'3cf5ee18-ee25-44ea-a444-2c37ba7f28be',// vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'CRN_number' => $vatNo,
            'street' => $address,
            'building' => $building,
            'plot_identification' => $plot_identification,
            'city_subdivision' => $city_subdivision,
            'city' => $city,
            'postal' => $postal,
            'VAT_number' => $vatNo,
            'VAT_name' => $companyName,
            'details' => $details,
            'sale' => $sale,
            'invoice_type' => $invoiceType,
            'invoice_type_no' => $invoiceTypeNo,

            'CRN_number_CUSTOMER' => $customer_vatNo,
            'street_CUSTOMER' => $address,
            'building_CUSTOMER' => $building_customer,
            'plot_identification_CUSTOMER' => $plot_identification_customer,
            'city_subdivision_CUSTOMER' => $city_subdivision_customer,
            'city_CUSTOMER' => $city_customer,
            'postal_CUSTOMER' => $postal_customer,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];

        $xmlInvoice = $this->getDefaultSimplifiedTaxInvoice($props);

        $private_key = $this->device->private_key;
        $token = $this->device->binary_security_token;
        $secret = $this->device->secret;



        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($token),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);

        $data = $this->signSingleInvoice($xmlInvoice,$sale->total,$sale->product_tax,$sale->date);

        //$invoice = (new InvoiceSign($xmlInvoice, $certificate))->sign();

        $invoiceHash = $data['invoice_hash'];

        $invoiceSignedXML = ($data['final']);



        $body = [
            "invoiceHash" => $invoiceHash,
            "uuid" => $UUID,
            "invoice" => base64_encode($invoiceSignedXML)
        ];


        $body = json_encode($body);

        $headers = [
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Accept: application/json',
            'accept-language: en',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ];

        $ch = curl_init();

        $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        if($this->live){
            $url = str_replace('developer-portal','core',$url);
        }

        curl_setopt($ch, CURLOPT_URL,$url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($ch, CURLOPT_USERPWD, "$token:$secret");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result=curl_exec ($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        //log_message('error',$status_code);
        if (curl_errno($ch)) {
            $result = curl_error($ch);
        }
        curl_close ($ch);

        // file_put_contents('certificate/compliance_invoice_a4_credit_'.$id.'.json', $result);


    }

    public function sample_pos_debit(){
        $invoice = (object)[
            'invoice_no' => '0001',
            'total'=> 10,
            'grand_total'=> 11.5,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> 1.5,
            'is_pos' => true,
            'is_invoice'=>true,
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => 'Mahmoud',
                'billing_address'=> 'Alriad',
                'email' => 'm@g.com',
                'tax_number' => '300000000000003',
                'billing_country' => 'SA',
                'billing_city' => 'Alriad',
                'billing_state' => 'Alriad',
                'billing_phone' => '5123456',
                'billing_postal' => '00000',
                'billing_building' => '0000'
            ]
        ];

        $invoiceItems[] = (object)[
            'product_id' => 100,
            'product_name' => 'Item A',
            'unit_quantity' => 1,
            'net_unit_price' => 10,
            'discount' => 0,
            'item_tax' => 1.5,
            'subtotal' => 10,
            'city_tax' => 0
        ];

        $customer = $invoice->customer;
        $customer_name=empty($customer->billing_name)? $customer->name : $customer->billing_name ;
        $customer_address=$customer->billing_address;
        $customer_email = $customer->email;
        $customer_tax_number = $customer->tax_number;
        $customer_country = $customer->billing_country;
        $customer_city = $customer->billing_city;
        $customer_state = $customer->billing_state;
        $customer_phone = $customer->billing_phone;
        $customer_postal = $customer->billing_postal;
        $customer_building = $customer->billing_building;

        $id = $invoice->invoice_no;
        $vatNo = $this->device->data['vat_no'];
        $address = $this->device->data['company_address'];
        $building = $this->device->data['company_building'];
        $plot_identification = $this->device->data['company_plot_identification'];
        $city_subdivision = $this->device->data['company_city_subdivision'];
        $city = $this->device->data['company_city'];
        $postal = $this->device->data['company_postal'];
        $companyName = $this->device->data['company_name'];
        $invoiceType = '0211010';
        $invoiceTypeNo = '383';

        $customer_vatNo = $customer_tax_number;
        $building_customer = $customer_building;
        $plot_identification_customer = $customer_address;
        $city_subdivision_customer = '0000';
        $city_customer = $customer_city;
        $postal_customer = $customer_postal;

        $sale = (object)[
            'id' => $invoice->invoice_no,
            'total'=> $invoice->total,
            'grand_total'=> $invoice->grand_total,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> $invoice->product_tax,
            'date' => date('Y-m-d').'T'.date('H:i:s')
        ];

        $details = $invoiceItems;
        $UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $props = [
            'invoice_serial_number' => $id,
            'uuid' => $UUID,//'3cf5ee18-ee25-44ea-a444-2c37ba7f28be',// vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'CRN_number' => $vatNo,
            'street' => $address,
            'building' => $building,
            'plot_identification' => $plot_identification,
            'city_subdivision' => $city_subdivision,
            'city' => $city,
            'postal' => $postal,
            'VAT_number' => $vatNo,
            'VAT_name' => $companyName,
            'details' => $details,
            'sale' => $sale,
            'invoice_type' => $invoiceType,
            'invoice_type_no' => $invoiceTypeNo,

            'CRN_number_CUSTOMER' => $customer_vatNo,
            'street_CUSTOMER' => $address,
            'building_CUSTOMER' => $building_customer,
            'plot_identification_CUSTOMER' => $plot_identification_customer,
            'city_subdivision_CUSTOMER' => $city_subdivision_customer,
            'city_CUSTOMER' => $city_customer,
            'postal_CUSTOMER' => $postal_customer,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];

        $xmlInvoice = $this->getDefaultSimplifiedTaxInvoice($props);

        $private_key = $this->device->private_key;
        $token = $this->device->binary_security_token;
        $secret = $this->device->secret;

        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($token),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);


        $data = $this->signSingleInvoice($xmlInvoice,$sale->total,$sale->product_tax,$sale->date);

        //$invoice = (new InvoiceSign($xmlInvoice, $certificate))->sign();

        $invoiceHash = $data['invoice_hash'];

        $invoiceSignedXML = ($data['final']);



        $body = [
            "invoiceHash" => $invoiceHash,
            "uuid" => $UUID,
            "invoice" => base64_encode($invoiceSignedXML)
        ];


        $body = json_encode($body);

        $headers = [
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Accept: application/json',
            'accept-language: en',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ];

        $ch = curl_init();

        $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        if($this->live){
            $url = str_replace('developer-portal','core',$url);
        }

        curl_setopt($ch, CURLOPT_URL,$url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($ch, CURLOPT_USERPWD, "$token:$secret");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result=curl_exec ($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        //log_message('error',$status_code);
        if (curl_errno($ch)) {
            $result = curl_error($ch);
        }
        curl_close ($ch);

        // file_put_contents('certificate/compliance_invoice_pos_debit_'.$id.'.json', $result);


    }

    public function sample_a4_debit(){
        $invoice = (object)[
            'invoice_no' => '0001',
            'total'=> 10,
            'grand_total'=> 11.5,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> 1.5,
            'is_pos' => true,
            'is_invoice'=>true,
            'date' => date('Y-m-d').'T'.date('H:i:s'),
            'customer' => (object)[
                'name' => 'Mahmoud',
                'billing_address'=> 'Alriad',
                'email' => 'm@g.com',
                'tax_number' => '300000000000003',
                'billing_country' => 'SA',
                'billing_city' => 'Alriad',
                'billing_state' => 'Alriad',
                'billing_phone' => '5123456',
                'billing_postal' => '00000',
                'billing_building' => '0000'
            ]
        ];

        $invoiceItems[] = (object)[
            'product_id' => 100,
            'product_name' => 'Item A',
            'unit_quantity' => 1,
            'net_unit_price' => 10,
            'discount' => 0,
            'item_tax' => 1.5,
            'subtotal' => 10,
            'city_tax' => 0
        ];

        $customer = $invoice->customer;
        $customer_name=empty($customer->billing_name)? $customer->name : $customer->billing_name ;
        $customer_address=$customer->billing_address;
        $customer_email = $customer->email;
        $customer_tax_number = $customer->tax_number;
        $customer_country = $customer->billing_country;
        $customer_city = $customer->billing_city;
        $customer_state = $customer->billing_state;
        $customer_phone = $customer->billing_phone;
        $customer_postal = $customer->billing_postal;
        $customer_building = $customer->billing_building;

        $id = $invoice->invoice_no;
        $vatNo = $this->device->data['vat_no'];
        $address = $this->device->data['company_address'];
        $building = $this->device->data['company_building'];
        $plot_identification = $this->device->data['company_plot_identification'];
        $city_subdivision = $this->device->data['company_city_subdivision'];
        $city = $this->device->data['company_city'];
        $postal = $this->device->data['company_postal'];
        $companyName = $this->device->data['company_name'];
        $invoiceType = '0111010';
        $invoiceTypeNo = '383';

        $customer_vatNo = $customer_tax_number;
        $building_customer = $customer_building;
        $plot_identification_customer = $customer_address;
        $city_subdivision_customer = '0000';
        $city_customer = $customer_city;
        $postal_customer = $customer_postal;


        $sale = (object)[
            'id' => $invoice->invoice_no,
            'total'=> $invoice->total,
            'grand_total'=> $invoice->grand_total,
            'order_discount'=> 0,//$invoice->order_discount,
            'product_tax'=> $invoice->product_tax,
            'date' => date('Y-m-d').'T'.date('H:i:s')
        ];

        $details = $invoiceItems;
        $UUID = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $props = [
            'invoice_serial_number' => $id,
            'uuid' => $UUID,//'3cf5ee18-ee25-44ea-a444-2c37ba7f28be',// vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'invoice_counter_number' => 1,
            'CRN_number' => $vatNo,
            'street' => $address,
            'building' => $building,
            'plot_identification' => $plot_identification,
            'city_subdivision' => $city_subdivision,
            'city' => $city,
            'postal' => $postal,
            'VAT_number' => $vatNo,
            'VAT_name' => $companyName,
            'details' => $details,
            'sale' => $sale,
            'invoice_type' => $invoiceType,
            'invoice_type_no' => $invoiceTypeNo,

            'CRN_number_CUSTOMER' => $customer_vatNo,
            'street_CUSTOMER' => $address,
            'building_CUSTOMER' => $building_customer,
            'plot_identification_CUSTOMER' => $plot_identification_customer,
            'city_subdivision_CUSTOMER' => $city_subdivision_customer,
            'city_CUSTOMER' => $city_customer,
            'postal_CUSTOMER' => $postal_customer,
            'total' => $sale->total,
            'grand_total' => $sale->grand_total,
            'product_tax' => $sale->product_tax,
        ];

        $xmlInvoice = $this->getDefaultSimplifiedTaxInvoice($props);

        $private_key = $this->device->private_key;
        $token = $this->device->binary_security_token;
        $secret = $this->device->secret;

        $certificate = (new Certificate(
        // get from ZATCA when you exchange the CSR via APIs
            base64_decode($token),
            // generated at stage one
            $private_key
        // get from ZATCA when you exchange the CSR via APIs
        ))->setSecretKey($secret);


        $data = $this->signSingleInvoice($xmlInvoice,$sale->total,$sale->product_tax,$sale->date);

        //$invoice = (new InvoiceSign($xmlInvoice, $certificate))->sign();

        $invoiceHash = $data['invoice_hash'];

        $invoiceSignedXML = ($data['final']);

        $token = $this->device->binary_security_token;
        $secret = $this->device->secret;

        $body = [
            "invoiceHash" => $invoiceHash,
            "uuid" => $UUID,
            "invoice" => base64_encode($invoiceSignedXML)
        ];


        $body = json_encode($body);

        $headers = [
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Accept: application/json',
            'accept-language: en',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ];

        $ch = curl_init();

        $url = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance/invoices';
        if($this->live){
            $url = str_replace('developer-portal','core',$url);
        }

        curl_setopt($ch, CURLOPT_URL,$url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($ch, CURLOPT_USERPWD, "$token:$secret");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result=curl_exec ($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        //log_message('error',$status_code);
        if (curl_errno($ch)) {
            $result = curl_error($ch);
        }
        curl_close ($ch);

        // file_put_contents('certificate/compliance_invoice_a4_debit_'.$id.'.json', $result);

    }
    public function generateCSID(){
        $requestID = $this->device->request_id;
        $token = $this->device->binary_security_token;
        $secret = $this->device->secret;

        $data = json_encode(['compliance_request_id' => $requestID]);
        $ch = curl_init();
        // Set the URL for the POST request
        if($this->live){
            curl_setopt($ch, CURLOPT_URL, 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/production/csids');
        }else{
            curl_setopt($ch, CURLOPT_URL, 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/production/csids');
        }


        // Set the HTTP headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'Accept-Version: V2',
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode($token.':'.$secret)
        ]);

        // Set the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        // Return the response instead of printing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL session
        $response = curl_exec($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code


        $json = json_decode($response , true);
        if(isset($json['dispositionMessage']) AND $json['dispositionMessage'] == 'ISSUED') {
            $this->device->update([
                'status' => 1,
                'binary_security_token' => $json['binarySecurityToken'],
                'secret' => $json['secret'],
            ]);
        }else{
            throw new \Exception($response);
        }

        // Check for errors
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        // Close the cURL session
        curl_close($ch);



        return $this->device;
    }
    function getDefaultSimplifiedTaxInvoice($props){

        $template = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"><ext:UBLExtensions>SET_UBL_EXTENSIONS_STRING</ext:UBLExtensions>' ."\n".
            "    \n" .
            '    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>' ."\n".
            '    <cbc:ID>SET_INVOICE_SERIAL_NUMBER</cbc:ID>' ."\n" .
            '    <cbc:UUID>SET_TERMINAL_UUID</cbc:UUID>' ."\n" .
            '    <cbc:IssueDate>SET_ISSUE_DATE</cbc:IssueDate>' ."\n" .
            '    <cbc:IssueTime>SET_ISSUE_TIME</cbc:IssueTime>' ."\n" .
            '    <cbc:InvoiceTypeCode name="SET_INVOICE_TYPE">SET_INVOICE_TYPE_NO</cbc:InvoiceTypeCode>' ."\n" .
            '    <cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>' ."\n" .
            '    <cbc:TaxCurrencyCode>SAR</cbc:TaxCurrencyCode>' ."\n" .
            '    <cac:BillingReference>' ."\n" .
            '        <cac:InvoiceDocumentReference>' ."\n" .
            '            <cbc:ID>"Invoice Number: 348; Invoice Issue Date: 2022-11-04"</cbc:ID>' ."\n" .
            '        </cac:InvoiceDocumentReference>' ."\n" .
            '    </cac:BillingReference>' ."\n" .
            '    <cac:AdditionalDocumentReference>' ."\n" .
            '        <cbc:ID>ICV</cbc:ID>' ."\n" .
            '        <cbc:UUID>SET_INVOICE_COUNTER_NUMBER</cbc:UUID>' ."\n" .
            '    </cac:AdditionalDocumentReference>' ."\n" .
            '    <cac:AdditionalDocumentReference>' ."\n" .
            '        <cbc:ID>PIH</cbc:ID>' ."\n" .
            '        <cac:Attachment>' ."\n" .
            '            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">SET_PREVIOUS_INVOICE_HASH</cbc:EmbeddedDocumentBinaryObject>' ."\n" .
            '        </cac:Attachment>' ."\n" .
            '    </cac:AdditionalDocumentReference>' ."\n" .
            '    <cac:AdditionalDocumentReference>' ."\n" .
            '        <cbc:ID>QR</cbc:ID>' ."\n" .
            '        <cac:Attachment>' ."\n" .
            '            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">SET_QR_CODE_DATA</cbc:EmbeddedDocumentBinaryObject>' ."\n" .
            '        </cac:Attachment>' ."\n" .
            '    </cac:AdditionalDocumentReference>' ."\n" .
            '    <cac:Signature>' ."\n" .
            '        <cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID>' ."\n" .
            '        <cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod>' ."\n" .
            '    </cac:Signature>' ."\n" .
            '    ' ."\n" .
            '    ' ."\n" .
            '    <cac:AccountingSupplierParty>' ."\n" .
            '        <cac:Party>' ."\n" .
            '            <cac:PartyIdentification>' ."\n" .
            '                <cbc:ID schemeID="CRN">SET_COMMERCIAL_REGISTRATION_NUMBER</cbc:ID>' ."\n" .
            '            </cac:PartyIdentification>' ."\n" .
            '            <cac:PostalAddress>' ."\n" .
            '                <cbc:StreetName>SET_STREET_NAME</cbc:StreetName>' ."\n" .
            '                <cbc:BuildingNumber>SET_BUILDING_NUMBER</cbc:BuildingNumber>' ."\n" .
            '                <cbc:PlotIdentification>SET_PLOT_IDENTIFICATION</cbc:PlotIdentification>' ."\n" .
            '                <cbc:CitySubdivisionName>SET_CITY_SUBDIVISION</cbc:CitySubdivisionName>' ."\n" .
            '                <cbc:CityName>SET_CITY</cbc:CityName>' ."\n" .
            '                <cbc:PostalZone>SET_POSTAL_NUMBER</cbc:PostalZone>' ."\n" .
            '                <cac:Country>' ."\n" .
            '                    <cbc:IdentificationCode>SA</cbc:IdentificationCode>' ."\n" .
            '                </cac:Country>' ."\n" .
            '            </cac:PostalAddress>' ."\n" .
            '            <cac:PartyTaxScheme>' ."\n" .
            '                <cbc:CompanyID>SET_VAT_NUMBER</cbc:CompanyID>' ."\n" .
            '                <cac:TaxScheme>' ."\n" .
            '                    <cbc:ID>VAT</cbc:ID>' ."\n" .
            '                </cac:TaxScheme>' ."\n" .
            '            </cac:PartyTaxScheme>' ."\n" .
            '            <cac:PartyLegalEntity>' ."\n" .
            '                <cbc:RegistrationName>SET_VAT_NAME</cbc:RegistrationName>' ."\n" .
            '            </cac:PartyLegalEntity>' ."\n" .
            '        </cac:Party>' ."\n" .
            '    </cac:AccountingSupplierParty>' ."\n" .
            '    <cac:AccountingCustomerParty>' ."\n" .
            '        <cac:Party>' ."\n" .
            '            <cac:PartyIdentification>' ."\n" .
            '                <cbc:ID schemeID="SAG">SET_COMMERCIAL_REGISTRATION_NUMBER_CUSTOMER</cbc:ID>' ."\n" .
            '            </cac:PartyIdentification>' ."\n" .
            '            <cac:PostalAddress>' ."\n" .
            '                <cbc:StreetName>SET_STREET_NAME_CUSTOMER</cbc:StreetName>' ."\n" .
            '                <cbc:BuildingNumber>SET_BUILDING_NUMBER_CUSTOMER</cbc:BuildingNumber>' ."\n" .
            '                <cbc:PlotIdentification>SET_PLOT_IDENTIFICATION_CUSTOMER</cbc:PlotIdentification>' ."\n" .
            '                <cbc:CitySubdivisionName>SET_CITY_SUBDIVISION_CUSTOMER</cbc:CitySubdivisionName>' ."\n" .
            '                <cbc:CityName>SET_CITY_CUSTOMER</cbc:CityName>' ."\n" .
            '                <cbc:PostalZone>SET_POSTAL_NUMBER_CUSTOMER</cbc:PostalZone>' ."\n" .
            '                <cac:Country>' ."\n" .
            '                    <cbc:IdentificationCode>SA</cbc:IdentificationCode>' ."\n" .
            '                </cac:Country>' ."\n" .
            '            </cac:PostalAddress>' ."\n" .
            '            <cac:PartyTaxScheme>' ."\n" .
            '                <cac:TaxScheme>' ."\n" .
            '                    <cbc:ID>VAT</cbc:ID>' ."\n" .
            '                </cac:TaxScheme>' ."\n" .
            '            </cac:PartyTaxScheme>' ."\n" .
            '            <cac:PartyLegalEntity>' ."\n" .
            '                <cbc:RegistrationName>SET_VAT_NAME_CUSTOMER</cbc:RegistrationName>' ."\n" .
            '            </cac:PartyLegalEntity>' ."\n" .
            '        </cac:Party>' ."\n" .
            '    </cac:AccountingCustomerParty>' ."\n" .
            '    <cac:Delivery>' ."\n" .
            '        <cbc:ActualDeliveryDate>SET_DELIVERY_DATE</cbc:ActualDeliveryDate>' ."\n" .
            '        <cbc:LatestDeliveryDate>SET_LATEST_DELIVERY_DATE</cbc:LatestDeliveryDate>' ."\n" .
            '    </cac:Delivery>' ."\n" .
            '    <cac:PaymentMeans>' ."\n" .
            '        <cbc:PaymentMeansCode>SET_PAYMENT_MEANS</cbc:PaymentMeansCode>' ."\n";
        $sale = $props['sale'];
        if($props['invoice_type_no'] == '383'){
            $template.='        <cbc:InstructionNote>TERMINATION</cbc:InstructionNote>' ."\n" ;
        }else if($props['invoice_type_no'] == '381'){
            $template.='        <cbc:InstructionNote>Returned Items</cbc:InstructionNote>' ."\n" ;
        }

        $template.='    </cac:PaymentMeans>' ."\n" .
            'SET_DISCOUNT_AREA'.
            '    <cac:TaxTotal>' ."\n" .
            '        <cbc:TaxAmount currencyID="SAR">SET_TAX_TOTAL</cbc:TaxAmount>' ."\n" .
            'SET_TAX_TEXT_AMOUNT'.'SET_TAX_WITHOUT_AMOUNT'.
            '    </cac:TaxTotal>' ."\n" .
            '    <cac:TaxTotal>' ."\n" .
            '        <cbc:TaxAmount currencyID="SAR">SET_TAX_AMOUNT_2</cbc:TaxAmount>' ."\n" .
            '    </cac:TaxTotal>' ."\n" .
            '    <cac:LegalMonetaryTotal>' ."\n" .
            '        <cbc:LineExtensionAmount currencyID="SAR">SET_LINE_AMOUNT</cbc:LineExtensionAmount>' ."\n" .
            '        <cbc:TaxExclusiveAmount currencyID="SAR">SET_EXCLUSIVE_AMOUNT</cbc:TaxExclusiveAmount>' ."\n" .
            '        <cbc:TaxInclusiveAmount currencyID="SAR">SET_INCLUSIVE_AMOUNT</cbc:TaxInclusiveAmount>' ."\n" .
            '        <cbc:AllowanceTotalAmount currencyID="SAR">SET_DISCOUNT_AMOUNT</cbc:AllowanceTotalAmount>' ."\n" .
            '        <cbc:PrepaidAmount currencyID="SAR">0.00</cbc:PrepaidAmount>' ."\n" .
            '        <cbc:PayableAmount currencyID="SAR">SET_PAID_AMOUNT</cbc:PayableAmount>' ."\n" .
            '    </cac:LegalMonetaryTotal>' ."\n".
            '    SET_INVOICE_ITEMS'.
            '</Invoice>';

        $details = $props['details'];

        $items = '';
        $totalTaxAmount =0;
        $totalWithoutTaxAmount = 0;
        $round = 0;
        $allTotal = 0;


        $sale->total = $this->formatDecimal($sale->grand_total,2) - $this->formatDecimal($sale->product_tax,2) + $this->formatDecimal($sale->order_discount,2);
        $sale->total = $sale->total < 0 ? $sale->total*-1 : $sale->total;
        foreach($details as $key=>$detail){
            $total = $detail->net_unit_price * $detail->unit_quantity;
            $total = $total < 0? $total*-1 : $total;
            $total = bcdiv($total,1,2);

            $pWT = $detail->net_unit_price * $detail->unit_quantity;
            $pWT = $pWT < 0? $pWT*-1:$pWT;
            $priceWithoutTax = $this->formatDecimal($pWT,2);

            if($total != $priceWithoutTax){
                if($round %2 == 0){
                    $priceWithoutTax = $priceWithoutTax;
                }else{
                    $priceWithoutTax = $total;
                }
                $round +=1;
            }
            $allTotal += $priceWithoutTax;

            if($key+1 == count($details)){

                if($allTotal <> $sale->total)
                    $priceWithoutTax = $this->formatDecimal(($sale->total - $allTotal) + $priceWithoutTax,2);
            }
            $taxID = 'S';
            $taxPerct = '15';
            $taxReason = '';

            $detail->item_tax = $detail->item_tax < 0 ? $detail->item_tax *-1: $detail->item_tax;
            $detail->subtotal = $detail->subtotal < 0 ? $detail->subtotal *-1: $detail->subtotal;
            $detail->city_tax = $detail->city_tax < 0 ? $detail->city_tax *-1: $detail->city_tax;

            if($detail->item_tax == 0){
                $taxID = 'O';
                $taxPerct = '0';
                $totalWithoutTaxAmount += $priceWithoutTax;//$detail->net_unit_price * $detail->unit_quantity;
            }else{
                $totalTaxAmount += $priceWithoutTax;//$detail->net_unit_price * $detail->unit_quantity;
            }

            $items .='    <cac:InvoiceLine>' ."\n" .
                '        <cbc:ID>'.($key+1).'</cbc:ID>' ."\n" .
                '        <cbc:InvoicedQuantity unitCode="PCE">'.$this->formatDecimal($detail->unit_quantity < 0 ? $detail->unit_quantity*-1 : $detail->unit_quantity,4).'</cbc:InvoicedQuantity>' ."\n" .
                '        <cbc:LineExtensionAmount currencyID="SAR">'.$priceWithoutTax.'</cbc:LineExtensionAmount>' ."\n" ;
            //if($detail->item_tax > 0){
            $items .='        <cac:TaxTotal>' ."\n" .
                '            <cbc:TaxAmount currencyID="SAR">'.$this->formatDecimal($detail->item_tax,2).'</cbc:TaxAmount>' ."\n" .
                '            <cbc:RoundingAmount currencyID="SAR">'.$this->formatDecimal($priceWithoutTax+$detail->item_tax,2).'</cbc:RoundingAmount>' ."\n" .
                '        </cac:TaxTotal>' ."\n" ;
            //}

            $items .='        <cac:Item>' ."\n" .
                '            <cbc:Name>'.($detail->product_name ? $detail->product_name:  'Item').'</cbc:Name>' ."\n" .
                '            <cac:ClassifiedTaxCategory>' ."\n" .
                '                <cbc:ID>'.$taxID.'</cbc:ID>' ."\n" ;

            // if($detail->item_tax > 0){
            $items .='                <cbc:Percent>'.$taxPerct.'</cbc:Percent>' ."\n" .$taxReason;
            //}
            if($detail->unit_quantity < 0){
                $detail->unit_quantity = $detail->unit_quantity*-1;
            }
            $items .='                <cac:TaxScheme>' ."\n" .
                '                    <cbc:ID>VAT</cbc:ID>' ."\n" .
                '                </cac:TaxScheme>' ."\n" .
                '            </cac:ClassifiedTaxCategory>' ."\n" .
                '        </cac:Item>' ."\n" .
                '        <cac:Price>' ."\n" .
                '            <cbc:PriceAmount currencyID="SAR">'.$this->formatDecimal((($priceWithoutTax)/$detail->unit_quantity),6).'</cbc:PriceAmount>' ."\n" .
                '        </cac:Price>' ."\n" .
                '    </cac:InvoiceLine>' ."\n";
        }



        $populated_template = str_replace('SET_INVOICE_TYPE_NO',$props['invoice_type_no'],$template);
        $populated_template = str_replace('SET_INVOICE_TYPE',$props['invoice_type'],$populated_template);
        $populated_template = str_replace('SET_INVOICE_ITEMS',$items,$populated_template);
        $populated_template = str_replace('SET_BILLING_REFERENCE','',$populated_template);




        $populated_template = str_replace('SET_COMMERCIAL_REGISTRATION_NUMBER_CUSTOMER',$props['CRN_number_CUSTOMER'],$populated_template);
        $populated_template = str_replace('SET_STREET_NAME_CUSTOMER',$props['street_CUSTOMER'],$populated_template);
        $populated_template = str_replace('SET_BUILDING_NUMBER_CUSTOMER',$props['building_CUSTOMER'],$populated_template);
        $populated_template = str_replace('SET_PLOT_IDENTIFICATION_CUSTOMER',$props['plot_identification_CUSTOMER'],$populated_template);
        $populated_template = str_replace('SET_CITY_SUBDIVISION_CUSTOMER',$props['city_subdivision_CUSTOMER'],$populated_template);
        $populated_template = str_replace('SET_CITY_CUSTOMER',$props['city_CUSTOMER'],$populated_template);
        $populated_template = str_replace('SET_POSTAL_NUMBER_CUSTOMER',$props['postal_CUSTOMER'],$populated_template);


        $populated_template = str_replace('SET_COMMERCIAL_REGISTRATION_NUMBER',$props['CRN_number'],$populated_template);
        $populated_template = str_replace('SET_STREET_NAME',$props['street'],$populated_template);
        $populated_template = str_replace('SET_BUILDING_NUMBER',$props['building'],$populated_template);
        $populated_template = str_replace('SET_PLOT_IDENTIFICATION',$props['plot_identification'],$populated_template);
        $populated_template = str_replace('SET_CITY_SUBDIVISION',$props['city_subdivision'],$populated_template);
        $populated_template = str_replace('SET_CITY',$props['city'],$populated_template);
        $populated_template = str_replace('SET_POSTAL_NUMBER',$props['postal'],$populated_template);


        $populated_template = str_replace('SET_INVOICE_SERIAL_NUMBER',$props['invoice_serial_number'],$populated_template);
        $populated_template = str_replace('SET_TERMINAL_UUID',$props['uuid'],$populated_template);
        $populated_template = str_replace('SET_ISSUE_DATE',$props['date'],$populated_template);
        $populated_template = str_replace('SET_ISSUE_TIME',$props['time'],$populated_template);
        $populated_template = str_replace('SET_PREVIOUS_INVOICE_HASH',$props['previous_invoice_hash'],$populated_template);
        $populated_template = str_replace('SET_INVOICE_COUNTER_NUMBER',$props['invoice_counter_number'],$populated_template);


        $populated_template = str_replace('SET_VAT_NUMBER',$props['VAT_number'],$populated_template);
        $populated_template = str_replace('SET_VAT_NAME',$props['VAT_name'],$populated_template);


        $populated_template = str_replace('SET_DELIVERY_DATE',$props['date'],$populated_template);
        $populated_template = str_replace('SET_LATEST_DELIVERY_DATE',$props['date'],$populated_template);

        $populated_template = str_replace('SET_PAYMENT_MEANS',42,$populated_template);

        $totalTaxAmount = $totalTaxAmount < 0 ? $totalTaxAmount *-1: $totalTaxAmount;
        $sale->total = $sale->total <0 ? $sale->total*-1: $sale->total;
        $sale->order_discount = $sale->order_discount <0 ? $sale->order_discount*-1: $sale->order_discount;
        $sale->grand_total = $sale->grand_total <0 ? $sale->grand_total*-1: $sale->grand_total;
        $totalWithoutTaxAmount = $totalWithoutTaxAmount <0? $totalWithoutTaxAmount*-1:$totalWithoutTaxAmount;
        $sale->product_tax = $sale->product_tax < 0 ? $sale->product_tax*-1: $sale->product_tax;
        if($sale->order_discount > 0){
            $sale->order_discount = $sale->total - ($sale->grand_total - $sale->product_tax) ;
        }

        $taxID = '';
        $taxPercentage = '';

        if($sale->order_discount == 0){
            $populated_template = str_replace('SET_TAXABLE_AMOUNT',$this->formatDecimal($totalTaxAmount,2),$populated_template);
            $populated_template = str_replace('SET_EXCLUSIVE_AMOUNT',$this->formatDecimal($sale->grand_total,2) - $this->formatDecimal($sale->product_tax,2),$populated_template);
            $populated_template = str_replace('SET_INCLUSIVE_AMOUNT',$this->formatDecimal($sale->grand_total,2),$populated_template);
            $populated_template = str_replace('SET_PAID_AMOUNT',$this->formatDecimal($sale->grand_total,2),$populated_template);
        }
        else{
            if($sale->order_discount < $totalTaxAmount && $sale->product_tax > 0){
                $totalTaxAmount = $totalTaxAmount -  $sale->order_discount;
                $taxID = 'S';
                $taxPercentage = '15';
            }else  if($sale->order_discount < $totalWithoutTaxAmount && $sale->product_tax > 0){
                $totalWithoutTaxAmount = $totalWithoutTaxAmount - $sale->order_discount;
                $taxID = 'O';
                $taxPercentage = '0';
            }else if($sale->product_tax == 0){
                $totalWithoutTaxAmount = $totalWithoutTaxAmount - $sale->order_discount;
                $taxID = 'O';
                $taxPercentage = '0';
            }
            $populated_template = str_replace('SET_TAXABLE_AMOUNT',$this->formatDecimal($totalTaxAmount - $sale->product_tax,2),$populated_template);
            $populated_template = str_replace('SET_EXCLUSIVE_AMOUNT',$this->formatDecimal($sale->grand_total,2) - $this->formatDecimal($sale->product_tax,2),$populated_template);
            $populated_template = str_replace('SET_INCLUSIVE_AMOUNT',$this->formatDecimal($sale->grand_total ,2),$populated_template);
            $populated_template = str_replace('SET_PAID_AMOUNT',$this->formatDecimal($sale->grand_total,2),$populated_template);
        }
        $data = '';
        $data2 = '';
        if($totalWithoutTaxAmount > 0){
            $data = '        <cac:TaxSubtotal>' ."\n" .
                '            <cbc:TaxableAmount currencyID="SAR">'.$this->formatDecimal($totalWithoutTaxAmount,2).'</cbc:TaxableAmount>' ."\n" .
                '            <cbc:TaxAmount currencyID="SAR">0</cbc:TaxAmount>' ."\n" .
                '            <cac:TaxCategory>' ."\n" .
                '                <cbc:ID>O</cbc:ID>' ."\n" .
                '                <cbc:Percent>0</cbc:Percent>' ."\n" .
                '                 <cbc:TaxExemptionReasonCode>VATEX-SA-OOS</cbc:TaxExemptionReasonCode>' ."\n" .
                '                 <cbc:TaxExemptionReason>Not Subject To VAT</cbc:TaxExemptionReason>' ."\n" .
                '                <cac:TaxScheme>' ."\n" .
                '                    <cbc:ID>VAT</cbc:ID>' ."\n" .
                '                </cac:TaxScheme>' ."\n" .
                '            </cac:TaxCategory>' ."\n" .
                '        </cac:TaxSubtotal>' ."\n";
        }

        if($totalTaxAmount > 0){
            $data2 ='        <cac:TaxSubtotal>' ."\n" .
                '            <cbc:TaxableAmount currencyID="SAR">'.$this->formatDecimal($totalTaxAmount,2).'</cbc:TaxableAmount>' ."\n" .
                '            <cbc:TaxAmount currencyID="SAR">SET_TAX_AMOUNT</cbc:TaxAmount>' ."\n" .
                '            <cac:TaxCategory>' ."\n" .
                '                <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">S</cbc:ID>' ."\n" .
                '                <cbc:Percent>SET_TAX_METHOD</cbc:Percent>' ."\n" .
                '                <cac:TaxScheme>' ."\n" .
                '                    <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>' ."\n" .
                '                </cac:TaxScheme>' ."\n" .
                '            </cac:TaxCategory>' ."\n" .
                '        </cac:TaxSubtotal>' ."\n" ;
        }

        $populated_template = str_replace('SET_LINE_AMOUNT',$this->formatDecimal($sale->total,2),$populated_template);
        $populated_template = str_replace('SET_TAX_WITHOUT_AMOUNT',$data,$populated_template);
        $populated_template = str_replace('SET_TAX_TEXT_AMOUNT',$data2,$populated_template);
        $populated_template = str_replace('SET_TAX_AMOUNT_2',$this->formatDecimal($sale->product_tax,2),$populated_template);
        $populated_template = str_replace('SET_TAX_TOTAL',$this->formatDecimal($sale->product_tax,2),$populated_template);
        $populated_template = str_replace('SET_TAX_AMOUNT',$this->formatDecimal($sale->product_tax,2),$populated_template);

        $populated_template = str_replace('SET_TAX_METHOD','15.00',$populated_template);





        $populated_template = str_replace('SET_DISCOUNT_AMOUNT',$this->formatDecimal($sale->order_discount,2),$populated_template);



        if($sale->order_discount > 0){
            $discountLevel =
                '    <cac:AllowanceCharge>'."\n".
                '        <cbc:ChargeIndicator>false</cbc:ChargeIndicator>'."\n".
                '        <cbc:AllowanceChargeReason>Discount</cbc:AllowanceChargeReason>'."\n".
                '        <cbc:Amount currencyID="SAR">'.$this->formatDecimal($sale->order_discount,2).'</cbc:Amount>'."\n".
                '        <cac:TaxCategory>'."\n".
                '            <cbc:ID>'.($taxID).'</cbc:ID>'."\n".
                '            <cbc:Percent>'.($taxPercentage).'</cbc:Percent>'."\n".
                '            <cac:TaxScheme>'."\n".
                '                <cbc:ID>VAT</cbc:ID>'."\n".
                '            </cac:TaxScheme>'."\n".
                '        </cac:TaxCategory>'."\n".
                '    </cac:AllowanceCharge>'."\n";

            $populated_template = str_replace('SET_DISCOUNT_AREA',$discountLevel,$populated_template);
        }else{
            $populated_template = str_replace('SET_DISCOUNT_AREA','',$populated_template);
        }

        return $populated_template;
    }
    function generateQR($params){
        $invoice_hash  = $this->getInvoiceHash($params['invoice_xml']);
        $digitalSignature = $params['digital_signature'];
        $placeName = $params['company_name'];
        $vat_no = $params['vat_no'];


        $total = $params['total'] ;
        $product_tax = $params['tax'];

        $QR = $this->einv_generate_tlv_qr_code(array($placeName,$vat_no,$params['date'],
            $this->formatDecimal($total+$product_tax),$this->formatDecimal( $product_tax),
            $invoice_hash,
            $digitalSignature,$params['public_key'],$params['certificate_signature']));

        return $QR;

    }

    function einv_generate_tlv_qr_code($array_tag=array()){
        $index=1;
        $tlv_string = '';
        foreach($array_tag as $tag_val){
            $tlv_string.=pack("H*", sprintf("%02X",(string) "$index")).
                pack("H*", sprintf("%02X",strlen((string) "$tag_val"))).
                (string) "$tag_val";
            $index++;
        }
        return base64_encode($tlv_string);
    }

    function createInvoiceDigitalSignature($invoice_hash,$private_key_string){


        $invoice_hash_bytes = base64_decode($invoice_hash);
        $cleanedup_private_key_string = $this->cleanUpPrivateKeyString($private_key_string);
        $wrapped_private_key_string = "-----BEGIN EC PRIVATE KEY-----\n".$cleanedup_private_key_string."\n-----END EC PRIVATE KEY-----";

        //openssl_public_encrypt( hash('sha256', $invoice_hash_bytes), $encrypted, $wrapped_private_key_string);
        openssl_sign($invoice_hash_bytes,$encrypted,$private_key_string,OPENSSL_ALGO_SHA256);

        //$digitalSignature = 'MEYCIQCcMqvQbWMnGL2tOwrLSWIivJs8jCJHhDgnNI3nthmYoAIhAPnidcuZJxXVn7lVR4AwtV9mWpCXQnaKc+Q05lBbVnfb';//base64_encode($encrypted);
        //$digitalSignature = '`MEUCIQDIn6FB/I5jLmXTONDZowQtmmPwJHtIfZ0hQq3oyHRLRwIgUdwx/E9rA9736McmhY9oACQLaVDcbeaPVz7rmdiBkHk=';//base64_decode('YE1FVUNJUUMwK2lVeVYwaE4raDk0dFRFTjV4bXpacWhhRjFmV2JabjZQdEFSa0hCSjRRSWdGbUZ5bmtNcXN3YmYvZXhlQ0V5Q2xOM0hNMG5PNUZYSWpSUzM4WUkrZUl3PQ==');
        return base64_encode($encrypted);
    }

    function cleanUpPrivateKeyString($private_key_string){
        $string = str_replace('-----BEGIN EC PRIVATE KEY-----
','',$private_key_string);

        $string = str_replace('-----END EC PRIVATE KEY-----','',$string);
        return trim($string);
    }

    function getCertificateInfo($certificate_string,$certInfo){
        $cleanedup_certificate_string = $this->cleanUpCertificateString($certificate_string);
        $wrapped_certificate_string = "-----BEGIN CERTIFICATE-----\n".$cleanedup_certificate_string."\n-----END CERTIFICATE-----";
        $hash =$this->getCertificateHash($cleanedup_certificate_string);


        $serialNo = $certInfo['serialNo'] ;//'';//$publicKey['serialNumberHex'];
        $issuer =$certInfo['issuer'] ;//'CN=eInvoicing'; //$cert['tbsCertificate']['issuer'];

        $pKey = base64_decode($certInfo['pKey']) ;//base64_decode('MFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEt5orzx8xcyOtUiW5j7VfKbGGIAQyHFl1/kQtZzpl6x9bH+Hp/iBbHv2pwpfQahxzx+oJqvq/SwQDSvFspJQ0aA==');
        $signature = base64_decode($certInfo['signature']);// base64_decode('MEYCIQD6Ept/BO8AHevHA5QmixkZIR2YTtO3aLXVQCiHiz2gdAIhAJc6oiNno4W5hOjR89NFD5xbKyivPsViMeChb+TblFR4');



        return [
            'hash' => $hash,
            'issuer' => $issuer,
            'serial_number' => $serialNo,
            'public_key' => $pKey,
            'signature' => $signature
        ];

    }

    function getCertificateHash($cleanedup_certificate_string){
        return base64_encode(hash('sha256', $cleanedup_certificate_string));
    }

    function cleanUpCertificateString($certificate_string){
        $string = str_replace('-----BEGIN CERTIFICATE-----','',$certificate_string);

        $string = str_replace('-----END CERTIFICATE-----','',$string);
        return trim($string);
    }

    function getInvoiceHash($invoiceXML){

        $pure_invoice_string = $this->getPureInvoiceString($invoiceXML);

        return base64_encode(hash('sha256', $pure_invoice_string,true));// base64_encode('8c700329251a682c221100428aaf44bc50818613293b0e541a13db5d1537f919'); //base64_encode(hash('sha256', $pure_invoice_string));
    }

    function getPureInvoiceString($invoiceXML){
        $doc = new DOMDocument;
        $doc->loadxml($invoiceXML);
        $book = $doc->documentElement;

        // we retrieve the chapter and remove it from the book
        $chapter = $book->getElementsByTagName('UBLExtensions')->item(0);
        if($chapter)
            $oldchapter = $book->removeChild($chapter);

        $chapter = $book->getElementsByTagName('Signature')->item(0);
        if($chapter)
            $oldchapter = $book->removeChild($chapter);

        $chapter = $book->getElementsByTagName('AdditionalDocumentReference')->item(2);
        if($chapter)
            $oldchapter = $book->removeChild($chapter);



        return C14N::canonicalizar($doc->saveXML());
    }

    public function formatDecimal($number, $decimals = 2)
    {
        if (!is_numeric($number)) {
            return null;
        }

        return number_format($number, $decimals, '.', '');
    }
}
