<?php
session_start(); 
include 'includes/header.php';
include 'backend/db.php'; 

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
        $_SESSION['success'] = "Login successful! Welcome back.";
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
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800 min-h-screen">
    <!-- Success Message -->
    <?php if (isset($_SESSION['success'])): ?>
    <div id="successMessage" class="fixed top-4 right-4 z-50 bg-green-500/10 border-l-4 border-green-500 text-green-300 p-4 rounded-lg shadow-lg transition-all duration-500 ease-in-out transform">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $_SESSION['success']; ?>
        </div>
    </div>
    <?php 
    unset($_SESSION['success']);
    endif; 
    ?>

    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <h2 class="text-3xl font-extrabold text-white mb-2">Welcome Back</h2>
                <p class="text-gray-400">Sign in to your account</p>
            </div>

            <!-- Login Card -->
            <div class="bg-gray-800/50 backdrop-blur-xl rounded-2xl shadow-xl overflow-hidden">
                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/10 border-l-4 border-red-500 text-red-300 p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo $error; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="p-8">
                    <form id="loginForm" method="POST" class="space-y-6">
                        <!-- Email Input -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-500"></i>
                                </div>
                                <input type="email" id="email" name="email" required
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-700 rounded-lg 
                                              bg-gray-700/50 text-gray-200 placeholder-gray-400
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter your email">
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-500"></i>
                                </div>
                                <input type="password" id="password" name="password" required
                                       class="block w-full pl-10 pr-10 py-2 border border-gray-700 rounded-lg 
                                              bg-gray-700/50 text-gray-200 placeholder-gray-400
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter your password">
                                <button type="button" id="togglePassword" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-300">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-3">Select Role</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="relative flex items-center p-3 rounded-lg border border-gray-700 bg-gray-700/50 cursor-pointer hover:bg-gray-700/70">
                                    <input type="radio" name="role" value="Admin" required class="hidden peer">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-shield text-gray-400 mr-2"></i>
                                        <span class="text-gray-300">Admin</span>
                                    </div>
                                    <div class="absolute top-0 right-0 bottom-0 left-0 rounded-lg ring-2 ring-transparent peer-checked:ring-blue-500"></div>
                                </label>
                                <label class="relative flex items-center p-3 rounded-lg border border-gray-700 bg-gray-700/50 cursor-pointer hover:bg-gray-700/70">
                                    <input type="radio" name="role" value="Nurse" required class="hidden peer">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-nurse text-gray-400 mr-2"></i>
                                        <span class="text-gray-300">Nurse</span>
                                    </div>
                                    <div class="absolute top-0 right-0 bottom-0 left-0 rounded-lg ring-2 ring-transparent peer-checked:ring-blue-500"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                class="w-full flex items-center justify-center px-4 py-2 border border-transparent 
                                       rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 
                                       hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 
                                       focus:ring-blue-500 transition-colors">
                            <span id="loginText">Sign in</span>
                            <span id="loadingSpinner" class="hidden ml-2">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>

                        <!-- Forgot Password Link -->
                        <div class="text-center mt-4">
                            <a href="forgot_password.php" 
                               class="text-sm text-blue-400 hover:text-blue-300 transition-colors">
                                Forgot your password?
                            </a>
                        </div>
                    </form>
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

        // Form submission and loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loginText').classList.add('hidden');
            document.getElementById('loadingSpinner').classList.remove('hidden');
        });

        // Success message animation
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    successMessage.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>