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

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Add new inventory item
        $name = $_POST['name'];
        $batch_number = $_POST['batch_number'];
        $quantity = $_POST['quantity'];
        $expiry_date = $_POST['expiry_date'];

        $query = "INSERT INTO inventory (name, batch_number, quantity, expiry_date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$name, $batch_number, $quantity, $expiry_date]);
    } elseif (isset($_POST['delete'])) {
        // Delete inventory item
        $id = $_POST['id'];

        $query = "DELETE FROM inventory WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
    }
}

// Fetch all inventory items from the database
$query = "SELECT id, name, batch_number, quantity, expiry_date FROM inventory ORDER BY expiry_date ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
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
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['batch_number']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($item['expiry_date']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="openEditModal(<?php echo $item['id']; ?>)" class="text-blue-400 hover:text-blue-300">
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
                    <label for="name" class="block text-gray-300 mb-2">Name</label>
                    <input type="text" name="name" id="name" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="batch_number" class="block text-gray-300 mb-2">Batch Number</label>
                    <input type="text" name="batch_number" id="batch_number" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="quantity" class="block text-gray-300 mb-2">Quantity</label>
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

    <!-- Delete Inventory Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Confirm Delete</h3>
            <p class="text-gray-300 mb-6">Are you sure you want to delete this item? This action cannot be undone.</p>
            <form method="POST" action="">
                <input type="hidden" name="id" id="delete-id">
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

    <!-- Edit Inventory Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Edit Item</h3>
            <div id="editModalContent">
                <!-- Loading animation -->
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-700 rounded w-3/4 mb-6"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId, id = null) {
            if (modalId === 'deleteModal') {
                document.getElementById('delete-id').value = id;
            }
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }
        
        function openEditModal(id) {
            // Show the modal with loading animation
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
            
            // Fetch item data via AJAX
            fetch(`get_inventory_item.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('editModalContent').innerHTML = `
                            <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                                ${data.error}
                            </div>`;
                        return;
                    }
                    
                    // Format the date for the date input field
                    const formattedDate = data.expiry_date;
                    
                    // Populate the modal with a form
                    document.getElementById('editModalContent').innerHTML = `
                        <form id="editItemForm">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-4">
                                <label for="edit_name" class="block text-gray-300 mb-2">Name</label>
                                <input type="text" id="edit_name" name="name" value="${data.name}" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                            </div>
                            <div class="mb-4">
                                <label for="edit_batch_number" class="block text-gray-300 mb-2">Batch Number</label>
                                <input type="text" id="edit_batch_number" name="batch_number" value="${data.batch_number}" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                            </div>
                            <div class="mb-4">
                                <label for="edit_quantity" class="block text-gray-300 mb-2">Quantity</label>
                                <input type="number" id="edit_quantity" name="quantity" value="${data.quantity}" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                            </div>
                            <div class="mb-4">
                                <label for="edit_expiry_date" class="block text-gray-300 mb-2">Expiry Date</label>
                                <input type="date" id="edit_expiry_date" name="expiry_date" value="${formattedDate}" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                            </div>
                            <div class="flex justify-end space-x-4">
                                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                                    Cancel
                                </button>
                                <button type="button" onclick="updateItem()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    Update
                                </button>
                            </div>
                        </form>
                    `;
                })
                .catch(error => {
                    document.getElementById('editModalContent').innerHTML = `
                        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                            An error occurred while loading data
                        </div>`;
                });
        }

        function updateItem() {
            const form = document.getElementById('editItemForm');
            const formData = new FormData(form);
            
            // Send AJAX request to update item
            fetch('update_inventory_item.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Display success message
                    document.getElementById('editModalContent').innerHTML = `
                        <div class="bg-green-500 text-white p-3 rounded-lg mb-4">
                            ${data.success}
                        </div>
                        <div class="text-center mt-4">
                            <button type="button" onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Refresh Page
                            </button>
                        </div>
                    `;
                    
                    // Auto-refresh after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    // Display error message
                    document.getElementById('editModalContent').innerHTML = `
                        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                            ${data.error || 'An error occurred'}
                        </div>
                        <button type="button" onclick="closeModal('editModal')" class="w-full px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 mt-4">
                            Close
                        </button>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('editModalContent').innerHTML = `
                    <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                        An error occurred during the update
                    </div>
                    <button type="button" onclick="closeModal('editModal')" class="w-full px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 mt-4">
                        Close
                    </button>
                `;
            });
        }

        // Close modal when clicking outside
        document.querySelectorAll('.fixed.inset-0').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>