<?php
namespace MPAY_VG\Soap;
use MPAY_VG\Core\OrderMapper;
use MPAY_VG\Core\Logger;
use MPAY_VG\Core\DB;
if (!defined('ABSPATH')) { exit; }

class Server {
    const MAX_BODY = 512000; // 500 KB
    const RATE_WINDOW = 30;  // seconds
    const RATE_LIMIT  = 30;  // max requests per IP within window

    public function handle() {
        $opts = \mpay_vg_get_settings();
        $guard = !empty($opts['enable_soap_guard']);
        $persist = !empty($opts['enable_soap_persist']);

    $start = microtime(true);
        $op = 'Unknown';
        $order_id = 0; $payment_id=''; $invoice_id=''; $amount=null; $currency='MDL';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $soap_file = null;
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;

        if ($guard && (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')) { status_header(405); header('Allow: POST'); exit; }
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($guard && (stripos($ct, 'text/xml') === false && stripos($ct, 'application/soap+xml') === false)) { status_header(415); exit; }

        if ($guard) {
            $k = 'mpay_rate_'.md5($ip); $now = time(); $bucket = get_transient($k);
            if (!is_array($bucket)) $bucket = [];
            $bucket = array_filter($bucket, function($t) use ($now){ return ($now - $t) < self::RATE_WINDOW; });
            $bucket[] = $now;
            if (count($bucket) > self::RATE_LIMIT) { status_header(429); echo 'Too Many Requests'; exit; }
            set_transient($k, $bucket, self::RATE_WINDOW);
        }

        $raw = file_get_contents('php://input') ?: '';
        if ($guard && strlen($raw) > self::MAX_BODY) { status_header(413); exit; }
        if ($persist) {
            $uploads = wp_upload_dir();
            $dir = trailingslashit($uploads['basedir']).'mpay-vg/soap';
            if (!file_exists($dir)) wp_mkdir_p($dir);
            $fname = 'soap-'.date('Ymd-His').'-'.wp_generate_password(6,false,false).'.xml';
            $path = trailingslashit($dir).$fname;
            file_put_contents($path, $raw);
            $soap_file = $path;
        }
        Logger::log('SOAP request primit', [
            'component' => 'soap.server',
            'event' => 'request_received',
            'body_bytes' => strlen($raw),
            'ip' => $ip,
        ]);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (trim($raw) === '') {
            if ($guard && $this->is_guard_ping($contentLength, $ip)) {
                Logger::log('SOAP guard ping interceptat', [
                    'component' => 'soap.server',
                    'event' => 'guard_ping',
                    'ip' => $ip,
                ]);
                $this->log_event('Ping', $order_id, $invoice_id, $payment_id, $amount, $currency, 'GuardPing', $ip, $start, $soap_file);
                status_header(204);
                header('X-MPay-Guard: PingAck');
                return;
            }
            $this->log_event($op,$order_id,$invoice_id,$payment_id,$amount,$currency,'EmptyBody',$ip,$start,$soap_file);
            return $this->fault('Client', 'Empty SOAP body');
        }
        if (!$doc->loadXML($raw, LIBXML_NONET | LIBXML_NOCDATA)) {
            $this->log_event($op,$order_id,$invoice_id,$payment_id,$amount,$currency,'InvalidXML',$ip,$start,$soap_file);
            return $this->fault('Client', 'Invalid XML');
        }

        // WS-Security verify
        $ver = WsSecurity::verify($doc);
        if (!$ver['ok']) {
            $code = $ver['code'] ?? 'AuthenticationFailed';
            $opName = $this->detectOp($doc) ?: 'Unknown';
            $this->log_event($opName, $order_id, $invoice_id, $payment_id, $amount, $currency, $code, $ip, $start, $soap_file);
            \mpay_vg_set_runtime('last_soap', ['when'=>time(),'ip'=>$ip,'op'=>$opName,'result'=>$code,'soap_file'=>$soap_file]);
            return $this->fault($code, $ver['msg'] ?? $code);
        }

        $op = $this->detectOp($doc);
        if ($op === 'GetOrderDetails') {
            \mpay_vg_set_runtime('last_soap', ['when'=>time(),'ip'=>$ip,'op'=>'GetOrderDetails','result'=>'OK','soap_file'=>$soap_file]);
            $res = $this->opGetOrderDetails($doc, $start, $ip, $soap_file);
            return $res;
        }
        if ($op === 'ConfirmOrderPayment') {
            \mpay_vg_set_runtime('last_soap', ['when'=>time(),'ip'=>$ip,'op'=>'ConfirmOrderPayment','result'=>'OK','soap_file'=>$soap_file]);
            $this->opConfirmOrderPayment($doc, $start, $ip, $soap_file);
            return;
        }
        $this->log_event('Unknown',$order_id,$invoice_id,$payment_id,$amount,$currency,'UnknownOp',$ip,$start,$soap_file);
        \mpay_vg_set_runtime('last_soap', ['when'=>time(),'ip'=>$ip,'op'=>'Unknown','result'=>'UnknownOp','soap_file'=>$soap_file]);
        return $this->fault('Client', 'Unknown operation');
    }

    private function detectOp(\DOMDocument $doc) {
        $xp = new \DOMXPath($doc);
        $n = $xp->query('//*[local-name()="Body"]/*[1]');
        if ($n && $n->length) return $n->item(0)->localName;
        return null;
    }

    private function opGetOrderDetails(\DOMDocument $doc, $start, $ip, $soap_file) {
        $xp = new \DOMXPath($doc);
        $serviceId = $this->text($xp, '//*[local-name()="ServiceID"]');
        $orderKey  = $this->text($xp, '//*[local-name()="OrderKey"]');

        $opts = \mpay_vg_get_settings();
        $order_id_for_log = OrderMapper::resolve_order_id($orderKey);
        if (!$serviceId || $serviceId !== ($opts['service_id'] ?? null)) {
            $this->log_event('GetOrderDetails', $order_id_for_log, '', '', null, 'MDL', 'UnknownService', $ip, $start, $soap_file);
            return $this->fault('UnknownService', 'ServiceID mismatch');
        }

        $order_id = $order_id_for_log;
        if (!$order_id) {
            $this->log_event('GetOrderDetails', 0, '', '', null, 'MDL', 'UnknownOrder', $ip, $start, $soap_file);
            return $this->fault('UnknownOrder', 'Order not found');
        }

        $details = OrderMapper::build_order_details($order_id, $orderKey);
        if (!$details) {
            $this->log_event('GetOrderDetails', $order_id, '', '', null, 'MDL', 'UnknownOrder', $ip, $start, $soap_file);
            return $this->fault('UnknownOrder', 'Order not found');
        }

        $body = $this->renderOrderDetails($details);
        $env  = $this->envelope($body);
        $env  = WsSecurity::sign($env);
        $this->log_event('GetOrderDetails', $order_id, '', '', $details['TotalAmountDue'], $details['Currency'], 'OK', $ip, $start, $soap_file);
        return $this->send($env);
    }

    private function opConfirmOrderPayment(\DOMDocument $doc, $start, $ip, $soap_file) {
        $xp = new \DOMXPath($doc);
        $serviceId = $this->text($xp, '//*[local-name()="ServiceID"]');
        $orderKey  = $this->text($xp, '//*[local-name()="OrderKey"]');
        $paymentId = $this->text($xp, '//*[local-name()="PaymentID"]');
        $invoiceId = $this->text($xp, '//*[local-name()="InvoiceID"]');
        $total     = $this->text($xp, '//*[local-name()="TotalAmount"]');
        $currency  = $this->text($xp, '//*[local-name()="Currency"]');
        $paidAt    = $this->text($xp, '//*[local-name()="PaidAt"]');

        $opts = \mpay_vg_get_settings();
        $resolvedOrderId = OrderMapper::resolve_order_id($orderKey);
        if (!$serviceId || $serviceId !== ($opts['service_id'] ?? null)) {
            $this->log_event('ConfirmOrderPayment', $resolvedOrderId, $invoiceId, $paymentId, floatval($total), $currency ?: 'MDL', 'UnknownService', $ip, $start, $soap_file);
            $this->fault('UnknownService', 'ServiceID mismatch');
            return;
        }

        // Validate currency and amount (reject invalid currency and obvious overpayment mismatch)
        if ($currency && strtoupper($currency) !== 'MDL') {
            $this->log_event('ConfirmOrderPayment', $resolvedOrderId, $invoiceId, $paymentId, floatval($total), $currency, 'InvalidCurrency', $ip, $start, $soap_file);
            $this->fault('InvalidCurrency', 'Currency must be MDL');
            return;
        }
        if (!$resolvedOrderId) {
            $this->log_event('ConfirmOrderPayment', 0, $invoiceId, $paymentId, floatval($total), $currency ?: 'MDL', 'UnknownOrder', $ip, $start, $soap_file);
            $this->fault('UnknownOrder', 'Order not found');
            return;
        }

        $order_obj = wc_get_order($resolvedOrderId);
        if (!$order_obj) {
            $this->log_event('ConfirmOrderPayment', $resolvedOrderId, $invoiceId, $paymentId, floatval($total), $currency ?: 'MDL', 'UnknownOrder', $ip, $start, $soap_file);
            $this->fault('UnknownOrder', 'Order not found');
            return;
        }
        $order_total = (float) $order_obj->get_total();
        $total_f = (float) $total;
        if ($total_f > $order_total + 0.01) {
            $this->log_event('ConfirmOrderPayment', $resolvedOrderId, $invoiceId, $paymentId, $total_f, $currency ?: 'MDL', 'AmountMismatch', $ip, $start, $soap_file);
            $this->fault('AmountMismatch', 'Total does not match order');
            return;
        }

        $payload = [
            'ServiceID'=>$serviceId, 'OrderKey'=>$orderKey, 'PaymentID'=>$paymentId,
            'InvoiceID'=>$invoiceId, 'TotalAmount'=>$total, 'Currency'=>$currency, 'PaidAt'=>$paidAt,
        ];
        $res = OrderMapper::apply_payment_confirmation($payload);

        // Per WSDL, a self-closing empty ConfirmOrderPaymentResponse with correct namespace is accepted
        $body = "<ConfirmOrderPaymentResponse xmlns=\"https://mpay.gov.md\" />";
        $env  = $this->envelope($body);
        $env  = WsSecurity::sign($env);
        $this->send($env);
        $this->log_event('ConfirmOrderPayment', $resolvedOrderId, $invoiceId, $paymentId, floatval($total), $currency ?: 'MDL', $res['msg'], $ip, $start, $soap_file);
        exit;
    }

    private function renderOrderDetails($d) {
        // Build Lines XML - OrderLine elements in ALPHABETICAL order per WSDL
        $linesXml = '';
        foreach ($d['Lines'] as $idx => $line) {
            $acc = $line['DestinationAccount'];
            $lp = ($idx === 0 && !empty($d['AllowPartialPayments'])) ? 'true' : 'false';
            $la = ($idx === 0 && !empty($d['AllowAdvancePayments'])) ? 'true' : 'false';
            $linePropsXml = self::propertiesXml($line['Properties'] ?? []);
            
            // DestinationAccount elements in alphabetical order
            $destinationXml = '<DestinationAccount>'
                .'<BankAccount>'.self::xe($acc['BankAccount']).'</BankAccount>'
                .'<BankCode>'.self::xe($acc['BankCode']).'</BankCode>'
                .'<BankFiscalCode>'.self::xe($acc['BankFiscalCode']).'</BankFiscalCode>'
                .'<BeneficiaryName>'.self::xe($acc['BeneficiaryName']).'</BeneficiaryName>';
            if (!empty($acc['ConfigurationCode'])) {
                $destinationXml .= '<ConfigurationCode>'.self::xe($acc['ConfigurationCode']).'</ConfigurationCode>';
            }
            if (!empty($acc['TreasuryAccount'])) {
                $destinationXml .= '<TreasuryAccount>'.self::xe($acc['TreasuryAccount']).'</TreasuryAccount>';
            }
            if (!empty($acc['TreasuryAccountName'])) {
                $destinationXml .= '<TreasuryAccountName>'.self::xe($acc['TreasuryAccountName']).'</TreasuryAccountName>';
            }
            $destinationXml .= '</DestinationAccount>';

            // OrderLine elements in ALPHABETICAL order
            $linesXml .= '<OrderLine>'
                .'<AllowAdvancePayments>'.$la.'</AllowAdvancePayments>'
                .'<AllowPartialPayments>'.$lp.'</AllowPartialPayments>'
                .'<AmountDue>'.self::xe(number_format((float)$line['AmountDue'], 2, '.', '')).'</AmountDue>'
                .$destinationXml
                .'<LineID>'.self::xe($line['LineID']).'</LineID>'
                .($linePropsXml !== '' ? '<Properties>'.$linePropsXml.'</Properties>' : '')
                .'<Reason>'.self::xe($line['Reason']).'</Reason>'
                .'</OrderLine>';
        }

        $propsXml = self::propertiesXml($d['Properties'] ?? []);

        // OrderDetails elements in ALPHABETICAL order per WSDL/MPay documentation
        $body = '<GetOrderDetailsResponse xmlns="https://mpay.gov.md">'
               .'<GetOrderDetailsResult>'
                 .'<OrderDetails>'
                   .'<AllowAdvancePayments>'.(!empty($d['AllowAdvancePayments'])?'true':'false').'</AllowAdvancePayments>'
                   .'<AllowPartialPayments>'.(!empty($d['AllowPartialPayments'])?'true':'false').'</AllowPartialPayments>'
                   .'<Currency>'.self::xe($d['Currency']).'</Currency>'
                   .'<CustomerID>'.self::xe($d['CustomerID']).'</CustomerID>'
                   .'<CustomerName>'.self::xe($d['CustomerName']).'</CustomerName>'
                   .'<CustomerType>'.self::xe($d['CustomerType']).'</CustomerType>'
                   .'<DueDate>'.self::xe($d['DueDate']).'</DueDate>'
                   .'<IssuedAt>'.self::xe($d['IssuedAt']).'</IssuedAt>'
                   .'<Lines>'.$linesXml.'</Lines>'
                   .'<OrderKey>'.self::xe($d['OrderKey']).'</OrderKey>'
                   .($propsXml !== '' ? '<Properties>'.$propsXml.'</Properties>' : '')
                   .'<Reason>'.self::xe($d['Reason']).'</Reason>'
                   .'<ServiceID>'.self::xe($d['ServiceID']).'</ServiceID>'
                   .'<Status>'.self::xe($d['Status']).'</Status>'
                   .'<TotalAmountDue>'.self::xe(number_format((float)$d['TotalAmountDue'], 2, '.', '')).'</TotalAmountDue>'
                 .'</OrderDetails>'
               .'</GetOrderDetailsResult>'
            .'</GetOrderDetailsResponse>';
        return $body;
    }

    private static function propertiesXml(array $props) {
        $xml = '';
        foreach ($props as $prop) {
            $rawName = $prop['Name'] ?? '';
            $rawDisplay = $prop['DisplayName'] ?? $rawName;
            $rawValue = $prop['Value'] ?? '';
            $name = self::xe(self::str_limit(preg_replace('/[^A-Za-z0-9 ]/', '', (string) $rawName), 36));
            $display = self::xe(self::str_limit(self::normalize_whitespace($rawDisplay), 36));
            $value = self::xe(self::str_limit(self::normalize_whitespace($rawValue), 255));
            $typeRaw = isset($prop['Type']) ? trim((string) $prop['Type']) : '';
            $type = $typeRaw !== '' ? self::xe(self::str_limit($typeRaw, 36)) : '';
            $requiredFlag = $prop['Required'] ?? null;
            $modifiableFlag = $prop['Modifiable'] ?? null;
            if ($name === '' && $value === '') {
                continue;
            }
            // OrderProperty elements in ALPHABETICAL order per WSDL
            $xml .= '<OrderProperty>'
                .'<DisplayName>'.$display.'</DisplayName>';
            if ($modifiableFlag !== null) {
                $xml .= '<Modifiable>'.(!empty($modifiableFlag) ? 'true' : 'false').'</Modifiable>';
            }
            $xml .= '<Name>'.$name.'</Name>';
            if ($requiredFlag !== null) {
                $xml .= '<Required>'.(!empty($requiredFlag) ? 'true' : 'false').'</Required>';
            }
            if ($type !== '') {
                $xml .= '<Type>'.$type.'</Type>';
            }
            $xml .= '<Value>'.$value.'</Value>'
                .'</OrderProperty>';
        }
        return $xml;
    }

    private static function xe($value) {
        // XML escaping that leaves UTF-8 diacritics intact.
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private static function str_limit($value, $limit) {
        $value = (string) $value;
        if ($limit <= 0) {
            return $value;
        }
        if (function_exists('mb_substr')) {
            if (mb_strlen($value) > $limit) {
                return mb_substr($value, 0, $limit);
            }
            return $value;
        }
        if (strlen($value) > $limit) {
            return substr($value, 0, $limit);
        }
        return $value;
    }

    private static function normalize_whitespace($value) {
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        return trim($value);
    }

    private function is_guard_ping($contentLength, $ip) {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            return false;
        }
        if ($contentLength > 0) {
            return false;
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '' || stripos($ua, 'mpay') !== false) {
            return true;
        }
        $knownIps = [];
        if (function_exists('apply_filters')) {
            $knownIps = (array) apply_filters('mpay_vg_guard_ping_ips', $knownIps);
        }
        return $ip && in_array($ip, $knownIps, true);
    }

    private function envelope($bodyXml) {
        $ts = gmdate('c');
        return '<?xml version="1.0" encoding="UTF-8"?>'
             . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Header>'
             . '<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">'
             . '<wsu:Timestamp xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'
             . '<wsu:Created>'.$ts.'</wsu:Created>'
             . '<wsu:Expires>'.gmdate('c', time()+300).'</wsu:Expires>'
             . '</wsu:Timestamp>'
             . '</wsse:Security>'
             . '</soapenv:Header>'
             . '<soapenv:Body wsu:Id="_1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'.$bodyXml.'</soapenv:Body>'
             . '</soapenv:Envelope>';
    }

    private function fault($code, $msg) {
        status_header(500);
        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body><soap:Fault>'
            .'<faultcode>'.esc_html($code).'</faultcode>'
            .'<faultstring>'.esc_html($msg).'</faultstring>'
            .'</soap:Fault></soap:Body></soap:Envelope>';
    }

    private function send($xml) {
        $opts = \mpay_vg_get_settings();
        $persistPath = null;
        if (!empty($opts['enable_soap_persist'])) {
            $uploads = wp_upload_dir();
            $dir = trailingslashit($uploads['basedir']).'mpay-vg/soap';
            if (!file_exists($dir)) { wp_mkdir_p($dir); }
            $fname = 'soap-response-'.date('Ymd-His').'-'.wp_generate_password(6,false,false).'.xml';
            $persistPath = trailingslashit($dir).$fname;
            file_put_contents($persistPath, $xml);
        }
        $bytes = strlen($xml);
        $sha256 = strtoupper(hash('sha256', $xml));
        $sha1 = strtoupper(hash('sha1', $xml));
        \mpay_vg_set_runtime('last_response', [
            'timestamp' => time(),
            'bytes' => $bytes,
            'sha256' => $sha256,
            'sha1' => $sha1,
            'persist_path' => $persistPath,
        ], 900);
        Logger::log('SOAP răspuns semnat livrat', [
            'component' => 'soap.server',
            'event' => 'response_sent',
            'bytes' => $bytes,
            'sha256' => $sha256,
            'sha1' => $sha1,
            'persist_path' => $persistPath,
        ]);
        status_header(200);
        header('Content-Type: text/xml; charset=utf-8');
        header('Content-Length: '.$bytes);
        $this->clear_output_buffers();
        echo $xml;
    }

    private function text(\DOMXPath $xp, $q) { $n = $xp->query($q); if (!$n || !$n->length) return null; return trim($n->item(0)->nodeValue); }

    private function log_event($op,$order_id,$invoice_id,$payment_id,$amount,$currency,$result,$ip,$start,$soap_file){
        $dur = intval(1000*(microtime(true)-$start));
        DB::insert_event([
            'op'=>$op,'order_id'=>$order_id,'invoice_id'=>$invoice_id,'payment_id'=>$payment_id,
            'amount'=>$amount,'currency'=>$currency,'result'=>$result,'ip'=>$ip,'duration_ms'=>$dur,'soap_file'=>$soap_file
        ]);
    $safeResults = ['OK','Confirmed','AlreadyConfirmed','ConfirmedDuplicate','LockedDuplicate','GuardPing'];
    if (!in_array($result, $safeResults, true)) {
            Logger::log('SOAP eveniment semnalat', [
                'component' => 'soap.server',
                'operation' => $op,
                'order_id' => $order_id,
                'invoice_id' => $invoice_id,
                'payment_id' => $payment_id,
                'amount' => $amount,
                'currency' => $currency,
                'result' => $result,
                'ip' => $ip,
                'duration_ms' => $dur,
            ], 'error');
        }
    }

    private function clear_output_buffers() : void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
