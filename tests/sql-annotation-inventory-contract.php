<?php
require __DIR__.'/helpers/sql-annotation-inventory.php';
$root = sys_get_temp_dir(); $path = tempnam($root,'annotation-'); $checks=0;
$annotation = '// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- owned probe';
$source = "<?php\nfunction first() {\n$annotation\n}\nfunction second() {}\n";
$check = static function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
try {
    file_put_contents($path,$source);$baseline=bvm_test_sql_annotation_inventory(array($path),$root);
    $check(count($baseline)===1 && str_contains($baseline[0],':first#1:'),'Named owner and ordinal');
    file_put_contents($path,str_replace('function first',"\n\n// function fake() {}\nfunction first",$source));
    $check($baseline===bvm_test_sql_annotation_inventory(array($path),$root),'Line drift and comment declarations ignored');
    file_put_contents($path,str_replace('function first','function changed',$source));
    $check($baseline!==bvm_test_sql_annotation_inventory(array($path),$root),'Changed owner rejected');
    file_put_contents($path,str_replace($annotation,$annotation."\n".$annotation,$source));
    $check($baseline!==bvm_test_sql_annotation_inventory(array($path),$root),'Added suppression rejected');
    file_put_contents($path,str_replace('DirectQuery --','NoCaching --',$source));
    $check($baseline!==bvm_test_sql_annotation_inventory(array($path),$root),'Changed rule codes rejected');
} finally { unlink($path); }
$check(!file_exists($path),'Fixture file cleanup');
echo "PASS $checks SQL annotation inventory controls\n";
