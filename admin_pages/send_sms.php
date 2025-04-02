<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

// Include the auth check file
require_once ROOT_PATH . 'includes/auth_check.php';
require_once ROOT_PATH . 'includes/sms.php';

// Include the database connection file
require_once ROOT_PATH . 'backend/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Send SMS';
include ROOT_PATH . 'includes/header.php';
// Include the sidebar
include ROOT_PATH . 'includes/sidebar.php';
?>

<div id="main-content" class="flex-1 lg:ml-64 transition-all duration-300 bg-gray-900 min-h-screen pt-16">
    <?php include ROOT_PATH . 'includes/navbar.php'; ?>

    <!-- Main Content Area -->
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Send SMS Notifications</h1>
            <p class="text-gray-400">Send personalized SMS messages to parents about vaccine information. Messages can include the parent's name and child's name automatically.</p>
        </div>

        <div class="bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <!-- SMS Form -->
            <form id="smsForm" class="space-y-6">
                <!-- Template Selection -->
                <div class="mb-4">
                    <label for="smsTemplate" class="block text-sm font-medium text-gray-300 mb-2">Select SMS Template</label>
                    <select id="smsTemplate" name="smsTemplate" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-white">
                        <option value="">Select a template...</option>
                        <option value="missed">Missed Vaccination Reminder</option>
                        <option value="upcoming">Upcoming Vaccination Reminder</option>
                        <option value="rescheduled">Rescheduled Vaccination</option>
                        <option value="custom">Custom Message</option>
                    </select>
                </div>

                <!-- Message Content -->
                <div class="mb-4">
                    <label for="messageContent" class="block text-sm font-medium text-gray-300 mb-2">Message Content</label>
                    <textarea id="messageContent" name="messageContent" rows="5" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-white" placeholder="Type your message here..."></textarea>
                    <div class="flex justify-between mt-2">
                        <span class="text-sm text-gray-400">Use [PARENT_NAME] and [CHILD_NAME] as placeholders to personalize messages</span>
                        <span id="charCount" class="text-sm text-gray-400">0/160 characters</span>
                    </div>
                </div>

                <!-- Recipients Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Recipients</label>
                    <div class="flex items-center space-x-4 mb-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="recipientType" value="all" class="form-radio h-4 w-4 text-blue-600 bg-gray-700" checked>
                            <span class="ml-2 text-gray-300">All Parents</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="recipientType" value="specific" class="form-radio h-4 w-4 text-blue-600 bg-gray-700">
                            <span class="ml-2 text-gray-300">Specific Parents</span>
                        </label>
                    </div>
                </div>

                <!-- Recipients Table (visible only when 'specific' is selected) -->
                <div id="recipientsContainer" class="mb-4 hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-gray-700 rounded-lg overflow-hidden">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="py-2 px-4 border-b border-gray-600 text-left">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-blue-600 bg-gray-700">
                                            <span class="ml-2 text-gray-300">Select All</span>
                                        </label>
                                    </th>
                                    <th class="py-2 px-4 border-b border-gray-600 text-left text-gray-300">Child Name</th>
                                    <th class="py-2 px-4 border-b border-gray-600 text-left text-gray-300">Guardian Name</th>
                                    <th class="py-2 px-4 border-b border-gray-600 text-left text-gray-300">Phone Number</th>
                                </tr>
                            </thead>
                            <tbody id="recipientsList" class="text-gray-300">
                                <!-- Will be populated by AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" id="sendSmsBtn" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                        <span>Send SMS</span>
                        <div id="spinner" class="hidden ml-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]"></div>
                    </button>
                </div>
            </form>

            <!-- Alert Messages -->
            <div id="successAlert" class="hidden mt-4 p-4 bg-green-900/50 text-green-400 rounded-md border border-green-700">
                <div class="flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span id="successMessage">SMS sent successfully!</span>
                </div>
            </div>

            <div id="errorAlert" class="hidden mt-4 p-4 bg-red-900/50 text-red-400 rounded-md border border-red-700">
                <div class="flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <span id="errorMessage">An error occurred. Please try again.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const smsForm = document.getElementById('smsForm');
        const messageContent = document.getElementById('messageContent');
        const charCount = document.getElementById('charCount');
        const smsTemplate = document.getElementById('smsTemplate');
        const recipientTypeRadios = document.getElementsByName('recipientType');
        const recipientsContainer = document.getElementById('recipientsContainer');
        const selectAll = document.getElementById('selectAll');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');
        const sendSmsBtn = document.getElementById('sendSmsBtn');
        const spinner = document.getElementById('spinner');

        // Update character count
        messageContent.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = `${count}/160 characters`;
            
            // Change color if exceeding limit
            if (count > 160) {
                charCount.classList.add('text-red-400');
                charCount.classList.remove('text-gray-400');
            } else {
                charCount.classList.add('text-gray-400');
                charCount.classList.remove('text-red-400');
            }
        });

        // Handle template selection
        smsTemplate.addEventListener('change', function() {
            let template = '';
            
            switch(this.value) {
                case 'missed':
                    template = "Dear [PARENT_NAME], we've noticed that your child [CHILD_NAME] missed their scheduled vaccination. Please contact us to reschedule at your earliest convenience.";
                    break;
                case 'upcoming':
                    template = "Dear [PARENT_NAME], this is a reminder that your child [CHILD_NAME] has an upcoming vaccination appointment. Please ensure you attend on the scheduled date.";
                    break;
                case 'rescheduled':
                    template = "Dear [PARENT_NAME], your child [CHILD_NAME]'s vaccination appointment has been rescheduled. Please contact us for more details.";
                    break;
                case 'custom':
                    template = "";
                    break;
            }
            
            messageContent.value = template;
            messageContent.dispatchEvent(new Event('input'));
        });

        // Handle recipient type selection
        for (const radio of recipientTypeRadios) {
            radio.addEventListener('change', function() {
                if (this.value === 'specific') {
                    recipientsContainer.classList.remove('hidden');
                    loadRecipients();
                } else {
                    recipientsContainer.classList.add('hidden');
                }
            });
        }

        // Load recipients
        function loadRecipients() {
            const recipientsList = document.getElementById('recipientsList');
            recipientsList.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-300">Loading...</td></tr>';
            
            fetch('get_recipients.php')
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        recipientsList.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-300">No recipients found</td></tr>';
                        return;
                    }
                    
                    recipientsList.innerHTML = '';
                    data.forEach(recipient => {
                        recipientsList.innerHTML += `
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-600">
                                    <input type="checkbox" name="recipients[]" value="${recipient.child_id}" class="recipient-checkbox form-checkbox h-4 w-4 text-blue-600 bg-gray-700">
                                </td>
                                <td class="py-2 px-4 border-b border-gray-600">${recipient.full_name}</td>
                                <td class="py-2 px-4 border-b border-gray-600">${recipient.guardian_name}</td>
                                <td class="py-2 px-4 border-b border-gray-600">${recipient.phone}</td>
                            </tr>
                        `;
                    });
                })
                .catch(error => {
                    console.error('Error loading recipients:', error);
                    recipientsList.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-red-400">Error loading recipients</td></tr>';
                });
        }

        // Handle select all checkbox
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.recipient-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Handle form submission
        smsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Hide any existing alerts
            successAlert.classList.add('hidden');
            errorAlert.classList.add('hidden');
            
            // Get form data
            const message = messageContent.value.trim();
            const recipientType = document.querySelector('input[name="recipientType"]:checked').value;
            const template = smsTemplate.value;
            
            // Validate message
            if (!message) {
                showError('Please enter a message');
                return;
            }
            
            if (message.length > 160) {
                showError('Message exceeds 160 characters limit');
                return;
            }
            
            // If specific recipients, check if any are selected
            if (recipientType === 'specific') {
                const selectedRecipients = document.querySelectorAll('input[name="recipients[]"]:checked');
                if (selectedRecipients.length === 0) {
                    showError('Please select at least one recipient');
                    return;
                }
            }
            
            // Show loading spinner
            sendSmsBtn.disabled = true;
            spinner.classList.remove('hidden');
            
            // Prepare form data
            const formData = new FormData();
            formData.append('message', message);
            formData.append('recipientType', recipientType);
            formData.append('template', template);
            
            if (recipientType === 'specific') {
                const selectedRecipients = document.querySelectorAll('input[name="recipients[]"]:checked');
                selectedRecipients.forEach(checkbox => {
                    formData.append('recipients[]', checkbox.value);
                });
            }
            
            // Send AJAX request
            fetch('process_sms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if the response is valid JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                throw new Error('Server returned non-JSON response. Please check server logs.');
            })
            .then(data => {
                if (data.success) {
                    showSuccess(data.message || 'SMS sent successfully!');
                    // Reset form after successful submission
                    smsTemplate.selectedIndex = 0; // Reset template selection
                    messageContent.value = ''; // Clear message
                    messageContent.dispatchEvent(new Event('input')); // Update character count
                } else {
                    showError(data.message || 'An error occurred while sending SMS');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An unexpected error occurred. Please try again.');
            })
            .finally(() => {
                // Hide loading spinner
                sendSmsBtn.disabled = false;
                spinner.classList.add('hidden');
            });
        });

        // Show success message
        function showSuccess(message) {
            const successMessage = document.getElementById('successMessage');
            successMessage.textContent = message;
            successAlert.classList.remove('hidden');
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                successAlert.classList.add('hidden');
            }, 5000);
        }

        // Show error message
        function showError(message) {
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = message;
            errorAlert.classList.remove('hidden');
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                errorAlert.classList.add('hidden');
            }, 5000);
        }
    });
</script>

</body>
</html>

<?php
// Close the database connection
$conn = null;
?>
