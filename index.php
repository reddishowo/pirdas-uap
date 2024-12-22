<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distance Monitoring Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.0.1/mqttws31.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-2xl p-8 mb-8">
            <h1 class="text-3xl font-bold mb-6 text-center text-indigo-800">Distance Monitoring System</h1>
            
            <!-- Controls -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-indigo-50 p-6 rounded-xl shadow-lg">
                    <h2 class="text-xl font-semibold mb-4 text-indigo-800 flex items-center">
                        <i class="fas fa-sliders-h mr-2"></i>
                        Offset Control
                    </h2>
                    <div class="flex space-x-4">
                        <button onclick="sendCommand('offset_on')" class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition duration-300 ease-in-out transform hover:-translate-y-1 hover:scale-110">
                            <i class="fas fa-power-off mr-2"></i>
                            Offset ON
                        </button>
                        <button onclick="sendCommand('offset_off')" class="bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition duration-300 ease-in-out transform hover:-translate-y-1 hover:scale-110">
                            <i class="fas fa-power-off mr-2"></i>
                            Offset OFF
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Status Display -->
            <div class="bg-indigo-50 p-6 rounded-xl shadow-lg mb-8">
                <h2 class="text-xl font-semibold mb-4 text-indigo-800 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Current Status
                </h2>
                <p id="statusDisplay" class="text-gray-700 text-lg"></p>
            </div>
            
            <!-- Distance Chart -->
            <div class="bg-indigo-50 p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold mb-4 text-indigo-800 flex items-center">
                    <i class="fas fa-chart-line mr-2"></i>
                    Distance Readings
                </h2>
                <canvas id="distanceChart" class="w-full h-96"></canvas>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script>
        // Initialize the chart
        const chart = new Chart(document.getElementById('distanceChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Distance (cm)',
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 200
                    }
                },
                animation: {
                    duration: 0
                }
            }
        });

        // MQTT client setup
        const client = mqtt.connect('wss://broker.emqx.io:8084/mqtt', {
            clientId: 'web_' + Math.random().toString(16).substr(2, 8),
            username: 'farriel',
            password: 'TRX7904AAM'
        });

        client.on('connect', () => {
            console.log('Connected to MQTT broker');
            client.subscribe('uapfix/distance', (err) => {
                if (!err) {
                    console.log('Subscribed to uapfix/distance');
                } else {
                    console.error('Error subscribing:', err);
                }
            });
        });

        client.on('message', (topic, message) => {
            if (topic === 'uapfix/distance') {
                const distance = parseFloat(message.toString());
                const timestamp = new Date().toLocaleTimeString();

                chart.data.labels.push(timestamp);
                chart.data.datasets[0].data.push(distance);

                // Limit the number of data points to 20
                if (chart.data.labels.length > 10) {
                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();
                }

                chart.update();
            }
        });

        function sendCommand(command) {
            const formData = new FormData();
            formData.append('command', command);
            fetch('api.php?action=send_command', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Command response:', data);
                if (!data.success) {
                    alert('Error sending command: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error sending command:', error);
                alert('Error sending command');
            });
        }

        function updateStatus() {
            fetch('api.php?action=get_status')
                .then(response => response.json())
                .then(data => {
                    const statusDisplay = document.getElementById('statusDisplay');
                    if (data.success && data.data) {
                        const status = data.data;
                        const timestamp = new Date(status.timestamp).toLocaleString();
                        statusDisplay.innerHTML = `
                            Offset: <span class="font-bold ${status.offset_status ? 'text-green-600' : 'text-red-600'}">
                                ${status.offset_status ? 'ON' : 'OFF'}
                            </span>,
                            Buzzer: <span class="font-bold ${status.buzzer_status ? 'text-green-600' : 'text-red-600'}">
                                ${status.buzzer_status ? 'ON' : 'OFF'}
                            </span>
                            <span class="text-gray-500 text-sm ml-2">(Last updated: ${timestamp})</span>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error updating status:', error);
                    document.getElementById('statusDisplay').textContent = 'Error fetching status';
                });
        }

        // Update status every 2 seconds
        setInterval(updateStatus, 2000);
        // Initial status update
        updateStatus();
    </script>
</body>
</html>