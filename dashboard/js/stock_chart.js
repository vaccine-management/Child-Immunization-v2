// Vaccine Stock Chart Configuration
function initStockChart(vaccineNames, stockLevels) {
    const vaccineStockOptions = {
        series: [{
            name: 'Stock Level',
            data: stockLevels
        }],
        chart: {
            type: 'bar',
            height: 320,
            toolbar: {
                show: false
            },
            fontFamily: 'Inter, sans-serif',
            background: 'transparent'
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 6,
                dataLabels: {
                    position: 'top',
                },
            },
        },
        colors: ['#3b82f6'],
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val;
            },
            offsetY: -20,
            style: {
                fontSize: '12px',
                colors: ["#E5E7EB"]
            }
        },
        xaxis: {
            categories: vaccineNames,
            position: 'bottom',
            labels: {
                style: {
                    colors: '#9CA3AF',
                    fontSize: '12px',
                    fontFamily: 'Inter, sans-serif',
                },
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            },
        },
        yaxis: {
            title: {
                text: 'Doses Available',
                style: {
                    color: '#9CA3AF',
                    fontSize: '12px',
                    fontFamily: 'Inter, sans-serif',
                }
            },
            labels: {
                style: {
                    colors: '#9CA3AF',
                    fontSize: '12px',
                    fontFamily: 'Inter, sans-serif',
                },
            },
        },
        grid: {
            borderColor: '#374151',
            strokeDashArray: 4,
            yaxis: {
                lines: {
                    show: true
                }
            }
        },
        tooltip: {
            theme: 'dark',
            y: {
                formatter: function (val) {
                    return val + " doses";
                }
            }
        }
    };

    const stockChart = new ApexCharts(document.querySelector("#vaccineStockChart"), vaccineStockOptions);
    stockChart.render();
    
    return stockChart;
} 