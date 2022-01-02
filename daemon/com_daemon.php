#!/usr/bin/php
<?php
/*
Check com_daemon_loop.php path bellow
Then ln -s  /path to/com_daemon.php /usr/bin/com_daemon
Start and stop the daemon via metern/config/config_daemon.php
And request values with houseenergy command
*/

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}

if (file_exists('/dev/shm/com_daemon.pid')) {
    $cdpid = (int) file_get_contents('/dev/shm/com_daemon.pid');
    exec("ps -ef | grep $cdpid | grep com_daemon", $ret);
    if (!isset($ret[1])) {
        $cdpid = null;
        unlink('/dev/shm/com_daemon.pid');
    }
} else {
    $cdpid = null;
}

if (isset($argv[1])) {
    if (($argv[1] == '-start' || $argv[1] == '-stop') && file_exists('/dev/shm/sdm_log.txt')) {
        unlink('/dev/shm/sdm_log.txt');
    }
    if ($argv[1] == '-start') {
        if (is_null($cdpid)) {
            $command = 'php /srv/http/comapps/daemon/com_daemon_loop.php' . ' > /dev/null 2>&1 & echo $!;';
            $cdpid     = exec($command);
            file_put_contents('/dev/shm/com_daemon.pid', $cdpid);
        } else {
            echo "com_daemon seem to be running as $cdpid";
        }
    } else if ($argv[1] == '-stop') {
        if (!is_null($cdpid)) {
            $command = exec("kill $cdpid > /dev/null 2>&1 &");
	    unlink('/dev/shm/com_daemon.pid');
        }
    } else {
        echo "Usage : com_daemon {start | stop}\n";
    }
} else {
    echo "Usage : com_daemon {start | stop}\n";
}
?>
