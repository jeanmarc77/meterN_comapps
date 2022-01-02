/* 05/03/16 Louviaux Jean-Marc - meterN communication app example for pulses meters */

String inputString = ""; // Input String
char charVal[8]; // convert float to an array of char
String str_out = ""; // convert char values into string

// Counters to be updated
unsigned long countE = 0;
unsigned long countW = 0;
unsigned long countG = 0;

// Keeping track of the timing of recent interrupts for electrical and water meters
unsigned long last_int_timeE = 0;
unsigned long last_int_timeW = 0;

// Use a digital input for the gas meter. It will measure a valid pulse width
unsigned long low_int_timeG = 0;
unsigned long high_int_timeG = 0;
int gasInputState = 0; // Current state of gas input
int lastgasInputState = 0; // Previous state

// Time between 2 pulses to calculate the electrical power
unsigned long pulse_timeE = 3600000;

// Meter live values and states
unsigned long now = 0;
float watt = 0;
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
  attachInterrupt(0, KirqE, FALLING); // Enable interrupt 0 which uses pin 3. Higher priority for faster meter => Electric one
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
    if (now - low_int_timeG > 30000) { // Clear gas meter state after 30secs
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

    // My gas IR barrier sensor is low when it detect the reflective part. It don't go faster than a pulse each 1sec and should last at least 250ms
    if (gasInputState == HIGH && now - high_int_timeG > 1000 && now - low_int_timeG > 250 ) { // valid
      state_G = 1;  // On
      countG++;
    }
    if (gasInputState == LOW) {
      low_int_timeG = now;
    } 
    else {
      high_int_timeG = now;
    }
  }
  lastgasInputState = gasInputState; // save the current state

    if (Serial.available()) {
    char inChar = (char)Serial.read();
    if (inChar == '\n') {
      if (inputString == "le") { // Retrieve live and state values
        watt = 3600000 / (float)pulse_timeE; // Powa
        dtostrf(watt ,4, 2, charVal);
        String str_out(charVal);
        Serial.println("elect(" + str_out + "*W)");
      }
      else if (inputString == "ws") { // Water state
        String str_out = String(state_W);
        Serial.println("water(" + str_out + "*l)");
      }
      else if (inputString == "gs") { // Gas state
        String str_out = String(state_G);
        Serial.println("gas(" + str_out + "*m3)");
      }
      else if (inputString == "relect") { // Retrieve values during a 5min period and put counter to zero. pooler.php will then increment the total counter.
        String str_out = String(countE);
        countE = 0;
        Serial.println("elect(" + str_out + "*Wh)"); // ID(VALUE*UNIT)
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

// Interrupt routines for electrical and water meters
// Elect
void KirqE() {
  now = millis();
  if (now - last_int_timeE > 30)  // Check to see if Kirq() was called in the last 30 milliseconds. My elect pulses have a 50ms width
  {
    countE++;
    pulse_timeE = now - last_int_timeE; // To calculate power
  }
  last_int_timeE = now;
}

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

