#include <WiFi.h>
#include <PubSubClient.h>

// WiFi credentials
const char* ssid = "AYENK";
const char* password = "TRX7904AAM";

// MQTT configuration
const char* mqtt_server = "broker.emqx.io"; // Replace with your MQTT broker's IP address
const int mqtt_port = 1883; // Default MQTT port
const char* mqtt_user = "farriel"; // Replace with your MQTT username
const char* mqtt_password = "TRX7904AAM"; // Replace with your MQTT password
const char* client_id = "mqttx_bd691ab0"; // Unique client ID

// MQTT topics
const char* distanceTopic = "uapfix/distance";
const char* statusTopic = "uapfix/status";
const char* commandTopic = "uapfix/command";

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
const long statusSendInterval = 2000;  // Send status every 2 seconds

WiFiClient espClient;
PubSubClient client(espClient);

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

  // Connect to WiFi
  WiFi.begin(ssid, password);
  Serial.println("Connecting to WiFi...");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nConnected to WiFi");

  // Set up MQTT client
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);

  // Attempt to connect to MQTT broker
  if (connectToMQTT()) {
    client.subscribe(commandTopic);
  }
}

void loop() {
  if (!client.connected()) {
    connectToMQTT();
  }
  client.loop();

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
        sendStatusUpdate();
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

  delay(100);
}

bool connectToMQTT() {
  while (!client.connected()) {
    Serial.print("Attempting MQTT connection...");
    // Create a random client ID
    String clientId = "ESP32Client-";
    clientId += String(random(0xffff), HEX);
    
    // Attempt to connect
    if (client.connect(clientId.c_str(), mqtt_user, mqtt_password)) {
      Serial.println("connected");
      // Once connected, publish an announcement...
      client.publish(statusTopic, "ESP32 connected");
      // ... and resubscribe
      client.subscribe(commandTopic);
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());
      Serial.println(" try again in 5 seconds");
      // Wait 5 seconds before retrying
      delay(5000);
    }
  }
  return true;
}

void callback(char* topic, byte* payload, unsigned int length) {
  Serial.print("Message arrived [");
  Serial.print(topic);
  Serial.print("] ");
  String message;
  for (int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  Serial.println(message);

  if (strcmp(topic, commandTopic) == 0) {
    if (message == "offset_on") {
      addOffset = true;
      digitalWrite(redLightPin, LOW);
      Serial.println("Offset turned ON via MQTT");
      sendStatusUpdate();
    } else if (message == "offset_off") {
      addOffset = false;
      digitalWrite(redLightPin, HIGH);
      Serial.println("Offset turned OFF via MQTT");
      sendStatusUpdate();
    } else if (message == "buzzer_on") {
      Serial.println("Buzzer control enabled via MQTT");
      sendStatusUpdate();
    } else if (message == "buzzer_off") {
      analogWrite(buzzerPin, 0);
      isBuzzerActive = false;
      Serial.println("Buzzer turned OFF via MQTT");
      sendStatusUpdate();
    }
  }
}

void sendDistanceData(int distance) {
  if (client.connected()) {
    String message = String(distance);
    client.publish(distanceTopic, message.c_str());
    Serial.println("Distance data sent: " + message);
  }
}

void sendStatusUpdate() {
  if (client.connected()) {
    String message = "Offset: " + String(addOffset ? "ON" : "OFF") + ", Buzzer: " + String(isBuzzerActive ? "ON" : "OFF");
    client.publish(statusTopic, message.c_str());
    Serial.println("Status update sent: " + message);
  }
}