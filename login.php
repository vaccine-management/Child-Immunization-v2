<?php
session_start(); 
include 'includes/header.php';
include 'backend/db.php'; 

// Store the redirect message if the user just logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email']; 
    $password = $_POST['password'];
    $role = $_POST['role']; 

    // Fetch user from the database with plain text password comparison
    $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = :email AND password = :password AND role = :role");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Store user data in session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        
        // Set login success message
        $_SESSION['login_success'] = true;
        header('Location: index.php');
        exit();
    } else {
        $error = "Invalid email, password, or role.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Child Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .login-bg {
            background-image: url('assets/login.jpg');
            background-size: cover;
            background-position: center;
        }
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .input-icon {
            top: 50%;
            transform: translateY(-50%);
        }
        .floating-label {
            transform: translateY(0);
            font-size: 0.75rem;
        }
        .input-with-label:focus + .floating-label,
        .input-with-label:not(:placeholder-shown) + .floating-label {
            transform: translateY(-1.5rem);
            font-size: 0.75rem;
        }
        .role-card {
            transition: all 0.3s ease;
        }
        .role-card.selected {
            border-color: rgba(59, 130, 246, 1);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body class="bg-gray-900 h-screen">
    <!-- Background with overlay -->
    <div class="relative h-full">
        <div class="absolute inset-0 login-bg opacity-20"></div>
        
        <!-- Abstract shapes for visual interest -->
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-float"></div>
        <div class="absolute bottom-1/3 right-1/4 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-float" style="animation-delay: 2s;"></div>
        
        <!-- Login Container -->
        <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-md w-full space-y-8 animate__animated animate__fadeIn">
                <!-- Logo and Title -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 mb-5 shadow-lg">
                        <i class="fas fa-syringe text-white text-3xl"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight">Child Immunization</h2>
                    <p class="mt-2 text-sm text-blue-400">Healthcare Management System</p>
                </div>

                <!-- Login Card -->
                <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden">
                    <!-- Error Message -->
                    <?php if (isset($error)): ?>
                        <div class="bg-red-500/20 border-l-4 border-red-500 p-4 animate__animated animate__headShake">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                                <p class="text-red-300"><?php echo $error; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="p-8">
                        <h3 class="text-xl font-bold text-white mb-6">Sign in to your account</h3>
                        
                        <form id="loginForm" method="POST" class="space-y-6">
                            <!-- Email Input -->
                            <div class="relative">
                                <div class="absolute left-3 top-3.5 text-gray-400">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <input type="email" id="email" name="email" required
                                       class="w-full bg-gray-800/50 border border-gray-700 text-white rounded-lg pl-10 pr-3 py-3
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent 
                                              transition-all duration-200"
                                       placeholder="Your email address">
                            </div>

                            <!-- Password Input -->
                            <div class="relative">
                                <div class="absolute left-3 top-3.5 text-gray-400">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input type="password" id="password" name="password" required
                                       class="w-full bg-gray-800/50 border border-gray-700 text-white rounded-lg pl-10 pr-10 py-3
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                              transition-all duration-200"
                                       placeholder="Your password">
                                <button type="button" id="togglePassword" 
                                        class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-300 focus:outline-none">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                            <!-- Role Selection -->
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-white">Select your role</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="role-card bg-gray-800/50 border border-gray-700 rounded-lg p-4 cursor-pointer hover:border-blue-400 transition-all duration-200" data-role="Admin">
                                        <input type="radio" name="role" value="Admin" class="hidden" required>
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center mb-2">
                                                <i class="fas fa-user-shield text-blue-400 text-xl"></i>
                                            </div>
                                            <span class="text-white font-medium">Admin</span>
                                        </div>
                                    </div>
                                    <div class="role-card bg-gray-800/50 border border-gray-700 rounded-lg p-4 cursor-pointer hover:border-blue-400 transition-all duration-200" data-role="Nurse">
                                        <input type="radio" name="role" value="Nurse" class="hidden" required>
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-full bg-green-500/20 flex items-center justify-center mb-2">
                                                <i class="fas fa-user-nurse text-green-400 text-xl"></i>
                                            </div>
                                            <span class="text-white font-medium">Nurse</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Remember me / Forgot password -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input id="remember-me" name="remember-me" type="checkbox" 
                                          class="h-4 w-4 text-blue-500 bg-gray-800 border-gray-700 rounded focus:ring-blue-500 focus:ring-offset-gray-900">
                                    <label for="remember-me" class="ml-2 block text-sm text-gray-300">
                                        Remember me
                                    </label>
                                </div>
                                <a href="forgot_password.php" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">
                                    Forgot password?
                                </a>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit"
                                    class="group relative w-full flex items-center justify-center py-3 px-4 
                                           bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700
                                           text-white font-medium rounded-lg transition-all duration-200
                                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                    <i class="fas fa-sign-in-alt text-blue-300 group-hover:text-blue-200 transition-colors"></i>
                                </span>
                                <span id="loginText">Sign in</span>
                                <span id="loadingSpinner" class="hidden ml-2">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center mt-6">
                    <p class="text-sm text-gray-400">
                        © 2023 Child Immunization System. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
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

        // Handle role selection
        const roleCards = document.querySelectorAll('.role-card');
        roleCards.forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                roleCards.forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Check the radio input
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });

        // Show loading spinner on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loginText').classList.add('hidden');
            document.getElementById('loadingSpinner').classList.remove('hidden');
        });
    </script>
</body>
</html>