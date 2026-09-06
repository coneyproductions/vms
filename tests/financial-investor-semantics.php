<?php
/** Run against an isolated 0.2.3 source copy with the companion patch applied. */
declare(strict_types=1);
define('ABSPATH', __DIR__);
function __($s, $d = '') { return $s; }
$path = getenv('BVM_FINANCIAL_INVESTOR_CANDIDATE');
if (!$path || !is_file($path)) { throw new RuntimeException('Supply the isolated patched Investor provider file.'); }
$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/docs/financial-authority-investor-baseline.json'), true);
if (hash_file('sha256', $path) !== $manifest['candidate_sha256']) { throw new RuntimeException('Unexpected Investor candidate provenance'); }
require $path;
$method = new ReflectionMethod(VMS_Investor_Metrics_Provider::class, 'annotate_financial_authority');
$metrics = array();
foreach (array('ticket_revenue', 'labor_cost', 'performer_cost', 'estimated_event_net', 'concession_cost') as $key) {
    $metrics[$key] = array('kind' => 'currency', 'available' => true, 'value' => 0.0, 'auto_value' => 10.0, 'auto_source_type' => 'auto_actual', 'source_type' => 'auto_actual');
}
$out = $method->invoke(VMS_Investor_Metrics_Provider::instance(), $metrics);
foreach (array('labor_cost', 'performer_cost', 'estimated_event_net', 'concession_cost') as $key) {
    if ($out[$key]['financial_basis'] !== 'FORECAST' || $out[$key]['source_type'] !== 'fallback' || $out[$key]['finalized']) { throw new RuntimeException('Model falsely labeled actual: ' . $key); }
}
if ($out['ticket_revenue']['financial_basis'] !== 'TRANSACTIONAL_ACTUAL') { throw new RuntimeException('Transaction basis lost'); }
$out = $method->invoke(VMS_Investor_Metrics_Provider::instance(), $metrics, true);
if ($out['ticket_revenue']['financial_basis'] !== 'RECORDED_ACTUAL') { throw new RuntimeException('Cached record labeled current'); }
$metrics['ticket_revenue']['manual_override_active'] = true;
$out = $method->invoke(VMS_Investor_Metrics_Provider::instance(), $metrics, true);
if ($out['ticket_revenue']['financial_basis'] !== 'MANUAL_ACTUAL' || $out['ticket_revenue']['value'] !== 0.0 || $out['ticket_revenue']['auto_value'] !== 10.0) { throw new RuntimeException('Explicit zero manual override or underlying evidence changed'); }
$metrics['ticket_revenue']['available'] = false;
$out = $method->invoke(VMS_Investor_Metrics_Provider::instance(), $metrics);
if ($out['ticket_revenue']['financial_basis'] !== 'UNAVAILABLE') { throw new RuntimeException('Unavailable amount labeled actual'); }
echo "Investor financial semantics PASS (model, cached, override, zero, unavailable)\n";
