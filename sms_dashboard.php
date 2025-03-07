<?php
session_start();

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

// Get SMS logs for display
$stmt = $conn->query("
    SELECT sl.*, c.full_name as child_name 
    FROM sms_logs sl
    LEFT JOIN children c ON sl.child_id = c.child_id
    ORDER BY sl.sent_at DESC
    LIMIT 100
");
$smsLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stmt = $conn->query("
    SELECT 
        COUNT(*) as total_sms,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_sms,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_sms,
        COUNT(DISTINCT recipient) as unique_recipients,
        COUNT(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
        COUNT(CASE WHEN message_type = 'registration' THEN 1 ELSE 0 END) as registration_count,
        COUNT(CASE WHEN message_type = 'reminder' THEN 1 ELSE 0 END) as reminder_count,
        COUNT(CASE WHEN message_type = 'missed' THEN 1 ELSE 0 END) as missed_count,
        COUNT(CASE WHEN message_type = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_count
    FROM sms_logs
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get notification from session if exists
$notification = null;
if (isset($_SESSION['sms_notification'])) {
    $notification = $_SESSION['sms_notification'];
    unset($_SESSION['sms_notification']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Dashboard - ImmunizeHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="ml-64 mt-16 p-6">
        <!-- Page Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-white mb-2">SMS Management</h1>
            <p class="text-blue-200">Send reminders and view message history</p>
        </div>

        <!-- Notification Display -->
        <?php if ($notification): ?>
        <div class="mb-6 p-4 rounded-lg animate__animated animate__fadeIn <?php echo $notification['status'] === 'success' ? 'bg-green-600' : 'bg-red-600'; ?>">
            <p class="font-medium"><?php echo $notification['message']; ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Stats Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-600 mr-4">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Total SMS Sent</p>
                        <p class="text-xl font-semibold"><?php echo $stats['total_sms']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-600 mr-4">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Successful</p>
                        <p class="text-xl font-semibold"><?php echo $stats['successful_sms']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-600 mr-4">
                        <i class="fas fa-times"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Failed</p>
                        <p class="text-xl font-semibold"><?php echo $stats['failed_sms']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-600 mr-4">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Today's Messages</p>
                        <p class="text-xl font-semibold"><?php echo $stats['today_count']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Send Upcoming Reminders -->
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-bell text-yellow-400 mr-2"></i>
                    Send Upcoming Vaccination Reminders
                </h3>
                <form action="../backend/send_sms_reminders.php" method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-300 text-sm font-medium mb-2">Days Before Appointment</label>
                        <select name="days_before" class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
                            <option value="1">1 Day Before</option>
                            <option value="2">2 Days Before</option>
                            <option value="3" selected>3 Days Before</option>
                            <option value="7">1 Week Before</option>
                        </select>
                    </div>
                    <button type="submit" name="send_upcoming_reminders" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded transition duration-200">
                        Send Reminders
                    </button>
                </form>
            </div>
            
            <!-- Send Missed Notifications -->
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                    Send Missed Vaccination Notifications
                </h3>
                <p class="text-gray-400 mb-4">Send notifications to parents whose children have missed their scheduled vaccinations.</p>
                <form action="../backend/send_sms_reminders.php" method="POST">
                    <button type="submit" name="send_missed_notifications" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded transition duration-200">
                        Send Missed Notifications
                    </button>
                </form>
            </div>
        </div>
        
        <!-- SMS Logs -->
        <div class="bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="p-4 bg-gray-700">
                <h3 class="text-lg font-semibold">Recent SMS Logs</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Recipient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Message</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-800 divide-y divide-gray-700">
                        <?php foreach ($smsLogs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo date('M d, Y H:i', strtotime($log['sent_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $log['recipient']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php 
                                    switch($log['message_type']) {
                                        case 'registration': 
                                            echo '<span class="px-2 py-1 rounded text-xs bg-blue-900 text-blue-300">Registration</span>';
                                            break;
                                        case 'reminder': 
                                            echo '<span class="px-2 py-1 rounded text-xs bg-yellow-900 text-yellow-300">Reminder</span>';
                                            break;
                                        case 'missed': 
                                            echo '<span class="px-2 py-1 rounded text-xs bg-red-900 text-red-300">Missed</span>';
                                            break;
                                        case 'rescheduled': 
                                            echo '<span class="px-2 py-1 rounded text-xs bg-purple-900 text-purple-300">Rescheduled</span>';
                                            break;
                                        default:
                                            echo '<span class="px-2 py-1 rounded text-xs bg-gray-700 text-gray-300">Other</span>';
                                    }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="truncate max-w-xs" title="<?php echo htmlspecialchars($log['message']); ?>">
                                    <?php echo htmlspecialchars(substr($log['message'], 0, 50)) . (strlen($log['message']) > 50 ? '...' : ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">
                                        Success
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-900 text-red-300">
                                        Failed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($smsLogs)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-400">No SMS logs found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // Fade out notifications after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const notifications = document.querySelectorAll('.animate__fadeIn');
            notifications.forEach(function(notification) {
                notification.classList.add('animate__fadeOut');
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 1000);
            });
        }, 5000);
    });
    </script>
</body>
</html>