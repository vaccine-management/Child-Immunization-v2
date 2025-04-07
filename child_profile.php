<?php

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Nurse') {
    header('Location: index.php');
    exit();
}

// Add this at the top of your file after session_start() to enable better error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include 'backend/db.php';
// Include the schedule next vaccine function
include 'schedule_next_vaccine.php';

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

// Fetch vaccination records
$stmt = $conn->prepare("
    SELECT 
        v.child_id,
        v.vaccine_id,
        v.vaccine_name,
        v.dose_number,
        v.scheduled_date,
        v.administered_date,
        v.status,
        v.notes,
        a.scheduled_time,
        vac.target_disease,
        vac.administration_method,
        vac.dosage,
        u.username as administered_by_name
    FROM vaccinations v
    LEFT JOIN vaccines vac ON v.vaccine_id = vac.id
    LEFT JOIN appointments a ON v.child_id = a.child_id AND v.scheduled_date = a.scheduled_date
    LEFT JOIN users u ON v.administered_by = u.id
    WHERE v.child_id = :child_id 
    ORDER BY 
        CASE v.status 
            WHEN 'Administered' THEN 1 
            WHEN 'Scheduled' THEN 2 
            WHEN 'Missed' THEN 3 
            ELSE 4 
        END,
        v.scheduled_date ASC,
        v.administered_date DESC
");
$stmt->bindParam(':child_id', $childId);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group vaccinations by status for easier display
$administeredVaccines = [];
$scheduledVaccines = [];
$missedVaccines = [];
$allVaccines = [];

// Also track which doses have been taken for each vaccine
$takenVaccines = [];

foreach ($vaccinations as $vaccination) {
    $allVaccines[] = $vaccination;
    
    if ($vaccination['status'] === 'Administered') {
        $administeredVaccines[] = $vaccination;
        
        // Track which doses have been taken
        if (!isset($takenVaccines[$vaccination['vaccine_name']])) {
            $takenVaccines[$vaccination['vaccine_name']] = [];
        }
        $takenVaccines[$vaccination['vaccine_name']][] = $vaccination['dose_number'];
    } 
    elseif ($vaccination['status'] === 'Scheduled') {
        $scheduledVaccines[] = $vaccination;
    }
    elseif ($vaccination['status'] === 'Missed') {
        $missedVaccines[] = $vaccination;
    }
}

// Calculate vaccination progress
$totalVaccines = count($allVaccines);
$completedVaccines = count($administeredVaccines);
$progressPercentage = $totalVaccines > 0 ? round(($completedVaccines / $totalVaccines) * 100) : 0;

// Get available vaccines for dropdown
$stmt = $conn->prepare("SELECT id, name FROM vaccines ORDER BY name");
$stmt->execute();
$availableVaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
$debug_log = ["Debug log started at " . date('Y-m-d H:i:s')];

// Handle recording a vaccine
if (isset($_POST['record_vaccine'])) {
    $vaccineName = trim($_POST['vaccine_name']);
    $doseNumber = isset($_POST['dose_number']) ? (int)trim($_POST['dose_number']) : 1;
    $dateTaken = $_POST['date_taken'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        // Validate inputs
        if (empty($vaccineName) || empty($dateTaken)) {
            throw new Exception("Vaccine name and date taken are required.");
        }
        
        // Check if the date is valid (not in the future)
        $dateObj = new DateTime($dateTaken);
        $today = new DateTime();
        if ($dateObj > $today) {
            throw new Exception("Cannot record a vaccine with a future date.");
        }
        
        // Check if this vaccine is scheduled and if it's before the scheduled date
        $stmt = $conn->prepare("
            SELECT scheduled_date 
            FROM vaccinations 
            WHERE child_id = :child_id 
            AND vaccine_name = :vaccine_name 
            AND dose_number = :dose_number 
            AND status = 'Scheduled'
        ");
        $stmt->execute([
            ':child_id' => $childId,
            ':vaccine_name' => $vaccineName,
            ':dose_number' => $doseNumber
        ]);
        $scheduledVaccine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($scheduledVaccine) {
            $scheduledDate = new DateTime($scheduledVaccine['scheduled_date']);
            $today = new DateTime($dateTaken);
            
            // Compare dates without time
            $scheduledDate->setTime(0, 0);
            $today->setTime(0, 0);
            
            if ($today < $scheduledDate) {
                throw new Exception("This vaccine is scheduled for " . $scheduledDate->format('Y-m-d') . ". Cannot record it before the scheduled date.");
            }
        }
        
        // Look up the max doses for this vaccine
        $stmt = $conn->prepare("SELECT max_doses FROM vaccines WHERE name = :vaccine_name");
        $stmt->execute([':vaccine_name' => $vaccineName]);
        $maxDoses = $stmt->fetchColumn();
        
        if ($doseNumber < 1 || $doseNumber > $maxDoses) {
            throw new Exception("Invalid dose number. This vaccine requires doses between 1 and $maxDoses.");
        }
        
        // Check if this specific dose has already been recorded
        if (isset($takenVaccines[$vaccineName]) && in_array($doseNumber, $takenVaccines[$vaccineName])) {
            throw new Exception("Dose $doseNumber for $vaccineName has already been recorded.");
        }
        
        // Start a transaction
        $conn->beginTransaction();
        $debug_log[] = "Transaction started";
        
        // Check if the vaccine is already scheduled
        $stmt = $conn->prepare("
            SELECT v.child_id, vac.name as vaccine_name, v.dose_number, v.scheduled_date
            FROM vaccinations v
            JOIN vaccines vac ON v.vaccine_id = vac.id
            WHERE v.child_id = :child_id 
            AND vac.name = :vaccine_name 
            AND v.dose_number = :dose_number 
            AND v.status = 'Scheduled'
        ");
        $stmt->execute([
            ':child_id' => $childId,
            ':vaccine_name' => $vaccineName,
            ':dose_number' => $doseNumber
        ]);
        $scheduledVaccine = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug_log[] = "Checked for scheduled vaccine: " . ($scheduledVaccine ? "Found: {$scheduledVaccine['vaccine_name']} Dose {$scheduledVaccine['dose_number']}" : "Not found");

        if ($scheduledVaccine) {
            // Update the scheduled vaccine to "Administered" using the composite key
            $stmt = $conn->prepare("UPDATE vaccinations 
                                   SET administered_date = :administered_date, 
                                       status = 'Administered',
                                       notes = :notes,
                                       vaccine_id = (SELECT id FROM vaccines WHERE name = :vaccine_name),
                                       administered_by = :administered_by
                                   WHERE child_id = :child_id
                                   AND vaccine_name = :vaccine_name
                                   AND dose_number = :dose_number");
            $result = $stmt->execute([
                ':administered_date' => $dateTaken,
                ':notes' => $notes,
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber,
                ':administered_by' => $_SESSION['user']['id']
            ]);
            $debug_log[] = "Updated vaccination status: " . ($result ? "Success" : "Failed");
            
            // Find the appointment associated with this vaccine
            $appointmentDate = $scheduledVaccine['scheduled_date'];
            
            // Find appointment_id with only the date
            $stmt = $conn->prepare("
                SELECT av.id, av.appointment_id 
                FROM appointment_vaccines av
                JOIN appointments a ON a.id = av.appointment_id
                JOIN vaccines vac ON av.vaccine_id = vac.id
                WHERE a.child_id = :child_id 
                AND vac.name = :vaccine_name 
                AND av.dose_number = :dose_number
                AND a.scheduled_date = :scheduled_date
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber,
                ':scheduled_date' => $appointmentDate
            ]);
            $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
            $debug_log[] = "Checked for appointment with date only: " . ($appointmentData ? "Found ID: {$appointmentData['appointment_id']}" : "Not found");
            
            if ($appointmentData) {
                $appointmentId = $appointmentData['appointment_id'];
                
                // Update appointment_vaccines status
                $stmt = $conn->prepare("
                    UPDATE appointment_vaccines 
                    SET status = 'administered' 
                    WHERE appointment_id = :appointment_id 
                    AND vaccine_id = (SELECT id FROM vaccines WHERE name = :vaccine_name)
                    AND dose_number = :dose_number
                ");
                $result = $stmt->execute([
                    ':appointment_id' => $appointmentId,
                    ':vaccine_name' => $vaccineName,
                    ':dose_number' => $doseNumber
                ]);
                $debug_log[] = "Updated appointment_vaccines: " . ($result ? "Success" : "Failed");
                
                // Check all vaccines for this appointment
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'administered' THEN 1 ELSE 0 END) as completed 
                    FROM appointment_vaccines 
                    WHERE appointment_id = :appointment_id
                ");
                $stmt->execute([':appointment_id' => $appointmentId]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                $debug_log[] = "Checked appointment completion: Total: {$counts['total']}, Completed: {$counts['completed']}";
                
                // Update appointment status - use values valid in the appointments table
                $appointmentStatus = 'scheduled';
                if ($counts['completed'] > 0) {
                    if ($counts['completed'] == $counts['total']) {
                        $appointmentStatus = 'completed';
                    } else {
                        $appointmentStatus = 'scheduled';  // Changed from partially_completed to a valid ENUM value
                    }
                }
                $debug_log[] = "Setting appointment status to: $appointmentStatus";
                
                // Update the appointment status (removed actual_date)
                $stmt = $conn->prepare("
                    UPDATE appointments 
                    SET status = :status
                    WHERE id = :appointment_id
                ");
                $result = $stmt->execute([
                    ':status' => $appointmentStatus,
                    ':appointment_id' => $appointmentId
                ]);
                $debug_log[] = "Updated appointment: " . ($result ? "Success" : "Failed");
            }
        } else {
            // For unscheduled vaccines, create a new appointment (without scheduled_time and actual_date)
            $debug_log[] = "Creating new walk-in appointment";
            $stmt = $conn->prepare("
                INSERT INTO appointments (
                    child_id, scheduled_date, status, notes
                ) VALUES (
                    :child_id, :scheduled_date, 'completed', :notes
                )
            ");
            
            $result = $stmt->execute([
                ':child_id' => $childId,
                ':scheduled_date' => $dateTaken,
                ':notes' => "Walk-in vaccination: $vaccineName (Dose $doseNumber)"
            ]);
            $debug_log[] = "Created appointment: " . ($result ? "Success" : "Failed");
            
            $appointmentId = $conn->lastInsertId();
            $debug_log[] = "New appointment ID: $appointmentId";
            
            // Insert record for the vaccine as "Administered"
            $stmt = $conn->prepare("
                INSERT INTO vaccinations (
                    child_id, 
                    vaccine_id, 
                    vaccine_name, 
                    dose_number, 
                    administered_date, 
                    administered_by,
                    status, 
                    notes
                ) VALUES (
                    :child_id, 
                    (SELECT id FROM vaccines WHERE name = :vaccine_name), 
                    :vaccine_name, 
                    :dose_number, 
                    :administered_date, 
                    :administered_by,
                    'Administered', 
                    :notes
                )
            ");
            $result = $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber,
                ':administered_date' => $dateTaken,
                ':administered_by' => $_SESSION['user']['id'],
                ':notes' => $notes
            ]);
            $debug_log[] = "Created vaccination record: " . ($result ? "Success" : "Failed");
            
            // Link to appointment_vaccines
            $stmt = $conn->prepare("
                INSERT INTO appointment_vaccines (
                    appointment_id, vaccine_id, vaccine_name, dose_number, status
                ) VALUES (
                    :appointment_id, 
                    (SELECT id FROM vaccines WHERE name = :vaccine_name),
                    :vaccine_name, 
                    :dose_number, 
                    'administered'
                )
            ");
            
            $result = $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber
            ]);
            $debug_log[] = "Created appointment_vaccines link: " . ($result ? "Success" : "Failed");
        }
        
        // Schedule the next vaccine automatically
        $nextVaccine = scheduleNextVaccine($conn, $childId, $child['date_of_birth'], $vaccineName, $doseNumber);
        if ($nextVaccine) {
            $debug_log[] = "Automatically scheduled next vaccine: {$nextVaccine['vaccine_name']} (Dose {$nextVaccine['dose_number']}) for {$nextVaccine['scheduled_date']}";
        } else {
            $debug_log[] = "No next vaccine to schedule";
        }
        
        // Commit the transaction
        $conn->commit();
        $debug_log[] = "Transaction committed successfully";
        
        // Update vaccine inventory quantity
        try {
            // Get the vaccine ID
            $stmt = $conn->prepare("SELECT id FROM vaccines WHERE name = :vaccine_name");
            $stmt->execute([':vaccine_name' => $vaccineName]);
            $vaccineId = $stmt->fetchColumn();
            
            if ($vaccineId) {
                // Decrement the quantity in vaccines table
                $stmt = $conn->prepare("
                    UPDATE vaccines 
                    SET quantity = GREATEST(quantity - 1, 0) 
                    WHERE id = :vaccine_id
                ");
                $stmt->execute([':vaccine_id' => $vaccineId]);
                $debug_log[] = "Updated vaccine quantity for ID: $vaccineId";
            }
        } catch (Exception $e) {
            // Log error but don't stop the process
            error_log("Warning: Failed to update vaccine quantity: " . $e->getMessage());
            $debug_log[] = "Warning: Failed to update vaccine quantity: " . $e->getMessage();
        }
        
        // Refresh the page data
        header("Location: child_profile.php?id=$childId&success=1&vaccine=$vaccineName&dose=$doseNumber");
        exit();
    } 
    catch (Exception $e) {
        // Roll back the transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
            $debug_log[] = "Transaction rolled back";
        }
        $error = "Failed to record vaccine: " . $e->getMessage();
        $debug_log[] = "Error: " . $e->getMessage();
        
        // Log debugging info to a file for easier troubleshooting
        file_put_contents('vaccine_debug.log', date('Y-m-d H:i:s') . " - " . implode("\n", $debug_log) . "\n\n", FILE_APPEND);
    }
}

// Handle scheduling a vaccine for missed appointments
if (isset($_POST['schedule_vaccine'])) {
    $vaccineName = trim($_POST['vaccine_name']);
    $doseNumber = isset($_POST['dose_number']) ? (int)trim($_POST['dose_number']) : 1;
    $scheduledDate = $_POST['scheduled_date'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Get vaccine_id from vaccine name
        $stmt = $conn->prepare("SELECT id FROM vaccines WHERE name = :name");
        $stmt->execute([':name' => $vaccineName]);
        $vaccineData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vaccineData) {
            throw new Exception("Vaccine not found: $vaccineName");
        }
        
        $vaccineId = $vaccineData['id'];
        
        // Check if this vaccine dose has already been administered
        $stmt = $conn->prepare("
            SELECT status 
            FROM vaccinations 
            WHERE child_id = :child_id 
            AND vaccine_id = :vaccine_id 
            AND dose_number = :dose_number
        ");
        $stmt->execute([
            ':child_id' => $childId,
            ':vaccine_id' => $vaccineId,
            ':dose_number' => $doseNumber
        ]);
        $existingVaccine = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingVaccine) {
            if ($existingVaccine['status'] === 'Administered') {
                throw new Exception("This dose has already been administered and cannot be scheduled again.");
            }
            
            // If it's already scheduled or missed, update it instead of creating a new one
            $stmt = $conn->prepare("
                UPDATE vaccinations
                SET scheduled_date = :scheduled_date,
                    status = 'Scheduled',
                    notes = :notes
                WHERE child_id = :child_id
                AND vaccine_id = :vaccine_id
                AND dose_number = :dose_number
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_id' => $vaccineId,
                ':dose_number' => $doseNumber,
                ':scheduled_date' => $scheduledDate,
                ':notes' => $notes
            ]);
            
            // Check if there's an existing appointment for this vaccine
            $stmt = $conn->prepare("
                SELECT av.appointment_id
                FROM appointment_vaccines av
                JOIN appointments a ON av.appointment_id = a.id
                WHERE a.child_id = :child_id
                AND av.vaccine_id = :vaccine_id
                AND av.dose_number = :dose_number
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_id' => $vaccineId,
                ':dose_number' => $doseNumber
            ]);
            $existingAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingAppointment) {
                // Update existing appointment
                $stmt = $conn->prepare("
                    UPDATE appointments
                    SET scheduled_date = :scheduled_date,
                        status = 'scheduled',
                        notes = :notes
                    WHERE id = :appointment_id
                ");
                $stmt->execute([
                    ':appointment_id' => $existingAppointment['appointment_id'],
                    ':scheduled_date' => $scheduledDate,
                    ':notes' => "Rescheduled vaccination: $vaccineName (Dose $doseNumber)"
                ]);
                
                // Update appointment_vaccines status
                $stmt = $conn->prepare("
                    UPDATE appointment_vaccines
                    SET status = 'scheduled'
                    WHERE appointment_id = :appointment_id
                    AND vaccine_id = :vaccine_id
                    AND dose_number = :dose_number
                ");
                $stmt->execute([
                    ':appointment_id' => $existingAppointment['appointment_id'],
                    ':vaccine_id' => $vaccineId,
                    ':dose_number' => $doseNumber
                ]);
                
                $appointmentId = $existingAppointment['appointment_id'];
            } else {
                // Create a new appointment
                $stmt = $conn->prepare("
                    INSERT INTO appointments (
                        child_id, scheduled_date, status, notes
                    ) VALUES (
                        :child_id, :scheduled_date, 'scheduled', :notes
                    )
                ");
                $stmt->execute([
                    ':child_id' => $childId,
                    ':scheduled_date' => $scheduledDate,
                    ':notes' => "Scheduled vaccination: $vaccineName (Dose $doseNumber)"
                ]);
                $appointmentId = $conn->lastInsertId();
                
                // Link vaccine to appointment
                $stmt = $conn->prepare("
                    INSERT INTO appointment_vaccines (
                        appointment_id, vaccine_id, vaccine_name, dose_number, status
                    ) VALUES (
                        :appointment_id, :vaccine_id, :vaccine_name, :dose_number, 'scheduled'
                    )
                ");
                $stmt->execute([
                    ':appointment_id' => $appointmentId,
                    ':vaccine_id' => $vaccineId,
                    ':vaccine_name' => $vaccineName,
                    ':dose_number' => $doseNumber
                ]);
            }
        } else {
            // Create the appointment first
            $stmt = $conn->prepare("
                INSERT INTO appointments (
                    child_id, scheduled_date, status, notes
                ) VALUES (
                    :child_id, :scheduled_date, 'scheduled', :notes
                )
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':scheduled_date' => $scheduledDate,
                ':notes' => "Scheduled vaccination: $vaccineName (Dose $doseNumber)"
            ]);
            $appointmentId = $conn->lastInsertId();
            
            // Create the vaccination record with appointment_id
            $stmt = $conn->prepare("
                INSERT INTO vaccinations (
                    child_id, vaccine_id, vaccine_name, dose_number, scheduled_date, status, notes
                ) VALUES (
                    :child_id, :vaccine_id, :vaccine_name, :dose_number, :scheduled_date, 'Scheduled', :notes
                )
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_id' => $vaccineId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber,
                ':scheduled_date' => $scheduledDate,
                ':notes' => $notes
            ]);
            
            // Link vaccine to appointment
            $stmt = $conn->prepare("
                INSERT INTO appointment_vaccines (
                    appointment_id, vaccine_id, vaccine_name, dose_number, status
                ) VALUES (
                    :appointment_id, :vaccine_id, :vaccine_name, :dose_number, 'scheduled'
                )
            ");
            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':vaccine_id' => $vaccineId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber
            ]);
        }
        
        // Commit transaction
        $conn->commit();
        $successMessage = "Vaccination scheduled successfully.";
        
        // Refresh the page data
        header("Location: child_profile.php?id=$childId&schedule_success=1&vaccine=$vaccineName&dose=$doseNumber");
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $errorMessage = $e->getMessage();
    }
}

// Handle child information updates
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
    
    try {
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
        
        $successMessage = "Child information updated successfully.";
        
        // Refresh child data
        $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = :child_id");
        $stmt->bindParam(':child_id', $childId);
        $stmt->execute();
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $errorMessage = "Failed to update child information: " . $e->getMessage();
    }
}

// Set page title
$pageTitle = "Child Profile: " . $child['full_name'];

// Check for success message from URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $vaccineName = isset($_GET['vaccine']) ? $_GET['vaccine'] : 'Vaccine';
    $doseNumber = isset($_GET['dose']) ? $_GET['dose'] : '';
    $successMessage = "$vaccineName" . ($doseNumber ? " (Dose $doseNumber)" : "") . " recorded successfully.";
}

if (isset($_GET['schedule_success']) && $_GET['schedule_success'] == 1) {
    $vaccineName = isset($_GET['vaccine']) ? $_GET['vaccine'] : 'Vaccine';
    $doseNumber = isset($_GET['dose']) ? $_GET['dose'] : '';
    $successMessage = "$vaccineName" . ($doseNumber ? " (Dose $doseNumber)" : "") . " scheduled successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Immunization System</title>
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
                    <div class="bg-blue-600 p-3 rounded-lg mr-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-semibold text-white"><?php echo htmlspecialchars($child['full_name']); ?></h2>
                        <p class="text-gray-400">ID: <?php echo htmlspecialchars($child['child_id']); ?></p>
                    </div>
                </div>
                <div class="mt-4 md:mt-0 flex space-x-2">
                    <a href="edit_child.php?id=<?php echo urlencode($child['child_id']); ?>" 
                       class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg 
                              shadow-lg transition duration-300 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        Edit Profile
                    </a>
                    <a href="children.php" 
                       class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg 
                              shadow-lg transition duration-300 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($successMessage)): ?>
                <div class="bg-green-900 text-green-100 p-4 rounded-lg mb-6 animate__animated animate__fadeIn">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $successMessage; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-900 text-red-100 p-4 rounded-lg mb-6 animate__animated animate__fadeIn">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $errorMessage; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Child Information Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Personal Information -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-700">
                        <h3 class="text-lg font-semibold text-white">Personal Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-400">Full Name</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['full_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Date of Birth</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['date_of_birth']); ?> (<?php echo $ageString; ?>)</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Gender</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['gender']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Birth Weight</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['birth_weight']); ?> kg</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Place of Birth</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['place_of_birth']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guardian Information -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-700">
                        <h3 class="text-lg font-semibold text-white">Guardian Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-400">Guardian Name</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['guardian_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Phone Number</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['phone']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Email Address</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['email'] ?: 'Not provided'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Home Address</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['address'] ?: 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-700">
                        <h3 class="text-lg font-semibold text-white">Medical Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-400">Birth Complications</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['birth_complications'] ?: 'None reported'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Known Allergies</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['allergies'] ?: 'None reported'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Previous Vaccinations</p>
                                <p class="text-white"><?php echo htmlspecialchars($child['previous_vaccinations'] ?: 'None reported'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vaccination Progress -->
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-700">
                    <h3 class="text-lg font-semibold text-white">Vaccination Progress</h3>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <div class="flex justify-between mb-1">
                            <span class="text-white"><?php echo $completedVaccines; ?> of <?php echo $totalVaccines; ?> vaccines administered</span>
                            <span class="text-white"><?php echo $progressPercentage; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $progressPercentage; ?>%"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div class="bg-blue-900/30 p-4 rounded-lg">
                            <p class="text-2xl font-bold text-blue-400"><?php echo count($allVaccines); ?></p>
                            <p class="text-gray-400">Total Vaccines</p>
                        </div>
                        <div class="bg-green-900/30 p-4 rounded-lg">
                            <p class="text-2xl font-bold text-green-400"><?php echo count($administeredVaccines); ?></p>
                            <p class="text-gray-400">Administered</p>
                        </div>
                        <div class="bg-yellow-900/30 p-4 rounded-lg">
                            <p class="text-2xl font-bold text-yellow-400"><?php echo count($scheduledVaccines); ?></p>
                            <p class="text-gray-400">Scheduled</p>
                        </div>
                        <div class="bg-red-900/30 p-4 rounded-lg">
                            <p class="text-2xl font-bold text-red-400"><?php echo count($missedVaccines); ?></p>
                            <p class="text-gray-400">Missed</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vaccination History -->
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-white">Vaccination History</h3>
                    <div class="flex space-x-2">
                        <button id="recordVaccineBtn" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg 
                                                 shadow-lg transition duration-300 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Record Vaccine
                        </button>
                        <button id="scheduleVaccineBtn" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg 
                                                   shadow-lg transition duration-300 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Schedule Vaccine
                        </button>
                    </div>
                </div>

                <!-- Record Vaccine Form (Initially Hidden) -->
                <div id="recordVaccineForm" class="p-6 border-b border-gray-700 hidden">
                    <h4 class="text-lg font-medium text-white mb-4">Record Administered Vaccine</h4>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="vaccine_name" class="block text-gray-300 text-sm font-medium mb-2">Vaccine Name</label>
                                <select id="vaccine_name" name="vaccine_name" required
                                        class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                               focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="">Select Vaccine</option>
                                    <?php foreach ($availableVaccines as $vaccine): ?>
                                        <option value="<?php echo htmlspecialchars($vaccine['name']); ?>">
                                            <?php echo htmlspecialchars($vaccine['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="dose_number" class="block text-gray-300 text-sm font-medium mb-2">Dose Number</label>
                                <input type="number" id="dose_number" name="dose_number" min="1" value="1" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div>
                                <label for="date_taken" class="block text-gray-300 text-sm font-medium mb-2">Date Administered</label>
                                <input type="date" id="date_taken" name="date_taken" required
                                       value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div>
                                <label for="notes" class="block text-gray-300 text-sm font-medium mb-2">Notes</label>
                                <textarea id="notes" name="notes" rows="1"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" id="cancelRecordBtn" 
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg 
                                           shadow-lg transition duration-300">
                                Cancel
                            </button>
                            <button type="submit" name="record_vaccine" 
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg 
                                           shadow-lg transition duration-300 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M5 13l4 4L19 7"></path>
                                </svg>
                                Record Vaccine
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Schedule Vaccine Form (Initially Hidden) -->
                <div id="scheduleVaccineForm" class="p-6 border-b border-gray-700 hidden">
                    <h4 class="text-lg font-medium text-white mb-4">Schedule Vaccine</h4>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="schedule_vaccine_name" class="block text-gray-300 text-sm font-medium mb-2">Vaccine Name</label>
                                <select id="schedule_vaccine_name" name="vaccine_name" required
                                        class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                               focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="">Select Vaccine</option>
                                    <?php foreach ($availableVaccines as $vaccine): ?>
                                        <option value="<?php echo htmlspecialchars($vaccine['name']); ?>">
                                            <?php echo htmlspecialchars($vaccine['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="schedule_dose_number" class="block text-gray-300 text-sm font-medium mb-2">Dose Number</label>
                                <input type="number" id="schedule_dose_number" name="dose_number" min="1" value="1" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div>
                                <label for="scheduled_date" class="block text-gray-300 text-sm font-medium mb-2">Scheduled Date</label>
                                <input type="date" id="scheduled_date" name="scheduled_date" required
                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div>
                                <label for="schedule_notes" class="block text-gray-300 text-sm font-medium mb-2">Notes</label>
                                <textarea id="schedule_notes" name="notes" rows="1"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" id="cancelScheduleBtn" 
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg 
                                           shadow-lg transition duration-300">
                                Cancel
                            </button>
                            <button type="submit" name="schedule_vaccine" 
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg 
                                           shadow-lg transition duration-300 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Schedule Vaccine
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Vaccination Tabs -->
                <div class="border-b border-gray-700">
                    <nav class="flex flex-wrap">
                        <button class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 border-blue-500 text-white" 
                                data-tab="all-vaccines">All</button>
                        <button class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 border-transparent" 
                                data-tab="administered-vaccines">Administered</button>
                        <button class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 border-transparent" 
                                data-tab="scheduled-vaccines">Scheduled</button>
                        <button class="tab-link px-6 py-3 text-gray-300 hover:text-white border-b-2 border-transparent" 
                                data-tab="missed-vaccines">Missed</button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- All Vaccines Tab -->
                    <div id="all-vaccines" class="tab-pane block">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-300">
                                <thead class="text-xs uppercase bg-gray-700 text-gray-300">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Vaccine</th>
                                        <th scope="col" class="px-6 py-3">Dose</th>
                                        <th scope="col" class="px-6 py-3">Scheduled Date</th>
                                        <th scope="col" class="px-6 py-3">Administered Date</th>
                                        <th scope="col" class="px-6 py-3">Status</th>
                                        <th scope="col" class="px-6 py-3">Administered By</th>
                                        <th scope="col" class="px-6 py-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($allVaccines)): ?>
                                        <tr class="border-b border-gray-700">
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-400">No vaccination records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($allVaccines as $vaccine): ?>
                                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['dose_number']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['scheduled_date'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['administered_date'] ?? 'Not yet administered'); ?></td>
                                                <td class="px-6 py-4">
                                                    <?php if ($vaccine['status'] === 'Administered'): ?>
                                                        <span class="px-2 py-1 text-xs rounded-full bg-green-500/10 text-green-400">Administered</span>
                                                    <?php elseif ($vaccine['status'] === 'Scheduled'): ?>
                                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-500/10 text-blue-400">Scheduled</span>
                                                    <?php elseif ($vaccine['status'] === 'Missed'): ?>
                                                        <span class="px-2 py-1 text-xs rounded-full bg-red-500/10 text-red-400">Missed</span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-500/10 text-gray-400"><?php echo htmlspecialchars($vaccine['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['administered_by_name'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['notes'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Administered Vaccines Tab -->
                    <div id="administered-vaccines" class="tab-pane hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-300">
                                <thead class="text-xs uppercase bg-gray-700 text-gray-300">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Vaccine</th>
                                        <th scope="col" class="px-6 py-3">Dose</th>
                                        <th scope="col" class="px-6 py-3">Administered Date</th>
                                        <th scope="col" class="px-6 py-3">Administered By</th>
                                        <th scope="col" class="px-6 py-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($administeredVaccines)): ?>
                                        <tr class="border-b border-gray-700">
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-400">No administered vaccines found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($administeredVaccines as $vaccine): ?>
                                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['dose_number']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['administered_date']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['administered_by_name'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['notes'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Scheduled Vaccines Tab -->
                    <div id="scheduled-vaccines" class="tab-pane hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-300">
                                <thead class="text-xs uppercase bg-gray-700 text-gray-300">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Vaccine</th>
                                        <th scope="col" class="px-6 py-3">Dose</th>
                                        <th scope="col" class="px-6 py-3">Scheduled Date</th>
                                        <th scope="col" class="px-6 py-3">Notes</th>
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($scheduledVaccines)): ?>
                                        <tr class="border-b border-gray-700">
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-400">No scheduled vaccines found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($scheduledVaccines as $vaccine): ?>
                                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['dose_number']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['scheduled_date']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['notes'] ?? ''); ?></td>
                                                <td class="px-6 py-4">
                                                    <button class="record-scheduled-btn text-green-400 hover:text-green-300 transition-colors"
                                                            data-vaccine="<?php echo htmlspecialchars($vaccine['vaccine_name']); ?>"
                                                            data-dose="<?php echo htmlspecialchars($vaccine['dose_number']); ?>"
                                                            data-date="<?php echo htmlspecialchars($vaccine['scheduled_date']); ?>">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                  d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Missed Vaccines Tab -->
                    <div id="missed-vaccines" class="tab-pane hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-300">
                                <thead class="text-xs uppercase bg-gray-700 text-gray-300">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Vaccine</th>
                                        <th scope="col" class="px-6 py-3">Dose</th>
                                        <th scope="col" class="px-6 py-3">Scheduled Date</th>
                                        <th scope="col" class="px-6 py-3">Notes</th>
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($missedVaccines)): ?>
                                        <tr class="border-b border-gray-700">
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-400">No missed vaccines found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($missedVaccines as $vaccine): ?>
                                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['dose_number']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['scheduled_date']); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($vaccine['notes'] ?? ''); ?></td>
                                                <td class="px-6 py-4 flex space-x-2">
                                                    <button class="reschedule-btn text-blue-400 hover:text-blue-300 transition-colors"
                                                            data-vaccine="<?php echo htmlspecialchars($vaccine['vaccine_name']); ?>"
                                                            data-dose="<?php echo htmlspecialchars($vaccine['dose_number']); ?>">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </button>
                                                    <button class="record-missed-btn text-green-400 hover:text-green-300 transition-colors"
                                                            data-vaccine="<?php echo htmlspecialchars($vaccine['vaccine_name']); ?>"
                                                            data-dose="<?php echo htmlspecialchars($vaccine['dose_number']); ?>">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                  d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabLinks.forEach(tab => {
                        tab.classList.remove('border-blue-500', 'text-white');
                        tab.classList.add('border-transparent');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.remove('border-transparent');
                    this.classList.add('border-blue-500', 'text-white');
                    
                    // Hide all tab panes
                    tabPanes.forEach(pane => {
                        pane.classList.add('hidden');
                    });
                    
                    // Show the corresponding tab pane
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.remove('hidden');
                });
            });
            
            // Record Vaccine Form Toggle
            const recordVaccineBtn = document.getElementById('recordVaccineBtn');
            const recordVaccineForm = document.getElementById('recordVaccineForm');
            const cancelRecordBtn = document.getElementById('cancelRecordBtn');
            
            recordVaccineBtn.addEventListener('click', function() {
                recordVaccineForm.classList.remove('hidden');
                scheduleVaccineForm.classList.add('hidden');
                recordVaccineForm.scrollIntoView({ behavior: 'smooth' });
            });
            
            cancelRecordBtn.addEventListener('click', function() {
                recordVaccineForm.classList.add('hidden');
            });
            
            // Schedule Vaccine Form Toggle
            const scheduleVaccineBtn = document.getElementById('scheduleVaccineBtn');
            const scheduleVaccineForm = document.getElementById('scheduleVaccineForm');
            const cancelScheduleBtn = document.getElementById('cancelScheduleBtn');
            
            scheduleVaccineBtn.addEventListener('click', function() {
                scheduleVaccineForm.classList.remove('hidden');
                recordVaccineForm.classList.add('hidden');
                scheduleVaccineForm.scrollIntoView({ behavior: 'smooth' });
            });
            
            cancelScheduleBtn.addEventListener('click', function() {
                scheduleVaccineForm.classList.add('hidden');
            });
            
            // Record Scheduled Vaccine buttons
            const recordScheduledBtns = document.querySelectorAll('.record-scheduled-btn');
            
            recordScheduledBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const vaccine = this.getAttribute('data-vaccine');
                    const dose = this.getAttribute('data-dose');
                    const date = this.getAttribute('data-date');
                    
                    // Fill in the record form
                    document.getElementById('vaccine_name').value = vaccine;
                    document.getElementById('dose_number').value = dose;
                    document.getElementById('date_taken').value = new Date().toISOString().split('T')[0];
                    
                    // Show the form
                    recordVaccineForm.classList.remove('hidden');
                    scheduleVaccineForm.classList.add('hidden');
                    recordVaccineForm.scrollIntoView({ behavior: 'smooth' });
                });
            });
            
            // Record Missed Vaccine buttons
            const recordMissedBtns = document.querySelectorAll('.record-missed-btn');
            
            recordMissedBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const vaccine = this.getAttribute('data-vaccine');
                    const dose = this.getAttribute('data-dose');
                    
                    // Fill in the record form
                    document.getElementById('vaccine_name').value = vaccine;
                    document.getElementById('dose_number').value = dose;
                    document.getElementById('date_taken').value = new Date().toISOString().split('T')[0];
                    
                    // Show the form
                    recordVaccineForm.classList.remove('hidden');
                    scheduleVaccineForm.classList.add('hidden');
                    recordVaccineForm.scrollIntoView({ behavior: 'smooth' });
                });
            });
            
            // Reschedule buttons
            const rescheduleBtns = document.querySelectorAll('.reschedule-btn');
            
            rescheduleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const vaccine = this.getAttribute('data-vaccine');
                    const dose = this.getAttribute('data-dose');
                    
                    // Fill in the schedule form
                    document.getElementById('schedule_vaccine_name').value = vaccine;
                    document.getElementById('schedule_dose_number').value = dose;
                    
                    // Set date to one week from today
                    const nextWeek = new Date();
                    nextWeek.setDate(nextWeek.getDate() + 7);
                    document.getElementById('scheduled_date').value = nextWeek.toISOString().split('T')[0];
                    
                    // Show the form
                    scheduleVaccineForm.classList.remove('hidden');
                    recordVaccineForm.classList.add('hidden');
                    scheduleVaccineForm.scrollIntoView({ behavior: 'smooth' });
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
