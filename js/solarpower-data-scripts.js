jQuery(document).ready(function($) {
    if (typeof SolarPowerData !== 'undefined') {
        // Function to format power values
        function formatValue(value, unit) {
            value = parseFloat(value);
            if (unit === 'W' || unit === 'kW' || unit === 'MW') {
                if (value >= 1000000) {
                    return (value / 1000000).toFixed(2) + ' MW';
                } else if (value >= 1000) {
                    return (value / 1000).toFixed(2) + ' kW';
                } else {
                    return value + ' W';
                }
            } else if (unit === 'Wh' || unit === 'kWh' || unit === 'MWh') {
                if (value >= 1000000) {
                    return (value / 1000000).toFixed(2) + ' MWh';
                } else if (value >= 1000) {
                    return (value / 1000).toFixed(2) + ' kWh';
                } else {
                    return value + ' Wh';
                }
            } else {
                return value + ' ' + unit;
            }
        }

        // Prepare datasets
        var datasets = [];
        var colors = ['red', 'green', 'blue', 'orange', 'purple', 'cyan'];

        SolarPowerData.entities.forEach(function(entity, index) {
            datasets.push({
                label: entity.label,
                data: entity.data,
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length],
                borderWidth: 1,
                pointRadius: 0,
                tension: 0.2,
                parsing: false,
            });
        });

        // Create chart
        var ctx = document.getElementById('solarpowerChart').getContext('2d');
        new Chart(ctx, {
            type: SolarPowerData.chartType,
            data: {
                datasets: datasets
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            parser: 'yyyy-MM-dd HH:mm:ss',
                            tooltipFormat: 'dd.MM.yyyy HH:mm',
                            unit: SolarPowerData.granularity,
                            displayFormats: {
                                'hour': 'dd.MM HH:mm',
                                'day': 'dd.MM.yyyy',
                                'week': 'ww/yyyy'
                            }
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 10
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                // Format based on the first entity's unit
                                var unit = SolarPowerData.entities[0].unit;
                                return formatValue(value, unit);
                            }
                        },
                        title: {
                            display: true,
                            text: 'Value'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                var value = context.parsed.y;
                                var unit = SolarPowerData.entities[context.datasetIndex].unit;
                                return label + ': ' + formatValue(value, unit);
                            }
                        }
                    }
                }
            }
        });
    } else {
        console.error('SolarPowerData is not defined.');
    }
});
