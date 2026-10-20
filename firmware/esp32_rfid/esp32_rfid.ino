/*
 * SmartGym Operations - ESP32 + RC522 RFID Access Terminal
 * Gleah's Fitness Gym
 *
 * Reads RFID cards / keyfobs and sends the UID to the SmartGym PHP server.
 * The server decides IN / OUT / DENIED (capacity, expired membership, unknown card)
 * and this device shows the result with LEDs, a buzzer, an optional 16x2 I2C LCD,
 * and an optional relay for an electric door lock / turnstile.
 *
 * Libraries (Arduino Library Manager):
 *   - MFRC522 by GithubCommunity (miguelbalboa)
 *   - ArduinoJson by Benoit Blanchon (v6 or v7)
 *   - LiquidCrystal I2C by Frank de Brabander (only if USE_LCD = 1)
 * Board: "ESP32 Dev Module" (Espressif ESP32 core)
 *
 * Wiring (RC522 -> ESP32):
 *   SDA(SS) -> GPIO 5    SCK  -> GPIO 18   MOSI -> GPIO 23
 *   MISO    -> GPIO 19   RST  -> GPIO 4    3.3V -> 3V3 (NOT 5V)   GND -> GND
 * Indicators:
 *   Green LED -> GPIO 25 (220Ω)   Red LED -> GPIO 26 (220Ω)   Buzzer -> GPIO 27
 *   Relay IN  -> GPIO 14 (optional door lock)
 *   LCD I2C   -> SDA GPIO 21, SCL GPIO 22 (optional)
 */
#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>

// ---------------- CONFIGURE THESE ----------------
const char* WIFI_SSID   = "YOUR_WIFI_NAME";
const char* WIFI_PASS   = "YOUR_WIFI_PASSWORD";
// Use your server/PC LAN IP, not "localhost". Copy from SmartGym > Settings.
const char* SERVER_URL  = "http://192.168.1.10/smartgym/api/rfid.php";
const char* API_KEY     = "PASTE_DEVICE_API_KEY_FROM_SETTINGS";
const char* DEVICE_ID   = "ESP32-01";
#define USE_LCD    0      // 1 = 16x2 I2C LCD attached
#define USE_RELAY  1      // 1 = pulse relay on successful IN/OUT
#define LCD_ADDR   0x27
// -------------------------------------------------

#define PIN_SS      5
#define PIN_RST     4
#define PIN_GREEN   25
#define PIN_RED     26
#define PIN_BUZZER  27
#define PIN_RELAY   14
#define RELAY_MS    3000
#define SAME_CARD_COOLDOWN_MS 3000

MFRC522 rfid(PIN_SS, PIN_RST);

#if USE_LCD
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
LiquidCrystal_I2C lcd(LCD_ADDR, 16, 2);
#endif

String lastUid = "";
unsigned long lastUidAt = 0;

void lcdShow(const String& l1, const String& l2 = "") {
#if USE_LCD
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print(l1.substring(0, 16));
  lcd.setCursor(0, 1); lcd.print(l2.substring(0, 16));
#endif
  Serial.println("[LCD] " + l1 + " | " + l2);
}

void beep(int times, int ms) {
  for (int i = 0; i < times; i++) {
    digitalWrite(PIN_BUZZER, HIGH); delay(ms);
    digitalWrite(PIN_BUZZER, LOW);  delay(ms / 2);
  }
}

void signalResult(const String& action) {
  if (action == "IN" || action == "OUT") {
    digitalWrite(PIN_GREEN, HIGH);
    beep(1, 120);
#if USE_RELAY
    digitalWrite(PIN_RELAY, HIGH);
    delay(RELAY_MS);
    digitalWrite(PIN_RELAY, LOW);
#else
    delay(1500);
#endif
    digitalWrite(PIN_GREEN, LOW);
  } else if (action == "IGNORED") {
    beep(1, 40);
  } else {  // DENIED or ERROR
    digitalWrite(PIN_RED, HIGH);
    beep(3, 150);
    delay(1200);
    digitalWrite(PIN_RED, LOW);
  }
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  lcdShow("Connecting WiFi", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 20000) {
    digitalWrite(PIN_RED, !digitalRead(PIN_RED));
    delay(300);
  }
  digitalWrite(PIN_RED, LOW);
  if (WiFi.status() == WL_CONNECTED) {
    lcdShow("WiFi connected", WiFi.localIP().toString());
    beep(2, 60);
  } else {
    lcdShow("WiFi FAILED", "Retrying...");
  }
}

String readUid() {
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  return uid;
}

void sendScan(const String& uid) {
  connectWiFi();
  if (WiFi.status() != WL_CONNECTED) { signalResult("ERROR"); return; }

  HTTPClient http;
  http.begin(SERVER_URL);
  http.setTimeout(5000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-KEY", API_KEY);
  String body = String("{\"uid\":\"") + uid + "\",\"device_id\":\"" + DEVICE_ID + "\"}";
  int code = http.POST(body);

  if (code <= 0) {
    Serial.printf("HTTP error: %s\n", http.errorToString(code).c_str());
    lcdShow("Server offline", "Try again");
    signalResult("ERROR");
    http.end();
    return;
  }

  String payload = http.getString();
  http.end();
  Serial.printf("HTTP %d: %s\n", code, payload.c_str());

  DynamicJsonDocument doc(1024);
  if (deserializeJson(doc, payload)) {
    lcdShow("Bad response", String(code));
    signalResult("ERROR");
    return;
  }
  String action  = doc["action"]  | "ERROR";
  String message = doc["message"] | "";
  int inside     = doc["inside"]   | 0;
  int capacity   = doc["capacity"] | 0;

  if (action == "IN" || action == "OUT") {
    lcdShow(message, String(action) + "  " + inside + "/" + capacity);
  } else if (action == "IGNORED") {
    lcdShow("Already scanned", "Please wait");
  } else {
    lcdShow("ACCESS DENIED", message);
  }
  signalResult(action);
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_GREEN, OUTPUT);
  pinMode(PIN_RED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  pinMode(PIN_RELAY, OUTPUT);
  digitalWrite(PIN_RELAY, LOW);

#if USE_LCD
  lcd.init();
  lcd.backlight();
#endif
  lcdShow("SmartGym RFID", "Starting...");

  SPI.begin();          // SCK 18, MISO 19, MOSI 23
  rfid.PCD_Init();
  delay(50);
  rfid.PCD_DumpVersionToSerial();

  connectWiFi();
  lcdShow("Tap your card", "Gleah's Fitness");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) connectWiFi();

  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    delay(50);
    return;
  }

  String uid = readUid();
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  if (uid == lastUid && millis() - lastUidAt < SAME_CARD_COOLDOWN_MS) return;
  lastUid = uid;
  lastUidAt = millis();

  Serial.println("Card UID: " + uid);
  lcdShow("Card detected", uid);
  sendScan(uid);
  delay(300);
  lcdShow("Tap your card", "Gleah's Fitness");
}
