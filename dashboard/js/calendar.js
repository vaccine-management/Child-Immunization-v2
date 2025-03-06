// Calendar Configuration
function initCalendar(events) {
    const calendarEl = document.getElementById('calendar');
    
    if (!calendarEl) {
        console.error('Calendar element not found');
        return null;
    }
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'today'
        },
        height: 320,
        events: events,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        // Theme customizations for MedHealth-inspired design
        themeSystem: 'standard',
        contentHeight: 'auto',
        buttonText: {
            today: 'Today'
        },
        dayMaxEvents: 1,
        // Adding custom classes to elements
        eventClassNames: 'rounded-lg shadow-md',
        viewDidMount: function(view) {
            // Add custom classes to buttons
            document.querySelectorAll('.fc-button').forEach(btn => {
                btn.classList.add('shadow-sm');
            });
            
            // Add custom styles to day cells
            document.querySelectorAll('.fc-daygrid-day').forEach(day => {
                day.classList.add('hover:bg-gray-700', 'transition-colors');
            });
        }
    });
    
    calendar.render();
    
    // Additional customizations after render
    setTimeout(() => {
        styleFullCalendar();
    }, 100);
    
    return calendar;
}

// Function to apply additional custom styling to the FullCalendar
function styleFullCalendar() {
    // Style the header buttons to match MedHealth design
    document.querySelectorAll('.fc-button-primary').forEach(btn => {
        btn.style.backgroundColor = '#2563EB';
        btn.style.borderColor = '#2563EB';
        btn.style.borderRadius = '6px';
        btn.style.padding = '0.25rem 0.5rem';
        btn.style.boxShadow = '0 2px 4px rgba(37, 99, 235, 0.2)';
        btn.style.fontSize = '0.75rem';
    });
    
    // Style the calendar header
    const header = document.querySelector('.fc-header-toolbar');
    if (header) {
        header.style.padding = '10px';
        header.style.marginBottom = '0';
    }
    
    // Style the title to be more prominent
    const title = document.querySelector('.fc-toolbar-title');
    if (title) {
        title.style.fontSize = '0.95rem';
        title.style.fontWeight = '600';
        title.style.letterSpacing = '0.25px';
    }
    
    // Add border radius to events
    document.querySelectorAll('.fc-event').forEach(event => {
        event.style.borderRadius = '4px';
        event.style.padding = '1px 3px';
        event.style.borderWidth = '0';
        event.style.fontSize = '0.7rem';
    });
    
    // Reduce cell height
    document.querySelectorAll('.fc-daygrid-day-frame').forEach(frame => {
        frame.style.minHeight = '0';
    });
}