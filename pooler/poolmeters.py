#!/usr/bin/env python
# pyserial is needed http://pyserial.sourceforge.net/
# If running as http user, you'll need to add it to your com. group 
# ls -l /dev/ttyACM*
# crw-rw---- 1 root uucp 188, 0 Feb 18 09:02 /dev/ttyACM0
# usermod -aG uucp http

import serial, sys, time, glob

if len(sys.argv) == 1:
    print("Abording: Provide an argument"), sys.exit()
elif len(sys.argv) > 2:
    print("Abording: Too many arguments"), sys.exit()
else:
#PORT = (glob.glob("/dev/ttyACM*")) # Detect the arduino port
#ser = serial.Serial(PORT[0], 921600, timeout=10)
#ser = serial.Serial(PORT[0], 19200, timeout=10)
   ser=serial.Serial("/dev/arduino",921600,timeout=3)
   if ser.isOpen():
      if sys.argv[1] == 'rgas': # Gas & reset counter
         ser.write("rgas\n".encode("utf-8"))
      elif sys.argv[1] == 'rwater': # Water & reset counter
         ser.write("rwater\n".encode("utf-8"))
      elif sys.argv[1] == 'gs': # Gas state
         ser.write("gs\n".encode("utf-8"))
      elif sys.argv[1] == 'ws': # Water state
         ser.write("ws\n".encode("utf-8"))
      elif sys.argv[1] == 'gt': # Gas timer/err
         ser.write("gt\n".encode("utf-8"))
      elif sys.argv[1] == 'wt': # Water timer/err
         ser.write("wt\n".encode("utf-8"))
      else:
         print("Abording:",sys.argv[1],"is not a valid argument")

      line=ser.readline(64).decode("utf-8")
      print(line,end="")

   else:
      print("Abording: serial not open")

#print("done")
ser.flushInput()
ser.flushOutput()
ser.close()
quit()
sys.exit()
