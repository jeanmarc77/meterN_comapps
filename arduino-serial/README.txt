A meterN communication app for pulses meters using arduino-serial
https://github.com/todbot/arduino-serial

How to :

- Connect your Arduino sensors and connect it via USB ;)
- Compile cd path to you com app/arduino-serial then make
- ln -s /path to you com app/arduino-serial /usb/bin/arduino-serial
	- Test : 
	arduino-serial -q -p /dev/ttyACM0 -S "le" -r
	1(418.07*W)

Have fun !
