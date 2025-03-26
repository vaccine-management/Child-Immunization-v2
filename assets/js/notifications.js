/**
 * Child Immunization System Notification Handler
 * Provides auto-dismissable notifications with animation support
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the notification system
    initNotifications();
});

/**
 * Initialize the notification system
 */
function initNotifications() {
    // Set up auto-dismissal for all notifications
    const notifications = document.querySelectorAll('.notification');
    if (notifications.length > 0) {
        notifications.forEach(notification => {
            // Add close button if it doesn't exist
            if (!notification.querySelector('.notification-close')) {
                addCloseButton(notification);
            }
            
            // Set timeout to auto-dismiss
            setTimeout(() => {
                dismissNotification(notification);
            }, 5000); // 5 seconds
        });
    }
    
    // Add event listeners to all close buttons
    const closeButtons = document.querySelectorAll('.notification-close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notification = this.closest('.notification');
            if (notification) {
                dismissNotification(notification);
            }
        });
    });
}

/**
 * Add a close button to a notification
 * @param {HTMLElement} notification - The notification element
 */
function addCloseButton(notification) {
    // Create close button
    const closeButton = document.createElement('button');
    closeButton.className = 'notification-close absolute top-2 right-2 text-gray-500 hover:text-gray-700';
    closeButton.innerHTML = `
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    `;
    
    // Add position relative to notification if not already set
    const currentPosition = window.getComputedStyle(notification).getPropertyValue('position');
    if (currentPosition !== 'relative' && currentPosition !== 'absolute') {
        notification.style.position = 'relative';
    }
    
    // Add the close button to the notification
    notification.appendChild(closeButton);
    
    // Add click event listener
    closeButton.addEventListener('click', function() {
        dismissNotification(notification);
    });
}

/**
 * Dismiss a notification with animation
 * @param {HTMLElement} notification - The notification element to dismiss
 */
function dismissNotification(notification) {
    // Check if notification already has animation classes
    if (notification.classList.contains('animate__animated')) {
        // Switch from any entrance animation to fadeOut
        const entranceClasses = [
            'animate__fadeIn', 
            'animate__fadeInRight', 
            'animate__fadeInLeft',
            'animate__fadeInUp',
            'animate__fadeInDown'
        ];
        
        entranceClasses.forEach(className => {
            if (notification.classList.contains(className)) {
                notification.classList.remove(className);
            }
        });
        
        notification.classList.add('animate__fadeOut');
    } else {
        // Add basic fade out if no animation class exists
        notification.style.transition = 'opacity 0.5s ease-out';
        notification.style.opacity = '0';
    }
    
    // Remove from DOM after animation completes
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 500);
}

/**
 * Create a notification dynamically
 * @param {string} message - The notification message
 * @param {string} type - The type of notification (success, error, warning, info)
 * @param {HTMLElement} container - The container to append the notification to
 */
function createNotification(message, type, container) {
    // Define classes based on notification type
    let classes, icon;
    
    switch (type) {
        case 'success':
            classes = 'bg-green-100 border-l-4 border-green-500 text-green-700';
            icon = '<svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            break;
        case 'error':
            classes = 'bg-red-100 border-l-4 border-red-500 text-red-700';
            icon = '<svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
            break;
        case 'warning':
            classes = 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700';
            icon = '<svg class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
            break;
        default: // info
            classes = 'bg-blue-100 border-l-4 border-blue-500 text-blue-700';
            icon = '<svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification animate__animated animate__fadeIn ${classes} p-4 rounded-lg shadow-md mb-4 relative`;
    notification.innerHTML = `
        <div class="flex items-center">
            <div class="flex-shrink-0">
                ${icon}
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium">${message}</p>
            </div>
        </div>
    `;
    
    // Add close button
    addCloseButton(notification);
    
    // Append to container
    if (container) {
        container.prepend(notification);
    } else {
        // Default to first main content div if no container specified
        const mainContent = document.querySelector('main') || document.querySelector('.ml-64');
        if (mainContent) {
            mainContent.prepend(notification);
        } else {
            document.body.prepend(notification);
        }
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        dismissNotification(notification);
    }, 5000);
    
    return notification;
}
