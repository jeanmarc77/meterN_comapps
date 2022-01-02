#!/bin/bash
# CPU temperature indicator/sensor
# ln -s /srv/http/comapps/cputemp.sh /usr/bin/cputemp

TEMP="`cat /sys/class/thermal/thermal_zone0/temp`"
TEMP=$((TEMP/1000))
echo -e "cpu($TEMP*°)"
