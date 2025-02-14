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

            // Update the user's password in the database (plain text for now)
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            // Delete the token from the password_resets table
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            $success = "Your password has been reset successfully. You can now <a href='login.php' class='text-blue-500'>login</a>.";
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
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-96">
            <h2 class="text-2xl font-bold text-white mb-6">Reset Password</h2>
            <?php if (isset($error)): ?>
                <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="bg-green-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-4">
                    <label for="password" class="block text-white mb-2">New Password</label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <div class="mb-6">
                    <label for="confirm_password" class="block text-white mb-2">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Reset Password
                </button>
            </form>
        </div>
    </div>
</body>
</html>