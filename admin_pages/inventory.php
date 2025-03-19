<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the auth check file
require_once ROOT_PATH . 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include the database connection file
require_once ROOT_PATH . 'backend/db.php';
// Include the vaccine helper functions
require_once ROOT_PATH . 'backend/vaccine_helpers.php';

// Debugging function
function debug_log($message, $data = null) {
    error_log($message . ($data ? ': ' . print_r($data, true) : ''));
}

// Log form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log('POST data received', $_POST);
}

// Test database connection
try {
    $testQuery = $conn->query("SELECT 1");
    debug_log('Database connection test successful');
} catch (PDOException $e) {
    debug_log('Database connection test failed', $e->getMessage());
    die("Database connection error: " . $e->getMessage());
}

// Fetch all vaccines for the dropdown
$vaccines = getAllVaccines();

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        debug_log('Processing add item form');
        
        // Get and sanitize input
        $vaccine_name = trim($_POST['vaccine_name'] ?? '');
        $batch_number = trim($_POST['batch_number'] ?? '');
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        
        debug_log('Form data', [
            'vaccine_name' => $vaccine_name,
            'batch_number' => $batch_number,
            'quantity' => $quantity,
            'expiry_date' => $expiry_date
        ]);
        
        // Validate input
        $errors = [];
        
        if (empty($vaccine_name)) {
            $errors[] = "Vaccine name is required";
        }
        
        if (empty($batch_number)) {
            $errors[] = "Batch number is required";
        }
        
        if ($quantity <= 0) {
            $errors[] = "Quantity must be greater than zero";
        }
        
        if (empty($expiry_date)) {
            $errors[] = "Expiry date is required";
        } elseif (strtotime($expiry_date) === false) {
            $errors[] = "Invalid expiry date format";
        }
        
        debug_log('Validation errors', $errors);
        
        // If no errors, add inventory item
        if (empty($errors)) {
            try {
                // Check database connection
                if (!$conn) {
                    throw new Exception("Database connection not established");
                }
                
                debug_log('Database connection successful');
                
                // Begin transaction
                $conn->beginTransaction();
                
                // First check if the vaccine exists
                $stmt = $conn->prepare("SELECT id FROM vaccines WHERE name = ?");
                $stmt->execute([$vaccine_name]);
                $vaccine = $stmt->fetch(PDO::FETCH_ASSOC);
                
                debug_log('Vaccine check result', $vaccine);
                
                // If vaccine doesn't exist, add it
                if (!$vaccine) {
                    debug_log('Adding new vaccine');
                    $stmt = $conn->prepare("INSERT INTO vaccines (name) VALUES (?)");
                    $result = $stmt->execute([$vaccine_name]);
                    
                    if (!$result) {
                        throw new Exception("Failed to add vaccine: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    $vaccine_id = $conn->lastInsertId();
                    debug_log('New vaccine ID', $vaccine_id);
                } else {
                    $vaccine_id = $vaccine['id'];
                    debug_log('Using existing vaccine ID', $vaccine_id);
                }
                
                // Now add the inventory item
                debug_log('Adding inventory item');
                $stmt = $conn->prepare("INSERT INTO inventory (vaccine_id, batch_number, quantity, expiry_date) 
                                       VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([$vaccine_id, $batch_number, $quantity, $expiry_date]);
                
                if (!$result) {
                    throw new Exception("Failed to add inventory: " . implode(", ", $stmt->errorInfo()));
                }
                
                // Commit transaction
                $conn->commit();
                
                debug_log('Inventory item added successfully');
                $_SESSION['inventory_success'] = "Added {$quantity} doses of {$vaccine_name} (Batch: {$batch_number}) to inventory!";
            } catch (Exception $e) {
                // Rollback transaction on error
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                
                debug_log('Error adding inventory item', $e->getMessage());
                $_SESSION['inventory_error'] = "Error: " . $e->getMessage();
            }
        } else {
            $_SESSION['inventory_error'] = implode("<br>", $errors);
        }
        
        // Redirect to prevent form resubmission
        header('Location: inventory.php');
        exit();
    } elseif (isset($_POST['update'])) {
        // Update inventory item
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        
        // Use helper function to update inventory quantity
        $result = updateInventoryQuantity($id, $quantity);
        
        if ($result) {
            $_SESSION['inventory_success'] = "Inventory updated successfully!";
        } else {
            $_SESSION['inventory_error'] = "Failed to update inventory.";
        }
        
        header('Location: inventory.php');
        exit();
    } elseif (isset($_POST['delete'])) {
        // Delete inventory item
        $id = $_POST['id'];

        // Use helper function to delete inventory item
        $result = deleteInventoryItem($id);
        
        if ($result) {
            $_SESSION['inventory_success'] = "Inventory item removed successfully!";
        } else {
            $_SESSION['inventory_error'] = "Failed to delete inventory item.";
        }
        
        header('Location: inventory.php');
        exit();
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
    <style>
        /* Base alert styles */
        .auto-dismiss-alert {
            opacity: 1;
            position: relative;
        }
        
        /* Success alert - 2 second animation */
        .success-alert {
            animation: quickFadeOut 2s forwards;
        }
        
        /* Error alert - 5 second animation */
        .error-alert {
            animation: slowFadeOut 5s forwards;
        }
        
        /* Quick fade animation for success messages */
        @keyframes quickFadeOut {
            0% { opacity: 0; transform: translateY(-20px); }
            20% { opacity: 1; transform: translateY(0); }
            80% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        
        /* Slow fade animation for error messages */
        @keyframes slowFadeOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
    </style>
</head>
<body class="bg-gray-900">
    <?php require_once ROOT_PATH . 'includes/header.php'; ?>
    <?php require_once ROOT_PATH . 'includes/navbar.php'; ?>
    <?php require_once ROOT_PATH . 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="container mx-auto px-4 py-8">
            <!-- Success message -->
            <?php if (isset($_SESSION['inventory_success'])): ?>
                <div id="successAlert" class="auto-dismiss-alert success-alert bg-green-600 text-white px-4 py-3 rounded mb-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <p><?php echo $_SESSION['inventory_success']; ?></p>
                        </div>
                        <button onclick="dismissAlert('successAlert')" class="text-white hover:text-gray-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['inventory_success']); ?>
            <?php endif; ?>

            <!-- Error message -->
            <?php if (isset($_SESSION['inventory_error'])): ?>
                <div id="errorAlert" class="auto-dismiss-alert error-alert bg-red-600 text-white px-4 py-3 rounded mb-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p><?php echo $_SESSION['inventory_error']; ?></p>
                        </div>
                        <button onclick="dismissAlert('errorAlert')" class="text-white hover:text-gray-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['inventory_error']); ?>
            <?php endif; ?>
            
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
            <form method="POST" action="" id="addInventoryForm">
                <div class="mb-4">
                    <label for="vaccine_name" class="block text-gray-300 mb-2">Vaccine Name</label>
                    <input type="text" name="vaccine_name" id="vaccine_name" placeholder="Enter vaccine name" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="batch_number" class="block text-gray-300 mb-2">Batch Number</label>
                    <input type="text" name="batch_number" id="batch_number" placeholder="Enter batch number" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="quantity" class="block text-gray-300 mb-2">Quantity</label>
                    <input type="number" name="quantity" id="quantity" placeholder="Enter quantity" min="1" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
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
                        Add Item
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

        // Function to dismiss an alert manually
        function dismissAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.opacity = '0';
                alert.style.visibility = 'hidden';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }
        }

        // Auto-dismiss alerts with different timings
        document.addEventListener('DOMContentLoaded', function() {
            // Get all alerts
            const successAlerts = document.querySelectorAll('.success-alert');
            const errorAlerts = document.querySelectorAll('.error-alert');
            
            // Handle success alerts (2 seconds)
            successAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 2000); // 2 seconds for success alerts
            });
            
            // Handle error alerts (5 seconds)
            errorAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 5000); // 5 seconds for error alerts
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const inventoryForm = document.getElementById('addInventoryForm');
            if (inventoryForm) {
                inventoryForm.addEventListener('submit', function(e) {
                    console.log('Form submission triggered');
                    
                    // Validate form data
                    const vaccine_name = document.getElementById('vaccine_name').value.trim();
                    const batch_number = document.getElementById('batch_number').value.trim();
                    const quantity = document.getElementById('quantity').value;
                    const expiry_date = document.getElementById('expiry_date').value;
                    
                    console.log('Form data:', {
                        vaccine_name,
                        batch_number,
                        quantity,
                        expiry_date
                    });
                    
                    // Let form submit normally
                });
            } else {
                console.error('Inventory form not found');
            }
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>