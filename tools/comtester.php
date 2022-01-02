<?php
// A simple script to test your com app reliability hence you can adjust the parameters. 
// Use on CLI only (php comtester.php). Turn off 123solar and/or meterN while testing.

//$command = '485solar-get -d -n 0';
//$command = 'sdm120c -a1 -b9600 -2 -qpievfg /dev/sdm';
//$command = '485solar-get -d -n 1';
//$command = 'SBFspot -finq -q -123s=DATA -cfg ../123solar/config/SBFspot_0.cfg';
$command = 'aurora -a 2 -c -T -Y3 -d0 -e -l3 /dev/solar';
//
$try       = 15;
$timemax   = 0;
$timemin   = 10000000;
$log       = 'comtest.log';
$errcnt    = 0;

// No edit should be needed bellow
date_default_timezone_set('Europe/Brussels');
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
$percent = 0;
for ($i = 1; $i <= $try; $i++) {
    system('clear');
    echo "Testing $command ($percent%)\n\n";
    if(isset($output[0])) {
    echo($output[0]);
    }
    $start = microtime(true);
    $output=null;
    exec("$command", $output, $error);
    if ($error == 0) {
        $time_elapsed_secs = microtime(true) - $start;
        if ($time_elapsed_secs > $timemax) {
            $timemax = $time_elapsed_secs;
        }
        if ($time_elapsed_secs < $timemin) {
            $timemin = $time_elapsed_secs;
        }
    } else {
        $errcnt++;
    }
    $percent = round((100/$try)*$i);
}
system('clear');
$timemin = round(($timemin*1000), 2);
$timemax = round(($timemax*1000), 2);
$stamp = date('d/m/Y H:i:s');
if ($errcnt != $try) {
    $data = "$stamp : $command\nResult : best $timemin ms - worst $timemax ms - $errcnt error(s)\n\n";
    echo "$data";
    file_put_contents($log, $data, FILE_APPEND);
} else {
    echo "Errors while testing : $command\n";
}
?>
