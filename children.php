<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Prevent form resubmission on refresh
if (isset($_SESSION['form_submitted']) && $_SESSION['form_submitted'] === true) {
    // Clear the form submission flag to prevent multiple insertions
    unset($_SESSION['form_submitted']);
    
    // If page is being refreshed after submission, just display the page without processing form again
    if (!isset($_POST) || empty($_POST)) {
        // Continue to display the page, but skip the POST processing section
    } 
    // If there's a new form submission (not a refresh), let it proceed
}

// Generate a CSRF token for the form if one doesn't exist
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// Include database connection
include 'backend/db.php';

//function  to generate the unique ID
function generateChildID($fullName, $guardianName, $dateOfBirth) {
    // Get first letters of each word in child's name
    $nameInitials = array_map(function($word) {
        return strtoupper(substr($word, 0, 1));
    }, explode(' ', $fullName));
    
    // Get first letters of guardian's name
    $guardianInitials = array_map(function($word) {
        return strtoupper(substr($word, 0, 1));
    }, explode(' ', $guardianName));
    
    // Format date as YYMMDD
    $dateFormat = date('ymd', strtotime($dateOfBirth));
    
    // Combine parts
    $nameStr = implode('', $nameInitials);
    $guardianStr = implode('', $guardianInitials);
    
    // Create the ID
    // where C = Child initials, G = Guardian initials
    $childID = sprintf("%s-%s-%s", 
        str_pad($nameStr, 4, '0'), 
        str_pad($guardianStr, 4, '0'), 
        $dateFormat
    );
    
    return $childID;
}

/**
 * Create a complete vaccination schedule for a child
 * @param PDO $conn Database connection
 * @param string $childID Child ID
 * @param string $dateOfBirth Child's date of birth
 * @param string $fullName Child's full name
 * @param string $guardianName Guardian's name
 * @param string $phone Guardian's phone number
 * @return array|null Next upcoming vaccine information or null if none
 */
function createVaccinationSchedule($conn, $childID, $dateOfBirth, $fullName, $guardianName, $phone) {
    $birthDate = new DateTime($dateOfBirth);
    $today = new DateTime();
    
    // Get all vaccine schedules ordered by age
    $stmt = $conn->query("SELECT vs.*, v.name as vaccine_name, v.target_disease, v.administration_method, v.dosage 
                         FROM vaccine_schedule vs
                         JOIN vaccines v ON vs.vaccine_id = v.id
                         ORDER BY vs.age_value ASC, vs.dose_number ASC");
    $vaccineSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($vaccineSchedules)) {
        error_log("No vaccine schedules found in vaccine_schedule table");
        return null;
    }

    // Group vaccines by appointment date to consolidate multiple vaccines on the same day
    $appointmentDates = [];
    $nextVaccine = null;

    foreach ($vaccineSchedules as $schedule) {
        $scheduledDate = clone $birthDate;
        
        try {
            // Calculate the scheduled date based on child's age
            switch ($schedule['age_unit']) {
                case 'days':
                    $scheduledDate->add(new DateInterval("P{$schedule['age_value']}D"));
                    break;
                case 'weeks':
                    $scheduledDate->add(new DateInterval("P{$schedule['age_value']}W"));
                    break;
                case 'months':
                    $scheduledDate->add(new DateInterval("P{$schedule['age_value']}M"));
                    break;
                case 'years':
                    $scheduledDate->add(new DateInterval("P{$schedule['age_value']}Y"));
                    break;
                default:
                    throw new Exception("Invalid age_unit: {$schedule['age_unit']}");
            }

            $appointmentDate = $scheduledDate->format('Y-m-d');
            $isPast = $scheduledDate < $today;
            $status = $isPast ? 'missed' : 'scheduled';
            
            // Group vaccines by appointment date
            if (!isset($appointmentDates[$appointmentDate])) {
                $appointmentDates[$appointmentDate] = [
                    'date' => $appointmentDate,
                    'status' => $status,
                    'vaccines' => [],
                    'isPast' => $isPast
                ];
            }
            
            // Add this vaccine to the appointment date
            $appointmentDates[$appointmentDate]['vaccines'][] = [
                'vaccine_id' => $schedule['vaccine_id'],
                'vaccine_name' => $schedule['vaccine_name'],
                'dose_number' => $schedule['dose_number'],
                'status' => $status,
                'notes' => $schedule['notes'] ?? '',
                'target_disease' => $schedule['target_disease'] ?? '',
                'administration_method' => $schedule['administration_method'] ?? '',
                'dosage' => $schedule['dosage'] ?? ''
            ];
            
            // Track the next upcoming vaccine (earliest future date)
            if (!$isPast && (!$nextVaccine || $scheduledDate < new DateTime($nextVaccine['date']))) {
                $nextVaccine = [
                    'name' => $schedule['vaccine_name'],
                    'dose' => $schedule['dose_number'],
                    'date' => $appointmentDate,
                    'time' => '08:00:00' // Default appointment time
                ];
            }
        } catch (Exception $e) {
            error_log("Error creating schedule for {$schedule['vaccine_name']} (Dose {$schedule['dose_number']}): " . $e->getMessage());
            throw $e; // Re-throw to rollback transaction
        }
    }

    // Now create appointments for each date and add all vaccines for that date
    foreach ($appointmentDates as $date => $appointmentData) {
        $defaultTime = '08:00:00';
        $vaccineNames = array_map(function($v) {
            return "{$v['vaccine_name']} (Dose {$v['dose_number']})";
        }, $appointmentData['vaccines']);
        
        $notesText = "Vaccination: " . implode(", ", $vaccineNames);
        
        // Insert appointment
        $stmt = $conn->prepare("INSERT INTO appointments (
            child_id, scheduled_date, scheduled_time, status, notes
        ) VALUES (
            :child_id, :scheduled_date, :scheduled_time, :status, :notes
        )");
        
        $stmt->execute([
            ':child_id' => $childID,
            ':scheduled_date' => $date,
            ':scheduled_time' => $defaultTime,
            ':status' => $appointmentData['status'],
            ':notes' => $notesText
        ]);
        
        $appointmentId = $conn->lastInsertId();
        
        // Insert each vaccine for this appointment
        foreach ($appointmentData['vaccines'] as $vaccine) {
            // Insert vaccination record
            $stmt = $conn->prepare("INSERT INTO vaccinations (
                child_id, vaccine_id, vaccine_name, dose_number, scheduled_date, 
                status, notes
            ) VALUES (
                :child_id, :vaccine_id, :vaccine_name, :dose_number, :scheduled_date, 
                :status, :notes
            )");
            
            $stmt->execute([
                ':child_id' => $childID,
                ':vaccine_id' => $vaccine['vaccine_id'],
                ':vaccine_name' => $vaccine['vaccine_name'],
                ':dose_number' => $vaccine['dose_number'],
                ':scheduled_date' => $date,
                ':status' => $vaccine['status'],
                ':notes' => $vaccine['notes']
            ]);
            
            // Link vaccine to appointment
            $stmt = $conn->prepare("INSERT INTO appointment_vaccines (
                appointment_id, vaccine_id, vaccine_name, dose_number, status
            ) VALUES (
                :appointment_id, :vaccine_id, :vaccine_name, :dose_number, :status
            )");
            
            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':vaccine_id' => $vaccine['vaccine_id'],
                ':vaccine_name' => $vaccine['vaccine_name'],
                ':dose_number' => $vaccine['dose_number'],
                ':status' => $vaccine['status']
            ]);
        }
    }
    
    return $nextVaccine;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a fresh form submission, not a refresh
    if (!isset($_SESSION['form_submitted']) || $_SESSION['form_submitted'] !== true) {
        // Validate the CSRF token
        if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
            $error = "Invalid form submission. Please try again.";
        } else {
            // Collect and sanitize input
            $fullName = trim($_POST['full_name']);
            $dateOfBirth = trim($_POST['date_of_birth']);
            $gender = trim($_POST['gender']);
            $birthWeight = floatval($_POST['birth_weight']);
            $placeOfBirth = trim($_POST['place_of_birth']);
            
            $guardianName = trim($_POST['guardian_name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            
            $birthComplications = trim($_POST['birth_complications']);
            $allergies = trim($_POST['allergies']);
            $previousVaccinations = trim($_POST['previous_vaccinations']);
            $registrationDate = date('Y-m-d');

            // Date validation
            $today = new DateTime();
            $inputDate = new DateTime($dateOfBirth);
            
            // Validate required inputs
            if (empty($fullName) || empty($dateOfBirth) || empty($gender) || empty($guardianName) || empty($phone)) {
                $error = "Required fields must be filled out.";
            } elseif ($birthWeight <= 0) {
                $error = "Birth weight must be a positive number.";
            } elseif ($inputDate > $today) {
                $error = "Date of birth cannot be in the future.";
            } else {
                // Generate child_id
                $childID = generateChildID($fullName, $guardianName, $dateOfBirth);

                // Start transaction
                $conn->beginTransaction();
                
                try {
                    // Insert child into database
                    $stmt = $conn->prepare("INSERT INTO children (
                        child_id, full_name, date_of_birth, gender, birth_weight, place_of_birth,
                        guardian_name, phone, email, address,
                        birth_complications, allergies, previous_vaccinations, registration_date
                    ) VALUES (
                        :child_id, :full_name, :date_of_birth, :gender, :birth_weight, :place_of_birth,
                        :guardian_name, :phone, :email, :address,
                        :birth_complications, :allergies, :previous_vaccinations, :registration_date
                    )");
                    
                    $stmt->execute([
                        ':child_id' => $childID,
                        ':full_name' => $fullName,
                        ':date_of_birth' => $dateOfBirth,
                        ':gender' => $gender,
                        ':birth_weight' => $birthWeight,
                        ':place_of_birth' => $placeOfBirth,
                        ':guardian_name' => $guardianName,
                        ':phone' => $phone,
                        ':email' => $email,
                        ':address' => $address,
                        ':birth_complications' => $birthComplications,
                        ':allergies' => $allergies,
                        ':previous_vaccinations' => $previousVaccinations,
                        ':registration_date' => $registrationDate
                    ]);

                    // Insert medical record
                    $stmt = $conn->prepare("INSERT INTO medical_records (child_id, birth_complications, allergies, previous_vaccinations) 
                                          VALUES (:child_id, :birth_complications, :allergies, :previous_vaccinations)");
                    $stmt->execute([
                        ':child_id' => $childID,
                        ':birth_complications' => $birthComplications,
                        ':allergies' => $allergies,
                        ':previous_vaccinations' => $previousVaccinations
                    ]);

                    // Create vaccination schedule for the child
                    $nextVaccine = createVaccinationSchedule($conn, $childID, $dateOfBirth, $fullName, $guardianName, $phone);

                    // Send SMS with next vaccine info
                    if ($nextVaccine) {
                        require_once ROOT_PATH . 'includes/sms.php';
                        $message = "Dear $guardianName, your child $fullName (ID: $childID) has been registered. " .
                                  "Next vaccine: {$nextVaccine['name']} (Dose {$nextVaccine['dose']}) " .
                                  "on {$nextVaccine['date']} at {$nextVaccine['time']}.";
                        $smsResult = sendSMS($phone, $message);

                        // Log SMS
                        $stmt = $conn->prepare("INSERT INTO sms_logs (child_id, recipient, message, status, message_type, response) 
                                              VALUES (:child_id, :recipient, :message, :status, 'registration', :response)");
                        $stmt->execute([
                            ':child_id' => $childID,
                            ':recipient' => $phone,
                            ':message' => $message,
                            ':status' => $smsResult['success'] ? 'success' : 'failed',
                            ':response' => json_encode($smsResult)
                        ]);

                        $_SESSION['success'] = "Child registered successfully with ID: $childID. " .
                                              ($smsResult['success'] ? "SMS sent with next vaccine details." : 
                                              "SMS sending failed: {$smsResult['message']}");
                    } else {
                        $_SESSION['success'] = "Child registered successfully with ID: $childID. " .
                                             "No upcoming vaccines scheduled.";
                    }

                    $conn->commit();
                    
                    // Prevent form resubmission
                    $_SESSION['form_submitted'] = true;
                    $_SESSION['form_token'] = bin2hex(random_bytes(32));
                    
                    header("Location: children.php");
                    exit();

                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Failed to register child: " . $e->getMessage();
                    error_log("Registration error: " . $e->getMessage());
                }
            }
        }
    }
}

// Fetch all children from the database
$stmt = $conn->query("SELECT child_id, full_name, gender, date_of_birth, birth_weight, guardian_name, phone FROM children ORDER BY child_id DESC");
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define success message
if (isset($_GET['success'])) {
    $success = "Child registered successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Children Management - Immunization System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Notification Container -->
    <div class="top-20 right-6 fixed z-50 w-96">
        <?php if (isset($error)): ?>
            <div class="notification bg-red-500 text-white p-4 rounded-lg shadow-lg animate__animated animate__fadeInRight">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo $error; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="notification bg-green-500 text-white p-4 rounded-lg shadow-lg animate__animated animate__fadeInRight mb-4">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo $_SESSION['success']; ?>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($success)): ?>
            <div class="notification bg-green-500 text-white p-4 rounded-lg shadow-lg animate__animated animate__fadeInRight mb-4">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo $success; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['warning'])): ?>
            <div class="notification bg-yellow-500 text-white p-4 rounded-lg shadow-lg animate__animated animate__fadeInRight">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <?php echo $_SESSION['warning']; ?>
                </div>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="p-4 sm:ml-64 pt-20">
        <div class="p-4 rounded-lg">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <h2 class="text-2xl font-semibold text-white mb-4 md:mb-0">
                    <i class="fas fa-child text-blue-400 mr-2"></i> Children Management
                </h2>
                <button id="showFormButton" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg 
                                             shadow-lg transition duration-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Register New Child
                </button>
            </div>

            <!-- Registration Form (Hidden by default) -->
            <div id="registrationForm" class="bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-6 <?php echo (isset($error) || (isset($_GET['show_form']) && $_GET['show_form'] === '1')) ? '' : 'hidden'; ?> animate__animated animate__fadeIn">
                <div class="flex items-center justify-between p-4 border-b border-gray-700">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                            </path>
                        </svg>
                        Register New Child
                    </h3>
                    <button id="closeForm" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <form method="POST" class="space-y-8">
                        <!-- CSRF Token -->
                        <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>">
                        
                        <!-- Child's Personal Information -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-blue-400 uppercase tracking-wider">Child's Personal Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label for="full_name" class="block text-gray-300 text-sm font-medium mb-2">Full Name</label>
                                    <input type="text" id="full_name" name="full_name" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="date_of_birth" class="block text-gray-300 text-sm font-medium mb-2">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="gender" class="block text-gray-300 text-sm font-medium mb-2">Gender</label>
                                    <select id="gender" name="gender" required
                                            class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                   focus:ring-2 focus:ring-blue-500 transition duration-300">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="birth_weight" class="block text-gray-300 text-sm font-medium mb-2">Weight at Birth (kg)</label>
                                    <input type="number" step="0.01" id="birth_weight" name="birth_weight" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="place_of_birth" class="block text-gray-300 text-sm font-medium mb-2">Place of Birth</label>
                                    <select id="place_of_birth" name="place_of_birth" required
                                            class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                   focus:ring-2 focus:ring-blue-500 transition duration-300">
                                        <option value="">Select Place of Birth</option>
                                        <option value="Hospital">Hospital</option>
                                        <option value="Home">Home</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Parent/Guardian Information -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-green-400 uppercase tracking-wider">Parent/Guardian Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label for="guardian_name" class="block text-gray-300 text-sm font-medium mb-2">Guardian's Full Name</label>
                                    <input type="text" id="guardian_name" name="guardian_name" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="phone" class="block text-gray-300 text-sm font-medium mb-2">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="email" class="block text-gray-300 text-sm font-medium mb-2">Email Address</label>
                                    <input type="email" id="email" name="email"
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div class="col-span-2">
                                    <label for="address" class="block text-gray-300 text-sm font-medium mb-2">Home Address</label>
                                    <textarea id="address" name="address" rows="2"
                                              class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                     focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Information -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-red-400 uppercase tracking-wider">Medical Information</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="birth_complications" class="block text-gray-300 text-sm font-medium mb-2">Birth Complications</label>
                                    <textarea id="birth_complications" name="birth_complications" rows="2"
                                              class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                     focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                                </div>
                                <div>
                                    <label for="allergies" class="block text-gray-300 text-sm font-medium mb-2">Known Allergies</label>
                                    <textarea id="allergies" name="allergies" rows="2"
                                              class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                     focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                                </div>
                                <div>
                                    <label for="previous_vaccinations" class="block text-gray-300 text-sm font-medium mb-2">Previous Vaccinations</label>
                                    <textarea id="previous_vaccinations" name="previous_vaccinations" rows="2"
                                              class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                     focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg 
                                                        shadow-lg transition duration-300 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Register Child
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success)): ?>
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

            <?php if (isset($error)): ?>
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

            <!-- Children Table -->
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-white">Registered Children</h3>
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search children..." 
                               class="px-4 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                      focus:ring-2 focus:ring-blue-500 transition duration-300 w-64">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs uppercase bg-gray-700 text-gray-300">
                            <tr>
                                <th scope="col" class="px-6 py-3">Child ID</th>
                                <th scope="col" class="px-6 py-3">Full Name</th>
                                <th scope="col" class="px-6 py-3">Gender</th>
                                <th scope="col" class="px-6 py-3">Date of Birth</th>
                                <th scope="col" class="px-6 py-3">Birth Weight (kg)</th>
                                <th scope="col" class="px-6 py-3">Guardian</th>
                                <th scope="col" class="px-6 py-3">Phone</th>
                                <th scope="col" class="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="childrenTableBody">
                            <?php foreach ($children as $child): ?>
                                <tr class="border-b border-gray-700 hover:bg-gray-700">
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($child['child_id']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($child['full_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($child['gender']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($child['date_of_birth']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($child['birth_weight']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($child['guardian_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($child['phone']); ?></td>
                                    <td class="px-6 py-4 flex space-x-2">
                                        <a href="child_profile.php?id=<?php echo urlencode($child['child_id']); ?>" 
                                           class="text-blue-400 hover:text-blue-300 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="edit_child.php?id=<?php echo urlencode($child['child_id']); ?>" 
                                           class="text-yellow-400 hover:text-yellow-300 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form toggle functionality
        const showFormButton = document.getElementById('showFormButton');
        const closeFormButton = document.getElementById('closeForm');
        const registrationForm = document.getElementById('registrationForm');

        showFormButton.addEventListener('click', () => {
            registrationForm.classList.remove('hidden');
            registrationForm.scrollIntoView({ behavior: 'smooth' });
        });

        closeFormButton.addEventListener('click', () => {
            registrationForm.classList.add('hidden');
        });

        // Hide form after successful registration
        <?php if (isset($_SESSION['success']) || isset($success)): ?>
        registrationForm.classList.add('hidden');
        <?php endif; ?>

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const childrenTableBody = document.getElementById('childrenTableBody');
        const tableRows = childrenTableBody.querySelectorAll('tr');

        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            
            tableRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
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
    </script>
</body>
</html>
