<?php
session_start();
include 'includes/header.php';
include 'backend/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Debugging: Print the token from the form
        error_log("Token from form: $token");

        // Check if the token is valid and not expired
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = :token AND expires > NOW()");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $email = $reset['email'];

            // Debugging: Print the email associated with the token
            error_log("Email found: $email");

// Hash the password securely before storing
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Debugging to verify hash generation
error_log("Original password: " . substr($password, 0, 3) . "***");
error_log("Hashed password length: " . strlen($hashedPassword));

// Update the user's password in the database with the hashed password
$stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
$stmt->bindParam(':password', $hashedPassword);
$stmt->bindParam(':email', $email);
            $stmt->execute();

            // Delete the token from the password_resets table
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            $success = "Your password has been reset successfully. You can now Login";
        } else {
            // Debugging: Check if the token exists but is expired
            $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            $expiredReset = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($expiredReset) {
                $error = "The password reset link has expired. Please request a new one.";
            } else {
                $error = "Invalid or expired token.";
            }
        }
    }
} else {
    $token = $_GET['token'] ?? '';

    // Debugging: Print the token from the URL
    error_log("Token from URL: $token");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Child Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .reset-bg {
            background-image: url('assets/login.jpg');
            background-size: cover;
            background-position: center;
        }
        .glass-effect {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-gradient {
            background: linear-gradient(to right, #3b82f6, #2563eb);
        }
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        .password-strength {
            height: 5px;
            transition: all 0.3s ease;
        }
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        .pulse-animation {
            animation: pulse-border 2s infinite;
        }
    </style>
</head>
<body class="bg-slate-900 text-gray-200 font-sans antialiased">
    <div class="min-h-screen flex">
        <!-- Left Side - Information Section -->
        <div class="hidden lg:flex lg:w-1/2 relative reset-bg">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-900/90 via-slate-900/80 to-slate-900/95"></div>
            <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16">
                <!-- Logo and Title -->
                <div class="mb-12 animate__animated animate__fadeInLeft">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white/10 backdrop-blur-sm mb-6 shadow-lg">
                        <i class="fas fa-syringe text-blue-400 text-3xl"></i>
                    </div>
                    <h1 class="text-4xl xl:text-5xl font-bold text-white mb-4">CHILD IMMUNIZATION SYSTEM</h1>
                    <p class="text-xl text-blue-200 leading-relaxed">Reset your password securely</p>
                </div>
                
                <!-- Password Reset Info -->
                <div class="space-y-6 animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="flex items-center space-x-5 group transition-all duration-300">
                        <div class="w-12 h-12 rounded-full bg-blue-600/20 flex items-center justify-center shadow-lg">
                            <i class="fas fa-lock text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Secure Reset</h3>
                            <p class="text-gray-300">Your information is kept private and secure</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-5 group transition-all duration-300">
                        <div class="w-12 h-12 rounded-full bg-blue-600/20 flex items-center justify-center shadow-lg">
                            <i class="fas fa-shield-alt text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Password Tips</h3>
                            <p class="text-gray-300">Use a strong, unique password with mixed characters</p>
                        </div>
                    </div>
                </div>
                
                <!-- Version Badge -->
                <div class="mt-12 animate__animated animate__fadeIn animate__delay-2s">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-900/30 text-blue-300 border border-blue-800">
                        <span class="w-2 h-2 rounded-full bg-blue-400 mr-2 animate-pulse"></span>
                        Vaccine Management System v2.0
                    </span>
                </div>
            </div>
        </div>

        <!-- Right Side - Reset Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 md:p-8 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900">
            <div class="w-full max-w-md space-y-8 animate__animated animate__fadeIn">
                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border-l-4 border-red-500 p-4 animate__animated animate__headShake rounded-r-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                            <p class="text-red-300"><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Success Message -->
                <?php if (isset($success)): ?>
                    <div class="bg-green-500/20 border-l-4 border-green-500 p-4 animate__animated animate__fadeIn rounded-r-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <p class="text-green-300"><?php echo $success; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-8">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-bold text-white mb-2">Reset Password</h2>
                            <p class="text-gray-400">Create a new secure password</p>
                        </div>
                        
                        <form method="POST" id="resetForm" class="space-y-6">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <!-- New Password Input -->
                            <div class="space-y-2">
                                <div class="relative group">
                                    <div class="absolute left-3 top-3.5 text-gray-400 group-focus-within:text-blue-400 transition-colors duration-200">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <input type="password" id="password" name="password" required
                                           class="form-input w-full bg-slate-800/80 border border-slate-700 text-white rounded-lg pl-10 pr-10 py-3
                                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                                  transition-all duration-200"
                                           placeholder="Enter new password">
                                    <button type="button" class="toggle-password absolute right-3 top-3.5 text-gray-400 hover:text-blue-400 focus:outline-none transition-colors duration-200" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                
                                <!-- Password Strength Indicator -->
                                <div class="w-full bg-slate-700/50 rounded-full overflow-hidden">
                                    <div id="password-strength" class="password-strength w-0 h-1 rounded-full bg-red-500"></div>
                                </div>
                                <p id="password-feedback" class="text-xs text-gray-400 ml-1"></p>
                            </div>

                            <!-- Confirm Password Input -->
                            <div class="relative group">
                                <div class="absolute left-3 top-3.5 text-gray-400 group-focus-within:text-blue-400 transition-colors duration-200">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       class="form-input w-full bg-slate-800/80 border border-slate-700 text-white rounded-lg pl-10 pr-10 py-3
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                              transition-all duration-200"
                                       placeholder="Confirm new password">
                                <button type="button" class="toggle-password absolute right-3 top-3.5 text-gray-400 hover:text-blue-400 focus:outline-none transition-colors duration-200" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="password-match" class="text-xs text-gray-400 ml-1 hidden">Passwords match <i class="fas fa-check-circle text-green-400"></i></p>
                            <p id="password-mismatch" class="text-xs text-red-400 ml-1 hidden">Passwords do not match <i class="fas fa-times-circle"></i></p>

                            <!-- Submit Button -->
                            <button type="submit"
                                    id="submitBtn"
                                    class="group relative w-full flex items-center justify-center py-3 px-4 
                                           text-white font-semibold rounded-lg
                                           btn-gradient shadow-lg shadow-blue-500/20
                                           hover:shadow-blue-500/40 transition-all duration-300 transform hover:translate-y-[-2px]">
                                <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                    <i class="fas fa-key text-blue-200 group-hover:text-white transition-colors duration-200"></i>
                                </span>
                                Reset Password
                            </button>
                        </form>
                        
                        <!-- Back to Login -->
                        <div class="mt-6 text-center">
                            <a href="login.php" class="text-sm text-blue-400 hover:text-blue-300 hover:underline transition-all duration-200">
                                <i class="fas fa-arrow-left mr-1"></i> Back to login
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Help Text -->
                <div class="mt-6 text-center text-gray-400 text-sm">
                    <p>Need help? <a href="#" class="text-blue-400 hover:underline">Contact Support</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
         // Function to hide notifications after 5 seconds with fade out animation
    function hideNotifications() {
        const errorMessage = document.getElementById('error-message');
        const successMessage = document.getElementById('success-message');

        if (errorMessage) {
            setTimeout(() => {
                errorMessage.classList.add('animate__animated', 'animate__fadeOut');
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 1000);
            }, 5000); // 5 seconds
        }

        if (successMessage) {
            setTimeout(() => {
                successMessage.classList.add('animate__animated', 'animate__fadeOut');
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 1000);
            }, 5000); // 5 seconds
        }
    }

    // Call the function when the page loads
    window.onload = hideNotifications;
    
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthIndicator = document.getElementById('password-strength');
        const passwordFeedback = document.getElementById('password-feedback');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let feedback = '';
            
            // Calculate password strength
            if (password.length >= 8) {
                strength += 25;
            }
            
            if (password.match(/[A-Z]/)) {
                strength += 25;
            }
            
            if (password.match(/[0-9]/)) {
                strength += 25;
            }
            
            if (password.match(/[^A-Za-z0-9]/)) {
                strength += 25;
            }
            
            // Update strength indicator
            strengthIndicator.style.width = strength + '%';
            
            // Update color based on strength
            if (strength <= 25) {
                strengthIndicator.className = 'password-strength h-1 bg-red-500';
                feedback = 'Weak password';
            } else if (strength <= 50) {
                strengthIndicator.className = 'password-strength h-1 bg-orange-500';
                feedback = 'Moderate password';
            } else if (strength <= 75) {
                strengthIndicator.className = 'password-strength h-1 bg-yellow-500';
                feedback = 'Good password';
            } else {
                strengthIndicator.className = 'password-strength h-1 bg-green-500';
                feedback = 'Strong password';
            }
            
            passwordFeedback.textContent = feedback;
            
            // Check if passwords match
            checkPasswordsMatch();
        });
        
        // Check if passwords match
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('password-match');
        const passwordMismatch = document.getElementById('password-mismatch');
        
        function checkPasswordsMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword === '') {
                passwordMatch.classList.add('hidden');
                passwordMismatch.classList.add('hidden');
                return;
            }
            
            if (password === confirmPassword) {
                passwordMatch.classList.remove('hidden');
                passwordMismatch.classList.add('hidden');
                confirmPasswordInput.classList.remove('border-red-500');
                confirmPasswordInput.classList.add('border-green-500');
            } else {
                passwordMatch.classList.add('hidden');
                passwordMismatch.classList.remove('hidden');
                confirmPasswordInput.classList.remove('border-green-500');
                confirmPasswordInput.classList.add('border-red-500');
            }
        }
        
        confirmPasswordInput.addEventListener('input', checkPasswordsMatch);
        
        // Form submission animation
        document.getElementById('resetForm').addEventListener('submit', function() {
            const submitButton = document.getElementById('submitBtn');
            submitButton.classList.add('pulse-animation');
        });
    </script>
</body>
</html>