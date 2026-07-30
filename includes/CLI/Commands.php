<?php
namespace MPAY_VG\CLI;
use MPAY_VG\Core\DiagnosticsSnapshot;
use MPAY_VG\Core\DiagnosticsTools;
if (!defined('ABSPATH')) { exit; }

class Commands {
  public static function register(){
    \WP_CLI::add_command('mpay check', [__CLASS__,'check']);
    \WP_CLI::add_command('mpay diagnostics snapshot', [__CLASS__,'diagnostics_snapshot']);
    \WP_CLI::add_command('mpay diagnostics soap', [__CLASS__,'diagnostics_soap']);
    \WP_CLI::add_command('mpay diagnostics create-order', [__CLASS__,'diagnostics_create_order']);
    \WP_CLI::add_command('mpay wssec inspect', [__CLASS__,'wssec_inspect']);
  }
  public static function check($args, $assoc){
    $order_id = intval($assoc['order'] ?? 0);
    if (!$order_id){ \WP_CLI::error('Specificați --order=<id>'); return; }
    \MPAY_VG\Core\Invoices::maybe_attach_invoice_pdf($order_id);
    \WP_CLI::success("Order #$order_id reconciled (InvoiceID/PDF fetch attempted).");
  }

  public static function diagnostics_snapshot($args, $assoc) {
    $orderQuery = $assoc['order'] ?? '';
    $snapshot = DiagnosticsSnapshot::build([
        'order_query' => $orderQuery,
        'soap_limit' => isset($assoc['soap-limit']) ? (int) $assoc['soap-limit'] : 20,
        'db_limit' => isset($assoc['db-limit']) ? (int) $assoc['db-limit'] : 20,
        'debug_limit' => isset($assoc['debug-limit']) ? (int) $assoc['debug-limit'] : 20,
        'availability_limit' => isset($assoc['availability-limit']) ? (int) $assoc['availability-limit'] : 50,
    ]);
    \WP_CLI::print_value([
        'generated_at' => gmdate('c'),
        'snapshot' => $snapshot,
    ], ['format' => 'json_pretty']);
  }

  public static function diagnostics_soap($args, $assoc) {
    $snapshot = DiagnosticsSnapshot::build([
        'soap_limit' => isset($assoc['limit']) ? (int) $assoc['limit'] : 20,
        'settings' => \mpay_vg_get_settings(),
    ]);
    $files = $snapshot['soap']['persisted_samples'] ?? [];
    if (!$files) {
        \WP_CLI::log('Nu există fișiere SOAP persistente sau funcția este dezactivată.');
        return;
    }
    $table = array_map(function($file){
        return [
            'name' => $file['name'] ?? '',
            'size' => $file['size'] ?? '',
            'modified' => $file['modified'] ?? '',
            'path' => $file['path'] ?? '',
        ];
    }, $files);
    \WP_CLI\Utils\format_items('table', $table, ['name','size','modified','path']);
  }

  public static function diagnostics_create_order($args, $assoc) {
    $amount = isset($assoc['amount']) ? (float) $assoc['amount'] : 250.0;
    $reason = $assoc['reason'] ?? 'MPay Diagnostics Test';
    $payload = DiagnosticsTools::create_test_order([
        'amount' => $amount,
        'reason' => $reason,
    ]);
    if (empty($payload['success'])) {
        \WP_CLI::error($payload['message'] ?? 'Nu am putut crea comanda.');
        return;
    }
    \WP_CLI::success($payload['message'] ?? 'Comanda de test creată.');
    if (!empty($payload['order'])) {
        \WP_CLI::print_value($payload['order'], ['format' => 'yaml']);
    }
  }

    public static function wssec_inspect($args, $assoc) {
    $lastSignature = \mpay_vg_get_runtime('last_signature', []);
    $lastVerify = \mpay_vg_get_runtime('last_verify', []);
    $lastResponse = \mpay_vg_get_runtime('last_response', []);
    $output = [
      'last_signature' => is_array($lastSignature) ? $lastSignature : [],
      'last_verification' => is_array($lastVerify) ? $lastVerify : [],
      'last_response' => is_array($lastResponse) ? $lastResponse : [],
    ];

    if (!empty($assoc['persisted'])) {
      $path = $assoc['persisted'];
      if (!file_exists($path)) {
        \WP_CLI::warning('Fișierul specificat la --persisted nu există: '.$path);
      } else {
        $output['persisted_file'] = self::hash_file($path);
      }
    }

    if (!empty($assoc['compare'])) {
      $path = $assoc['compare'];
      if (!file_exists($path)) {
        \WP_CLI::warning('Fișierul specificat la --compare nu există: '.$path);
      } else {
        $output['comparison'] = self::hash_file($path);
      }
    }

    $format = $assoc['format'] ?? 'json_pretty';
    \WP_CLI::print_value($output, ['format' => $format]);
    }

    private static function hash_file($path) {
    $raw = @file_get_contents($path);
    if ($raw === false) {
      return [
        'path' => $path,
        'error' => 'Nu pot citi fișierul',
      ];
    }
    return [
      'path' => $path,
      'bytes' => strlen($raw),
      'sha256' => strtoupper(hash('sha256', $raw)),
      'sha1' => strtoupper(hash('sha1', $raw)),
      'md5' => strtoupper(hash('md5', $raw)),
      'preview' => base64_encode(substr($raw, 0, 120)),
    ];
    }
}
