<?php
session_start(); // Move session_start() to the top

include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email']; // Use 'email' instead of 'username'
    $password = $_POST['password'];
    $role = $_POST['role']; // Add role to the form data

    // Database connection details
    $host = 'localhost';
    $dbname = 'immunization_system';
    $user = 'root'; // Default MySQL user in XAMPP
    $password_db = ''; // Default MySQL password in XAMPP (empty)

    try {
        // Connect to MySQL
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch user from the database
        $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = :email AND role = :role");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Store user data in session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            header('Location: index.php');
            exit();
        } else {
            $error = "Invalid email, password, or role.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    
</head>
<body class="bg-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-96">
            <h2 class="text-2xl font-bold text-white mb-6">Login</h2>
            <?php if (isset($error)): ?>
                <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form id="loginForm" method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-white mb-2">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <div class="mb-6 relative">
                    <label for="password" class="block text-white mb-2">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                    <button type="button" id="togglePassword" class="absolute right-3 top-10 text-white">👁️</button>
                </div>
                <div class="mb-6">
                    <label class="block text-white mb-2">Role</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="role" value="Admin" class="form-radio text-blue-500" required>
                            <span class="ml-2 text-white">Admin</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="role" value="Nurse" class="form-radio text-blue-500" required>
                            <span class="ml-2 text-white">Nurse</span>
                        </label>
                    </div>
                </div>
                <div id="message" class="mt-4 text-center text-sm"></div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300 flex items-center justify-center">
                    <span id="loginText">Login</span>
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
    <script src="js/login.js"></script>
</body>
</html>