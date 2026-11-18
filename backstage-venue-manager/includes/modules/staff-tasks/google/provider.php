<?php
defined('ABSPATH') || exit;

const BVMGR_GOOGLE_SCOPE = 'https://www.googleapis.com/auth/calendar.app.created';

/** All transport results are reduced to safe codes before they reach persistence/UI. */
function bvmgr_google_http(string $method, string $url, array $args = array()): array
{
    if (!empty($GLOBALS['bvmgr_tasks_transaction']) || bvmgr_staffing_transaction_active()) throw new RuntimeException('google_transport_in_transaction');
    $response = wp_remote_request($url, array_merge(array('method'=>$method, 'timeout'=>15, 'redirection'=>0, 'limit_response_size'=>1048576, 'sslverify'=>true), $args));
    if (is_wp_error($response)) return array('code'=>0, 'data'=>array(), 'reason'=>'network');
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $code = (int)wp_remote_retrieve_response_code($response);
    $reason = is_array($data) ? ($data['error']['errors'][0]['reason'] ?? (is_string($data['error'] ?? null) ? $data['error'] : '')) : '';
    $allowed = array('invalid_grant','invalid_client','rateLimitExceeded','userRateLimitExceeded','quotaExceeded','forbidden','insufficientPermissions','notFound','duplicate','conditionNotMet');
    return array('code'=>$code, 'data'=>is_array($data)?$data:array(), 'reason'=>in_array($reason,$allowed,true)?$reason:'provider_error');
}
function bvmgr_google_token(array $body): array
{
    return bvmgr_google_http('POST', 'https://oauth2.googleapis.com/token', array('body'=>array_merge($body, array('client_id'=>BVM_GOOGLE_CLIENT_ID, 'client_secret'=>BVM_GOOGLE_CLIENT_SECRET))));
}
function bvmgr_google_tokens(array $data, array $prior = array()): array
{
    if (empty($data['access_token']) || !is_string($data['access_token']) || empty($data['expires_in'])) return array();
    $refresh = $data['refresh_token'] ?? ($prior['refresh_token'] ?? '');
    if (!is_string($refresh) || $refresh === '') return array();
    return array('access_token'=>$data['access_token'], 'refresh_token'=>$refresh, 'expires'=>time() + min(86400, max(1,(int)$data['expires_in'])));
}
function bvmgr_google_api(array &$state, int $user, string $method, string $path, ?array $body = null, string $etag = ''): array
{
    $connection =& $state['connections'][$user];
    if (($connection['client_hash']??'')!==hash('sha256',BVM_GOOGLE_CLIENT_ID)) { $connection['status']='authorization_required'; bvmgr_google_save($state); }
    if (($connection['status'] ?? '') !== 'connected') return array('code'=>401, 'reason'=>'authorization_required', 'data'=>array());
    $tokens = bvmgr_google_unseal($connection['tokens'] ?? '');
    for ($attempt=0; $attempt<2; $attempt++) {
        if (empty($tokens['refresh_token'])) { $connection['status']='authorization_required'; bvmgr_google_save($state); return array('code'=>401,'reason'=>'authorization_required','data'=>array()); }
        if (($tokens['expires'] ?? 0) <= time()+60 || $attempt===1) {
            $r = bvmgr_google_token(array('grant_type'=>'refresh_token','refresh_token'=>$tokens['refresh_token']));
            if ($r['code']!==200) {
                if (in_array($r['reason'],array('invalid_grant','invalid_client'),true) || in_array($r['code'],array(400,401,403),true)) { $connection['status']='authorization_required'; $connection['tokens']=''; bvmgr_google_save($state); $r['code']=401; }
                return $r;
            }
            $tokens = bvmgr_google_tokens($r['data'], $tokens);
            if (!$tokens) { $connection['status']='authorization_required'; bvmgr_google_save($state); return array('code'=>401,'reason'=>'authorization_required','data'=>array()); }
            $connection['tokens']=bvmgr_google_seal($tokens); bvmgr_google_save($state);
        }
        $args = array('headers'=>array('Authorization'=>'Bearer '.$tokens['access_token']));
        if ($body!==null) { $args['headers']['Content-Type']='application/json'; $args['body']=wp_json_encode($body); }
        if ($etag!=='') $args['headers']['If-Match']=$etag;
        $r = bvmgr_google_http($method,'https://www.googleapis.com/calendar/v3/'.$path,$args);
        if ($r['code']!==401) return $r;
    }
    $connection['status']='authorization_required'; $connection['tokens']=''; bvmgr_google_save($state);
    return array('code'=>401,'reason'=>'authorization_required','data'=>array());
}
function bvmgr_google_failure(array $response, int $attempt): array
{
    $code = $response['code'];
    if ($code===401) return array('state'=>'authorization_required','next'=>0);
    $transient = $code===0 || $code===429 || $code>=500 || $code===412 || $code===409 || $code===404 || $code===410 || ($code===403 && in_array($response['reason'],array('rateLimitExceeded','userRateLimitExceeded','quotaExceeded'),true));
    return array('state'=>$transient && $attempt<8 ? 'failed_retryable':'permanent_failure', 'next'=>$transient && $attempt<8 ? time()+min(21600,60*(2**min($attempt,8)))+random_int(0,30):0);
}
