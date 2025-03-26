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

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $vaccine_name = trim($_POST['vaccine_name']);
        $target_disease = trim($_POST['target_disease']);
        $manufacturer = trim($_POST['manufacturer']);
        $batch_number = trim($_POST['batch_number']);
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
        $expiry_date = $_POST['expiry_date'];
        $max_doses = filter_var($_POST['max_doses'], FILTER_VALIDATE_INT);
        $administration_method = trim($_POST['administration_method']);
        $dosage = trim($_POST['dosage']);
        $storage_requirements = trim($_POST['storage_requirements']);
        $contraindications = trim($_POST['contraindications']);
        $side_effects = trim($_POST['side_effects']);

        if (addVaccine($vaccine_name, $target_disease, $manufacturer, $batch_number, $quantity, 
                      $expiry_date, $max_doses, $administration_method, $dosage, 
                      $storage_requirements, $contraindications, $side_effects)) {
            $_SESSION['inventory_success'] = "Added {$quantity} doses of {$vaccine_name} (Batch: {$batch_number}) to inventory!";
        } else {
            $_SESSION['inventory_error'] = "Error adding vaccine to inventory.";
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        if (deleteVaccine($id)) {
            $_SESSION['inventory_success'] = "Vaccine deleted successfully!";
        } else {
            $_SESSION['inventory_error'] = "Error deleting vaccine.";
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $batch_number = trim($_POST['batch_number']);
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
        $expiry_date = $_POST['expiry_date'];
        $manufacturer = trim($_POST['manufacturer']);
        
        if (updateVaccine($id, $batch_number, $quantity, $expiry_date, $manufacturer)) {
            $_SESSION['inventory_success'] = "Vaccine updated successfully!";
        } else {
            $_SESSION['inventory_error'] = "Error updating vaccine.";
        }
    }
    header('Location: inventory.php');
    exit();
}

// Fetch all vaccines
$vaccines = getAllVaccines();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Child Immunization System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        .alert {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 0.25rem;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        .alert-success {
            animation: fadeOut 0.5s ease-in-out 2s forwards;
        }
        .alert-danger {
            animation: fadeOut 0.5s ease-in-out 5s forwards;
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
                <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
                    <?php 
                    echo $_SESSION['inventory_success'];
                    unset($_SESSION['inventory_success']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Error message -->
            <?php if (isset($_SESSION['inventory_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorAlert">
                    <?php 
                    echo $_SESSION['inventory_error'];
                    unset($_SESSION['inventory_error']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
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
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vaccine Name</th>
                            <th>Target Disease</th>
                            <th>Batch Number</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Manufacturer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($vaccines)): ?>
                            <?php foreach ($vaccines as $vaccine): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vaccine['id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($vaccine['name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($vaccine['target_disease'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($vaccine['batch_number'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $vaccine['id']; ?>">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="quantity" class="form-control" value="<?php echo htmlspecialchars($vaccine['quantity'] ?? '0'); ?>" min="0">
                                                <div class="input-group-append">
                                                    <button type="submit" name="update" class="btn btn-primary">Update</button>
                                                </div>
                                        </div>
                                        </form>
                                    </td>
                                    <td><?php echo htmlspecialchars($vaccine['expiry_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($vaccine['manufacturer'] ?? ''); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button onclick="openEditModal(
                                                <?php echo $vaccine['id']; ?>, 
                                                '<?php echo htmlspecialchars($vaccine['batch_number'] ?? ''); ?>',
                                                <?php echo htmlspecialchars($vaccine['quantity'] ?? '0'); ?>,
                                                '<?php echo htmlspecialchars($vaccine['expiry_date'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($vaccine['manufacturer'] ?? ''); ?>'
                                            )" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this vaccine?');">
                                                <input type="hidden" name="id" value="<?php echo $vaccine['id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No vaccines found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Vaccine Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-gray-800 rounded-lg p-8 w-full max-w-md">
                <h3 class="text-xl font-bold mb-4">Add New Vaccine</h3>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Add New Vaccine</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="form-group">
                                <label for="vaccine_name">Vaccine Name</label>
                                <input type="text" class="form-control" id="vaccine_name" name="vaccine_name" required>
                            </div>
                            <div class="form-group">
                                <label for="target_disease">Target Disease</label>
                                <input type="text" class="form-control" id="target_disease" name="target_disease" required>
                            </div>
                            <div class="form-group">
                                <label for="manufacturer">Manufacturer</label>
                                <input type="text" class="form-control" id="manufacturer" name="manufacturer" required>
                            </div>
                            <div class="form-group">
                                <label for="batch_number">Batch Number</label>
                                <input type="text" class="form-control" id="batch_number" name="batch_number" required>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                            </div>
                            <div class="form-group">
                                <label for="max_doses">Maximum Doses</label>
                                <input type="number" class="form-control" id="max_doses" name="max_doses" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="administration_method">Administration Method</label>
                                <input type="text" class="form-control" id="administration_method" name="administration_method" required>
                            </div>
                            <div class="form-group">
                                <label for="dosage">Dosage</label>
                                <input type="text" class="form-control" id="dosage" name="dosage" required>
                            </div>
                            <div class="form-group">
                                <label for="storage_requirements">Storage Requirements</label>
                                <textarea class="form-control" id="storage_requirements" name="storage_requirements" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="contraindications">Contraindications</label>
                                <textarea class="form-control" id="contraindications" name="contraindications" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="side_effects">Side Effects</label>
                                <textarea class="form-control" id="side_effects" name="side_effects" rows="2"></textarea>
                            </div>
                            <button type="submit" name="add" class="btn btn-primary">Add Vaccine</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Inventory Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Edit Vaccine</h3>
            <form method="POST" action="">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-4">
                    <label for="edit_batch_number" class="block text-gray-300 mb-2">Batch Number</label>
                    <input type="text" name="batch_number" id="edit_batch_number" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="edit_quantity" class="block text-gray-300 mb-2">Quantity</label>
                    <input type="number" name="quantity" id="edit_quantity" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" min="0" required>
                </div>
                <div class="mb-4">
                    <label for="edit_expiry_date" class="block text-gray-300 mb-2">Expiry Date</label>
                    <input type="date" name="expiry_date" id="edit_expiry_date" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="edit_manufacturer" class="block text-gray-300 mb-2">Manufacturer</label>
                    <input type="text" name="manufacturer" id="edit_manufacturer" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
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
        
        function openEditModal(id, batch_number, quantity, expiry_date, manufacturer) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_batch_number').value = batch_number;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_expiry_date').value = expiry_date;
            document.getElementById('edit_manufacturer').value = manufacturer;
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