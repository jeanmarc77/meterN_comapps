/* 17/08/20 Louviaux Jean-Marc - meterN communication app example for gas and water pulses meters */

String inputString = ""; // Input String
char charVal[8]; // convert float to an array of char
String str_out = ""; // convert char values into string
String str_out2 = "";

// Counters
unsigned long Wcount = 0;
unsigned long Gcount = 0;
// Digitals inputs status
int Ginputstate = 0; // Current state of gas
int Gprevinputstate = 0; // Previous state
int Winputstate = 0; // Current state of water
int Wprevinputstate = 0;
// Keeping track of the timing
unsigned long Ghigh_time = 0;
unsigned long Glow_time = 0;
unsigned long Whigh_time = 0;
unsigned long Wlow_time = 0;

// Time between 2 pulses to calculate the water flow
unsigned long Wprevhigh_time = 0;
unsigned long Wpulse_time = 3600000;
// Meter live values and stats
unsigned long now = 0;
float gas = 0; // convert
float Wlive = 0;  // convert
String Gstat = "<font color='#8B0000'>Off</font>";

// Minimal pulse width
float Gtimer = 1000000;
float Wtimer = 1000000;
int Gerr = 0;
int Werr = 0;

void setup() {
  pinMode(0, INPUT); // Digital input pin 0. Gas meter
  pinMode(2, INPUT); // Digital input pin 2. Water meter
  Serial.begin(57600); // Really fast serial
}

void loop() {
  now = millis(); // up to 50days
  // Gas
  if (Gstat == "<font color='#228B22'>On</font>" && now - Glow_time > 30000) { // Clear gas meter state after 30secs
    Gstat = "<font color='#8B0000'>Off</font>";
  }
  // My gas meter is LOW on detection
  Ginputstate = digitalRead(0); // read the input pin
  if (Ginputstate != Gprevinputstate) { // Compare to its previous state
    now = millis();
    if (Ginputstate == LOW && now - Ghigh_time >= 1000) { // At least 1s between pulse
      Glow_time = now;
      Gstat = "<font color='#228B22'>On</font>";  // On
    }
    if (Ginputstate == HIGH && now - Glow_time > 300) { // min pulse width 300ms
      Ghigh_time = now;
      Gcount++;
    } else if (Ginputstate == HIGH && now - Glow_time < 300) {
      Gerr ++;
    }
    if ( Ghigh_time - Glow_time < Gtimer) { // pulse width
      Gtimer = Ghigh_time - Glow_time;
    }
  }
  Gprevinputstate = Ginputstate; // save the current state

  // Water
  Winputstate = digitalRead(2);
  if (Winputstate != Wprevinputstate) {
    now = millis();
    if (Winputstate == HIGH && now - Wlow_time >= 500 ) {  // At least 500ms between a liter, max 120 l/m
      Wprevhigh_time = Whigh_time;
      Whigh_time = now;
      Wpulse_time = Whigh_time - Wprevhigh_time;
    }
    if (Winputstate == LOW && now - Whigh_time > 50) { // min pulse width 50ms
      Wlow_time = now;
      Wcount ++;
    } else if (Winputstate == LOW && now - Whigh_time < 50) {
      Werr ++;
    }
    if (Wlow_time - Whigh_time < Wtimer) { // pulse width
      Wtimer = Wlow_time - Whigh_time;
    }
  }
  Wprevinputstate = Winputstate;

  if (Serial.available()) {
    char inChar = (char)Serial.read();
    if (inChar == '\n') {
      if (inputString == "ws") { // Water state
        if (now - Whigh_time > 10000) { // Clear water state after 10secs
          Serial.println("water(0*l/min)");
        } else { // liters per min from 6l/m (10sec)
          if (now - Whigh_time > Wpulse_time) {
            Wlive = 60000 / (now - Whigh_time);
            if (Wlive < 3) { // too low flow <3l/m
              Serial.println("water(~*l/min)");
            } else { // auto decrease
              dtostrf(Wlive , 4, 1, charVal);
              String str_out(charVal);
              str_out.trim();
              Serial.println("water(" + str_out + "*l/min)");
            }
          } else {
            Wlive = 60000 / (float) Wpulse_time;
            dtostrf(Wlive , 4, 1, charVal);
            String str_out(charVal);
            str_out.trim();
            Serial.println("water(" + str_out + "*l/min)");
          }
        }
      }
      else if (inputString == "gs") { // Gas state
        Serial.println("gas(" + Gstat + "*x)");
      }
      else if (inputString == "rwater") {
        String str_out = String(Wcount); // Ratio 1 pulse per liter
        Serial.println("water(" + str_out + "*l)");
        Wcount = 0;
      }
      else if (inputString == "rgas") {
        gas = (float)Gcount / 100; // Ratio 1 pulse per 1/100m3
        dtostrf(gas , 4, 2, charVal);
        String str_out(charVal);
        str_out.trim();
        Serial.println("gas(" + str_out + "*m3)");
        Gcount = 0;
      }
      else if (inputString == "wt") {
        String str_out = String(Wtimer);
        String str_out2 = String(Werr);
        Serial.println("Water min pulse width : " + str_out + "ms" + " Err : " + str_out2 + "\n");
        Werr = 0;
        Wtimer = 1000000;
      }
      else if (inputString == "gt") {
        String str_out = String(Gtimer);
        String str_out2 = String(Gerr);
        Serial.println("Gas min pulse width : " + str_out + "ms" + " Err : " + str_out2 + "\n");
        Gerr = 0;
        Gtimer = 1000000;
      }
      else {
        Serial.print(inputString);
        Serial.println(" : unknown command");
      }
      inputString = "";
    }
    else {
      if (inputString.length() < 10) { // Command limited to 10 chars
        inputString += inChar;
      }
    }
  }
}
