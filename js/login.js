const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

// Toggle password visibility
if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
        console.log('Toggle button clicked'); 
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        console.log('Password input type:', passwordInput.type); 
        togglePassword.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
    });
} else {
    console.error('Password input or toggle button not found');
}

// Form submission handler
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    // Clear previous messages
    clearMessages();

    const formData = {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
        role: document.querySelector('input[name="role"]:checked')?.value
    };

    if (!validateForm(formData)) {
        return;
    }

    try {
        setLoadingState(true);

        const response = await fetch('http://localhost/backend/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const responseText = await response.text();
        console.log('Backend Response Text:', responseText); 
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Trim and parse the JSON
        const trimmedResponse = responseText.trim();
        const data = JSON.parse(trimmedResponse);
        console.log('Backend Response:', data); 

        if (data.status === 'success') {
            showMessage(data.message, 'success');
            // Redirect to the dashboard after successful login
            window.location.href = 'index.php';
        } else {
            showMessage(data.message || 'An error occurred. Please try again.', 'error');
        }
    } catch (error) {
        console.error('Error during fetch or JSON parsing:', error); 
        showMessage('Server connection error. Please try again.', 'error');
    } finally {
        setLoadingState(false);
    }
});

// Display messages
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = `mt-4 text-center text-sm ${type === 'success' ? 'text-green-400' : 'text-red-400'}`;
    }
}

// Clear messages
function clearMessages() {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.textContent = '';
        messageDiv.className = '';
    }
}

// Set loading state
function setLoadingState(isLoading) {
    const loginButton = document.querySelector('button[type="submit"]');
    const loadingSpinner = document.getElementById('loadingSpinner');
    if (loginButton && loadingSpinner) {
        loginButton.disabled = isLoading;
        loadingSpinner.classList.toggle('hidden', !isLoading);
    }
}

// Validate form
function validateForm(formData) {
    if (!formData.email || !formData.password || !formData.role) {
        showMessage('Please fill in all fields.', 'error');
        return false;
    }
    return true;
}