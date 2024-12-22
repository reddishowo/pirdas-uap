<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distance Monitoring Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold mb-4">Distance Monitoring System</h1>

            <!-- Controls -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h2 class="text-lg font-semibold mb-3">Offset Control</h2>
                    <div class="flex space-x-4">
                        <button onclick="sendCommand('offset_on')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                            Offset ON
                        </button>
                        <button onclick="sendCommand('offset_off')" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                            Offset OFF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Status Display -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h2 class="text-lg font-semibold mb-2">Current Status</h2>
                <p id="statusDisplay" class="text-gray-700">Loading...</p>
            </div>

            <!-- Distance Chart -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h2 class="text-lg font-semibold mb-3">Distance Readings</h2>
                <canvas id="distanceChart"></canvas>
            </div>
        </div>
    </div>

    <script>
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
                        max: 400
                    }
                },
                animation: {
                    duration: 0
                }
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

        function updateChart() {
            fetch('api.php?action=get_readings')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        chart.data.labels = data.data.map(reading =>
                            new Date(reading.timestamp).toLocaleTimeString()
                        );
                        chart.data.datasets[0].data = data.data.map(reading =>
                            parseFloat(reading.distance)
                        );
                        chart.update();
                    }
                })
                .catch(error => console.error('Error updating chart:', error));
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

        // Update data every 2 seconds
        setInterval(() => {
            updateChart();
            updateStatus();
        }, 2000);

        // Initial update
        updateChart();
        updateStatus();
    </script>
</body>
</html>