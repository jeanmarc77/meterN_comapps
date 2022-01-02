#!/usr/bin/php
<?php
// chmod +x then ln -s /srv/http/comapps/req_sdm.php /usr/bin/req_sdm

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}

if(!isset($argv[1])) {
die("Abording: no valid argument given.\n");
}
if ($argv[1] == '-volt') {
    $outstr =  exec('cat /dev/shm/sdm_log.txt | egrep "^1_V\(" | grep "*V)"');
}
// and so on..

echo "$outstr";
?>
