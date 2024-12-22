<!DOCTYPE html>
<html>
<head>
    <title>Ultrasonic Sensor Monitor</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .chart-container {
            width: 100%;
            height: 400px;
        }

        .controls {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .status {
            padding: 10px;
            border-radius: 5px;
            background-color: #f0f0f0;
        }

        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            margin-left: 5px;
            font-weight: bold;
        }

        .status-on {
            background-color: #28a745;
            color: white;
        }

        .status-off {
            background-color: #dc3545;
            color: white;
        }

        .button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }

        .button:hover {
            background-color: #0056b3;
        }

        .data-table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .data-table th {
            background-color: #f4f4f4;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .toggle-button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .toggle-button.on {
            background-color: #28a745;
            color: white;
        }

        .toggle-button.off {
            background-color: #dc3545;
            color: white;
        }

        .table-container {
            margin-top: 20px;
        }

        .status-panel {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-title {
            font-size: 1.2em;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .status-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .status-label {
            width: 120px;
            font-weight: 500;
        }

        .last-update {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
        }
        .table-container{
            margin-top: 200px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>DETEKSI PELANGGARAN (ULTRASONIC)</h1>

        <div class="status-panel">
            <div class="status-title">Device Status</div>
            <div class="status-item">
                <span class="status-label">Offset:</span>
                <span id="offsetStatus" class="status-indicator status-off">OFF</span>
            </div>
            <div class="status-item">
                <span class="status-label">Buzzer:</span>
                <span id="buzzerStatus" class="status-indicator status-off">OFF</span>
            </div>
            <div class="last-update">Last updated: <span id="lastUpdateTime">-</span></div>
        </div>

        <div class="controls">
            <button class="button" onclick="downloadData()">Download Data</button>
        </div>

        <div class="chart-container">
            <canvas id="distanceChart"></canvas>
        </div>

        <div class="table-container">
            <h2>Recent Measurements</h2>
            <table class="data-table" id="dataTable">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Distance (cm)</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Chart initialization code remains the same
        const ctx = document.getElementById('distanceChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Distance (cm)',
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
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
                animation: false
            }
        });

        // Update status indicators
        function updateStatus(status) {
            const offsetIndicator = document.getElementById('offsetStatus');
            const buzzerIndicator = document.getElementById('buzzerStatus');
            const lastUpdateSpan = document.getElementById('lastUpdateTime');

            // Update offset status
            offsetIndicator.textContent = status.offset;
            offsetIndicator.className = `status-indicator status-${status.offset.toLowerCase()}`;

            // Update buzzer status
            buzzerIndicator.textContent = status.buzzer;
            buzzerIndicator.className = `status-indicator status-${status.buzzer.toLowerCase()}`;

            // Update last update time
            lastUpdateSpan.textContent = new Date(status.timestamp).toLocaleString();
        }

        // Poll device status
        function pollStatus() {
            fetch('device_status.json')
                .then(response => response.json())
                .then(status => {
                    updateStatus(status);
                })
                .catch(error => console.error('Error polling status:', error));
        }

        // Chart update function remains the same
        function updateChart(timestamp, distance) {
            const timeStr = new Date(timestamp).toLocaleTimeString();

            chart.data.labels.push(timeStr);
            chart.data.datasets[0].data.push(distance);

            if (chart.data.labels.length > 25) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
            }

            chart.update('none');
            updateTable(timestamp, distance);
        }

        // Table update function remains the same
        function updateTable(timestamp, distance) {
            const tableBody = document.querySelector('#dataTable tbody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>${new Date(timestamp).toLocaleString()}</td>
                <td>${distance}</td>
            `;
            tableBody.insertBefore(newRow, tableBody.firstChild);

            while (tableBody.children.length > 5) {
                tableBody.removeChild(tableBody.lastChild);
            }
        }

        // Download function remains the same
        function downloadData() {
            window.location.href = 'sensor_data.csv';
        }

        // Real-time data polling function
        function pollData() {
            fetch('get_history.php')
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        const lastEntry = data[data.length - 1];
                        updateChart(lastEntry.timestamp, lastEntry.distance);
                    }
                })
                .catch(error => console.error('Error polling data:', error));
        }

        // Initialize when page loads
        window.addEventListener('load', () => {
            // Load initial data
            fetch('get_history.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(entry => {
                        updateChart(entry.timestamp, entry.distance);
                    });
                });

            // Set up polling intervals
            setInterval(pollData, 1000);  // Poll sensor data every second
            setInterval(pollStatus, 500); // Poll status every 500ms
        });
    </script>
</body>
</html>