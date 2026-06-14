/*
 * Projekt: GuetNacht - Baby Sleep Tracker
 * Modul: Interaktive Medien 4 (FH Graubünden)
 * Team Physical Computing: Ali Tas, Naim El Amri Fernandez
 * Beschreibung: ESP32 liest PIR-Sensor (SR602) aus und sendet Status via HTTP POST an eine WebApp. 
 * LED dient als visuelles System-Feedback.
 */

#include <WiFi.h>
#include <HTTPClient.h>

// --- 1. WLAN & Server Konfiguration ---
const char* ssid = "REPLACE_WITH_SSID";                                 // SSID (Name) des lokalen WLAN-Netzwerks
const char* password = "REPLACE_WITH_PASSWORD";                         // Passwort des lokalen WLAN-Netzwerks
const char* serverUrl = "REPLACE_WITH_URL"; // Ziel-API der WebApp zur Datenannahme

// --- 2. Projekt Spezifikationen ---
const int babyId = 8;           // Eindeutige ID des Kindes/Bettes, um die Daten in der Datenbank richtig zuzuordnen

// --- 3. Hardware Konfiguration ---
const int pirPin = 7;           // Pin für den digitalen Eingang des PIR-Sensors (erfasst Bewegung)
const int ledPin = 8;           // Pin für die Status-LED (visuelles Feedback für aktive System- und Serververbindung)

int pirStatus = 0;              // Speichert den aktuell gemessenen Zustand des PIR-Sensors (1 = Bewegung, 0 = Ruhe)
int letzterPirStatus = 0;       // Speichert den vorherigen Sensorzustand, um nur Statusänderungen zu loggen (Flankenerkennung)

// NEU: Variable speichert, ob der letzte Server-Kontakt erfolgreich war
bool letzteVerbindungOK = true; // Hilfsvariable für die LED-Logik: Speichert ab, ob der Server auf den letzten Sendeversuch reagiert hat

// Timer-Variablen für den regelmässigen WLAN-Check
unsigned long letzterWlanCheck = 0;   // Speichert den Zeitpunkt der letzten Verbindungsprüfung (in Millisekunden)
const long wlanCheckIntervall = 2000; // Alle 2000 Millisekunden (2 Sekunden) prüfen, ohne das System zu blockieren

// Initialisiert die Hardware-Pins, startet die serielle Kommunikation und verbindet den ESP32 mit dem WLAN.
void setup() {
  Serial.begin(115200);         // Startet die serielle Konsole für Debug-Ausgaben (mit 115200 Baud)
  pinMode(pirPin, INPUT);       // Konfiguriert den Sensor-Pin als Eingang zum Auslesen der Bewegungsdaten
  
  pinMode(ledPin, OUTPUT);      // Konfiguriert den LED-Pin als Ausgang zur Ansteuerung des Lämpchens
  
  // LED beim Start kurz anschalten als Hardware-Test
  digitalWrite(ledPin, HIGH);   // LED leuchtet auf
  delay(500);                   // Wartet eine halbe Sekunde
  digitalWrite(ledPin, LOW);    // LED erlischt wieder

  Serial.println();
  Serial.print("Verbinde mit WLAN: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);   // Leitet den Verbindungsaufbau zum WLAN-Router ein

  // Warteschleife: Blockiert den Programmfluss solange, bis der ESP32 eine IP-Adresse erhalten hat
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  
  Serial.println("\n✅ WLAN erfolgreich verbunden!");
  Serial.print("IP-Adresse: ");
  Serial.println(WiFi.localIP());
  Serial.println("System bereit. Warte auf Bewegung...");
  
  // WLAN sofort einmal prüfen, um die LED nach dem Start direkt richtig zu setzen
  pruefeWlanUndSetzeLED();
  
  delay(2000); // Gibt dem PIR-Sensor etwas Zeit zur anfänglichen Kalibrierung, bevor das Monitoring beginnt
}

// Hauptschleife des Programms: Überwacht den Sensor auf Änderungen und prüft periodisch die Verbindung zum Backend.
void loop() {
  // --- A. PIR Sensor auslesen ---
  pirStatus = digitalRead(pirPin); // Liest den aktuellen digitalen Wert vom Sensor (HIGH oder LOW)

  // Prüft, ob sich der Zustand seit dem letzten Zyklus verändert hat (nur bei Start oder Ende einer Bewegung reagieren)
  if (pirStatus != letzterPirStatus) {
    
    if (pirStatus == HIGH) {
      Serial.println("🔴 Bewegung erkannt! (Sende Daten...)");
    } else {
      Serial.println("🟢 Bewegung gestoppt! (Sende Daten...)");
    }

    sendeDatenAnServer(pirStatus); // Übergibt den neuen Sensorwert an die Sende-Funktion
    letzterPirStatus = pirStatus;  // Sichert den neuen Zustand für den nächsten Durchlauf
    
    delay(2000); // Entprellen / Verzögerung: Verhindert versehentliche Mehrfachauslösungen innerhalb kurzer Zeit (Debouncing)
  }
  
  // --- B. WLAN Status im Hintergrund live prüfen ---
  // Wir nutzen millis(), damit der ESP32 nicht komplett blockiert wird (wie bei delay)
  unsigned long aktuellerZeitpunkt = millis(); // Ruft die Laufzeit des ESP32 in Millisekunden ab
  
  // Kontrolliert, ob das definierte Intervall (2 Sekunden) seit der letzten Prüfung vergangen ist
  if (aktuellerZeitpunkt - letzterWlanCheck >= wlanCheckIntervall) {
    pruefeWlanUndSetzeLED();               // Führt den WLAN/Server-Check durch
    letzterWlanCheck = aktuellerZeitpunkt; // Timer zurücksetzen für den nächsten Zyklus
  }

  delay(100); // Kurze Pause (100ms) am Ende der Schleife, um den Prozessor zu entlasten
}

// Überprüft den aktuellen Status der WLAN-Verbindung sowie die letzte Server-Antwort und aktualisiert die Status-LED entsprechend.
// --- ÜBERARBEITETE Funktion: Kontrolliert das WLAN & Server und schaltet die LED ---
void pruefeWlanUndSetzeLED() {
  // LED leuchtet NUR, wenn WLAN verbunden ist UND der Server beim letzten Mal geantwortet hat
  if (WiFi.status() == WL_CONNECTED && letzteVerbindungOK == true) {
    digitalWrite(ledPin, HIGH); // LED AN (Aktive Verbindung und stabiles System)
  } else {
    digitalWrite(ledPin, LOW);  // LED AUS (Kein WLAN oder Server down / Timeout)
  }
}

// Baut ein JSON-Paket mit den System- und Sensordaten zusammen und übermittelt dieses per HTTP POST an die WebApp-Schnittstelle.
// --- 4. Funktion für den HTTP POST Request ---
void sendeDatenAnServer(int statusWert) {
  if(WiFi.status() == WL_CONNECTED) { // Sicherstellen, dass vor dem Senden überhaupt noch Netz da ist
    HTTPClient http;
    
    http.begin(serverUrl);            // Initialisiert die Verbindung zur Ziel-URL
    http.addHeader("Content-Type", "application/json"); // Deklariert den Body-Inhalt als JSON für das PHP-Backend

    int rssi = WiFi.RSSI();           // Liest die echte WLAN-Signalstärke (in dBm) vom Netzwerkchip aus
    int signalStaerke = 0;            // Variable zur Aufnahme des übersetzten Signalwertes

    // Übersetzt den komplexen dBm-Wert in eine einfache Skala von 0-5 für das Dashboard der WebApp
    if (rssi > -55) {
      signalStaerke = 5; 
    } else if (rssi > -65) {
      signalStaerke = 4; 
    } else if (rssi > -75) {
      signalStaerke = 3; 
    } else if (rssi > -85) {
      signalStaerke = 2; 
    } else if (rssi > -95) {
      signalStaerke = 1; 
    } else {
      signalStaerke = 0; 
    }
    
    int batteryLevel = 100; // Dummy-Wert: Da noch kein Spannungsteiler gelötet wurde, wird vorerst 100% Batterie gesendet

    // Konstruiert den exakten JSON-String, wie vom Backend gefordert (inkl. Bewegung, Signal, Batterie und Baby-ID)
    String jsonPayload = "{\"baby_id\": " + String(babyId) + 
                         ", \"bewegung\": " + String(statusWert) + 
                         ", \"battery\": " + String(batteryLevel) + 
                         ", \"signal\": " + String(signalStaerke) + "}";
    
    int httpResponseCode = http.POST(jsonPayload); // Feuert den POST-Request ab und empfängt den HTTP-Statuscode (z.B. 200)
    
    // --- NEU: Server-Status auswerten ---
    if (httpResponseCode > 0) {      // Positive Response-Codes bedeuten in der Regel, dass der Server die Anfrage verarbeitet hat
      letzteVerbindungOK = true;     // Server hat geantwortet, alles gut!
      
      Serial.print("HTTP Code: ");
      Serial.println(httpResponseCode);
      String response = http.getString();
      Serial.println("Server sagt: " + response);
      
      Serial.print("Gesendete WLAN-Stärke: ");
      Serial.print(signalStaerke);
      Serial.println(" / 5");
    } else {
      letzteVerbindungOK = false;    // Fehler! Server nicht erreichbar (z.B. Timeout oder Connection Refused).
      
      Serial.print("❌ Fehler beim Senden. Code: ");
      Serial.println(httpResponseCode);
    }
    
    // LED-Status nach dem Senden sofort aktualisieren, damit Störungen (z.B. Datenbank-Absturz) direkt sichtbar werden
    pruefeWlanUndSetzeLED(); 
    
    http.end(); // Schliesst die HTTP-Verbindung sauber und gibt Ressourcen des Mikrocontrollers wieder frei
  } else {
    Serial.println("❌ Senden fehlgeschlagen: Keine WLAN-Verbindung!");
    letzteVerbindungOK = false;  // Markiert die Systemverbindung als gestört
    pruefeWlanUndSetzeLED();     // LED sofort ausschalten, um den Abbruch anzuzeigen
  }
}