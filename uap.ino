#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClient.h>

// WiFi credentials
const char* ssid = "AYENK";
const char* password = "TRX7904AAM";

// Server URLs
const char* sseUrl = "http://192.168.1.64/UAPFIX/sse.php";
const char* dataUrl = "http://192.168.1.64/UAPFIX/update_distance.php";
const char* statusUrl = "http://192.168.1.64/UAPFIX/update_status.php"; // New URL for status updates

// Pin definitions
const int trigPin = 12;
const int echoPin = 13;
const int buttonPin = 17;
const int redLightPin = 14;
const int violationPin = 27;
const int buzzerPin = 26;

// State variables
int buttonState = 0;
int lastButtonState = 0;
bool addOffset = false;
bool isBuzzerActive = false;
unsigned long lastDebounceTime = 0;
unsigned long debounceDelay = 50;

// Ultrasonic sensor variables
long duration;
int distance;

// Timer variables
unsigned long lastSendTime = 0;
unsigned long lastStatusSendTime = 0;
const long sendInterval = 500;
const long statusSendInterval = 500;  // Send status every 2 seconds

WiFiClient client;
HTTPClient http;
bool sseConnected = false;

void setup() {
    pinMode(trigPin, OUTPUT);
    pinMode(echoPin, INPUT);
    pinMode(buttonPin, INPUT_PULLUP);
    pinMode(redLightPin, OUTPUT);
    pinMode(violationPin, OUTPUT);
    pinMode(buzzerPin, OUTPUT);

    Serial.begin(9600);
    digitalWrite(redLightPin, HIGH);
    digitalWrite(violationPin, LOW);
    analogWrite(buzzerPin, 0);

    WiFi.begin(ssid, password);
    Serial.println("Connecting to WiFi...");
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\nConnected to WiFi");

    connectToSSE();
}

void loop() {
    // Handle button debounce and offset toggle
    int reading = digitalRead(buttonPin);
    if (reading != lastButtonState) {
        lastDebounceTime = millis();
    }
    
    if ((millis() - lastDebounceTime) > debounceDelay) {
        if (reading != buttonState) {
            buttonState = reading;
            if (buttonState == LOW) {
                addOffset = !addOffset;
                digitalWrite(redLightPin, !addOffset);
                Serial.print("Offset status changed to: ");
                Serial.println(addOffset ? "ON" : "OFF");
                sendStatusUpdate(); // Send immediate status update when button is pressed
            }
        }
    }
    lastButtonState = reading;

    // Measure distance
    digitalWrite(trigPin, LOW);
    delayMicroseconds(2);
    digitalWrite(trigPin, HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPin, LOW);
    duration = pulseIn(echoPin, HIGH);
    distance = duration * 0.034 / 2;

    if (distance <= 0 || distance > 400) {
        Serial.println("Invalid reading detected.");
        isBuzzerActive = false;
        analogWrite(buzzerPin, 0);
        return;
    }

    if (addOffset) {
        distance += 200;
    }

    // Handle buzzer activation based on distance
    bool previousBuzzerState = isBuzzerActive;
    if (!addOffset) {
        if (distance < 21) {
            digitalWrite(violationPin, HIGH);
            int buzzerVolume = map(distance, 0, 20, 0, 255);
            analogWrite(buzzerPin, buzzerVolume);
            isBuzzerActive = true;
            Serial.println("Violation detected! Distance: " + String(distance) + "cm, Buzzer: " + String(buzzerVolume));
        } else {
            digitalWrite(violationPin, LOW);
            analogWrite(buzzerPin, 0);
            isBuzzerActive = false;
        }
    } else {
        digitalWrite(violationPin, LOW);
        analogWrite(buzzerPin, 0);
        isBuzzerActive = false;
    }

    // Send status update if buzzer state changed
    if (previousBuzzerState != isBuzzerActive) {
        sendStatusUpdate();
    }

    // Regular data and status updates
    if (millis() - lastSendTime >= sendInterval) {
        sendDistanceData(distance);
        lastSendTime = millis();
    }

    if (millis() - lastStatusSendTime >= statusSendInterval) {
        sendStatusUpdate();
        lastStatusSendTime = millis();
    }

    handleSSE();
    delay(100);
}

void sendStatusUpdate() {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        
        // Make sure this URL matches your server configuration
        String url = "http://192.168.1.64/UAPFIX/update_status.php";
        
        // Add parameters to URL
        url += "?offset=" + String(addOffset ? "1" : "0");
        url += "&buzzer=" + String(isBuzzerActive ? "1" : "0");
        
        
        http.begin(url);
        int httpResponseCode = http.GET();
        
        if (httpResponseCode > 0) {
            String response = http.getString();
            Serial.println("Status update sent successfully");
        } else {
            Serial.print("Error sending status update. Error code: ");
            Serial.println(httpResponseCode);
            Serial.print("URL attempted: ");
            Serial.println(url);
        }
        
        http.end();
    } else {
        Serial.println("WiFi not connected. Cannot send status update.");
    }
}

void sendDistanceData(int distance) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        String url = String(dataUrl) + "?distance=" + String(distance);
        
        http.begin(url);
        int httpResponseCode = http.GET();
        
        if (httpResponseCode > 0) {
            Serial.println("Data sent successfully");
        } else {
            Serial.println("Error sending data");
        }
        
        http.end();
    }
}

void connectToSSE() {
    HTTPClient http;
    
    String host = "192.168.1.64";
    String path = "/UAPFIX/sse.php";
    
    if (!client.connect(host.c_str(), 80)) {
        Serial.println("Failed to connect to SSE server");
        sseConnected = false;
        return;
    }

    String request = String("GET ") + path + " HTTP/1.1\r\n" +
                    "Host: " + host + "\r\n" +
                    "Accept: text/event-stream\r\n" +
                    "Cache-Control: no-cache\r\n" +
                    "Connection: keep-alive\r\n\r\n";
                    
    client.print(request);
    
    unsigned long timeout = millis();
    while (client.available() == 0) {
        if (millis() - timeout > 5000) {
            Serial.println("Client Timeout!");
            client.stop();
            sseConnected = false;
            return;
        }
    }

    while (client.available()) {
        String line = client.readStringUntil('\n');
        if (line == "\r") {
            Serial.println("Headers received");
            break;
        }
    }

    Serial.println("Connected to SSE server");
    sseConnected = true;
}

void handleSSE() {
    if (!sseConnected) {
        connectToSSE();
        return;
    }

    while (client.available()) {
        String line = client.readStringUntil('\n');
        line.trim();
        if (line.startsWith("data:")) {
            String data = line.substring(5);
            processSSEMessage(data);
        }
    }

    if (!client.connected()) {
        Serial.println("SSE connection lost");
        sseConnected = false;
    }
}

void processSSEMessage(String message) {
    Serial.println("SSE message received: " + message);

    if (message == "offset_on") {
        addOffset = true;
        digitalWrite(redLightPin, LOW);
        Serial.println("Offset turned ON via SSE");
        sendStatusUpdate();
    } 
    else if (message == "offset_off") {
        addOffset = false;
        digitalWrite(redLightPin, HIGH);
        Serial.println("Offset turned OFF via SSE");
        sendStatusUpdate();
    }
    else if (message == "buzzer_on") {
        // We'll let the distance measurement control the buzzer
        // but we can update the status
        Serial.println("Buzzer control enabled via SSE");
        sendStatusUpdate();
    }
    else if (message == "buzzer_off") {
        // Force buzzer off
        analogWrite(buzzerPin, 0);
        isBuzzerActive = false;
        Serial.println("Buzzer turned OFF via SSE");
        sendStatusUpdate();
    }
}