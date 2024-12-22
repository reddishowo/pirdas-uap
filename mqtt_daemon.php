<?php
// mqtt_daemon.php

require_once 'mqtt_handler.php';

class MQTTDaemon {
    private $handler;
    private $running = true;
    private $reconnectDelay = 5; // seconds
    private $maxReconnectAttempts = 10;

    public function __construct() {
        $this->handler = new MQTTHandler();
    }

    public function run() {
        echo "Starting MQTT daemon...\n";
        
        while ($this->running) {
            try {
                echo "Attempting to connect to MQTT broker...\n";
                
                $reconnectAttempts = 0;
                while ($reconnectAttempts < $this->maxReconnectAttempts) {
                    try {
                        $this->handler->startListening();
                        // If we get here, connection was successful
                        $reconnectAttempts = 0;
                        break;
                    } catch (Exception $e) {
                        $reconnectAttempts++;
                        echo "Connection attempt {$reconnectAttempts} failed: " . $e->getMessage() . "\n";
                        
                        if ($reconnectAttempts >= $this->maxReconnectAttempts) {
                            echo "Max reconnection attempts reached. Waiting 60 seconds before trying again...\n";
                            sleep(60);
                            $reconnectAttempts = 0;
                        } else {
                            echo "Waiting {$this->reconnectDelay} seconds before reconnecting...\n";
                            sleep($this->reconnectDelay);
                        }
                    }
                }
            } catch (Exception $e) {
                echo "Critical error: " . $e->getMessage() . "\n";
                echo "Daemon will restart in 60 seconds...\n";
                sleep(60);
            }
        }
    }

    public function stop() {
        $this->running = false;
    }
}

// Start the daemon
$daemon = new MQTTDaemon();

try {
    $daemon->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}