<?php
/** Private file-backed intercepted provider shared by independent PHP workers. No real Google credentials. */
if (!defined('BVM_GOOGLE_CLIENT_ID')) define('BVM_GOOGLE_CLIENT_ID','fixture-client');
if (!defined('BVM_GOOGLE_CLIENT_SECRET')) define('BVM_GOOGLE_CLIENT_SECRET','fixture-secret-not-real');
if (!defined('BVM_GOOGLE_TOKEN_KEY')) define('BVM_GOOGLE_TOKEN_KEY',base64_encode(str_repeat('F',32)));
function google_fake(callable $operation) {
    $path=getenv('BVM_GOOGLE_FAKE');
    $root = getenv('BVM_GOOGLE_FIXTURE_ROOT');
    if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1' || !$root || !is_dir($root)
        || realpath(dirname($path ?: '')) !== realpath($root) || basename($path ?: '') !== 'google-fake.json'
        || is_link($path) || str_starts_with(realpath($root), realpath(ABSPATH))) {
        throw new RuntimeException('Explicit private disposable Google fixture root required');
    }
    $f=fopen($path,'c+'); flock($f,LOCK_EX); $s=json_decode(stream_get_contents($f),true)?:array('calendars'=>array(),'events'=>array(),'calls'=>array(),'fault'=>array());
    try { $r=$operation($s); rewind($f); ftruncate($f,0); fwrite($f,json_encode($s)); fflush($f); return $r; }
    finally { flock($f,LOCK_UN); fclose($f); }
}
function google_fake_fault(string $method,string $match,$code,string $reason=''): void { google_fake(static function(&$s)use($method,$match,$code,$reason){$s['fault']=array('method'=>$method,'match'=>$match,'code'=>$code,'reason'=>$reason);}); }
function google_fake_response(int $code,array $data): array {return array('response'=>array('code'=>$code,'message'=>'fixture'),'headers'=>array(),'body'=>wp_json_encode($data),'cookies'=>array());}
add_filter('pre_http_request',static function($pre,$args,$url){
    if (!str_starts_with($url,'https://oauth2.googleapis.com/') && !str_starts_with($url,'https://www.googleapis.com/calendar/v3/')) return $pre;
    if (!empty($GLOBALS['bvmgr_tasks_transaction']) || bvmgr_staffing_transaction_active()) throw new RuntimeException('Google request in transaction');
    $result=google_fake(static function(&$s)use($args,$url){
        $method=$args['method']; $path=rawurldecode(parse_url($url,PHP_URL_PATH));
        $s['calls'][]=array('method'=>$method,'path'=>$path);
        $fault=$s['fault']; $hit=$fault && $fault['method']===$method && str_contains($path,$fault['match']);
        if ($hit) { $s['fault']=array(); if ($fault['code']!=='after') return google_fake_response((int)$fault['code'],array('error'=>($fault['reason']==='invalid_grant'?'invalid_grant':array('errors'=>array(array('reason'=>$fault['reason'])))))); }
        if ($path==='/token') {
            $body=$args['body'];
            if ($body['grant_type']==='refresh_token') return google_fake_response(200,array('access_token'=>'fixture-access','expires_in'=>3600,'refresh_token'=>'fixture-rotated-refresh'));
            $user=(int)substr($body['code'],8); $c=bvmgr_google_state()['connections'][$user];
            // Callback consumed state first, so fixture nonce is provided independently by the test.
            $claims=array('iss'=>'https://accounts.google.com','aud'=>BVM_GOOGLE_CLIENT_ID,'sub'=>$s['subject']??('google-'.$user),'exp'=>time()+3600,'iat'=>time(),'nonce'=>$s['nonce']);
            return google_fake_response(200,array('access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires_in'=>3600,'scope'=>'openid '.BVMGR_GOOGLE_SCOPE,'id_token'=>'header.'.rtrim(strtr(base64_encode(json_encode($claims)),'+/','-_'),'=').'.signature'));
        }
        $body=isset($args['body'])?json_decode($args['body'],true):array();
        if ($path==='/calendar/v3/calendars' && $method==='POST') {
            $s['calendar_sequence']=($s['calendar_sequence']??0)+1; $id='calendar-'.$s['calendar_sequence'].'@group.calendar.google.com'; $body['id']=$id; $s['calendars'][$id]=$body;
            return $hit?new WP_Error('timeout','fixture response lost'):google_fake_response(200,$body);
        }
        if (!preg_match('~^/calendar/v3/calendars/([^/]+)(?:/events(?:/([^/]+))?)?$~',$path,$m)) throw new RuntimeException('unexpected Google route');
        $cal=$m[1]; if ($cal==='primary') throw new RuntimeException('primary calendar accessed');
        if (!isset($s['calendars'][$cal])) return google_fake_response(404,array());
        if (!str_contains($path,'/events')) return google_fake_response(200,$s['calendars'][$cal]);
        $id=$m[2]??($body['id']??''); $key=$cal.':'.$id;
        if ($method==='GET') return google_fake_response(isset($s['events'][$key])?200:404,$s['events'][$key]??array());
        if (isset($body['attendees']) || isset($body['reminders'])) throw new RuntimeException('unowned Google field');
        // Independently prove both task commit and durable delivery intent before every external write.
        $reader=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $reader->set_prefix($GLOBALS['wpdb']->prefix);
        $state=json_decode($reader->get_var("SELECT option_value FROM {$reader->options} WHERE option_name='bvmgr_tasks_google_v1'"),true);
        $owned=false;
        foreach ($state['mirrors'] as $mirror) if (($state['connections'][$mirror['user']]['calendar']??'')===$cal && bvmgr_google_event_id($state['site'],$mirror['task'])===$id) {
            $revision=$reader->get_var($reader->prepare('SELECT revision FROM %i WHERE id=%d',bvmgr_tasks_table_name('task_instances'),$mirror['task']));
            if ($revision===null || empty($mirror['attempted'])) throw new RuntimeException('uncommitted work sent'); $owned=true;
        }
        $reader->close(); if (!$owned) throw new RuntimeException('durable intent missing');
        if ($method==='POST' && isset($s['events'][$key])) return google_fake_response(409,array());
        if ($method==='PATCH') {
            if (!isset($s['events'][$key])) return google_fake_response(404,array());
            if (($args['headers']['If-Match']??'')!==$s['events'][$key]['etag']) return google_fake_response(412,array());
            $body=array_replace_recursive($s['events'][$key],$body);
        }
        $body['etag']='"'.bin2hex(random_bytes(8)).'"'; $body['id']=$id; $s['events'][$key]=$body;
        if (!empty($s['pause'])) { $GLOBALS['google_fake_pause']=$s['pause']; unset($s['pause']); }
        if (!empty($s['fail_save'])) { $GLOBALS['google_fake_fail_save']=true; unset($s['fail_save']); }
        return $hit?new WP_Error('timeout','fixture remote create succeeded'):google_fake_response(200,$body);
    });
    if (!empty($GLOBALS['google_fake_pause'])) { $signal=$GLOBALS['google_fake_pause']; unset($GLOBALS['google_fake_pause']); file_put_contents($signal,'ready'); usleep(2500000); }
    return $result;
},PHP_INT_MAX,3);
add_filter('query',static function($sql){if (!empty($GLOBALS['google_fake_fail_save']) && str_starts_with($sql,'INSERT INTO') && str_contains($sql,'bvmgr_tasks_google_v1')) {unset($GLOBALS['google_fake_fail_save']);throw new RuntimeException('fixture_state_write_failed');}return $sql;},PHP_INT_MAX);
