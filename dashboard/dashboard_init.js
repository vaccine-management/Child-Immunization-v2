document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar responsiveness
    initSidebarResponsive();
    
    // Initialize stock level chart if data is available
    let stockChart;
    if (typeof vaccineNames !== 'undefined' && typeof stockLevels !== 'undefined') {
        stockChart = initStockChart(vaccineNames, stockLevels);
        
        // Only update chart on window resize, not on sidebar hover
        window.addEventListener('resize', function() {
            if (stockChart) {
                stockChart.updateOptions({
                    // Maintain the same options but trigger a resize
                    chart: {
                        width: '100%'
                    }
                });
            }
        });
    }
    
    // Initialize age distribution chart if data is available
    let ageChart;
    if (typeof ageGroups !== 'undefined' && typeof ageCounts !== 'undefined') {
        ageChart = initAgeChart(ageGroups, ageCounts);
    }
    
    // Initialize calendar if data is available
    let calendar;
    if (typeof calendarEvents !== 'undefined') {
        calendar = initCalendar(calendarEvents);
        
        // Update calendar size only on window resize
        window.addEventListener('resize', function() {
            if (calendar) {
                setTimeout(function() {
                    calendar.updateSize();
                }, 300);
            }
        });
    }
}); 