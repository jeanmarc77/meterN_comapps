#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
// Will send meterN data to MQTT using Mosquitto. Thanks to Marco Fiorio
// sudo chmod +x mqtt_energy.php
// sudo chown www-data\: mqtt_energy.php
// sudo ln -s /var/www/comapps/mqtt_energy.php /usr/bin/mqtt_energy


$frequenza = 10; // seconds for loop

function mqtt($arr)
{
    $msg = json_encode($arr);
    //$CMD = "mosquitto_pub -d -h '192.168.0.105' -t 'domoticz/in' -m '$msg'";
    $CMD = "timeout --kill-after=15s 10s mosquitto_pub -d -h '192.168.0.105' -t 'domoticz/in' -m '$msg'";
    exec($CMD, $output);
    //$return = implode(PHP_EOL, $output);
    //echo $return;
}

// IDX Produzione = 4 ; prelievi = 5 ; autoconsumo = 6 ; fascia f1 = 7 ; fascia f23 = 8 ; immissioni = 9 ; consumi = 10 
// domoticz device = dummy device type electrical: instanteo + contatore
//device type = dummy device: electrical instanteno + contatore
//os command:
//sudo mosquitto_pub -d -h 192.168.0.105 -t 'domoticz/in' -m "{"idx": 196, "nvalue": 0, "svalue":"1200.3;12345" }"
// domoticz/in {"idx": 196, "nvalue": 0, "svalue":"1200.3;1" }
$ID_prod   = 4;
$ID_prel   = 5;
$ID_autoc  = 6;
$ID_f1     = 7;
$ID_f23    = 8;
$ID_imm    = 9;
$ID_cons   = 10;
$ID_boiler = 130;

//IDX ampere = 12 ; volt = 13
// device type = dummy device: type = ampere (1fase)
// domoticz/in {"idx": 196, "nvalue": 0, "svalue":"12.3" }  12,3A
// se ampere (3fasi) // domoticz/in {"idx": 196, "nvalue": 0, "svalue":"p1;p2;p3" }  p1=fase1;p2=fase2;p3=fase3
$ID_A = 12;
//device type = dummy device: type = voltaggio
// domoticz/in {"idx": 196, "nvalue": 0, "svalue":"220.3" } 220,3V
$ID_V = 13;

//IDX water = 101
//device type = dummy device: type = GAS
// domoticz/in {"idx": 196, "nvalue": 0, "svalue":"15.0" }  // 0.015m3
$ID_h2o = 101;


//IDX temperature + humidity = 11
//device type = dummy device: type = temp+humid (DHT22)
// domoticz/in {"idx": 197, "nvalue": 0, "svalue": "44.1;42;2" }  //44.1°C 42%  
//note: ;2 at the means: 0, normal    1, comfortable    2, dry   3, wet
//should be: if humidity == nil then HUM_NORMAL     if humidity <= 30 then HUM_DRY(2)  if humidity >= 70 then HUM_WET(3)   if humidity > 35  and   humidity < 65  and  temperature >= 22 and temperature <= 26 then HUM_COMFORTABLE(1)
$ID_temp_umid = 11;

while (true) {
    
    if (file_exists('/dev/shm/mN_LIVEMEMORY.json') && file_exists('/dev/shm/mN_ILIVEMEMORY.json') && file_exists('/dev/shm/mN_MEMORY.json')) {
        
        $data_mN_LIVEM      = file_get_contents('/dev/shm/mN_LIVEMEMORY.json');
        $data_mN_ILIVEM     = file_get_contents('/dev/shm/mN_ILIVEMEMORY.json');
        $data_mN_MEMORY     = file_get_contents('/dev/shm/mN_MEMORY.json');
        $memarray_mN_LIVEM  = json_decode($data_mN_LIVEM, true);
        $memarray_mN_ILIVEM = json_decode($data_mN_ILIVEM, true);
        $memarray_mN_MEMORY = json_decode($data_mN_MEMORY, true);
        
        $prod_KWH   = $memarray_mN_MEMORY["Last2"]; //Last2 = Produzione Wh
        $cons_KWH   = $memarray_mN_MEMORY["Last1"]; //Last1  = Consumi Wh
        $consumiW   = $memarray_mN_LIVEM["Consumi1"]; //Consumi1 = consumi W
        $prodW      = $memarray_mN_LIVEM["Produzione2"]; //Produzinoe2 = produzione W
        $prel_KWH   = $memarray_mN_MEMORY["Last3"]; //Last3 = Prelievi Wh
        $prelW      = $memarray_mN_LIVEM["Prelievi3"]; //Prelievi2 = prelievi W
        $imm_KWH    = $memarray_mN_MEMORY["Last4"];
        $immW       = $memarray_mN_LIVEM["Immissioni4"];
        $auto_KWH   = $memarray_mN_MEMORY["Last5"];
        $autoW      = $memarray_mN_LIVEM["Autoconsumo5"];
        $f1_KWH     = $memarray_mN_MEMORY["Last8"];
        $f1W        = $memarray_mN_LIVEM["PrelieviF18"];
        $f23_KWH    = $memarray_mN_MEMORY["Last9"];
        $f23W       = $memarray_mN_LIVEM["PrelieviF239"];
        $boiler_KWH = $memarray_mN_MEMORY["Last12"];
        $boilerW    = $memarray_mN_LIVEM["Boiler12"];
        $temp       = $memarray_mN_LIVEM["temperatura6"];
        $humi       = $memarray_mN_LIVEM["Umidità7"];
        $V          = $memarray_mN_ILIVEM["Voltage1"]; //Voltage1 = Volt
        $A          = $memarray_mN_ILIVEM["Corrente2"]; //Corrente2 = Ampere
        $h2o        = $memarray_mN_ILIVEM["ACQUA7"] * 1000; //ACQUA7 = acqua in m3
        
        // produzione:
        $svalue = "$prodW;$prod_KWH";
        $arr    = array(
            'idx' => $ID_prod,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        // consumi:
        $svalue = "$consumiW;$cons_KWH";
        $arr    = array(
            'idx' => $ID_cons, // Da usare un idx per ogni valore
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        //prelievi:
        $svalue = "$prelW;$prel_KWH";
        $arr    = array(
            'idx' => $ID_prel,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        // immissioni:
        $svalue = "$immW;$imm_KWH";
        $arr    = array(
            'idx' => $ID_imm,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        //autoconsumo:
        $svalue = "$autoW;$auto_KWH";
        $arr    = array(
            'idx' => $ID_autoc,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        // prelievi f1:
        $svalue = "$f1W;$f1_KWH";
        $arr    = array(
            'idx' => $ID_f1,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        //prelivi F23:
        $svalue = "$f23W;$f23_KWH";
        $arr    = array(
            'idx' => $ID_f23,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        //boiler:
        $svalue = "$boilerW;$boiler_KWH";
        $arr    = array(
            'idx' => $ID_boiler,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        //temperatura
        // domoticz/in {"idx": 197, "nvalue": 0, "svalue": "44.1;42;2" }  //44.1°C 42%  
        //note: ;2 ($ht)  at the end means: 0, normal    1, comfortable    2, dry   3, wet
        if ($humi == '') {
            $ht = 0;
        } elseif ($humi <= 30) {
            $ht = 2;
        } elseif ($humi >= 70) {
            $ht = 3;
        } elseif ($humi > 35 && $humi < 65 && $temp <= 22 && $temp <= 26) {
            $ht = 1;
        } else {
            $ht = 0;
        }
        $svalue = "$temp;$humi;$ht";
        $arr    = array(
            'idx' => $ID_temp_umid,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        $out    = mqtt($arr);
        //Volt:
        $svalue = "$V";
        $arr    = array(
            'idx' => $ID_V,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        mqtt($arr);
        //Ampere:
        $svalue = "$A";
        $arr    = array(
            'idx' => $ID_A,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        mqtt($arr);
        //h2o:
        $svalue = "$h2o";
        $arr    = array(
            'idx' => $ID_h2o,
            'nvalue' => 0,
            'svalue' => $svalue
        );
        mqtt($arr);
        
    } else { // ain't running
        //die("Aborting: no file \n");
        echo "No file\n";
        sleep(3);
    }
    
    sleep($frequenza);
}
?>
