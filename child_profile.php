<?php

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Nurse') {
    header('Location: index.php');
    exit();
}


// Include database connection
include 'backend/db.php';

// Get child ID from URL
if (!isset($_GET['id'])) {
    header('Location: children.php');
    exit();
}

$childId = $_GET['id'];

// Fetch child details with all necessary information
$stmt = $conn->prepare("SELECT 
    c.child_id,
    c.full_name,
    c.date_of_birth,
    c.gender,
    c.birth_weight,
    c.place_of_birth,
    c.guardian_name,
    c.phone,
    c.email,
    c.address,
    c.birth_complications,
    c.allergies,
    c.previous_vaccinations
    FROM children c 
    WHERE c.child_id = :child_id");
$stmt->bindParam(':child_id', $childId);
$stmt->execute();
$child = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    header('Location: children.php');
    exit();
}

// Calculate age from date_of_birth
$birthDate = new DateTime($child['date_of_birth']);
$today = new DateTime();
$age = $birthDate->diff($today);

// Format age string
if ($age->y > 0) {
    $ageString = $age->y . " year" . ($age->y > 1 ? "s" : "");
} elseif ($age->m > 0) {
    $ageString = $age->m . " month" . ($age->m > 1 ? "s" : "");
} else {
    $ageString = $age->d . " day" . ($age->d > 1 ? "s" : "");
}

// Fetch vaccination records with scheduled time
$stmt = $conn->prepare("
    SELECT *, TIME_FORMAT(scheduled_time, '%h:%i %p') as formatted_time 
    FROM vaccinations 
    WHERE child_id = :child_id 
    ORDER BY scheduled_date DESC, scheduled_time DESC
");
$stmt->bindParam(':child_id', $childId);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate vaccination progress without using the vaccines table
$takenVaccines = count(array_filter($vaccinations, function($v) {
    return $v['status'] === 'Taken';
}));

// Get distinct vaccine names from vaccinations table for progress calculation
$stmt = $conn->query("SELECT COUNT(DISTINCT vaccine_name) FROM vaccinations");
$totalVaccineTypes = $stmt->fetchColumn();

// Set a default value if no vaccines found to avoid division by zero
if ($totalVaccineTypes == 0) {
    $totalVaccineTypes = 1;
    $progress = 0;
} else {
    // Calculate progress based on how many unique vaccines have been taken
    $stmt = $conn->query("SELECT COUNT(DISTINCT vaccine_name) FROM vaccinations WHERE status = 'Taken' AND child_id = '$childId'");
    $uniqueTakenVaccines = $stmt->fetchColumn();
    $progress = ($uniqueTakenVaccines / $totalVaccineTypes) * 100;
}

// Get vaccine options from existing vaccinations instead of vaccines table
$stmt = $conn->query("SELECT DISTINCT vaccine_name FROM vaccinations ORDER BY vaccine_name");
$vaccineOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Add default vaccines if none found in the system
if (empty($vaccineOptions)) {
    $vaccineOptions = [
        'BCG',
        'OPV 0',
        'OPV 1',
        'OPV 2',
        'OPV 3',
        'Pentavalent 1',
        'Pentavalent 2',
        'Pentavalent 3',
        'Rotavirus 1',
        'Rotavirus 2',
        'PCV 1',
        'PCV 2',
        'PCV 3',
        'IPV',
        'Measles',
        'Yellow Fever',
        'Vitamin A'
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $dateTaken = date('Y-m-d');

        if (empty($vaccineName)) {
            $error = "Vaccine name is required.";
        } else {
            // Check if the vaccine is already scheduled
            $stmt = $conn->prepare("SELECT id FROM vaccinations WHERE child_id = :child_id AND vaccine_name = :vaccine_name AND status = 'Scheduled'");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName
            ]);
            $scheduledVaccine = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($scheduledVaccine) {
                // Update the scheduled vaccine to "Taken"
                $stmt = $conn->prepare("UPDATE vaccinations SET date_taken = :date_taken, status = 'Taken' WHERE id = :id");
                $stmt->execute([
                    ':date_taken' => $dateTaken,
                    ':id' => $scheduledVaccine['id']
                ]);
            } else {
                // Insert a new record for the vaccine as "Taken"
                $stmt = $conn->prepare("INSERT INTO vaccinations (child_id, vaccine_name, date_taken, status) 
                                      VALUES (:child_id, :vaccine_name, :date_taken, 'Taken')");
                $stmt->execute([
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccineName,
                    ':date_taken' => $dateTaken
                ]);
            }

            header("Location: child_profile.php?id=$childId&success=1");
            exit();
        }
    }

    if (isset($_POST['schedule_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $scheduledDate = trim($_POST['scheduled_date']);
        $scheduledTime = trim($_POST['scheduled_time']);

        if (empty($vaccineName) || empty($scheduledDate) || empty($scheduledTime)) {
            $error = "All fields are required.";
        } else {
            // Insert a new record for the vaccine as "Scheduled"
            $stmt = $conn->prepare("
                INSERT INTO vaccinations 
                    (child_id, vaccine_name, scheduled_date, scheduled_time, status) 
                VALUES 
                    (:child_id, :vaccine_name, :scheduled_date, :scheduled_time, 'Scheduled')
            ");
            try {
                $stmt->execute([
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccineName,
                    ':scheduled_date' => $scheduledDate,
                    ':scheduled_time' => $scheduledTime
                ]);
                header("Location: child_profile.php?id=$childId&success=2");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to schedule vaccine.";
            }
        }
    }

    if (isset($_POST['update_child'])) {
        $fullName = trim($_POST['full_name']);
        $birthWeight = floatval($_POST['birth_weight']);
        $gender = trim($_POST['gender']);
        $placeOfBirth = trim($_POST['place_of_birth']);
        $guardianName = trim($_POST['guardian_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $birthComplications = trim($_POST['birth_complications']);
        $allergies = trim($_POST['allergies']);
        $previousVaccinations = trim($_POST['previous_vaccinations']);

        if (empty($fullName) || empty($gender) || empty($guardianName) || empty($phone)) {
            $error = "Required fields must be filled out.";
        } else {
            $stmt = $conn->prepare("UPDATE children SET 
                full_name = :full_name,
                birth_weight = :birth_weight,
                gender = :gender,
                place_of_birth = :place_of_birth,
                guardian_name = :guardian_name,
                phone = :phone,
                email = :email,
                address = :address,
                birth_complications = :birth_complications,
                allergies = :allergies,
                previous_vaccinations = :previous_vaccinations
                WHERE child_id = :child_id");
            
            try {
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':birth_weight' => $birthWeight,
                    ':gender' => $gender,
                    ':place_of_birth' => $placeOfBirth,
                    ':guardian_name' => $guardianName,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':address' => $address,
                    ':birth_complications' => $birthComplications,
                    ':allergies' => $allergies,
                    ':previous_vaccinations' => $previousVaccinations,
                    ':child_id' => $childId
                ]);
                header("Location: child_profile.php?id=$childId&success=3");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to update child details.";
            }
        }
    }

    if (isset($_POST['update_personal'])) {
        $fullName = trim($_POST['full_name']);
        $birthWeight = floatval($_POST['birth_weight']);
        $gender = trim($_POST['gender']);
        $placeOfBirth = trim($_POST['place_of_birth']);

        if (empty($fullName) || empty($gender)) {
            $error = "Required fields must be filled out.";
        } else {
            $stmt = $conn->prepare("UPDATE children SET 
                full_name = :full_name,
                birth_weight = :birth_weight,
                gender = :gender,
                place_of_birth = :place_of_birth
                WHERE child_id = :child_id");
            
            try {
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':birth_weight' => $birthWeight,
                    ':gender' => $gender,
                    ':place_of_birth' => $placeOfBirth,
                    ':child_id' => $childId
                ]);
                header("Location: child_profile.php?id=$childId&success=4");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to update personal details.";
            }
        }
    }

    if (isset($_POST['update_guardian'])) {
        $guardianName = trim($_POST['guardian_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);

        if (empty($guardianName) || empty($phone)) {
            $error = "Required fields must be filled out.";
        } else {
            $stmt = $conn->prepare("UPDATE children SET 
                guardian_name = :guardian_name,
                phone = :phone,
                email = :email,
                address = :address
                WHERE child_id = :child_id");
            
            try {
                $stmt->execute([
                    ':guardian_name' => $guardianName,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':address' => $address,
                    ':child_id' => $childId
                ]);
                header("Location: child_profile.php?id=$childId&success=5");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to update guardian details.";
            }
        }
    }

    if (isset($_POST['update_medical'])) {
        $birthComplications = trim($_POST['birth_complications']);
        $allergies = trim($_POST['allergies']);
        $previousVaccinations = trim($_POST['previous_vaccinations']);

        $stmt = $conn->prepare("UPDATE children SET 
            birth_complications = :birth_complications,
            allergies = :allergies,
            previous_vaccinations = :previous_vaccinations
            WHERE child_id = :child_id");
        
        try {
            $stmt->execute([
                ':birth_complications' => $birthComplications,
                ':allergies' => $allergies,
                ':previous_vaccinations' => $previousVaccinations,
                ':child_id' => $childId
            ]);
            header("Location: child_profile.php?id=$childId&success=6");
            exit();
        } catch (PDOException $e) {
            $error = "Failed to update medical history.";
        }
    }
}

// Define success messages
if (isset($_GET['success'])) {
    $messages = [
        1 => "Vaccine recorded successfully!",
        2 => "Vaccine scheduled successfully!",
        3 => "Child details updated successfully!",
        4 => "Personal information updated successfully!",
        5 => "Guardian information updated successfully!",
        6 => "Medical history updated successfully!"
    ];
    $message = $messages[$_GET['success']] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($child['full_name']); ?> - Child Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* Custom scrollbar styles */
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #1f2937;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-6">
                        <!-- Gender-specific Avatar -->
                        <div class="relative">
                            <div class="h-20 w-20 rounded-full bg-gradient-to-br 
                                <?php echo $child['gender'] === 'Male' 
                                    ? 'from-blue-400/20 to-blue-600/20 border-blue-500/50' 
                                    : 'from-pink-400/20 to-pink-600/20 border-pink-500/50'; ?> 
                                border-2 flex items-center justify-center">
                                <svg class="w-12 h-12 
                                    <?php echo $child['gender'] === 'Male' ? 'text-blue-400' : 'text-pink-400'; ?>" 
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </div>
                        <!-- Child Info -->
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-1">
                                <?php echo htmlspecialchars($child['full_name']); ?>
                            </h1>
                            <div class="flex items-center space-x-4 text-sm">
                                <span class="text-blue-200">ID: <?php echo htmlspecialchars($child['child_id']); ?></span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium 
                                    <?php echo $child['gender'] === 'Male' 
                                        ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                        : 'bg-pink-500/10 text-pink-400 border border-pink-500/20'; ?>">
                                    <?php echo $child['gender']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Quick Stats -->
            <div class="grid grid-cols-4 divide-x divide-blue-500/20 border-t border-blue-500/20">
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Age</p>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($ageString); ?></p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Birth Weight</p>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($child['birth_weight']); ?> kg</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Guardian</p>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($child['guardian_name']); ?></p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Contact</p>
                    <a href="tel:<?php echo htmlspecialchars($child['phone']); ?>" 
                       class="text-white font-semibold hover:text-blue-300 transition duration-300">
                        <?php echo htmlspecialchars($child['phone']); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Detailed Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Personal Information -->
            <div class="bg-gray-800 rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-white">Personal Information</h2>
                    <button type="button" onclick="togglePersonalEdit()"
                            class="text-blue-400 hover:text-blue-300 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Edit
                    </button>
                </div>

                <!-- View Mode -->
                <div id="personal-view" class="space-y-3">
                    <div>
                        <p class="text-gray-400 text-sm">Full Name</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['full_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Date of Birth</p>
                        <p class="text-white"><?php echo date('F j, Y', strtotime($child['date_of_birth'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Birth Weight</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['birth_weight']); ?> kg</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Place of Birth</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['place_of_birth']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Gender</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['gender']); ?></p>
                    </div>
                </div>

                <!-- Edit Mode -->
                <form id="personal-edit" method="POST" class="hidden space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Full Name *</label>
                        <input type="text" name="full_name" required
                               value="<?php echo htmlspecialchars($child['full_name']); ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Birth Weight (kg) *</label>
                        <input type="number" step="0.01" name="birth_weight" required
                               value="<?php echo htmlspecialchars($child['birth_weight']); ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Place of Birth</label>
                        <input type="text" name="place_of_birth"
                               value="<?php echo htmlspecialchars($child['place_of_birth']); ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Gender *</label>
                        <select name="gender" required
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                            <option value="Male" <?php echo $child['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $child['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3 pt-3">
                        <button type="button" onclick="togglePersonalEdit()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit" name="update_personal"
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Guardian Information -->
            <div class="bg-gray-800 rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-white">Guardian Information</h2>
                    <button type="button" onclick="toggleGuardianEdit()"
                            class="text-blue-400 hover:text-blue-300 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Edit
                    </button>
                </div>

                <!-- View Mode -->
                <div id="guardian-view" class="space-y-3">
                    <div>
                        <p class="text-gray-400 text-sm">Guardian Name</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['guardian_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Phone Number</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['phone']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Email Address</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['email'] ?: 'Not provided'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Address</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['address']); ?></p>
                    </div>
                </div>

                <!-- Edit Mode -->
                <form id="guardian-edit" method="POST" class="hidden space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Guardian's Name *</label>
                        <input type="text" name="guardian_name" required
                               value="<?php echo htmlspecialchars($child['guardian_name']); ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Phone Number *</label>
                        <input type="tel" name="phone" required
                               value="<?php echo htmlspecialchars($child['phone']); ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Email Address</label>
                        <input type="email" name="email"
                               value="<?php echo htmlspecialchars($child['email']); ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">Address</label>
                        <textarea name="address" rows="2"
                                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg"><?php echo htmlspecialchars($child['address']); ?></textarea>
                    </div>
                    <div class="flex justify-end space-x-3 pt-3">
                        <button type="button" onclick="toggleGuardianEdit()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit" name="update_guardian"
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Medical History -->
            <div class="bg-gray-800 rounded-lg p-6 md:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-white">Medical History</h2>
                    <button type="button" onclick="toggleMedicalEdit()"
                            class="text-blue-400 hover:text-blue-300 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Edit
                    </button>
                </div>

                <!-- View Mode -->
                <div id="medical-view" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-gray-400 text-sm mb-2">Birth Complications</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['birth_complications'] ?: 'None reported'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-2">Allergies</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['allergies'] ?: 'None reported'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-2">Previous Vaccinations</p>
                        <p class="text-white"><?php echo htmlspecialchars($child['previous_vaccinations'] ?: 'None reported'); ?></p>
                    </div>
                </div>

                <!-- Edit Mode -->
                <form id="medical-edit" method="POST" class="hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Birth Complications</label>
                            <textarea name="birth_complications" rows="3"
                                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg"><?php echo htmlspecialchars($child['birth_complications']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Allergies</label>
                            <textarea name="allergies" rows="3"
                                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg"><?php echo htmlspecialchars($child['allergies']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Previous Vaccinations</label>
                            <textarea name="previous_vaccinations" rows="3"
                                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg"><?php echo htmlspecialchars($child['previous_vaccinations']); ?></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-3">
                        <button type="button" onclick="toggleMedicalEdit()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit" name="update_medical"
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Vaccination Management Section -->
        <div class="mt-6">
            <!-- Top Row - Progress & Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Progress Card -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-700/50 rounded-xl p-6 transform transition-all duration-300 hover:shadow-xl hover:shadow-blue-500/5">
                    <h2 class="text-xl font-bold text-white flex items-center mb-6">
                        <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Vaccination Progress
                    </h2>
                    
                    <!-- Modern Circular Progress Indicator -->
                    <div class="flex justify-center mb-6">
                        <div class="relative w-40 h-40">
                            <!-- Background Circle -->
                            <svg class="w-full h-full" viewBox="0 0 100 100">
                                <circle 
                                    cx="50" cy="50" r="40" 
                                    stroke="#1E293B" 
                                    stroke-width="8" 
                                    fill="none" 
                                />
                                
                                <!-- Progress Circle with Gradient -->
                                <circle 
                                    cx="50" cy="50" r="40" 
                                    stroke="url(#blue-gradient)" 
                                    stroke-width="8" 
                                    fill="none" 
                                    stroke-linecap="round"
                                    stroke-dasharray="<?php echo 2 * M_PI * 40; ?>" 
                                    stroke-dashoffset="<?php echo 2 * M_PI * 40 * (1 - $progress / 100); ?>" 
                                    transform="rotate(-90 50 50)"
                                />
                                
                                <!-- Define the gradient -->
                                <defs>
                                    <linearGradient id="blue-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#3B82F6" />
                                        <stop offset="100%" stop-color="#60A5FA" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            
                            <!-- Center Text -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-3xl font-bold text-white"><?php echo round($progress); ?>%</span>
                                <span class="text-xs text-gray-400">Complete</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-gray-400 text-sm">
                            <span class="text-blue-400 font-semibold"><?php echo $takenVaccines; ?></span> of 
                            <span class="text-blue-400 font-semibold"><?php echo $totalVaccineTypes; ?></span> vaccines completed
                        </p>
                    </div>
                </div>

                <!-- Record and Schedule Cards -->
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Record Vaccine Card -->
                        <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-green-500/20 rounded-xl p-6 transform transition-all duration-300 hover:shadow-xl hover:shadow-green-500/5">
                            <h2 class="text-xl font-bold text-white flex items-center mb-6">
                                <svg class="w-6 h-6 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Record Vaccine
                            </h2>
                            
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Select Vaccine</label>
                                    <div class="relative">
                                        <select name="vaccine_name" required 
                                                class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                       shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 focus:border-green-500/50
                                                       transition-colors appearance-none">
                                            <option value="">-- Select Vaccine --</option>
                                            <?php foreach ($vaccineOptions as $vaccine): ?>
                                                <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                                    <?php echo htmlspecialchars($vaccine); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <button type="submit" name="record_vaccine" 
                                            class="w-full flex items-center justify-center space-x-2 px-4 py-3 
                                                   bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700
                                                   text-white font-medium rounded-lg transition-all duration-200 
                                                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>Record as Taken</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Schedule Vaccine Card -->
                        <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-blue-500/20 rounded-xl p-6 transform transition-all duration-300 hover:shadow-xl hover:shadow-blue-500/5">
                            <h2 class="text-xl font-bold text-white flex items-center mb-6">
                                <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Schedule Vaccine
                            </h2>
                            
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Select Vaccine</label>
                                    <div class="relative">
                                        <select name="vaccine_name" required 
                                                class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                       shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                                       transition-colors appearance-none">
                                            <option value="">-- Select Vaccine --</option>
                                            <?php foreach ($vaccineOptions as $vaccine): ?>
                                                <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                                    <?php echo htmlspecialchars($vaccine); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Date</label>
                                        <input type="date" name="scheduled_date" required min="<?php echo date('Y-m-d'); ?>"
                                               class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                      shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                                      transition-colors">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Time</label>
                                        <input type="time" name="scheduled_time" required
                                               class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                      shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                                      transition-colors">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Notes (Optional)</label>
                                    <textarea name="notes" rows="2" 
                                              class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                     shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                                     transition-colors"></textarea>
                                </div>
                                
                                <div>
                                    <button type="submit" name="schedule_vaccine" 
                                            class="w-full flex items-center justify-center space-x-2 px-4 py-3 
                                                   bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700
                                                   text-white font-medium rounded-lg transition-all duration-200 
                                                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <span>Schedule Appointment</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vaccination History -->
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-700 rounded-xl p-6 transform transition-all duration-300 hover:shadow-xl hover:shadow-gray-700/5">
                <h2 class="text-xl font-bold text-white flex items-center mb-6">
                    <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Vaccination History
                </h2>
                
                <div class="overflow-x-auto max-h-[500px] scrollbar-thin scrollbar-thumb-gray-600 scrollbar-track-gray-800">
                    <table class="w-full">
                        <thead class="bg-gray-700/50 sticky top-0">
                            <tr>
                                <th class="py-3 px-6 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">
                                    Vaccine Name
                                </th>
                                <th class="py-3 px-6 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">
                                    Status
                                </th>
                                <th class="py-3 px-6 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">
                                    Date
                                </th>
                                <th class="py-3 px-6 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">
                                    Time
                                </th>
                                <th class="py-3 px-6 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">
                                    Notes
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/30">
                            <?php if (count($vaccinations) === 0): ?>
                                <tr>
                                    <td colspan="5" class="py-4 px-6 text-center text-gray-400">
                                        No vaccination records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php foreach ($vaccinations as $vaccination): ?>
                            <tr class="hover:bg-gray-700/20 transition-colors duration-150">
                                <td class="py-4 px-6 text-white font-medium">
                                    <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if ($vaccination['status'] === 'Taken'): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">
                                            Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">
                                            Scheduled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-gray-300">
                                    <?php 
                                        $date = $vaccination['status'] === 'Taken' 
                                              ? $vaccination['date_taken'] 
                                              : $vaccination['scheduled_date'];
                                    echo date('M d, Y', strtotime($date));
                                ?>
                                </td>
                                <td class="py-4 px-6 text-gray-300">
                                    <?php echo $vaccination['formatted_time'] ?? '-'; ?>
                                </td>
                                <td class="py-4 px-6 text-gray-300">
                                    <?php echo !empty($vaccination['notes']) ? htmlspecialchars($vaccination['notes']) : '-'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <?php if (isset($_GET['success'])): ?>
    <div id="notification" 
         class="fixed top-24 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg 
                animate__animated animate__fadeInRight flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span><?php echo $message; ?></span>
    </div>

    <script>
        // Notification functionality
        const notification = document.getElementById('notification');
        if (notification) {
            // Remove the success parameter from the URL
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            history.replaceState(null, '', url);

            // Hide the notification after 3 seconds
            setTimeout(() => {
                notification.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
                setTimeout(() => {
                    notification.remove();
                }, 1000);
            }, 3000);
        }
    </script>
    <?php endif; ?>

    <!-- Scripts -->
    <script>
        // Edit Modal Functionality
        const editModal = document.getElementById('editModal');
        const showEditModal = document.getElementById('showEditModal');
        const closeEditModal = document.getElementById('closeEditModal');
        const modalBackdrop = document.getElementById('modalBackdrop');

        function toggleModal(show) {
            editModal.classList.toggle('hidden', !show);
        }

        showEditModal.addEventListener('click', () => toggleModal(true));
        closeEditModal.addEventListener('click', () => toggleModal(false));
        modalBackdrop.addEventListener('click', () => toggleModal(false));

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') toggleModal(false);
        });

        function togglePersonalEdit() {
            const viewMode = document.getElementById('personal-view');
            const editMode = document.getElementById('personal-edit');
            viewMode.classList.toggle('hidden');
            editMode.classList.toggle('hidden');
        }

        function toggleGuardianEdit() {
            const viewMode = document.getElementById('guardian-view');
            const editMode = document.getElementById('guardian-edit');
            viewMode.classList.toggle('hidden');
            editMode.classList.toggle('hidden');
        }

        function toggleMedicalEdit() {
            const viewMode = document.getElementById('medical-view');
            const editMode = document.getElementById('medical-edit');
            viewMode.classList.toggle('hidden');
            editMode.classList.toggle('hidden');
        }
    </script>
</body>
</html>