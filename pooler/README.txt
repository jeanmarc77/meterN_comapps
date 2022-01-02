
	Pooler and Poolmeters, are meterN communication apps examples for pulses meters using an Arduino Leonardo.
	Read out more on http://www.metern.org
	--------

	* _poolmeters.ino is the Arduino sketch that count my gas and water meters (electrial S0 example included).

	* poolmeters.py fetch the arduino meters values/state using pyserial.

	* pooler.php serve for the main command. Invoke pooler will take the arduino counter value during a 5 min lapse then increment a total virtual counter.

	--------

How to :

- Load your modified _ino file in your Arduino

- Connect your Arduino sensors and connect it via USB ;)

- Install python and pip (pacman -Sy python python-pip on Arch Linux) then install pyserial (pip install pyserial)

- Put the apps poolmeters.py and pooler.php in your webserver directory (eg in /srv/http/comapps/) 

- Make sure they are both executable via (chmod a+x poolmeters.py pooler.php)

- Allow access the com. port to http user (eg usermod -aG uucp http)

- Edit poolmeters.py to adujst it to your needs. This script will communicate with your arduino using pyserial.

- Edit pooler.php, it will be use for the 'Main command'.

- Make them system wide available 
ln -s  /path to/pooler.php /usr/bin/pooler
ln -s  /path to/poolmeters.py /usr/bin/poolmeters

- Test the app :

[root@odroid comapps]# poolmeters gs
gas(0*m3)
[root@odroid comapps]# pooler water
water(9*l)

- Setup meterN with your pooler commands (eg 'pooler gas' for main command and 'poolmeters gs' for live) test and start meterN.

- you may check if it's launched as http user:
[root@odroid comapps]# ps -ef | grep poolmeters
http     25944 23786 17 11:16 ?        00:00:00 python /usr/bin/poolmeters ws


Have fun !
 
