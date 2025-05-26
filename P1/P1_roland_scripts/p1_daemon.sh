#!/bin/bash

WAIT=10 # in seconds
while true
do
  echo "Initializing tty port ..."
  setserial -z /dev/ttyUSBp1
  sleep 1
  echo "Clean port buffer, with dummy request"
  /usr/local/bin/get_p1.py >/dev/null 2>&1
  sleep 1
  while true
  do
    echo "Get data"
    /usr/local/bin/get_p1.py > /var/run/shm/p1_output_new.txt
    if [ "$(cat /var/run/shm/p1_output_new.txt|wc -l)" -ge "22" ]
    then
      mv /var/run/shm/p1_output_new.txt /var/run/shm/p1_output.txt

      Solar=$(/var/www/123solar/scripts/pool123s.php|sed 's/^.(//;s/\*.*//')
      SolarW=$(/var/www/123solar/scripts/pool123slive.php|sed 's/^.(//;s/\..*//;s/\*.*//')
      GasKwT=$(tail -4 /var/www/metern/data/csv/$(date '+%Y%m%d').csv|grep -v Time|head -1|awk -F, '{print $NF}')
      while read TYPE VAL
      do
        case ${TYPE} in
          InLow)
            InLow=$(echo ${VAL}|sed 's/\.//')
            ;;
          InHigh)
            InHigh=$(echo ${VAL}|sed 's/\.//')
            ;;
          OutLow)
            OutLow=$(echo ${VAL}|sed 's/\.//')
            ;;
          OutHigh)
            OutHigh=$(echo ${VAL}|sed 's/\.//')
            ;;
          Gas)
            Gas=${VAL}
            ;;
          TRF)
            TRF=$(echo ${VAL}|awk '{print $1 * 1}')
            ;;
          WIn)
            WIn=$(echo ${VAL}|awk '{print $1 * 1000}')
            ;;
          WOut)
            WOut=$(echo ${VAL}|awk '{print $1 * 1000}')
            ;;
        esac
      done < <(grep -v "DSMR" /var/run/shm/p1_output.txt|
        sed 's/1-0:1.8.1/InLow/;s/1-0:1.8.2/InHigh/;s/1-0:2.8.1/OutLow/;s/1-0:2.8.2/OutHigh/;
        s/1-0:1.7.0/WIn/;s/1-0:2.7.0/WOut/;s/1-0:1.8.9/WhIn/;s/1-0:2.8.9/WhOut/;
        s/0-0:96.14.0/TRF/;s/^(/Gas /;s/(/ /g;s/\*.*//g;s/)//g'|
        egrep -v ':|!|ontrol|eventueel|\/|^$')

      WhIn=$(expr ${InLow} + ${InHigh} )
      WhOut=$(expr ${OutLow} + ${OutHigh} )
      if [ "${TRF}" -eq "2" ]
      then
        InLowW=0
        OutLowW=0
        InHighW=${WIn}
        OutHighW=${WOut}
      else
        InLowW=${WIn}
        OutLowW=${WOut}
        InHighW=0
        OutHighW=0
      fi
      Usage=$(expr ${WhIn} + ${Solar} - ${WhOut} )
      UsageW=$(expr ${WIn} + ${SolarW} - ${WOut} )
      if [ "${UsageW}" -lt "0" ]
      then
        UsageW=0
      fi
      GasLast=$(echo ${Gas} 0${GasKwT}|awk '{print $1 - $2}' )        
      (
        echo "TRF(${TRF})"
        echo "InHigh(${InHigh}*Wh)"
        echo "InLow(${InLow}*Wh)"
        echo "OutHigh(${OutHigh}*Wh)"
        echo "OutLow(${OutLow}*Wh)"
        echo "InHighW(${InHighW}*W)"
        echo "InLowW(${InLowW}*W)"
        echo "OutHighW(${OutHighW}*W)"
        echo "OutLowW(${OutLowW}*W)"
        echo "WhIn(${WhIn}*Wh)"
        echo "WhOut(${WhOut}*Wh)"
        echo "WIn(${WIn}*W)"
        echo "WOut(${WOut}*W)"
        echo "Solar(${Solar}*Wh)"
        echo "SolarW(${SolarW}*W)"
        echo "Usage(${Usage}*Wh)"
        echo "UsageW(${UsageW}*W)"
        echo "Gas(${Gas}*M3)"
        echo "GasLast(${GasLast}*M3)"
      ) > /var/run/shm/meterndata_new.txt
      mv /var/run/shm/meterndata_new.txt /var/run/shm/meterndata.txt
    fi
    sleep ${WAIT}
  done
done
