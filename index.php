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
    </style>
</head>
<body>
    <div class="container">
        <h1>Ultrasonic Sensor Monitor</h1>
        
        <div class="controls">
            <div class="status">
                Offset Status: <span id="offsetStatus">OFF</span>
            </div>
            <button class="button" onclick="toggleOffset()">Toggle Offset</button>
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
                }
            }
        });

        // Load historical data on page load
        async function loadHistoricalData() {
            try {
                const response = await fetch('get_history.php');
                const data = await response.json();
                
                // Update chart with historical data
                data.forEach(entry => {
                    const timeStr = new Date(entry.timestamp).toLocaleTimeString();
                    chart.data.labels.push(timeStr);
                    chart.data.datasets[0].data.push(entry.distance);
                });
                
                chart.update();
                
                // Update table
                updateDataTable(data);
            } catch (error) {
                console.error('Error loading historical data:', error);
            }
        }

        // Update chart data
        function updateChart(distance) {
            const now = new Date();
            const timeStr = now.toLocaleTimeString();
            
            chart.data.labels.push(timeStr);
            chart.data.datasets[0].data.push(distance);
            
            // Keep only last 50 data points
            if (chart.data.labels.length > 50) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
            }
            
            chart.update();
            
            // Update table with new data
            const tableBody = document.querySelector('#dataTable tbody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>${now.toLocaleString()}</td>
                <td>${distance}</td>
            `;
            tableBody.insertBefore(newRow, tableBody.firstChild);
            
            // Keep only last 50 rows in table
            while (tableBody.children.length > 50) {
                tableBody.removeChild(tableBody.lastChild);
            }
        }

        // Update data table
        function updateDataTable(data) {
            const tableBody = document.querySelector('#dataTable tbody');
            tableBody.innerHTML = '';
            
            data.forEach(entry => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${new Date(entry.timestamp).toLocaleString()}</td>
                    <td>${entry.distance}</td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Download data as CSV
        function downloadData() {
            window.location.href = 'sensor_data.csv';
        }

        // Toggle offset status
        function toggleOffset() {
            const currentStatus = document.getElementById('offsetStatus').innerText;
            const newStatus = currentStatus === 'OFF' ? 'ON' : 'OFF';
            
            fetch('toggle_offset.php', {
                method: 'POST',
                body: JSON.stringify({ status: newStatus.toLowerCase() })
            })
            .then(response => response.text())
            .then(result => {
                document.getElementById('offsetStatus').innerText = newStatus;
            });
        }

        // Connect to SSE
        const evtSource = new EventSource('sse.php');
        
        evtSource.onmessage = function(event) {
            const data = event.data;
            if (data === 'offset_on' || data === 'offset_off') {
                document.getElementById('offsetStatus').innerText = 
                    data === 'offset_on' ? 'ON' : 'OFF';
            } else {
                // Assume it's distance data
                const distance = parseInt(data);
                if (!isNaN(distance)) {
                    updateChart(distance);
                }
            }
        };

        // Load historical data when page loads
        loadHistoricalData();
    </script>
</body>
</html>