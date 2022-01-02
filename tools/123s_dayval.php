#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
$path = '/srv/http/123solar';
$invtnum = 1;

define('checkaccess', TRUE);
include "$path/config/config_invt$invtnum.php";

$dir    = $path . '/data/invt' . $invtnum . '/production/';
$output = glob($dir . '*.csv');
rsort($output);
//print_r($output);
if (isset($output[0])) {
	$lines       = file($output[0]);
	$cntlines = count($lines);
	$array = preg_split('/,/', $lines[$cntlines - 1]);
	$year  = substr($array[0], 0, 4);
	$month = substr($array[0], 4, 2);
	$day   = substr($array[0], 6, 2);
	$production = round((floatval($array[1]) * ${'CORRECTFACTOR' . $invtnum}), 1);
}

echo "$day/$month/$year $production kWh";
    
?>
