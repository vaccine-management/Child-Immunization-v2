<?php
session_start(); 
include 'includes/header.php';
include 'backend/db.php'; 

// Clear error on page refresh (not form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = null; 
}

// Store the redirect message if the user just logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email']; 
    $password = $_POST['password'];
    $role = $_POST['role']; 

    // Enhanced debugging
    error_log("Login attempt - Email: $email, Role: $role");
    
    // Check if user exists first
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    $user_exists = $check_stmt->fetchColumn() > 0;
    
    error_log("User exists in database: " . ($user_exists ? "YES" : "NO"));
    
    if (!$user_exists) {
        $error = "No account found with this email. Please check your email address.";
    } else {
        // Check if user with this email and role exists
        $check_role_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND role = :role");
        $check_role_stmt->bindParam(':email', $email);
        $check_role_stmt->bindParam(':role', $role);
        $check_role_stmt->execute();
        $role_matches = $check_role_stmt->fetchColumn() > 0;
        
        error_log("Email and role match in database: " . ($role_matches ? "YES" : "NO"));
        
        if (!$role_matches) {
            $error = "The selected role doesn't match the account. Please select the correct role.";
        } else {
            // Fetch user from the database (with matching email and role)
            $stmt = $conn->prepare("SELECT id, email, password, role, username FROM users WHERE email = :email AND role = :role");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debug password verification
            error_log("User found - ID: {$user['id']}, Email: {$user['email']}, Role: {$user['role']}");
            error_log("Password hash from DB: " . $user['password']);
            
            // Verify password using password_verify
            $password_verified = password_verify($password, $user['password']);
            error_log("password_verify() result: " . ($password_verified ? "TRUE" : "FALSE"));
            
            if ($password_verified) {
                // Password is correct, set session data
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
                    'role' => $user['role'],
                    'username' => $user['username']
        ];
        
        // Set login success message
        $_SESSION['login_success'] = true;
                error_log("Login successful - Redirecting to dashboard");
        header('Location: dashboard.php');
        exit();
    } else {
                error_log("Password verification failed");
                $error = "Invalid password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Existing meta tags and title -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .login-bg {
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
        .role-card {
            transition: all 0.3s ease;
        }
        .role-card.selected {
            border-color: rgba(59, 130, 246, 1);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
            transform: translateY(-4px);
        }
        .btn-gradient {
            background: linear-gradient(to right, #3b82f6, #2563eb);
        }
        .feature-icon {
            transition: all 0.3s ease;
        }
        .feature-item:hover .feature-icon {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
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
        <div class="hidden lg:flex lg:w-1/2 relative login-bg">
            <div class="absolute inset-0 bg-gradient-to-b from-blue-900/95 to-gray-900/95"></div>
            <div class="relative z-10 flex flex-col justify-center px-16">
                <!-- Logo and Title -->
                <div class="mb-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white/10 backdrop-blur-sm mb-6">
                        <i class="fas fa-syringe text-blue-400 text-3xl"></i>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-2">CHILD IMMUNIZATION SYSTEM</h1>
                    <p class="text-xl text-blue-200">Ensuring a healthier future for every child</p>
                </div>
                
                <!-- Features List -->
                <div class="space-y-8">
                    <div class="flex items-center space-x-5">
                        <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center">
                            <i class="fas fa-shield-alt text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Secure Management</h3>
                            <p class="text-gray-300">Safe and confidential handling of records</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-5">
                        <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center">
                            <i class="fas fa-chart-line text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Real-time Tracking</h3>
                            <p class="text-gray-300">Monitor immunization progress effectively</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-5">
                        <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center">
                            <i class="fas fa-bell text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Smart Notifications</h3>
                            <p class="text-gray-300">Timely reminders for vaccinations</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 md:p-8 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900">
            <div class="w-full max-w-md space-y-8 animate__animated animate__fadeIn">
                <!-- Error Message -->
                <?php if (isset($error)): ?>
    <div id="error-message" class="bg-red-500/20 border-l-4 border-red-500 p-4 animate__animated animate__headShake rounded-r-lg shadow-lg">
                        <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                            <p class="text-red-300"><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-white mb-2">Welcome Back</h2>
                    <p class="text-gray-400">Sign in to access your dashboard</p>
                </div>
                        <form id="loginForm" method="POST" class="space-y-6">
                            <!-- Email Input -->
                            <div class="relative group">
                                <div class="absolute left-3 top-3.5 text-gray-400 group-focus-within:text-blue-400 transition-colors duration-200">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <input type="email" id="email" name="email" required
                                       class="form-input w-full bg-slate-800/80 border border-slate-700 text-white rounded-lg pl-10 pr-3 py-3
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent 
                                              transition-all duration-200"
                                       placeholder="Your email address">
                            </div>

                            <!-- Password Input -->
                            <div class="relative group">
                                <div class="absolute left-3 top-3.5 text-gray-400 group-focus-within:text-blue-400 transition-colors duration-200">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input type="password" id="password" name="password" required
                                       class="form-input w-full bg-slate-800/80 border border-slate-700 text-white rounded-lg pl-10 pr-10 py-3
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                              transition-all duration-200"
                                       placeholder="Your password">
                                <button type="button" id="togglePassword" 
                                        class="absolute right-3 top-3.5 text-gray-400 hover:text-blue-400 focus:outline-none transition-colors duration-200">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                            <!-- Role Selection -->
                            <div class="mt-4 mb-6">
                                <label class="block text-gray-400 mb-2">Select your role:</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="role-card bg-slate-800/80 border border-slate-700 rounded-lg p-4 cursor-pointer hover:border-blue-400 transition-all duration-300" data-role="Admin">
                                        <input type="radio" name="role" value="Admin" class="hidden" required>
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-full bg-blue-600/20 flex items-center justify-center mb-3">
                                                <i class="fas fa-user-shield text-blue-400 text-xl"></i>
                                            </div>
                                            <span class="text-white font-medium">Admin</span>
                                        </div>
                                    </div>
                                    <div class="role-card bg-slate-800/80 border border-slate-700 rounded-lg p-4 cursor-pointer hover:border-blue-400 transition-all duration-300" data-role="Nurse">
                                        <input type="radio" name="role" value="Nurse" class="hidden" required>
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-full bg-blue-600/20 flex items-center justify-center mb-3">
                                                <i class="fas fa-user-nurse text-blue-400 text-xl"></i>
                                            </div>
                                            <span class="text-white font-medium">Nurse</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Forgot Password Link -->
                            <div class="flex justify-end">
                                <a href="forgot_password.php" class="text-sm text-blue-400 hover:text-blue-300 hover:underline transition-all duration-200">
                                    Forgot password?
                                </a>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit"
                                    class="group relative w-full flex items-center justify-center py-3 px-4 
                                          text-white font-semibold rounded-lg
                                          btn-gradient shadow-lg shadow-blue-500/20
                                          hover:shadow-blue-500/40 transition-all duration-300 transform hover:translate-y-[-2px]">
                                <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                    <i class="fas fa-sign-in-alt text-blue-200 group-hover:text-white transition-colors duration-200"></i>
                                </span>
                                Sign in
                            </button>
                        </form>
                    
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to hide notifications after 5 seconds with fade out animation
        function hideNotifications() {
            const errorMessage = document.getElementById('error-message');

            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.classList.remove('animate__headShake');
                    errorMessage.classList.add('animate__fadeOut');
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 1000);
                }, 3000); 
            }
        }

        // Call the function when the page loads
        window.onload = hideNotifications;
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
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

        // Role selection with enhanced animation
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.role-card').forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Check the radio input
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });

        // Add subtle animation to the form
        const form = document.getElementById('loginForm');
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.classList.add('pulse-animation');
        });
    </script>
</body>
</html>