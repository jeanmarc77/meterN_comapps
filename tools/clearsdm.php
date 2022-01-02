#!/usr/bin/php
<?php
// A simple script to clear a stuck com port
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
date_default_timezone_set('Europe/Brussels');
$lockfile = '/var/lock/LCK..sdm';
$mnfile   = '/tmp/sdm_log.txt';

if (file_exists($lockfile)) {
    $now = time();
    $ft  = filemtime($lockfile);
    if ($now - $ft > 30) { // 30 sec
        exec("cp /tmp/lastlog.log /tmp/bug$now.log");
        exec('pkill -f sdm120c');
        sleep(2);
        if (file_exists($lockfile)) {
            exec("rm -f $lockfile");
        }
        if (file_exists($mnfile)) {
            file_put_contents($mnfile, '', FILE_APPEND);
            chmod($mnfile, 0666);
        }
        $output = date('Ymd H:i:s') . "\n";
        file_put_contents('/tmp/clearsdm.txt', $output, FILE_APPEND);
        
    }
}
?>
