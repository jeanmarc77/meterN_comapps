/* 08/03/16 Louviaux Jean-Marc - meterN communication app example for pulses meters */
// Gas & Water

String inputString = ""; // Input String
char charVal[8]; // convert float to an array of char
String str_out = ""; // convert char values into string

// Counters to be updated
unsigned long countW = 0;
unsigned long countG = 0;

// Keeping track of the timing of recent interrupts for electrical and water meters
unsigned long last_int_timeW = 0;

// Use a digital input for the gas meter. It will measure a valid pulse width
unsigned long low_timeG = 0;
// error log and smalest monitor gas pulse time width
unsigned int gpw = 10000;
unsigned int gpmin = 10000;
// count gas errors
unsigned long err_timeG = 0;

int gasInputState = 0; // Current state of gas input
int lastgasInputState = 0; // Previous state

// Meter live values and states
unsigned long now = 0;
float gas = 0;
float water = 0;
int state_W = 0;
int state_G = 0;

/*
Arduino Leonardo, digital pin layout :
 
 Board    int.0  int.1  int.2  int.3
 Pin          3      2      0      1
 */

void setup() {
  attachInterrupt(1, KirqW, FALLING); // Interrupt 1, pin 2 => Water meter
  pinMode(0, INPUT); // Digital input pin 0. Lower priority => Gas meter

  //Serial.begin(115200); // fast serial
  Serial.begin(921600); // Really fast serial
  while (!Serial) {
    ; // Needed for Leonardo only
  }
}

void loop() {
  if (state_G == 1 || state_W == 1) {
    now = millis();
    if (now - low_timeG > 30000) { // Clear gas meter state after 30secs
      state_G = 0;
    }
    if (now - last_int_timeW > 8000) { // Clear water state after 8secs
      state_W = 0;
    }
  }
  // My gas meter don't use the interrupt method to avoid parasitic pulses
  gasInputState = digitalRead(0); // read the input pin
  if (gasInputState != lastgasInputState) { // compare to its previous state
    now = millis();

    // My gas IR barrier sensor is low when it detect the reflective part. A pulse should last at least 300ms
    if (gasInputState == HIGH && now - low_timeG >= 300 ) { // valid
      state_G = 1;  // On
      if (now - low_timeG < gpw) {
        gpw = now - low_timeG;
      }
      countG++;
    } 
    else { // gas error
      if (now - low_timeG < gpmin) {
        gpmin = now - low_timeG;
      }
      err_timeG++;
    }
    if (gasInputState == LOW) {
      low_timeG = now;
    } 
  }
  lastgasInputState = gasInputState; // save the current state

    if (Serial.available()) {
    char inChar = (char)Serial.read();
    if (inChar == '\n') {
      if (inputString == "ws") { // Water state
        String str_out = String(state_W);
        Serial.println("water(" + str_out + "*l)");
      }
      else if (inputString == "gs") { // Gas state
        String str_out = String(state_G);
        Serial.println("gas(" + str_out + "*m3)");
      }
      else if (inputString == "rwater") {
        String str_out = String(countW); // Ratio 1 pulse per liter
        countW = 0;
        Serial.println("water(" + str_out + "*l)");
      }
      else if (inputString == "rgas") {
        gas = (float)countG / 100; // Ratio 1 pulse per 1/100m3
        dtostrf(gas ,4, 2, charVal);
        String str_out(charVal);
        countG = 0;
        Serial.println("gas(" + str_out + "*m3)");
      } 
      else if (inputString == "err") {
        String str_out = "Max pulse:";
        str_out += String(gpw);
        str_out += " Min :";
        str_out += String(gpmin);
        str_out += " Errors:";
        str_out += String(err_timeG);
        Serial.println(str_out);
        err_timeG = 0;
        gpw = 10000;
        gpmin= 10000;
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
  //delay(50); // little breath
}

// Interrupt routines for electrical and water meters

// Water
void KirqW() {
  now = millis();
  if (now - last_int_timeW > 1000) // At least 1s between a liter
  {
    countW++;
    state_W = 1;
  }
  last_int_timeW = now;
}









