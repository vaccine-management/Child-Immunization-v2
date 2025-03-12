<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

session_start();

// Include the auth check file
require_once ROOT_PATH . 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include the database connection file
require_once ROOT_PATH . 'backend/db.php';
// Include the vaccine helper functions
require_once ROOT_PATH . 'backend/vaccine_helpers.php';

// Fetch all vaccines for the dropdown
$vaccines = getAllVaccines();

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Add new inventory item
        $vaccine_id = $_POST['vaccine_id'];
        $batch_number = $_POST['batch_number'];
        $quantity = $_POST['quantity'];
        $expiry_date = $_POST['expiry_date'];

        // Check if the item already exists in the inventory
        $stmt = $conn->prepare("SELECT id, quantity FROM inventory WHERE vaccine_id = ? AND batch_number = ?");
        $stmt->execute([$vaccine_id, $batch_number]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_item) {
            // Item exists, update the quantity
            $new_quantity = $existing_item['quantity'] + $quantity;
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = ?, expiry_date = ? WHERE id = ?");
            $result = $update_stmt->execute([$new_quantity, $expiry_date, $existing_item['id']]);
        } else {
            // Item does not exist, add it to the inventory
            $insert_stmt = $conn->prepare("INSERT INTO inventory (vaccine_id, batch_number, quantity, expiry_date) VALUES (?, ?, ?, ?)");
            $result = $insert_stmt->execute([$vaccine_id, $batch_number, $quantity, $expiry_date]);
        }

        if ($result) {
            $successMessage = "Inventory item added/updated successfully!";
        } else {
            $errorMessage = "Failed to add/update inventory item.";
        }
    } elseif (isset($_POST['update'])) {
        // Update inventory item
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        
        // Use helper function to update inventory quantity
        $result = updateInventoryQuantity($id, $quantity);
        
        if ($result) {
            $successMessage = "Inventory updated successfully!";
        } else {
            $errorMessage = "Failed to update inventory.";
        }
    } elseif (isset($_POST['delete'])) {
        // Delete inventory item
        $id = $_POST['id'];

        // Use helper function to delete inventory item
        $result = deleteInventoryItem($id);
        
        if ($result) {
            $successMessage = "Inventory item deleted successfully!";
        } else {
            $errorMessage = "Failed to delete inventory item.";
        }
    }
}

// Fetch all inventory items from the database using the helper function
$inventory_items = getAllInventory();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Child Immunization System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="bg-gray-900">
    <?php require_once ROOT_PATH . 'includes/header.php'; ?>
    <?php require_once ROOT_PATH . 'includes/navbar.php'; ?>
    <?php require_once ROOT_PATH . 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Inventory Management</h1>
                    <p class="text-gray-400">Manage and view all inventory items</p>
                </div>
                <button onclick="openModal('addModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New Item
                </button>
            </div>
            
            <!-- Inventory Table -->
            <div class="overflow-x-auto bg-gray-800 rounded-lg shadow-lg">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Vaccine</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Batch Number</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Quantity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Expiry Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-900 divide-y divide-gray-700">
                        <?php if (!empty($inventory_items)): ?>
                            <?php foreach ($inventory_items as $item): ?>
                                <tr class="hover:bg-gray-800/50 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['id']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['vaccine_name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['batch_number']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['expiry_date']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="openEditModal(<?php echo $item['id']; ?>, '<?php echo $item['quantity']; ?>')" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="openModal('deleteModal', <?php echo $item['id']; ?>)" class="text-red-400 hover:text-red-300">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm text-gray-300 text-center">No inventory items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Inventory Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Add New Item</h3>
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="vaccine_id" class="block text-gray-300 mb-2">Vaccine</label>
                    <select name="vaccine_id" id="vaccine_id" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                        <option value="">Select Vaccine</option>
                        <?php foreach ($vaccines as $vaccine): ?>
                            <option value="<?php echo $vaccine['id']; ?>"><?php echo htmlspecialchars($vaccine['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="batch_number" class="block text-gray-300 mb-2">Batch Number</label>
                    <input type="text" name="batch_number" id="batch_number" placeholder="BSYTE7364"class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="quantity" placeholder="10" class="block text-gray-300 mb-2">Quantity</label>
                    <input type="number" name="quantity" id="quantity" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="expiry_date" class="block text-gray-300 mb-2">Expiry Date</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit" name="add" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Add
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Inventory Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Edit Inventory Item</h3>
            <form method="POST" action="">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-4">
                    <label for="edit_quantity" class="block text-gray-300 mb-2">Quantity</label>
                    <input type="number" name="quantity" id="edit_quantity" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit" name="update" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Confirm Delete</h3>
            <p class="text-gray-300 mb-4">Are you sure you want to delete this inventory item?</p>
            <form method="POST" action="">
                <input type="hidden" name="id" id="delete_id">
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit" name="delete" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId, id) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
            
            if (id) {
                document.getElementById(modalId === 'deleteModal' ? 'delete_id' : 'edit_id').value = id;
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('flex');
            document.getElementById(modalId).classList.add('hidden');
        }
        
        function openEditModal(id, quantity) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_quantity').value = quantity;
            openModal('editModal');
        }
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>