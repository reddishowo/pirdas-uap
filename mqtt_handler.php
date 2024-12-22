// File 1: mqtt_handler.php
<?php
require 'vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MQTTHandler {
    private $host = 'broker.emqx.io';
    private $port = 1883;
    private $username = 'farriel';
    private $password = 'TRX7904AAM';
    private $clientId;
    private $db;
    
    public function __construct() {
        $this->clientId = 'php_mqtt_' . uniqid();
        try {
            $this->db = new PDO('mysql:host=localhost', 'root', '');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->db->exec("CREATE DATABASE IF NOT EXISTS distance_monitoring");
            $this->db = new PDO('mysql:host=localhost;dbname=distance_monitoring', 'root', '');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->initializeTables();
            echo "Database connection established\n";
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
    }

    private function initializeTables() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS distance_readings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            distance FLOAT NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS system_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            offset_status TINYINT(1) NOT NULL DEFAULT 0,
            buzzer_status TINYINT(1) NOT NULL DEFAULT 0,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    public function storeDistanceReading($distance) {
        try {
            $stmt = $this->db->prepare("INSERT INTO distance_readings (distance) VALUES (?)");
            return $stmt->execute([$distance]);
        } catch (PDOException $e) {
            error_log("Error storing distance: " . $e->getMessage());
            return false;
        }
    }
    
    public function storeSystemStatus($offsetStatus, $buzzerStatus) {
        try {
            // Convert boolean or string values to integers
            $offsetInt = filter_var($offsetStatus, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $buzzerInt = filter_var($buzzerStatus, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            
            $stmt = $this->db->prepare("INSERT INTO system_status (offset_status, buzzer_status) VALUES (?, ?)");
            return $stmt->execute([$offsetInt, $buzzerInt]);
        } catch (PDOException $e) {
            error_log("Error storing status: " . $e->getMessage());
            return false;
        }
    }
    
    public function getLatestReadings($limit = 50) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM distance_readings ORDER BY timestamp DESC LIMIT ?");
            $stmt->execute([$limit]);
            return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLatestStatus() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM system_status ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($status) {
                // Convert integer values to boolean for the API response
                $status['offset_status'] = (bool)$status['offset_status'];
                $status['buzzer_status'] = (bool)$status['buzzer_status'];
            }
            
            return $status;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return null;
        }
    }
    
    public function publishCommand($command) {
        try {
            $mqtt = new MqttClient($this->host, $this->port, $this->clientId);
            $connectionSettings = (new ConnectionSettings())
                ->setUsername($this->username)
                ->setPassword($this->password);
            
            $mqtt->connect($connectionSettings);
            $mqtt->publish('uapfix/command', $command, 0);
            $mqtt->disconnect();
            return true;
        } catch (Exception $e) {
            error_log("MQTT Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function startListening() {
        try {
            $mqtt = new MqttClient($this->host, $this->port, $this->clientId);
            
            $connectionSettings = (new ConnectionSettings())
                ->setUsername($this->username)
                ->setPassword($this->password)
                ->setKeepAliveInterval(60)
                ->setConnectTimeout(60);
            
            $mqtt->connect($connectionSettings);
            echo "Connected to MQTT broker successfully\n";
            
            $mqtt->subscribe('uapfix/distance', function ($topic, $message) {
                try {
                    echo "Received distance: $message\n";
                    $this->storeDistanceReading((float)$message);
                } catch (Exception $e) {
                    echo "Error processing distance message: " . $e->getMessage() . "\n";
                }
            }, 0);
            
            $mqtt->subscribe('uapfix/status', function ($topic, $message) {
                try {
                    echo "Received status: $message\n";
                    if (preg_match('/Offset: (ON|OFF), Buzzer: (ON|OFF)/', $message, $matches)) {
                        $offsetStatus = $matches[1] === 'ON';
                        $buzzerStatus = $matches[2] === 'ON';
                        $this->storeSystemStatus($offsetStatus, $buzzerStatus);
                    }
                } catch (Exception $e) {
                    echo "Error processing status message: " . $e->getMessage() . "\n";
                }
            }, 0);
            
            echo "Subscribed to topics, waiting for messages...\n";
            
            while (true) {
                $mqtt->loop(true);
                usleep(100000); // 100ms delay
            }
            
        } catch (Exception $e) {
            echo "MQTT Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}