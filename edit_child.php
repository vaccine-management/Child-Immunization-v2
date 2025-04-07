<?php
// Ensure user is logged in
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Nurse') {
    header('Location: index.php');
    exit();
}

// Enable better error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include 'backend/db.php';

// Get child ID from URL
if (!isset($_GET['id'])) {
    header('Location: children.php');
    exit();
}

$childId = $_GET['id'];

// Fetch child details
$stmt = $conn->prepare("SELECT 
    child_id, full_name, date_of_birth, gender, birth_weight, place_of_birth,
    guardian_name, phone, email, address,
    birth_complications, allergies, previous_vaccinations
    FROM children 
    WHERE child_id = :child_id");
$stmt->bindParam(':child_id', $childId);
$stmt->execute();
$child = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    header('Location: children.php');
    exit();
}

// Initialize success/error messages
$success = $error = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'child';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_child_info'])) {
        // Update child's personal information
        try {
            $fullName = trim($_POST['full_name']);
            $dateOfBirth = trim($_POST['date_of_birth']);
            $gender = trim($_POST['gender']);
            $birthWeight = floatval($_POST['birth_weight']);
            $placeOfBirth = trim($_POST['place_of_birth']);
            
            // Validate inputs
            if (empty($fullName) || empty($dateOfBirth) || empty($gender)) {
                throw new Exception("Required fields must be filled out.");
            }
            
            if ($birthWeight <= 0) {
                throw new Exception("Birth weight must be a positive number.");
            }
            
            // Update database
            $stmt = $conn->prepare("UPDATE children SET 
                full_name = :full_name,
                date_of_birth = :date_of_birth,
                gender = :gender,
                birth_weight = :birth_weight,
                place_of_birth = :place_of_birth
                WHERE child_id = :child_id");
                
            $stmt->execute([
                ':full_name' => $fullName,
                ':date_of_birth' => $dateOfBirth,
                ':gender' => $gender,
                ':birth_weight' => $birthWeight,
                ':place_of_birth' => $placeOfBirth,
                ':child_id' => $childId
            ]);
            
            $success = "Child's personal information updated successfully.";
            $activeTab = 'child';
            
            // Refresh child data
            $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = :child_id");
            $stmt->bindParam(':child_id', $childId);
            $stmt->execute();
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = "Failed to update child's information: " . $e->getMessage();
            $activeTab = 'child';
        }
    } 
    elseif (isset($_POST['update_guardian_info'])) {
        // Update guardian information
        try {
            $guardianName = trim($_POST['guardian_name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            
            // Validate inputs
            if (empty($guardianName) || empty($phone)) {
                throw new Exception("Guardian name and phone number are required.");
            }
            
            // Update database
            $stmt = $conn->prepare("UPDATE children SET 
                guardian_name = :guardian_name,
                phone = :phone,
                email = :email,
                address = :address
                WHERE child_id = :child_id");
                
            $stmt->execute([
                ':guardian_name' => $guardianName,
                ':phone' => $phone,
                ':email' => $email,
                ':address' => $address,
                ':child_id' => $childId
            ]);
            
            $success = "Guardian information updated successfully.";
            $activeTab = 'guardian';
            
            // Refresh child data
            $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = :child_id");
            $stmt->bindParam(':child_id', $childId);
            $stmt->execute();
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = "Failed to update guardian information: " . $e->getMessage();
            $activeTab = 'guardian';
        }
    } 
    elseif (isset($_POST['update_medical_info'])) {
        // Update medical information
        try {
            $birthComplications = trim($_POST['birth_complications']);
            $allergies = trim($_POST['allergies']);
            $previousVaccinations = trim($_POST['previous_vaccinations']);
            
            // Update database
            $stmt = $conn->prepare("UPDATE children SET 
                birth_complications = :birth_complications,
                allergies = :allergies,
                previous_vaccinations = :previous_vaccinations
                WHERE child_id = :child_id");
                
            $stmt->execute([
                ':birth_complications' => $birthComplications,
                ':allergies' => $allergies,
                ':previous_vaccinations' => $previousVaccinations,
                ':child_id' => $childId
            ]);
            
            // Also update medical_records table if it exists
            try {
                $stmt = $conn->prepare("UPDATE medical_records SET 
                    birth_complications = :birth_complications,
                    allergies = :allergies,
                    previous_vaccinations = :previous_vaccinations
                    WHERE child_id = :child_id");
                    
                $stmt->execute([
                    ':birth_complications' => $birthComplications,
                    ':allergies' => $allergies,
                    ':previous_vaccinations' => $previousVaccinations,
                    ':child_id' => $childId
                ]);
            } catch (Exception $e) {
                // If medical_records table doesn't exist or record doesn't exist, just continue
                error_log("Note: Could not update medical_records table: " . $e->getMessage());
            }
            
            $success = "Medical information updated successfully.";
            $activeTab = 'medical';
            
            // Refresh child data
            $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = :child_id");
            $stmt->bindParam(':child_id', $childId);
            $stmt->execute();
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = "Failed to update medical information: " . $e->getMessage();
            $activeTab = 'medical';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Child - <?php echo htmlspecialchars($child['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="p-4 sm:ml-64 pt-20">
        <div class="p-4 rounded-lg">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="flex items-center">
                    <div class="bg-yellow-600 p-3 rounded-lg mr-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-semibold text-white">Edit Child Profile</h2>
                        <p class="text-gray-400">ID: <?php echo htmlspecialchars($child['child_id']); ?></p>
                    </div>
                </div>
                <div class="mt-4 md:mt-0">
                    <a href="child_profile.php?id=<?php echo urlencode($child['child_id']); ?>" 
                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg 
                              shadow-lg transition duration-300 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Profile
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($success)): ?>
                <div class="bg-green-900 text-green-100 p-4 rounded-lg mb-6 animate__animated animate__fadeIn">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $success; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-900 text-red-100 p-4 rounded-lg mb-6 animate__animated animate__fadeIn">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="mb-6">
                <nav class="flex flex-wrap border-b border-gray-700">
                    <a href="#child-info" 
                       class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 transition-colors <?php echo $activeTab === 'child' ? 'border-blue-500 text-white' : 'border-transparent'; ?>"
                       data-tab="child-info">
                        <i class="fas fa-child mr-2"></i>Child Information
                    </a>
                    <a href="#guardian-info" 
                       class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 transition-colors <?php echo $activeTab === 'guardian' ? 'border-green-500 text-white' : 'border-transparent'; ?>"
                       data-tab="guardian-info">
                        <i class="fas fa-user-friends mr-2"></i>Guardian Information
                    </a>
                    <a href="#medical-info" 
                       class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 transition-colors <?php echo $activeTab === 'medical' ? 'border-red-500 text-white' : 'border-transparent'; ?>"
                       data-tab="medical-info">
                        <i class="fas fa-notes-medical mr-2"></i>Medical Information
                    </a>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Child Information Tab -->
                <div id="child-info" class="tab-pane <?php echo $activeTab === 'child' ? 'block' : 'hidden'; ?>">
                    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                        <div class="p-4 border-b border-gray-700">
                            <h3 class="text-lg font-semibold text-white">Child's Personal Information</h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="col-span-2">
                                        <label for="full_name" class="block text-gray-300 text-sm font-medium mb-2">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($child['full_name']); ?>" required
                                               class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                      focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    </div>
                                    <div>
                                        <label for="date_of_birth" class="block text-gray-300 text-sm font-medium mb-2">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo htmlspecialchars($child['date_of_birth']); ?>" required
                                               class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                      focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    </div>
                                    <div>
                                        <label for="gender" class="block text-gray-300 text-sm font-medium mb-2">Gender</label>
                                        <select id="gender" name="gender" required
                                                class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                       focus:ring-2 focus:ring-blue-500 transition duration-300">
                                            <option value="Male" <?php echo $child['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $child['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="birth_weight" class="block text-gray-300 text-sm font-medium mb-2">Weight at Birth (kg)</label>
                                        <input type="number" step="0.01" id="birth_weight" name="birth_weight" 
                                               value="<?php echo htmlspecialchars($child['birth_weight']); ?>" required
                                               class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                      focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    </div>
                                    <div>
                                        <label for="place_of_birth" class="block text-gray-300 text-sm font-medium mb-2">Place of Birth</label>
                                        <select id="place_of_birth" name="place_of_birth" required
                                                class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                       focus:ring-2 focus:ring-blue-500 transition duration-300">
                                            <option value="Hospital" <?php echo $child['place_of_birth'] === 'Hospital' ? 'selected' : ''; ?>>Hospital</option>
                                            <option value="Home" <?php echo $child['place_of_birth'] === 'Home' ? 'selected' : ''; ?>>Home</option>
                                            <option value="Other" <?php echo $child['place_of_birth'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" name="update_child_info" 
                                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg 
                                                   shadow-lg transition duration-300 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Save Child Information
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Guardian Information Tab -->
                <div id="guardian-info" class="tab-pane <?php echo $activeTab === 'guardian' ? 'block' : 'hidden'; ?>">
                    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                        <div class="p-4 border-b border-gray-700">
                            <h3 class="text-lg font-semibold text-white">Parent/Guardian Information</h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="col-span-2">
                                        <label for="guardian_name" class="block text-gray-300 text-sm font-medium mb-2">Guardian's Full Name</label>
                                        <input type="text" id="guardian_name" name="guardian_name" 
                                               value="<?php echo htmlspecialchars($child['guardian_name']); ?>" required
                                               class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                      focus:ring-2 focus:ring-green-500 transition duration-300">
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-gray-300 text-sm font-medium mb-2">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($child['phone']); ?>" required
                                               class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                      focus:ring-2 focus:ring-green-500 transition duration-300">
                                    </div>
                                    <div>
                                        <label for="email" class="block text-gray-300 text-sm font-medium mb-2">Email Address</label>
                                        <input type="email" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($child['email']); ?>"
                                               class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                      focus:ring-2 focus:ring-green-500 transition duration-300">
                                    </div>
                                    <div class="col-span-2">
                                        <label for="address" class="block text-gray-300 text-sm font-medium mb-2">Home Address</label>
                                        <textarea id="address" name="address" rows="3"
                                                  class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                         focus:ring-2 focus:ring-green-500 transition duration-300"><?php echo htmlspecialchars($child['address']); ?></textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" name="update_guardian_info" 
                                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg 
                                                   shadow-lg transition duration-300 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Save Guardian Information
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Medical Information Tab -->
                <div id="medical-info" class="tab-pane <?php echo $activeTab === 'medical' ? 'block' : 'hidden'; ?>">
                    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                        <div class="p-4 border-b border-gray-700">
                            <h3 class="text-lg font-semibold text-white">Medical Information</h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label for="birth_complications" class="block text-gray-300 text-sm font-medium mb-2">Birth Complications</label>
                                        <textarea id="birth_complications" name="birth_complications" rows="3"
                                                  class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                         focus:ring-2 focus:ring-red-500 transition duration-300"><?php echo htmlspecialchars($child['birth_complications']); ?></textarea>
                                    </div>
                                    <div>
                                        <label for="allergies" class="block text-gray-300 text-sm font-medium mb-2">Known Allergies</label>
                                        <textarea id="allergies" name="allergies" rows="3"
                                                  class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                         focus:ring-2 focus:ring-red-500 transition duration-300"><?php echo htmlspecialchars($child['allergies']); ?></textarea>
                                    </div>
                                    <div>
                                        <label for="previous_vaccinations" class="block text-gray-300 text-sm font-medium mb-2">Previous Vaccinations</label>
                                        <textarea id="previous_vaccinations" name="previous_vaccinations" rows="3"
                                                  class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                         focus:ring-2 focus:ring-red-500 transition duration-300"><?php echo htmlspecialchars($child['previous_vaccinations']); ?></textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" name="update_medical_info" 
                                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg 
                                                   shadow-lg transition duration-300 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Save Medical Information
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href').substring(1);
                    
                    // Hide all tab panes
                    tabPanes.forEach(pane => {
                        pane.classList.add('hidden');
                    });
                    
                    // Show target tab pane
                    document.getElementById(targetId).classList.remove('hidden');
                    
                    // Update active tab styling
                    tabLinks.forEach(link => {
                        link.classList.remove('border-blue-500', 'border-green-500', 'border-red-500', 'text-white');
                        link.classList.add('border-transparent', 'text-gray-300');
                    });
                    
                    // Add appropriate styling based on tab
                    if (targetId === 'child-info') {
                        this.classList.add('border-blue-500', 'text-white');
                    } else if (targetId === 'guardian-info') {
                        this.classList.add('border-green-500', 'text-white');
                    } else if (targetId === 'medical-info') {
                        this.classList.add('border-red-500', 'text-white');
                    }
                });
            });
            
            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.bg-green-900, .bg-red-900');
                alerts.forEach(alert => {
                    alert.classList.add('animate__fadeOut');
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>
