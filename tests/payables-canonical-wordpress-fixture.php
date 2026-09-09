<?php
/** Current bill-model contract. No payable/payment rows or exporter companions are required. */
require __DIR__ . '/helpers/current-wordpress-fixture.php';
$checks = 0; $created = array();
function payable_check($ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
function payable_post(string $type, string $title): int {
    global $created;
    $id = wp_insert_post(array('post_type'=>$type,'post_status'=>'draft','post_title'=>$title),true);
    payable_check(!is_wp_error($id) && $id > 0, 'Synthetic post created: '.$title);
    $created[] = $id;
    return $id;
}
function payable_meta(int $id, string $type, array $values): void {
    foreach ($values as $name=>$value) {
        $key = bvmgr_meta_key($type,$name);
        payable_check(is_string($key) && $key !== '', 'Canonical key: '.$type.'.'.$name);
        update_post_meta($id,$key,$value);
    }
}
function payable_database_manifest(): array {
    global $wpdb;
    $manifest = array();
    foreach ($wpdb->get_col('SHOW TABLES') as $table) {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i',$table),ARRAY_A);
        $hashes = array_map(static fn($row)=>hash('sha256',serialize($row)),$rows);
        sort($hashes); $manifest[$table] = hash('sha256',serialize($hashes));
    }
    ksort($manifest); return $manifest;
}
try {
    $venue = payable_post('vms_venue','Synthetic Payables Venue');
    $vendor = payable_post('vms_vendor','Synthetic Vendor Title');
    $support = payable_post('vms_vendor','Synthetic Supporting Vendor');
    // Explicit canonical admin-completed workflow; no invented attachment IDs or private W-9 files.
    payable_meta($vendor,'vendor',array('payee_dba'=>'Synthetic DBA','payee_legal_name'=>'Synthetic Legal','tax_profile_completed_at'=>1893456000));
    payable_meta($support,'vendor',array('payee_legal_name'=>'Synthetic Support Legal','tax_profile_completed_at'=>1893456000));
    payable_check(bvmgr_is_vendor_tax_profile_complete($vendor), 'Canonical completion recognized');
    $plans = array();
    foreach (array('flat_fee'=>'$1,250.50','attendance_bonus'=>'100.25','flat_fee_door_split'=>'50') as $structure=>$amount) {
        $plan = payable_post('vms_event_plan','Synthetic '.$structure);
        payable_meta($plan,'event_plan',array('date'=>'2030-03-10','venue_id'=>$venue,'band_vendor_id'=>$vendor,'comp_structure'=>$structure,'flat_fee_amount'=>$amount));
        update_post_meta($plan,'_vms_start_time','18:00'); update_post_meta($plan,'_vms_end_time','22:00');
        payable_check(get_post_meta($plan,bvmgr_meta_key('event_plan','date'),true) === '2030-03-10', 'Post-migration date seeded');
        payable_meta($plan,'event_plan',array('status'=>'published'));
        $plans[] = $plan;
    }
    $saved = bvmgr_save_event_plan_lineup_entries($plans[0],array(
        array('row_id'=>'fixture_primary','role'=>'primary','vendor_id'=>$vendor,'sort_order'=>0),
        array('row_id'=>'fixture_support','role'=>'supporting','vendor_id'=>$support,'sort_order'=>1,'guaranteed_fee'=>'75.25'),
    ));
    payable_check(count($saved['entries']) === 2, 'Canonical lineup normalization creates primary/supporting relationships');
    $before = payable_database_manifest();
    $result = bvmgr_payables_build_bills_for_export('2030-03-10',array($venue),array('terms_days'=>1));
    payable_check($before === payable_database_manifest(), 'All '.count($before).' disposable tables unchanged by export read');
    payable_check(count($result['bills']) === 2, 'One bill per vendor/date/venue group');
    $byVendor = array_column($result['bills'],null,'vendor_id');
    payable_check($byVendor[$vendor]['supplier'] === 'Synthetic DBA' && count($byVendor[$vendor]['lines']) === 3, 'DBA precedence and grouped primary lines');
    payable_check(array_sum(array_column($byVendor[$vendor]['lines'],'amount')) === 1400.75, 'Guaranteed compensation amounts preserved');
    payable_check($byVendor[$support]['supplier'] === 'Synthetic Support Legal' && $byVendor[$support]['lines'][0]['amount'] === 75.25, 'Supporting fee uses its own legal payee');
    payable_check($byVendor[$vendor]['due_date'] === '2030-03-11' && !$byVendor[$vendor]['payment_blocked'], 'DST date terms and tax-complete state');
    $reference = $result;
    foreach (array('UTC','America/Chicago','Asia/Tokyo') as $timezone) {
        date_default_timezone_set($timezone);
        payable_check(bvmgr_payables_build_bills_for_export('2030-03-10',array($venue),array('terms_days'=>1)) === $reference, 'Export is machine-timezone independent: '.$timezone);
    }
    delete_post_meta($vendor,bvmgr_meta_key('vendor','payee_dba'));
    payable_check(bvmgr_payables_resolve_vendor_payee_name($vendor) === 'Synthetic Legal', 'Legal-name fallback');
    delete_post_meta($vendor,bvmgr_meta_key('vendor','payee_legal_name'));
    payable_check(bvmgr_payables_resolve_vendor_payee_name($vendor) === 'Synthetic Vendor Title', 'Title fallback');
    delete_post_meta($vendor,bvmgr_meta_key('vendor','tax_profile_completed_at'));
    $blocked = bvmgr_payables_build_bills_for_export('2030-03-10',array($venue));
    payable_check(count($blocked['bills']) === 1 && count($blocked['warnings']) > 0, 'Incomplete primary excluded with explicit warning; supporting bill retained');
    $flagged = bvmgr_payables_build_bills_for_export('2030-03-10',array($venue),array('include_tax_incomplete'=>true));
    $flagged = array_column($flagged['bills'],null,'vendor_id');
    payable_check($flagged[$vendor]['payment_blocked'] && $flagged[$vendor]['tax_missing'], 'Explicit incomplete export retains payment-blocked flag');
    foreach ($plans as $plan) payable_meta($plan,'event_plan',array('status'=>'cancelled'));
    payable_check(bvmgr_payables_build_bills_for_export('2030-03-10',array($venue))['bills'] === array(), 'Cancelled workflow excluded');
    payable_check(bvmgr_payables_build_bills_for_export('',array())['bills'] === array(), 'Missing query scope yields no bills');
} finally {
    date_default_timezone_set('UTC');
    foreach (array_reverse($created) as $id) wp_delete_post($id,true);
    foreach ($created as $id) payable_check(get_post($id) === null, 'Synthetic post removed: '.$id);
}
echo "PASS $checks canonical payables fixture assertions; remaining database state owned and destroyed by supervisor\n";
