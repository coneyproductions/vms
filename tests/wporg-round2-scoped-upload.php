<?php
$source = file_get_contents(__DIR__ . '/../includes/core/private-files.php');
$a = strpos($source, 'if (!function_exists(\'bvmgr_private_files_with_scoped_upload_dir\'))');
$b = strpos($source, 'if (!function_exists(\'bvmgr_private_files_install_schema\'))', $a);
eval(substr($source, $a, $b - $a));
$filters = array();
function has_filter($hook, $callback) { global $filters; return $filters[$hook][$callback] ?? false; }
function add_filter($hook, $callback, $priority = 10) { global $filters; $filters[$hook][$callback] = $priority; }
function remove_filter($hook, $callback) { global $filters; unset($filters[$hook][$callback]); }
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS $message\n"; }
$source = file_get_contents(__DIR__ . '/../includes/admin/data-tools/actions-event-plan-import.php');
$a = strpos($source, "if (!function_exists('bvmgr_event_plan_import_with_scoped_upload_dir'))");
$b = strpos($source, "if (!function_exists('bvmgr_event_plan_import_redirect_storage_failure'))", $a);
eval(substr($source, $a, $b - $a));
foreach (array(
 array('bvmgr_private_files_with_scoped_upload_dir', 'bvmgr_private_files_upload_dir_context', 'bvmgr_private_files_filter_upload_dir'),
 array('bvmgr_event_plan_import_with_scoped_upload_dir', 'bvmgr_event_plan_import_upload_dir_context', 'bvmgr_event_plan_import_filter_upload_dir'),
) as [$scope, $key, $filter]) {
 foreach (array(RuntimeException::class, Error::class) as $exception) {
  $scope(array('outer'), function () use ($scope, $key, $filter, $exception) {
   try { $scope(array('inner'), function () use ($exception) { throw new $exception('fixture'); }); }
   catch (Throwable $error) { expect($error instanceof $exception, 'original exception preserved'); }
   expect($GLOBALS[$key] === array('outer') && has_filter('upload_dir', $filter) === 10, 'nested failure restores outer context and filter: ' . $scope);
  });
  expect(!array_key_exists($key, $GLOBALS) && has_filter('upload_dir', $filter) === false, 'outer scope restores absent state: ' . $scope);
 }
 $GLOBALS[$key] = null;
 add_filter('upload_dir', $filter, 7);
 expect($scope(array('temporary'), function () { return 'value'; }) === 'value', 'return value preserved');
 expect(array_key_exists($key, $GLOBALS) && $GLOBALS[$key] === null && has_filter('upload_dir', $filter) === 7, 'existing null context and nondefault filter restored exactly: ' . $scope);
 unset($GLOBALS[$key]); remove_filter('upload_dir', $filter);
}
