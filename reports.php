<?php
// Include the auth check file
include 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include database connection
include 'backend/db.php';

// Page title
$pageTitle = "Reports & Analytics";
?>

<?php include 'includes/header.php'; ?>

<!-- Add necessary stylesheets and scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Reports & Analytics</h1>
            <p class="text-gray-400">View and generate reports for the immunization system</p>
        </div>
        
        <!-- Reports Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <!-- Vaccination Status Report -->
            <div class="dashboard-card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-syringe text-blue-500 mr-2"></i>
                        Vaccination Status
                    </h2>
                    <a href="#" class="text-xs text-blue-400 hover:text-blue-300 flex items-center">
                        Export
                        <i class="fas fa-download ml-1 text-xs"></i>
                    </a>
                </div>
                <div class="h-56">
                    <canvas id="vaccinationStatusChart"></canvas>
                </div>
            </div>
            
            <!-- Age Distribution Report -->
            <div class="dashboard-card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-child text-green-500 mr-2"></i>
                        Age Distribution
                    </h2>
                    <a href="#" class="text-xs text-blue-400 hover:text-blue-300 flex items-center">
                        Export
                        <i class="fas fa-download ml-1 text-xs"></i>
                    </a>
                </div>
                <div class="h-56">
                    <canvas id="ageDistributionReportChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Appointments -->
            <div class="dashboard-card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-calendar text-purple-500 mr-2"></i>
                        Monthly Appointments
                    </h2>
                    <a href="#" class="text-xs text-blue-400 hover:text-blue-300 flex items-center">
                        Export
                        <i class="fas fa-download ml-1 text-xs"></i>
                    </a>
                </div>
                <div class="h-56">
                    <canvas id="monthlyAppointmentsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- More Reports Section -->
        <div class="mt-8">
            <h2 class="text-xl font-semibold text-white mb-4">Generated Reports</h2>
            
            <div class="dashboard-card p-5">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Report Name
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Date Generated
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800/30 divide-y divide-gray-700">
                            <tr class="hover:bg-gray-700/50 transition duration-150">
                                <td class="px-4 py-3 text-sm text-white">
                                    Monthly Vaccination Report
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <?php echo date('M d, Y'); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        PDF
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <div class="flex space-x-2">
                                        <button class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-700/50 transition duration-150">
                                <td class="px-4 py-3 text-sm text-white">
                                    Quarterly Inventory Report
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <?php echo date('M d, Y', strtotime('-7 days')); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Excel
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <div class="flex space-x-2">
                                        <button class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-700/50 transition duration-150">
                                <td class="px-4 py-3 text-sm text-white">
                                    Annual Coverage Report
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <?php echo date('M d, Y', strtotime('-14 days')); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        PDF
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <div class="flex space-x-2">
                                        <button class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // Initialize charts when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Vaccination Status Chart
        const vaccinationCtx = document.getElementById('vaccinationStatusChart').getContext('2d');
        new Chart(vaccinationCtx, {
            type: 'pie',
            data: {
                labels: ['Completed', 'Scheduled', 'Missed'],
                datasets: [{
                    data: [65, 25, 10],
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.7)',  // Green
                        'rgba(59, 130, 246, 0.7)',  // Blue
                        'rgba(239, 68, 68, 0.7)'    // Red
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#E5E7EB',
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            padding: 20
                        }
                    }
                }
            }
        });
        
        // Age Distribution Chart
        const ageCtx = document.getElementById('ageDistributionReportChart').getContext('2d');
        new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: ['0-1 year', '1-2 years', '2-3 years', '3+ years'],
                datasets: [{
                    label: 'Children by Age',
                    data: [42, 38, 35, 29],
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(55, 65, 81, 0.3)'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Monthly Appointments Chart
        const appointmentsCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
        new Chart(appointmentsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Appointments',
                    data: [28, 35, 42, 38, 45, 48],
                    fill: true,
                    backgroundColor: 'rgba(124, 58, 237, 0.2)',
                    borderColor: 'rgba(124, 58, 237, 1)',
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(124, 58, 237, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(124, 58, 237, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(55, 65, 81, 0.3)'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(55, 65, 81, 0.2)'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#E5E7EB',
                            font: {
                                family: 'Inter'
                            }
                        }
                    }
                }
            }
        });
    });
</script>
