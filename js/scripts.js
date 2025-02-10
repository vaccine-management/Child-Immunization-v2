document.addEventListener("DOMContentLoaded", function () {
    console.log("Page Loaded. Initializing components...");

    // Profile Dropdown Toggle
    const profileDropdown = document.getElementById('profileDropdown');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (profileDropdown && dropdownMenu) {
        profileDropdown.addEventListener('click', () => {
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            if (!profileDropdown.contains(event.target) && !dropdownMenu.contains(event.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });
    } else {
        console.warn("Profile dropdown elements not found.");
    }

    // Initialize Chart.js for Vaccine Distribution
    const ctx = document.getElementById('vaccineChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Polio', 'BCG', 'Measles', 'Tetanus'],
                datasets: [{
                    label: 'Vaccines Administered',
                    data: [120, 90, 80, 60],
                    backgroundColor: ['rgba(54, 162, 235, 0.2)', 'rgba(75, 192, 192, 0.2)', 'rgba(255, 206, 86, 0.2)', 'rgba(255, 99, 132, 0.2)'],
                    borderColor: ['rgba(54, 162, 235, 1)', 'rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)', 'rgba(255, 99, 132, 1)'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    } else {
        console.warn("vaccineChart element not found.");
    }

    // Initialize Chart.js for Histogram
    const histCtx = document.getElementById('vaccineHistogram');
    if (histCtx) {
        new Chart(histCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Vaccines Used',
                    data: [50, 70, 60, 80, 90, 100],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    } else {
        console.warn("vaccineHistogram element not found.");
    }

    // Initialize FullCalendar
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: [
                { title: 'Polio Vaccination', date: '2025-02-15', color: '#3b82f6' },
                { title: 'BCG Vaccination', date: '2025-02-20', color: '#10b981' },
                { title: 'Measles Vaccination', date: '2025-02-25', color: '#f59e0b' }
            ]
        });
        calendar.render();
    } else {
        console.warn("Calendar element not found.");
    }
});
