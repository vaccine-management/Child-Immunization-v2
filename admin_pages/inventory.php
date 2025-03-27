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
        /* Custom scrollbar styles */
        .overflow-x-auto::-webkit-scrollbar {
            height: 8px;
        }

        .overflow-x-auto::-webkit-scrollbar-track {
            background: #1f2937;
            border-radius: 4px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 4px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }

        /* Adjust main content spacing */
        #main-content {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        /* Ensure table container fits */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Make table cells more compact */
        .table td, .table th {
            padding: 0.5rem;
        }

        /* Ensure action buttons don't wrap */
        .flex.space-x-1 {
            flex-wrap: nowrap;
        }

        /* Table styles */
        .table {
            width: 100%;
            table-layout: fixed;
        }

        .table th {
            white-space: nowrap;
            padding: 0.75rem 1rem;
        }

        .table td {
            vertical-align: middle;
            padding: 0.75rem 1rem;
        }

        /* Truncate text with ellipsis */
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-900">
    <?php require_once ROOT_PATH . 'includes/header.php'; ?>
   <?php require_once ROOT_PATH . 'includes/navbar.php'; ?>
    <?php require_once ROOT_PATH . 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="container mx-auto px-4 py-8 max-w-6xl">
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
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider w-1/4">Vaccine Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider w-1/4">Target Disease</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider w-1/6">Quantity</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider w-1/6">Expiry Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider w-1/6">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-700">
                            <?php if (!empty($vaccines)): ?>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <tr class="hover:bg-gray-700 transition-colors duration-150">
                                        <td class="px-4 py-2 text-sm text-gray-300">
                                            <div class="truncate" title="<?php echo htmlspecialchars($vaccine['name'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($vaccine['name'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-300">
                                            <div class="truncate" title="<?php echo htmlspecialchars($vaccine['target_disease'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($vaccine['target_disease'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-300">
                                            <form method="POST" class="inline-flex">
                                                <input type="hidden" name="id" value="<?php echo $vaccine['id']; ?>">
                                                <div class="flex items-center space-x-1">
                                                    <input type="number" name="quantity" class="w-16 px-1 py-0.5 bg-gray-700 text-gray-300 rounded border border-gray-600 focus:outline-none focus:border-blue-500 text-sm" value="<?php echo htmlspecialchars($vaccine['quantity'] ?? '0'); ?>" min="0">
                                                    <button type="submit" name="update" class="px-2 py-0.5 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors duration-150 text-xs">
                                                        Update
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-300">
                                            <?php echo htmlspecialchars($vaccine['expiry_date'] ?? ''); ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-300">
                                            <div class="flex space-x-1">
                                                <button onclick="openViewModal(
                                                    <?php echo htmlspecialchars(json_encode($vaccine)); ?>
                                                )" class="p-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors duration-150" title="View Details">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>
                                                <button onclick="openEditModal(
                                                    <?php echo $vaccine['id']; ?>, 
                                                    '<?php echo htmlspecialchars($vaccine['batch_number'] ?? ''); ?>',
                                                    <?php echo htmlspecialchars($vaccine['quantity'] ?? '0'); ?>,
                                                    '<?php echo htmlspecialchars($vaccine['expiry_date'] ?? ''); ?>',
                                                    '<?php echo htmlspecialchars($vaccine['manufacturer'] ?? ''); ?>'
                                                )" class="p-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition-colors duration-150" title="Edit">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this vaccine?');">
                                                    <input type="hidden" name="id" value="<?php echo $vaccine['id']; ?>">
                                                    <button type="submit" name="delete" class="p-1 bg-red-600 text-white rounded hover:bg-red-700 transition-colors duration-150" title="Delete">
                                                        <i class="fas fa-trash text-xs"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-2 text-center text-sm text-gray-300">No vaccines found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

  
    <!-- View Vaccine Details Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Vaccine Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-400">Vaccine Name</p>
                        <p class="text-white" id="view_name"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Target Disease</p>
                        <p class="text-white" id="view_target_disease"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Manufacturer</p>
                        <p class="text-white" id="view_manufacturer"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Batch Number</p>
                        <p class="text-white" id="view_batch_number"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Quantity</p>
                        <p class="text-white" id="view_quantity"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Expiry Date</p>
                        <p class="text-white" id="view_expiry_date"></p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-400">Maximum Doses</p>
                        <p class="text-white" id="view_max_doses"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Administration Method</p>
                        <p class="text-white" id="view_administration_method"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Dosage</p>
                        <p class="text-white" id="view_dosage"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Storage Requirements</p>
                        <p class="text-white" id="view_storage_requirements"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Contraindications</p>
                        <p class="text-white" id="view_contraindications"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-400">Side Effects</p>
                        <p class="text-white" id="view_side_effects"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vaccine Modal -->
    <div id="addModal" class="fixed mt-12 inset-0 bg-black bg-opacity-50 hidden items-center justify-center overflow-y-auto "> 
        <div class="bg-gray-800 rounded-lg p-6 mt-12 max-w-2xl w-full mx-4">
            <div class="flex justify-between mt-12 items-center mb-6">
                <h3 class="mt-12 text-xl font-bold text-white">Add New Vaccine</h3>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="space-y-4 mt-12">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="vaccine_name" class="block text-sm font-medium text-gray-300 mt-12 mb-1">Vaccine Name</label>
                        <input type="text" id="vaccine_name" name="vaccine_name" placeholder="Vaccine name" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="target_disease" class="block text-sm font-medium text-gray-300 mb-1">Target Disease</label>
                        <input type="text" id="target_disease" name="target_disease" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="manufacturer" class="block text-sm font-medium text-gray-300 mb-1">Manufacturer</label>
                        <input type="text" id="manufacturer" name="manufacturer" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="batch_number" class="block text-sm font-medium text-gray-300 mb-1">Batch Number</label>
                        <input type="text" id="batch_number" name="batch_number" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-300 mb-1">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="0" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-300 mb-1">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="max_doses" class="block text-sm font-medium text-gray-300 mb-1">Maximum Doses</label>
                        <input type="number" id="max_doses" name="max_doses" min="1" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="administration_method" class="block text-sm font-medium text-gray-300 mb-1">Administration Method</label>
                        <input type="text" id="administration_method" name="administration_method" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="dosage" class="block text-sm font-medium text-gray-300 mb-1">Dosage</label>
                        <input type="text" id="dosage" name="dosage" required class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="storage_requirements" class="block text-sm font-medium text-gray-300 mb-1">Storage Requirements</label>
                        <textarea id="storage_requirements" name="storage_requirements" rows="2" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="contraindications" class="block text-sm font-medium text-gray-300 mb-1">Contraindications</label>
                        <textarea id="contraindications" name="contraindications" rows="2" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="side_effects" class="block text-sm font-medium text-gray-300 mb-1">Side Effects</label>
                        <textarea id="side_effects" name="side_effects" rows="2" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit" name="add" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Add Vaccine
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
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

        function openViewModal(vaccine) {
            document.getElementById('view_name').textContent = vaccine.name;
            document.getElementById('view_target_disease').textContent = vaccine.target_disease;
            document.getElementById('view_manufacturer').textContent = vaccine.manufacturer;
            document.getElementById('view_batch_number').textContent = vaccine.batch_number;
            document.getElementById('view_quantity').textContent = vaccine.quantity;
            document.getElementById('view_expiry_date').textContent = vaccine.expiry_date;
            document.getElementById('view_max_doses').textContent = vaccine.max_doses;
            document.getElementById('view_administration_method').textContent = vaccine.administration_method;
            document.getElementById('view_dosage').textContent = vaccine.dosage;
            document.getElementById('view_storage_requirements').textContent = vaccine.storage_requirements;
            document.getElementById('view_contraindications').textContent = vaccine.contraindications;
            document.getElementById('view_side_effects').textContent = vaccine.side_effects;
            openModal('viewModal');
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

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Handle success alerts
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.visibility = 'hidden';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 300);
                }, 2000);
            }

            // Handle error alerts
            const errorAlert = document.getElementById('errorAlert');
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.style.opacity = '0';
                    errorAlert.style.visibility = 'hidden';
                    setTimeout(() => {
                        errorAlert.style.display = 'none';
                    }, 300);
                }, 5000);
            }

            // Handle form submission
            const addVaccineForm = document.querySelector('form[method="POST"]');
            if (addVaccineForm) {
                addVaccineForm.addEventListener('submit', function(e) {
                    // Validate form data
                    const vaccine_name = document.getElementById('vaccine_name')?.value.trim();
                    const batch_number = document.getElementById('batch_number')?.value.trim();
                    const quantity = document.getElementById('quantity')?.value;
                    const expiry_date = document.getElementById('expiry_date')?.value;
                    
                    if (!vaccine_name || !batch_number || !quantity || !expiry_date) {
                        e.preventDefault();
                        alert('Please fill in all required fields');
                        return;
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>