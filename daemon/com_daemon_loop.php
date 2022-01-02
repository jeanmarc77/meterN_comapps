<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
// Beware, only use a tmpfs as /dev/shm (ramfs) !

while (true) {
    $dataarray = array();
    $output    = exec('sdm120c -a1 -b9600 -z3 -2 -qpievfg /dev/sdm');
    //$output    = exec("sdm120c -a1 -d3 -b9600 -2 -z3 -j20 -w5 -W40 -qpievfg /dev/sdm 2> /dev/shm/lastlog.log");
    $dataarray = preg_split('/[[:space:]]+/', $output);
    
    if (!isset($dataarray[6])) {
        $dataarray[6] = 'NOK';
    }
    if ($dataarray[6] == 'OK') { // Make sure the frame is complete before filling the tmp file
        $dataarray[0] = '1_V(' . $dataarray[0] . '*V)';
        $dataarray[1] = '1_P(' . $dataarray[1] . '*W)';
        $dataarray[2] = '1_PF(' . $dataarray[2] . '*F)';
        $dataarray[3] = '1_F(' . $dataarray[3] . '*Hz)';
        $dataarray[4] = '1_IE(' . $dataarray[4] . '*Wh)';
        $dataarray[5] = '1_EE(' . $dataarray[5] . '*Wh)';
        $str          = implode(PHP_EOL, $dataarray);
        file_put_contents('/dev/shm/sdm_log.txt', $str);
    } else {
		if (file_exists('/dev/shm/sdm_log.txt')) {
			$now = time();
			if ($now - filemtime('/dev/shm/sdm_log.txt') > 5) { // 5 sec
				unlink('/dev/shm/sdm_log.txt');
			}
		}
    }
    usleep(500000);
}
?>
