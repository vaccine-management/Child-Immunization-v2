<?php
session_start();
// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

// Database connection
include 'backend/db.php';

// Initialize variables
$error = '';
$success = '';
$item = null;

// Fetch inventory item details if ID is provided
if (isset($_GET['id'])) {
    $itemId = $_GET['id'];
    $stmt = $conn->prepare("SELECT id, name, batch_number, quantity, expiry_date FROM inventory WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $error = 'Item not found.';
    }
}

// Handle form submissions for updating inventory item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['id'];
    $name = trim($_POST['name']);
    $batch_number = trim($_POST['batch_number']);
    $quantity = trim($_POST['quantity']);
    $expiry_date = trim($_POST['expiry_date']);

    if (empty($name) || empty($batch_number) || empty($quantity) || empty($expiry_date)) {
        $error = 'All fields are required.';
    } else {
        $stmt = $conn->prepare("UPDATE inventory SET name = ?, batch_number = ?, quantity = ?, expiry_date = ? WHERE id = ?");
        $stmt->execute([$name, $batch_number, $quantity, $expiry_date, $itemId]);

        if ($stmt->rowCount() > 0) {
            $success = 'Item updated successfully!';
        } else {
            $error = 'Failed to update item. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Inventory Item - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold text-white mb-6">Edit Inventory Item</h2>

                <!-- Display error or success messages -->
                <?php if ($error): ?>
                    <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-500 text-white p-3 rounded-lg mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Edit Inventory Item Form -->
                <?php if ($item): ?>
                    <form method="POST" class="bg-gray-700 p-6 rounded-lg mb-6">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
                        <div class="mb-4">
                            <label for="name" class="block text-white mb-2">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="batch_number" class="block text-white mb-2">Batch Number</label>
                            <input type="text" id="batch_number" name="batch_number" value="<?php echo htmlspecialchars($item['batch_number']); ?>" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="quantity" class="block text-white mb-2">Quantity</label>
                            <input type="number" id="quantity" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="expiry_date" class="block text-white mb-2">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars($item['expiry_date']); ?>" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <button type="submit" name="update_item" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Update Item</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>