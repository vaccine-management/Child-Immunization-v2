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

// Get vaccine options from the vaccines table
$stmt = $conn->query("SELECT vaccine_name, max_doses, description, target_disease FROM vaccines ORDER BY vaccine_name");
$availableVaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get taken vaccines for this child
$stmt = $conn->prepare("
    SELECT vaccine_name, dose_number 
    FROM vaccinations 
    WHERE child_id = ? AND status = 'Administered'
    ORDER BY vaccine_name, dose_number
");
$stmt->execute([$childId]);
$takenVaccines = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($takenVaccines[$row['vaccine_name']])) {
        $takenVaccines[$row['vaccine_name']] = [];
    }
    $takenVaccines[$row['vaccine_name']][] = $row['dose_number'];
}

// Calculate total vaccines and progress
$totalVaccineTypes = count($availableVaccines);
$uniqueTakenVaccineNames = count(array_keys($takenVaccines));
$progress = ($totalVaccineTypes > 0) ? ($uniqueTakenVaccineNames / $totalVaccineTypes) * 100 : 0;

// Add this at the top of your file for a one-time check of the appointments table structure
try {
    $tableInfo = $conn->query("DESCRIBE appointments")->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents('appointment_table.log', print_r($tableInfo, true), FILE_APPEND);
} catch (Exception $e) {
    file_put_contents('appointment_table.log', "Error getting table info: " . $e->getMessage(), FILE_APPEND);
}

// First, let's add a function to check if a vaccine is fully administered
function isVaccineFullyAdministered($conn, $childId, $vaccineName) {
    // Get the maximum doses for this vaccine from the vaccines table
    $stmt = $conn->prepare("
        SELECT max_doses 
        FROM vaccines 
        WHERE vaccine_name = :vaccine_name
    ");
    $stmt->execute([':vaccine_name' => $vaccineName]);
    $maxDoses = $stmt->fetchColumn();
    
    if (!$maxDoses) {
        // If vaccine not found in vaccines table, try vaccine_schedule
        $stmt = $conn->prepare("
            SELECT MAX(dose_number) as max_doses 
            FROM vaccine_schedule 
            WHERE vaccine_name = :vaccine_name
        ");
        $stmt->execute([':vaccine_name' => $vaccineName]);
        $maxDoses = $stmt->fetchColumn();
    }
    
    // Get the number of administered doses
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM vaccinations 
        WHERE child_id = :child_id 
        AND vaccine_name = :vaccine_name 
        AND status = 'Administered'
    ");
    $stmt->execute([
        ':child_id' => $childId,
        ':vaccine_name' => $vaccineName
    ]);
    $administeredDoses = $stmt->fetchColumn();
    
    return $administeredDoses >= $maxDoses;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $doseNumber = isset($_POST['dose_number']) ? (int)trim($_POST['dose_number']) : 1;
        $dateTaken = date('Y-m-d');
        $notes = trim($_POST['notes'] ?? 'Recorded manually');
        $error = null;
        $debug_log = [];  // For storing debugging info

        try {
            if (empty($vaccineName)) {
                throw new Exception("Vaccine name is required.");
            }
            
            // Check if the vaccine is already fully administered
            if (isVaccineFullyAdministered($conn, $childId, $vaccineName)) {
                throw new Exception("This vaccine has already been fully administered and cannot be recorded again.");
            }

            // Check if this vaccine dose is scheduled and validate the date
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
            $stmt = $conn->prepare("SELECT max_doses FROM vaccines WHERE vaccine_name = :vaccine_name");
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
            $stmt = $conn->prepare("SELECT child_id, vaccine_name, dose_number, scheduled_date, scheduled_time FROM vaccinations 
                                   WHERE child_id = :child_id 
                                   AND vaccine_name = :vaccine_name 
                                   AND dose_number = :dose_number 
                                   AND status = 'Scheduled'");
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
                                           notes = :notes
                                       WHERE child_id = :child_id
                                       AND vaccine_name = :vaccine_name
                                       AND dose_number = :dose_number");
                $result = $stmt->execute([
                    ':administered_date' => $dateTaken,
                    ':notes' => $notes,
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccineName,
                    ':dose_number' => $doseNumber
                ]);
                $debug_log[] = "Updated vaccination status: " . ($result ? "Success" : "Failed");
                
                // Find the appointment associated with this vaccine (date only, no time)
                $appointmentDate = $scheduledVaccine['scheduled_date'];
                
                // Find appointment_id with only the date
                $stmt = $conn->prepare("
                    SELECT av.appointment_id 
                    FROM appointment_vaccines av
                    JOIN appointments a ON a.id = av.appointment_id
                    WHERE a.child_id = :child_id 
                    AND av.vaccine_name = :vaccine_name 
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
                        SET status = 'completed' 
                        WHERE appointment_id = :appointment_id 
                        AND vaccine_name = :vaccine_name 
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
                               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed 
                        FROM appointment_vaccines 
                        WHERE appointment_id = :appointment_id
                    ");
                    $stmt->execute([':appointment_id' => $appointmentId]);
                    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                    $debug_log[] = "Checked appointment completion: Total: {$counts['total']}, Completed: {$counts['completed']}";
                    
                    // Update appointment status - note the correct ENUM value 'partially_completed'
                    $appointmentStatus = 'scheduled';
                    if ($counts['completed'] > 0) {
                        if ($counts['completed'] == $counts['total']) {
                            $appointmentStatus = 'completed';
                        } else {
                            $appointmentStatus = 'partially_completed';
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
                        child_id, vaccine_name, dose_number, administered_date, status, notes
                    ) VALUES (
                        :child_id, :vaccine_name, :dose_number, :administered_date, 'Administered', :notes
                    )
                ");
                $result = $stmt->execute([
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccineName,
                    ':dose_number' => $doseNumber,
                    ':administered_date' => $dateTaken,
                    ':notes' => $notes
                ]);
                $debug_log[] = "Created vaccination record: " . ($result ? "Success" : "Failed");
                
                // Link to appointment_vaccines
                $stmt = $conn->prepare("
                    INSERT INTO appointment_vaccines (
                        appointment_id, vaccine_name, dose_number, status
                    ) VALUES (
                        :appointment_id, :vaccine_name, :dose_number, 'completed'
                    )
                ");
                
                $result = $stmt->execute([
                    ':appointment_id' => $appointmentId,
                    ':vaccine_name' => $vaccineName,
                    ':dose_number' => $doseNumber
                ]);
                $debug_log[] = "Created appointment_vaccines link: " . ($result ? "Success" : "Failed");
            }
            
            // Commit the transaction
            $conn->commit();
            $debug_log[] = "Transaction committed successfully";
            
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

    if (isset($_POST['schedule_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $doseNumber = isset($_POST['dose_number']) ? (int)trim($_POST['dose_number']) : 1;
        $scheduledDate = $_POST['scheduled_date'];
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Check if this vaccine dose has already been administered
            $stmt = $conn->prepare("
                SELECT status 
                FROM vaccinations 
                WHERE child_id = :child_id 
                AND vaccine_name = :vaccine_name 
                AND dose_number = :dose_number
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber
            ]);
            $existingVaccine = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingVaccine) {
                if ($existingVaccine['status'] === 'Administered') {
                    throw new Exception("This dose has already been administered and cannot be scheduled again.");
                }
            }
            
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
            
            // Create the vaccination record
            $stmt = $conn->prepare("
                INSERT INTO vaccinations (
                    child_id, vaccine_name, dose_number, scheduled_date, status, notes
                ) VALUES (
                    :child_id, :vaccine_name, :dose_number, :scheduled_date, 'Scheduled', :notes
                )
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber,
                ':scheduled_date' => $scheduledDate,
                ':notes' => $notes
            ]);
            
            // Link vaccine to appointment
            $stmt = $conn->prepare("
                INSERT INTO appointment_vaccines (
                    appointment_id, vaccine_name, dose_number, status
                ) VALUES (
                    :appointment_id, :vaccine_name, :dose_number, 'scheduled'
                )
            ");
            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber
            ]);
            
            // Update subsequent vaccine schedules
            // Get all scheduled vaccines after this date
            $stmt = $conn->prepare("
                SELECT 
                    v.vaccine_name, 
                    v.dose_number, 
                    v.scheduled_date,
                    vs.age_unit,
                    vs.age_value,
                    vac.max_doses
                FROM vaccinations v
                JOIN vaccine_schedule vs ON v.vaccine_name = vs.vaccine_name 
                    AND v.dose_number = vs.dose_number
                LEFT JOIN vaccines vac ON v.vaccine_name = vac.vaccine_name
                WHERE v.child_id = :child_id 
                AND v.status = 'Scheduled'
                AND v.scheduled_date > :scheduled_date
                ORDER BY v.scheduled_date ASC
            ");
            $stmt->execute([
                ':child_id' => $childId,
                ':scheduled_date' => $scheduledDate
            ]);
            $subsequentVaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate new dates for subsequent vaccines
            $lastDate = new DateTime($scheduledDate);
            foreach ($subsequentVaccines as $vaccine) {
                // Add minimum gap days to last vaccination date
                $newDate = clone $lastDate;
                $newDate->modify("+{$vaccine['minimum_gap_days']} days");
                
                // Update the vaccination schedule
                $stmt = $conn->prepare("
                    UPDATE vaccinations 
                    SET scheduled_date = :new_date
                    WHERE child_id = :child_id 
                    AND vaccine_name = :vaccine_name 
                    AND dose_number = :dose_number
                ");
                $stmt->execute([
                    ':new_date' => $newDate->format('Y-m-d'),
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccine['vaccine_name'],
                    ':dose_number' => $vaccine['dose_number']
                ]);
                
                // Also update the associated appointment
                $stmt = $conn->prepare("
                    UPDATE appointments a
                    JOIN appointment_vaccines av ON a.id = av.appointment_id
                    SET a.scheduled_date = :new_date
                    WHERE a.child_id = :child_id 
                    AND av.vaccine_name = :vaccine_name 
                    AND av.dose_number = :dose_number
                ");
                $stmt->execute([
                    ':new_date' => $newDate->format('Y-m-d'),
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccine['vaccine_name'],
                    ':dose_number' => $vaccine['dose_number']
                ]);
                
                $lastDate = $newDate;
            }
            
            $conn->commit();
            header("Location: child_profile.php?id=$childId&success=2");
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to schedule vaccine: " . $e->getMessage();
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

    // When recording a vaccination, add validation to check the scheduled date
    if (isset($_POST['record_vaccination'])) {
        $vaccineId = $_POST['vaccine_id'];
        $childId = $_POST['child_id'];
        $vaccineName = $_POST['vaccine_name'];
        $doseNumber = $_POST['dose_number'];
        $administeredDate = $_POST['administered_date'];
        $notes = $_POST['notes'];

        try {
            // Get the scheduled date for this vaccine dose
            $scheduledDateStmt = $conn->prepare("
                SELECT scheduled_date 
                FROM vaccinations 
                WHERE child_id = :child_id 
                AND vaccine_name = :vaccine_name 
                AND dose_number = :dose_number
                AND status = 'Scheduled'
            ");

            $scheduledDateStmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber
            ]);

            $scheduledDate = $scheduledDateStmt->fetch(PDO::FETCH_COLUMN);

            // Compare administered date with scheduled date
            $scheduledDateTime = new DateTime($scheduledDate);
            $administeredDateTime = new DateTime($administeredDate);
            $today = new DateTime();

            if ($administeredDateTime > $today) {
                throw new Exception("Administration date cannot be in the future.");
            } 
            
            if ($scheduledDate && $administeredDateTime < $scheduledDateTime) {
                throw new Exception("This vaccine dose cannot be administered before its scheduled date (" . $scheduledDate . ").");
            }

            // Start transaction
            $conn->beginTransaction();

            // Update the vaccination record
            $stmt = $conn->prepare("
                UPDATE vaccinations 
                SET administered_date = :administered_date,
                    administered_by = :administered_by,
                    status = 'Administered',
                    notes = :notes
                WHERE child_id = :child_id 
                AND vaccine_name = :vaccine_name 
                AND dose_number = :dose_number
            ");

            $stmt->execute([
                ':administered_date' => $administeredDate,
                ':administered_by' => $_SESSION['user']['name'],
                ':notes' => $notes,
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber
            ]);

            // Update the appointment_vaccines table
            $appointmentStmt = $conn->prepare("
                UPDATE appointment_vaccines 
                SET status = 'completed' 
                WHERE vaccine_name = :vaccine_name 
                AND dose_number = :dose_number 
                AND appointment_id IN (
                    SELECT id FROM appointments WHERE child_id = :child_id
                )
            ");

            $appointmentStmt->execute([
                ':vaccine_name' => $vaccineName,
                ':dose_number' => $doseNumber,
                ':child_id' => $childId
            ]);

            $conn->commit();
            $success = "Vaccination recorded successfully!";
            
            header("Location: child_profile.php?id=$childId&success=1");
            exit();

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// After the form processing and before the HTML starts, add this to refresh data:
if (isset($_GET['success']) && $_GET['success'] == '1') {
    // Refresh the takenVaccines data 
    $stmt = $conn->prepare("
        SELECT vaccine_name, dose_number 
        FROM vaccinations 
        WHERE child_id = :child_id AND status = 'Administered'
    ");
    $stmt->execute([':child_id' => $childId]);
    $takenVaccinesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recreate the structured array
    $takenVaccines = [];
    foreach ($takenVaccinesRaw as $vaccine) {
        $name = $vaccine['vaccine_name'];
        $dose = $vaccine['dose_number'];
        
        if (!isset($takenVaccines[$name])) {
            $takenVaccines[$name] = [];
        }
        
        $takenVaccines[$name][] = $dose;
    }
    
    // Recalculate progress
    $uniqueTakenVaccineNames = count(array_keys($takenVaccines));
    
    if ($totalVaccineTypes > 0) {
        $progress = ($uniqueTakenVaccineNames / $totalVaccineTypes) * 100;
    }
}

// Enhance success message display
if (isset($_GET['success'])) {
    $messages = [
        1 => isset($_GET['vaccine']) && isset($_GET['dose']) ? 
             "Successfully recorded " . htmlspecialchars($_GET['vaccine']) . " (Dose " . htmlspecialchars($_GET['dose']) . ")!" :
             "Vaccine recorded successfully!",
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
        
        /* Style for dropdown options */
        select option {
            padding: 8px;
            background-color: #1f2937; /* dark gray background */
            color: white;
        }
        
        select option:hover {
            background-color: #374151;
        }
        
        select option:disabled {
            color: #6b7280;
            font-style: italic;
        }
        
        /* Remove default select arrow in modern browsers */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        /* Remove default select arrow in IE */
        select::-ms-expand {
            display: none;
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
                            <span class="text-blue-400 font-semibold"><?php echo $uniqueTakenVaccineNames; ?></span> of 
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
                                        <select id="vaccine_select" name="vaccine_name" required 
                                                class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                       shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 focus:border-green-500/50
                                                       transition-colors appearance-none">
                                            <option value="" class="bg-gray-800">Select a vaccine...</option>
                                            <?php foreach ($availableVaccines as $vaccine): 
                                                $takenDoses = isset($takenVaccines[$vaccine['vaccine_name']]) ? 
                                                             count($takenVaccines[$vaccine['vaccine_name']]) : 0;
                                                $isFullyAdministered = $takenDoses >= $vaccine['max_doses'];
                                            ?>
                                                <option value="<?php echo htmlspecialchars($vaccine['vaccine_name']); ?>"
                                                        data-max-doses="<?php echo htmlspecialchars($vaccine['max_doses']); ?>"
                                                        data-description="<?php echo htmlspecialchars($vaccine['description']); ?>"
                                                        data-target-disease="<?php echo htmlspecialchars($vaccine['target_disease']); ?>"
                                                        data-taken-doses="<?php echo $takenDoses; ?>"
                                                        <?php echo $isFullyAdministered ? 'disabled' : ''; ?>
                                                        class="bg-gray-800">
                                                    <?php 
                                                        echo htmlspecialchars($vaccine['vaccine_name']);
                                                        if ($takenDoses > 0) {
                                                            echo " (" . $takenDoses . "/" . $vaccine['max_doses'] . " doses)";
                                                        }
                                                        if ($isFullyAdministered) {
                                                            echo " - Completed";
                                                        }
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </div>
                                    <!-- Vaccine Information Display -->
                                    <div id="vaccine_info" class="mt-2 text-sm text-gray-400 hidden">
                                        <p id="vaccine_description" class="mb-1"></p>
                                        <p id="vaccine_target" class="text-blue-400"></p>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Dose Number</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="dose_number" id="dose_number" min="1" required
                                               class="block w-24 bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                      shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 focus:border-green-500/50
                                                      transition-colors">
                                        <span id="dose_max_indicator" class="text-gray-400"></span>
                                    </div>
                                    <p id="dose_help_text" class="mt-1 text-sm text-gray-500 italic"></p>
                                    <div id="taken_doses_info" class="mt-1 text-sm text-yellow-500 italic"></div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Notes (Optional)</label>
                                    <textarea name="notes" rows="2" 
                                              class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                     shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 focus:border-green-500/50
                                                     transition-colors"></textarea>
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
                                        <span>Record Vaccination</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Schedule Vaccine Card -->
                        <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-blue-500/20 rounded-xl p-6 transform transition-all duration-300 hover:shadow-xl hover:shadow-blue-500/5">
                            <h2 class="text-xl font-bold text-white flex items-center mb-6">
                                <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                Schedule Vaccine
                            </h2>
                            
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Vaccine</label>
                                    <div class="relative">
                                        <select id="schedule_vaccine_select" name="vaccine_name" required 
                                                class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                       shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                                       transition-colors appearance-none">
                                            <option value="" class="bg-gray-800">Select a vaccine...</option>
                                            <?php foreach ($availableVaccines as $vaccine): 
                                                $isFullyAdministered = isset($takenVaccines[$vaccine['vaccine_name']]) && 
                                                             count($takenVaccines[$vaccine['vaccine_name']]) >= $vaccine['max_doses'];
                                            ?>
                                                <option value="<?php echo htmlspecialchars($vaccine['vaccine_name']); ?>"
                                                        data-max-doses="<?php echo htmlspecialchars($vaccine['max_doses']); ?>"
                                                        <?php echo $isFullyAdministered ? 'disabled' : ''; ?>
                                                        class="bg-gray-800">
                                                    <?php echo htmlspecialchars($vaccine['vaccine_name']); ?>
                                                    <?php if ($isFullyAdministered): ?> (All doses administered)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <!-- Add a custom dropdown arrow -->
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Dose Number</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="dose_number" id="schedule_dose_number" min="1" max="10" value="1" required
                                               class="block w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white 
                                                      shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                                      transition-colors">
                                        <div id="schedule_dose_max_indicator" class="hidden text-gray-400 whitespace-nowrap"></div>
                                    </div>
                                    <div id="schedule_taken_doses_info" class="mt-1 text-sm text-yellow-500 italic hidden"></div>
                                </div>
                                
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
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H9z"/>
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
                            <tr class="hover:bg-gray-700/20 transition-colors duration-150 <?php echo $vaccination['status'] === 'Administered' ? 'bg-green-900/10' : ''; ?>">
                                <td class="py-4 px-6 text-white font-medium">
                                    <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                    <span class="text-xs text-gray-400 ml-1">(Dose <?php echo htmlspecialchars($vaccination['dose_number']); ?>)</span>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if ($vaccination['status'] === 'Administered'): ?>
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
                                        $date = $vaccination['status'] === 'Administered' 
                                              ? $vaccination['administered_date'] 
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

    <!-- Error Notification -->
    <?php if (isset($error)): ?>
    <div id="errorNotification" 
         class="fixed top-24 right-4 z-50 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg 
                animate__animated animate__fadeInRight flex items-center">
        <div class="flex-shrink-0 mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <h3 class="font-semibold mb-1">Unable to Record Vaccine</h3>
            <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <button onclick="dismissError()" class="ml-4 text-white hover:text-red-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <script>
        // Add this to your existing script section or create a new one
        function dismissError() {
            const errorNotification = document.getElementById('errorNotification');
            if (errorNotification) {
                errorNotification.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
                setTimeout(() => {
                    errorNotification.remove();
                }, 1000);
            }
        }

        // Auto-dismiss error after 5 seconds
        if (document.getElementById('errorNotification')) {
            setTimeout(() => {
                dismissError();
            }, 5000);
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

        document.addEventListener('DOMContentLoaded', function() {
            const vaccineSelect = document.getElementById('vaccine_select');
            const doseInput = document.getElementById('dose_number');
            const doseHelpText = document.getElementById('dose_help_text');
            const doseMaxIndicator = document.getElementById('dose_max_indicator');
            const takenDosesInfo = document.getElementById('taken_doses_info');
            
            // Store taken vaccines data
            const takenVaccines = <?php echo json_encode($takenVaccines); ?>;
            
            if (vaccineSelect && doseInput) {
                vaccineSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const maxDoses = parseInt(selectedOption.dataset.maxDoses) || 0;
                    const description = selectedOption.dataset.description;
                    const targetDisease = selectedOption.dataset.targetDisease;
                    const takenDoses = parseInt(selectedOption.dataset.takenDoses) || 0;
                    const vaccineName = selectedOption.value;

                    // Update dose input constraints
                    doseInput.max = maxDoses;
                    doseMaxIndicator.textContent = maxDoses > 0 ? `/ ${maxDoses}` : '';

                    // Show vaccine information
                    if (description || targetDisease) {
                        vaccineInfo.classList.remove('hidden');
                        vaccineDescription.textContent = description;
                        vaccineTarget.textContent = targetDisease ? `Protects against: ${targetDisease}` : '';
                    } else {
                        vaccineInfo.classList.add('hidden');
                    }

                    // Update dose help text
                    if (maxDoses > 0) {
                        doseHelpText.textContent = `This vaccine requires ${maxDoses} dose${maxDoses > 1 ? 's' : ''} in total`;
                            doseHelpText.classList.remove('hidden');
                        } else {
                            doseHelpText.classList.add('hidden');
                    }

                    // Show taken doses information
                    if (takenDoses > 0) {
                        const takenDosesArray = takenVaccines[vaccineName] || [];
                        takenDosesInfo.textContent = `Previously taken doses: ${takenDosesArray.join(', ')}`;
                            takenDosesInfo.classList.remove('hidden');
                        
                        // Set default dose number to next required dose
                        const nextDose = takenDosesArray.length + 1;
                        if (nextDose <= maxDoses) {
                            doseInput.value = nextDose;
                        }
                        } else {
                            takenDosesInfo.classList.add('hidden');
                        doseInput.value = 1;
                    }
                });

                // Validate dose number on input
                doseInput.addEventListener('input', function() {
                    const selectedOption = vaccineSelect.options[vaccineSelect.selectedIndex];
                    const maxDoses = parseInt(selectedOption.dataset.maxDoses) || 0;
                    const value = parseInt(this.value) || 0;

                    if (value > maxDoses) {
                        this.value = maxDoses;
                    } else if (value < 1) {
                        this.value = 1;
                    }
                });
            }
            
            // Similar enhancements for the scheduling form
            const scheduleVaccineSelect = document.getElementById('schedule_vaccine_select');
            const scheduleDoseInput = document.getElementById('schedule_dose_number');
            const scheduleDoseMaxIndicator = document.getElementById('schedule_dose_max_indicator');
            const scheduleTakenDosesInfo = document.getElementById('schedule_taken_doses_info');
            
            if (scheduleVaccineSelect && scheduleDoseInput) {
                scheduleVaccineSelect.addEventListener('change', function() {
                    if (!this.value) return;
                    
                    const maxDoses = parseInt(this.options[this.selectedIndex].dataset.maxDoses) || 1;
                    const vaccineName = this.value;
                    
                    // Set max attribute on dose input
                    scheduleDoseInput.max = maxDoses;
                    
                    // Show max doses indicator
                    if (scheduleDoseMaxIndicator) {
                        scheduleDoseMaxIndicator.textContent = `(max: ${maxDoses})`;
                        scheduleDoseMaxIndicator.classList.remove('hidden');
                    }
                    
                    // Show already taken doses info and disable taken doses
                    if (scheduleTakenDosesInfo) {
                        if (takenVaccines[vaccineName] && takenVaccines[vaccineName].length > 0) {
                            const takenDosesArray = takenVaccines[vaccineName].map(d => parseInt(d)).sort((a, b) => a - b);
                            scheduleTakenDosesInfo.textContent = `Doses already administered: ${takenDosesArray.join(', ')}`;
                            scheduleTakenDosesInfo.classList.remove('hidden');
                            
                            // Set the dose input to the next available dose
                            let nextDose = 1;
                            while (takenDosesArray.includes(nextDose) && nextDose <= maxDoses) {
                                nextDose++;
                            }
                            if (nextDose <= maxDoses) {
                                scheduleDoseInput.value = nextDose;
                            }
                        } else {
                            scheduleTakenDosesInfo.classList.add('hidden');
                            scheduleDoseInput.value = 1;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>