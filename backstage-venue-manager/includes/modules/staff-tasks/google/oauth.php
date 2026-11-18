<?php
defined('ABSPATH') || exit;

function bvmgr_google_redirect_uri(): string
{
    $url=admin_url('admin-post.php?action=bvmgr_google_callback','https');
    $site=site_url(); $host=wp_parse_url($site,PHP_URL_HOST);
    if (in_array($host,array('localhost','127.0.0.1','[::1]'),true) && wp_parse_url($site,PHP_URL_SCHEME)==='http') $url=set_url_scheme($url,'http');
    return $url;
}
function bvmgr_google_page_url(): string { return admin_url('admin.php?page=bvm-google-tasks'); }
function bvmgr_google_begin(int $user, string $session)
{
    if (!bvmgr_google_configured() || !bvmgr_google_allowed_user($user) || $session==='') return new WP_Error('google_forbidden','A configured site and authenticated task assignee are required.');
    return bvmgr_google_exclusive(static function() use ($user,$session) {
        $state=bvmgr_google_state();
        if (empty($state['site'])) $state['site']=bin2hex(random_bytes(16));
        $c=$state['connections'][$user]??array('status'=>'not_connected','calendar'=>'','calendar_state'=>'new','calendar_marker'=>bin2hex(random_bytes(24)),'subject'=>'','tokens'=>'');
        $nonce=bin2hex(random_bytes(32)); $csrf=bin2hex(random_bytes(32));
        $c['oauth']=array('state_hash'=>hash('sha256',$csrf),'session_hash'=>hash('sha256',$session),'nonce'=>$nonce,'expires'=>time()+600);
        $state['connections'][$user]=$c; bvmgr_google_save($state);
        return add_query_arg(array('client_id'=>BVM_GOOGLE_CLIENT_ID,'redirect_uri'=>bvmgr_google_redirect_uri(),'response_type'=>'code',
            'scope'=>'openid '.BVMGR_GOOGLE_SCOPE,'access_type'=>'offline','prompt'=>'consent select_account','state'=>$csrf,'nonce'=>$nonce), 'https://accounts.google.com/o/oauth2/v2/auth');
    });
}

/** Token endpoint response arrives directly over validated TLS; validate its OIDC claims and nonce. */
function bvmgr_google_claims(string $jwt, string $nonce): ?array
{
    $parts=explode('.',$jwt); if (count($parts)!==3) return null;
    $claims=json_decode((string)base64_decode(strtr($parts[1],'-_','+/'),true),true);
    if (!is_array($claims) || !in_array($claims['iss']??'',array('https://accounts.google.com','accounts.google.com'),true)
        || ($claims['aud']??'')!==BVM_GOOGLE_CLIENT_ID || (isset($claims['azp']) && $claims['azp']!==BVM_GOOGLE_CLIENT_ID)
        || !is_int($claims['exp']??null) || !is_int($claims['iat']??null) || strlen((string)($claims['sub']??''))>255
        || ($claims['exp']??0)<=time() || ($claims['iat']??0)>time()+60 || !is_string($claims['sub']??null) || $claims['sub']===''
        || !is_string($claims['nonce']??null) || !hash_equals($nonce,$claims['nonce'])) return null;
    return $claims;
}
function bvmgr_google_callback(int $user, string $session, string $csrf, string $code)
{
    if (!bvmgr_google_configured() || !bvmgr_google_allowed_user($user) || $session==='' || strlen($csrf)!==64 || strlen($code)>4096) return false;
    return bvmgr_google_exclusive(static function() use ($user,$session,$csrf,$code) {
        $state=bvmgr_google_state(); $c =& $state['connections'][$user]; $oauth=$c['oauth']??array();
        if (empty($oauth) || $oauth['expires']<time() || !hash_equals($oauth['state_hash'],hash('sha256',$csrf)) || !hash_equals($oauth['session_hash'],hash('sha256',$session))) return false;
        unset($c['oauth']); bvmgr_google_save($state); // One use, including denial/failure.
        if ($code==='') return false;
        $r=bvmgr_google_token(array('grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>bvmgr_google_redirect_uri()));
        if ($r['code']!==200) { $c['oauth_error']='authorization_failed'; bvmgr_google_save($state); return false; }
        $claims=bvmgr_google_claims((string)($r['data']['id_token']??''),$oauth['nonce']);
        $scopes=explode(' ',(string)($r['data']['scope']??''));
        if (!$claims || !in_array(BVMGR_GOOGLE_SCOPE,$scopes,true) || array_diff($scopes,array('openid',BVMGR_GOOGLE_SCOPE))) { $c['oauth_error']='authorization_incomplete'; bvmgr_google_save($state); return false; }
        // Reconnect must use the original Google subject. Email equality is never identity proof.
        if (!empty($c['subject']) && $c['subject']!==$claims['sub']) { $c['oauth_error']='different_google_account'; bvmgr_google_save($state); return false; }
        foreach ($state['connections'] as $other=>$connection) if ((int)$other!==$user && ($connection['subject']??'')===$claims['sub']) { $c['oauth_error']='google_account_already_linked'; bvmgr_google_save($state); return false; }
        $tokens=bvmgr_google_tokens($r['data'],bvmgr_google_unseal($c['tokens']??''));
        if (!$tokens) { $c['oauth_error']='offline_authorization_required'; bvmgr_google_save($state); return false; }
        $c['client_hash']=hash('sha256',BVM_GOOGLE_CLIENT_ID); $c['subject']=$claims['sub']; $c['tokens']=bvmgr_google_seal($tokens); $c['status']='connected'; $c['oauth_error']='';
        bvmgr_google_save($state); // Calendar/initial synchronization happens in the worker.
        return true;
    });
}
function bvmgr_google_disconnect(array &$state, int $user): void
{
    // Stop and erase usable local credentials first. Keep subject/calendar identity for same-account reconnect.
    $c =& $state['connections'][$user];
    $c['status']='not_connected'; $c['tokens']=''; unset($c['oauth']);
    foreach ($state['mirrors'] as &$m) if ($m['user']===$user) $m['state']='not_connected';
    unset($m); bvmgr_google_save($state);
    // No remote calendar/event deletion and no project-wide revocation that could revoke another site.
}
