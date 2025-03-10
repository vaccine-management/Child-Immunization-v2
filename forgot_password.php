<?php
session_start();
include 'includes/header.php';
include 'backend/db.php';

// Load Composer's autoloader
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Check if the email exists in the database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(50));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Store the token in the database
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (:email, :token, :expires)");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires', $expires);
        $stmt->execute();

        // Send the reset link to the user's email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Server settings for Gmail SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Gmail SMTP server
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = 'dennisntete28@gmail.com'; 
            $mail->Password = 'vltp swtp mkzm hbpo'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = 587; // Gmail SMTP port

            // Recipients
            $mail->setFrom('your-email@gmail.com', 'Child Immunization System'); // Sender's email and name
            $mail->addAddress($email); // Recipient's email

            // Content
            $resetLink = "http://localhost/reset_password.php?token=$token"; // Update with your local or live URL
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "Click the link below to reset your password:<br><br><a href='$resetLink'>$resetLink</a>";

            $mail->send();
            $success = "A password reset link has been sent to your email.";
        } catch (Exception $e) {
            $error = "Failed to send the reset link. Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "No user found with that email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Child Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .forgot-bg {
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
        <div class="hidden lg:flex lg:w-1/2 relative forgot-bg">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-900/90 via-slate-900/80 to-slate-900/95"></div>
            <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16">
                <!-- Logo and Title -->
                <div class="mb-12 animate__animated animate__fadeInLeft">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white/10 backdrop-blur-sm mb-6 shadow-lg">
                        <i class="fas fa-syringe text-blue-400 text-3xl"></i>
                    </div>
                    <h1 class="text-4xl xl:text-5xl font-bold text-white mb-4">CHILD IMMUNIZATION SYSTEM</h1>
                    <p class="text-xl text-blue-200 leading-relaxed">Recover your account access</p>
                </div>
                
                <!-- Password Reset Info -->
                <div class="space-y-6 animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="flex items-center space-x-5 group transition-all duration-300">
                        <div class="w-12 h-12 rounded-full bg-blue-600/20 flex items-center justify-center shadow-lg">
                            <i class="fas fa-envelope text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Email Recovery</h3>
                            <p class="text-gray-300">We'll send a reset link to your registered email</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-5 group transition-all duration-300">
                        <div class="w-12 h-12 rounded-full bg-blue-600/20 flex items-center justify-center shadow-lg">
                            <i class="fas fa-shield-alt text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Secure Process</h3>
                            <p class="text-gray-300">Our recovery process is secure and private</p>
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

        <!-- Right Side - Forgot Password Form -->
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
                
                <!-- Success Message -->
                <?php if (isset($success)): ?>
                    <div id="success-message" class="bg-green-500/20 border-l-4 border-green-500 p-4 animate__animated animate__fadeIn rounded-r-lg shadow-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <p class="text-green-300"><?php echo $success; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="glass-effect rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-8">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-bold text-white mb-2">Forgot Password</h2>
                            <p class="text-gray-400">Enter your email to receive a reset link</p>
                        </div>
                        
                        <form method="POST" id="forgotForm" class="space-y-6">
                            <!-- Email Input -->
                            <div class="relative group">
                                <div class="absolute left-3 top-3.5 text-gray-400 group-focus-within:text-blue-400 transition-colors duration-200">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <input type="email" id="email" name="email" required
                                       class="form-input w-full bg-slate-800/80 border border-slate-700 text-white rounded-lg pl-10 py-3
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                              transition-all duration-200"
                                       placeholder="Enter your email address">
                            </div>

                            <!-- Submit Button -->
                            <button type="submit"
                                    id="submitBtn"
                                    class="group relative w-full flex items-center justify-center py-3 px-4 
                                           text-white font-semibold rounded-lg
                                           btn-gradient shadow-lg shadow-blue-500/20
                                           hover:shadow-blue-500/40 transition-all duration-300 transform hover:translate-y-[-2px]">
                                <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                    <i class="fas fa-paper-plane text-blue-200 group-hover:text-white transition-colors duration-200"></i>
                                </span>
                                Send Reset Link
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
        // Function to hide notifications after 5 seconds
        function hideNotifications() {
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');

            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.classList.add('animate__fadeOut');
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 1000);
                }, 5000); // 5 seconds
            }

            if (successMessage) {
                setTimeout(() => {
                    successMessage.classList.add('animate__fadeOut');
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 1000);
                }, 5000); // 5 seconds
            }
        }

        // Form submission animation
        document.getElementById('forgotForm').addEventListener('submit', function() {
            const submitButton = document.getElementById('submitBtn');
            submitButton.classList.add('pulse-animation');
            submitButton.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Sending...';
        });

        // Call the function when the page loads
        window.onload = hideNotifications;
    </script>
</body>
</html>