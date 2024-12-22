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
        .data-table th, .data-table td {
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
            margin-top: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ultrasonic Sensor Monitor</h1>
        
        <div class="controls">
            <div class="status">
                Status: <span id="offsetStatus">OFF</span>
            </div>
            <button id="toggleButton" class="toggle-button off" onclick="toggleOffset()">
                OFFSET OFF
            </button>
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
        let isOffsetOn = false;
        
        // Initialize chart
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
                animation: false // Disable animations for better performance
            }
        });

        // Update chart data
        function updateChart(timestamp, distance) {
            const timeStr = new Date(timestamp).toLocaleTimeString();
            
            chart.data.labels.push(timeStr);
            chart.data.datasets[0].data.push(distance);
            
            // Keep only last 50 data points
            if (chart.data.labels.length > 25) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
            }
            
            chart.update('none'); // Update without animation
            
            // Update table
            updateTable(timestamp, distance);
        }

        // Update table
        function updateTable(timestamp, distance) {
            const tableBody = document.querySelector('#dataTable tbody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>${new Date(timestamp).toLocaleString()}</td>
                <td>${distance}</td>
            `;
            tableBody.insertBefore(newRow, tableBody.firstChild);
            
            // Keep only last 50 rows
            while (tableBody.children.length > 5) {
                tableBody.removeChild(tableBody.lastChild);
            }
        }

        // Toggle offset
        function toggleOffset() {
            isOffsetOn = !isOffsetOn;
            const button = document.getElementById('toggleButton');
            const statusSpan = document.getElementById('offsetStatus');
            
            // Update button appearance
            button.className = `toggle-button ${isOffsetOn ? 'on' : 'off'}`;
            button.textContent = `OFFSET ${isOffsetOn ? 'ON' : 'OFF'}`;
            statusSpan.textContent = isOffsetOn ? 'ON' : 'OFF';

            // Send status to server
            fetch('toggle_offset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    status: isOffsetOn ? 'on' : 'off' 
                })
            });
        }

        // Download data
        function downloadData() {
            window.location.href = 'sensor_data.csv';
        }

        // Real-time data polling
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

        // Start polling when page loads
        window.addEventListener('load', () => {
            // Load initial data
            fetch('get_history.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(entry => {
                        updateChart(entry.timestamp, entry.distance);
                    });
                });

            // Set up polling interval
            setInterval(pollData, 1000); // Poll every second
        });
    </script>
</body>
</html>