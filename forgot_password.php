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
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-96">
            <h2 class="text-2xl font-bold text-white mb-6">Forgot Password</h2>
            <?php if (isset($error)): ?>
                <div id="error-message" class="bg-red-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div id="success-message" class="bg-green-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-white mb-2">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Send Reset Link
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="login.php" class="text-blue-500 hover:text-blue-400">Back to Login</a>
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
                    errorMessage.style.display = 'none';
                }, 5000); // 5 seconds
            }

            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 5000); // 5 seconds
            }
        }

        // Call the function when the page loads
        window.onload = hideNotifications;
    </script>
</body>
</html>