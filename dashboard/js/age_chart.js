// Age Distribution Chart Configuration
function initAgeChart(ageGroups, ageCounts) {
    // Set global chart defaults for dark theme
    Chart.defaults.color = '#E5E7EB';
    Chart.defaults.borderColor = '#374151';
    
    const ageCtx = document.getElementById('ageDistributionChart').getContext('2d');
    
    const ageData = {
        labels: ageGroups,
        datasets: [{
            label: 'Children by Age Group',
            data: ageCounts,
            backgroundColor: [
                'rgba(59, 130, 246, 0.7)', // Blue
                'rgba(16, 185, 129, 0.7)', // Green
                'rgba(245, 158, 11, 0.7)', // Yellow
                'rgba(139, 92, 246, 0.7)'  // Purple
            ],
            borderWidth: 0
        }]
    };
    
    const ageConfig = {
        type: 'doughnut',
        data: ageData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Inter',
                            size: 12
                        },
                        color: '#E5E7EB',
                        padding: 20
                    }
                }
            },
            cutout: '65%'
        }
    };
    
    const ageChart = new Chart(ageCtx, ageConfig);
    
    return ageChart;
}