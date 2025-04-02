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

            // Add registration date
            $registrationDate = date('Y-m-d'); 

            // Add date validation
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

                // Insert into database
                $stmt = $conn->prepare("INSERT INTO children (
                    child_id, full_name, date_of_birth, gender, birth_weight, place_of_birth,
                    guardian_name, phone, email, address,
                    birth_complications, allergies, previous_vaccinations, registration_date
                ) VALUES (
                    :child_id, :full_name, :date_of_birth, :gender, :birth_weight, :place_of_birth,
                    :guardian_name, :phone, :email, :address,
                    :birth_complications, :allergies, :previous_vaccinations, :registration_date
                )");
                
                try {
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
// After successful child insertion
require_once 'includes/sms.php';

// Get upcoming vaccines
$upcomingVaccines = [];
$stmt = $conn->prepare("
    SELECT v.vaccine_name, vs.age_unit, vs.age_value
    FROM vaccine_schedule vs
    JOIN vaccines v ON vs.vaccine_id = v.id
    ORDER BY 
        CASE vs.age_unit 
            WHEN 'days' THEN vs.age_value
            WHEN 'weeks' THEN vs.age_value * 7
            WHEN 'months' THEN vs.age_value * 30
            WHEN 'years' THEN vs.age_value * 365
        END ASC
");
$stmt->execute();
$vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$birthDate = new DateTime($dateOfBirth);
$today = new DateTime();

foreach ($vaccines as $vaccine) {
    $vaccineDate = clone $birthDate;
    switch ($vaccine['age_unit']) {
        case 'days':
            $vaccineDate->add(new DateInterval('P' . $vaccine['age_value'] . 'D'));
            break;
        case 'weeks':
            $vaccineDate->add(new DateInterval('P' . ($vaccine['age_value'] * 7) . 'D'));
            break;
        case 'months':
            $vaccineDate->add(new DateInterval('P' . $vaccine['age_value'] . 'M'));
            break;
        case 'years':
            $vaccineDate->add(new DateInterval('P' . $vaccine['age_value'] . 'Y'));
            break;
    }
    if ($vaccineDate > $today) {
        $upcomingVaccines[] = [
            'vaccine' => $vaccine['vaccine_name'],
            'date' => $vaccineDate->format('Y-m-d')
        ];
    }
}

// Send SMS with upcoming vaccines
if (!empty($upcomingVaccines)) {
    $message = "Dear $guardianName, your child $fullName has been registered (ID: $childID). Upcoming vaccines:\n";
    foreach ($upcomingVaccines as $vaccine) {
        $message .= "- {$vaccine['vaccine']} on {$vaccine['date']}\n";
    }
    $message = substr($message, 0, 160); // Ensure message fits SMS limit

    $smsResult = sendSMS($phone, $message);
    if ($smsResult['success']) {
        $_SESSION['success'] = "Child registered successfully with ID: $childID. SMS sent to $phone.";
    } else {
        $_SESSION['success'] = "Child registered successfully with ID: $childID.";
        $_SESSION['warning'] = "Failed to send SMS: " . $smsResult['message'];
    }
} else {
    $_SESSION['success'] = "Child registered successfully with ID: $childID. No upcoming vaccines scheduled.";
}
                    // Generate automatic vaccination schedule for the newly registered child
                    try {
                        // Fetch vaccine schedule information - ordered by age for proper scheduling
                        $scheduleStmt = $conn->query("
                            SELECT id, vaccine_name, age_unit, age_value, dose_number, 
                                   administration_method, dosage, target_disease, notes 
                            FROM vaccine_schedule 
                            ORDER BY 
                                CASE age_unit 
                                    WHEN 'days' THEN age_value
                                    WHEN 'weeks' THEN age_value * 7
                                    WHEN 'months' THEN age_value * 30
                                    WHEN 'years' THEN age_value * 365
                                END ASC,
                                dose_number ASC
                        ");
                        
                        // Check if we got any vaccine schedules
                        $vaccineSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($vaccineSchedules)) {
                            error_log("Warning: No vaccine schedules found in the database. Creating default schedule.");
                            // Create some default schedules if none exist
                            $vaccineSchedules = [
                                [
                                    'id' => 1, 
                                    'vaccine_name' => 'BCG', 
                                    'age_unit' => 'birth', 
                                    'age_value' => 0, 
                                    'dose_number' => 1
                                ],
                                [
                                    'id' => 2, 
                                    'vaccine_name' => 'OPV', 
                                    'age_unit' => 'months', 
                                    'age_value' => 2, 
                                    'dose_number' => 1
                                ],
                                [
                                    'id' => 3, 
                                    'vaccine_name' => 'OPV', 
                                    'age_unit' => 'months', 
                                    'age_value' => 4, 
                                    'dose_number' => 2
                                ],
                                [
                                    'id' => 4, 
                                    'vaccine_name' => 'Measles', 
                                    'age_unit' => 'months', 
                                    'age_value' => 9, 
                                    'dose_number' => 1
                                ]
                            ];
                        }
                        
                        // Group vaccines by their scheduled date from birth
                        $groupedVaccinesByDate = [];
                        $birthDate = new DateTime($dateOfBirth);
                        $registrationDateTime = new DateTime($registrationDate);
                        
                        // For logging
                        error_log("Child Birth Date: " . $birthDate->format('Y-m-d'));
                        error_log("Registration Date: " . $registrationDateTime->format('Y-m-d'));
                        
                        foreach ($vaccineSchedules as $vaccine) {
                            // Create a proper date interval based on the unit and value
                            $scheduleDate = clone $birthDate; // Start from birth date
                            
                            // Handle special case for 'birth'
                            if (isset($vaccine['age_unit']) && $vaccine['age_unit'] === 'birth') {
                                // No need to add any time
                            } else {
                                // Add appropriate interval based on age unit and value
                                $ageUnit = isset($vaccine['age_unit']) ? $vaccine['age_unit'] : 'months';
                                $ageValue = isset($vaccine['age_value']) ? $vaccine['age_value'] : 0;
                                
                                switch ($ageUnit) {
                                    case 'days':
                                        $scheduleDate->add(new DateInterval('P' . $ageValue . 'D'));
                                        break;
                                    case 'weeks':
                                        $scheduleDate->add(new DateInterval('P' . ($ageValue * 7) . 'D'));
                                        break;
                                    case 'months':
                                        $scheduleDate->add(new DateInterval('P' . $ageValue . 'M'));
                                        break;
                                    case 'years':
                                        $scheduleDate->add(new DateInterval('P' . $ageValue . 'Y'));
                                        break;
                                    default:
                                        // Default to months if unit is unrecognized
                                        $scheduleDate->add(new DateInterval('P' . $ageValue . 'M'));
                                }
                            }
                            
                            $appointmentDate = $scheduleDate->format('Y-m-d');
                            
                            if (!isset($groupedVaccinesByDate[$appointmentDate])) {
                                $groupedVaccinesByDate[$appointmentDate] = [];
                            }
                            $groupedVaccinesByDate[$appointmentDate][] = $vaccine;
                        }
                        
                        // Create appointments for each date
                        foreach ($groupedVaccinesByDate as $appointmentDate => $vaccines) {
                            // Skip past dates - only schedule future appointments
                            $today = date('Y-m-d');
                            if ($appointmentDate < $today) {
                                // For vaccines that should have been taken already (e.g., birth vaccines for older children)
                                // You could either skip them or mark them as 'Missed'
                                continue;
                            }
                            
                            // Set default appointment time
                            $appointmentTime = '08:00:00';
                            
                            // Get the age description for the first vaccine in this group
                            $firstVaccine = $vaccines[0];
                            $ageDescription = $firstVaccine['age_value'] . ' ' . $firstVaccine['age_unit'];
                            
                            // Create a single appointment record for this date
                            $appointmentNotes = count($vaccines) . " vaccine(s) scheduled for " . 
                                               date('F j, Y', strtotime($appointmentDate)) . 
                                               " (" . $ageDescription . ")";
                            
                            $stmt = $conn->prepare("
                                INSERT INTO appointments (
                                    child_id, scheduled_date, status, notes
                                ) VALUES (
                                    :child_id, :scheduled_date, 'scheduled', :notes
                                )
                            ");
                            
                            $stmt->execute([
                                ':child_id' => $childID,
                                ':scheduled_date' => $appointmentDate,
                                ':notes' => $appointmentNotes
                            ]);
                            
                            // Get the appointment ID
                            $appointmentId = $conn->lastInsertId();
                            
                            // Create individual vaccination records for each vaccine
                            foreach ($vaccines as $vaccine) {
                                $vaccineName = $vaccine['vaccine_name'];
                                $doseNumber = $vaccine['dose_number'];
                                
                                // Create detailed notes with all available information
                                $detailedNotes = "Scheduled " . $vaccine['age_value'] . " " . $vaccine['age_unit'];
                                
                                if (!empty($vaccine['administration_method'])) {
                                    $detailedNotes .= " | Method: " . $vaccine['administration_method'];
                                }
                                
                                if (!empty($vaccine['dosage'])) {
                                    $detailedNotes .= " | Dosage: " . $vaccine['dosage'];
                                }
                                
                                if (!empty($vaccine['target_disease'])) {
                                    $detailedNotes .= " | Protects against: " . $vaccine['target_disease'];
                                }
                                
                                if (!empty($vaccine['notes'])) {
                                    $detailedNotes .= " | " . $vaccine['notes'];
                                }
                                
                                // Insert into the vaccinations table
                                $vaccineStmt = $conn->prepare("
                                    INSERT INTO vaccinations (
                                        child_id, 
                                        vaccine_name, 
                                        dose_number,
                                        scheduled_date, 
                                        scheduled_time, 
                                        status, 
                                        notes
                                    ) VALUES (
                                        :child_id,
                                        :vaccine_name,
                                        :dose_number, 
                                        :scheduled_date,
                                        :scheduled_time,
                                        'Scheduled',
                                        :notes
                                    )
                                ");
                                
                                $vaccineStmt->execute([
                                    ':child_id' => $childID,
                                    ':vaccine_name' => $vaccineName,
                                    ':dose_number' => $doseNumber,
                                    ':scheduled_date' => $appointmentDate,
                                    ':scheduled_time' => $appointmentTime,
                                    ':notes' => $detailedNotes
                                ]);
                                
                                // Insert into the appointment_vaccines table
                                $apptVaccineStmt = $conn->prepare("
                                    INSERT INTO appointment_vaccines (
                                        appointment_id,
                                        vaccine_name,
                                        dose_number,
                                        status
                                    ) VALUES (
                                        :appointment_id,
                                        :vaccine_name,
                                        :dose_number,
                                        'scheduled'
                                    )
                                ");
                                
                                $apptVaccineStmt->execute([
                                    ':appointment_id' => $appointmentId,
                                    ':vaccine_name' => $vaccineName,
                                    ':dose_number' => $doseNumber
                                ]);
                            }
                        }
                        
                        // Set success message
                        if ($childID) {
                            $stmt = $conn->prepare("INSERT INTO medical_records (child_id, birth_complications, allergies, previous_vaccinations) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$childID, $birthComplications, $allergies, $previousVaccinations]);
                            
                            // Send SMS notification with vaccination schedule
                            try {
                                require_once 'sms-service/sms-adapter.php';
                                
                                // Clean and validate phone number
                                $phone = trim($phone);
                                
                                // Make sure we have a valid phone number
                                if (empty($phone)) {
                                    error_log("Empty phone number for child {$childID}. Cannot send SMS.");
                                    throw new Exception("Phone number is empty");
                                }
                                
                                // Add + prefix if missing (and number is numeric)
                                if (strpos($phone, '+') !== 0 && preg_match('/^\d+$/', $phone)) {
                                    $phone = '+' . $phone;
                                    error_log("Added + prefix to phone number: $phone for child: $childID");
                                }
                                
                                // Verify phone meets minimum requirements
                                if (strlen($phone) < 10) {
                                    error_log("Phone number too short: $phone for child: $childID");
                                    throw new Exception("Phone number is too short: $phone");
                                }
                                
                                // Check for non-numeric characters (except +)
                                if (!preg_match('/^\+?\d+$/', $phone)) {
                                    error_log("Phone contains invalid characters: $phone for child: $childID");
                                    throw new Exception("Phone number contains invalid characters: $phone");
                                }
                                
                                // Debug log all parameters we're passing to the SMS function
                                error_log("About to send SMS with vaccination schedule - Details:");
                                error_log("Phone: $phone");
                                error_log("Child Name: $fullName");
                                error_log("Guardian Name: $guardianName");
                                error_log("Child ID: $childID");
                                error_log("Schedule Data Count: " . count($groupedVaccinesByDate));
                                
                                // Make sure the schedule data is properly formatted
                                if (empty($groupedVaccinesByDate)) {
                                    error_log("Warning: Empty vaccination schedule for child {$childID}");
                                }
                                
                                // Check that at least one date has valid vaccine data
                                $hasValidSchedule = false;
                                foreach ($groupedVaccinesByDate as $date => $vaccines) {
                                    if (!empty($vaccines)) {
                                        $hasValidSchedule = true;
                                        break;
                                    }
                                }
                                
                                if (!$hasValidSchedule) {
                                    error_log("Warning: No valid vaccines found in schedule for child {$childID}");
                                    // Create a simple schedule so the SMS doesn't fail
                                    $registrationDate = date('Y-m-d');
                                    $twoMonthsLater = date('Y-m-d', strtotime('+2 months'));
                                    $fourMonthsLater = date('Y-m-d', strtotime('+4 months'));
                                    
                                    $groupedVaccinesByDate = [
                                        $twoMonthsLater => [
                                            ['vaccine_name' => 'First Vaccination', 'dose_number' => '1']
                                        ],
                                        $fourMonthsLater => [
                                            ['vaccine_name' => 'Second Vaccination', 'dose_number' => '2']
                                        ]
                                    ];
                                    
                                    error_log("Created simple schedule with " . count($groupedVaccinesByDate) . " dates");
                                }
                                
                                // Use the reliable method that tries multiple approaches
                                try {
                                    $smsResult = sendRegistrationWithScheduleSMS_Reliable($phone, $fullName, $guardianName, $groupedVaccinesByDate, $childID);
                                    
                                    // Debug logging 
                                    error_log("SMS sending attempt result for child {$childID}: " . json_encode($smsResult));
                                } catch (Exception $smsException) {
                                    error_log("Exception during SMS function execution: " . $smsException->getMessage());
                                    $smsResult = [
                                        'status' => 'error',
                                        'message' => 'Exception during SMS function execution: ' . $smsException->getMessage()
                                    ];
                                }
                                
                                // Set success message based on SMS result
                                if (isset($smsResult['success']) && $smsResult['success']) {
                                    $_SESSION['success'] = "Child successfully registered with ID: " . $childID . 
                                        ". A detailed SMS with the vaccination schedule has been sent to " . $phone . ".";
                                } else if (isset($smsResult['status']) && $smsResult['status'] === 'success') {
                                    $_SESSION['success'] = "Child successfully registered with ID: " . $childID . 
                                        ". A detailed SMS with the vaccination schedule has been sent to " . $phone . ".";
                                } else {
                                    // Log the error but don't prevent registration
                                    error_log("Failed to send registration SMS for child {$childID}: " . json_encode($smsResult));
                                    
                                    // Check for specific error types
                                    if (isset($smsResult['credentialIssues']) && !empty($smsResult['credentialIssues'])) {
                                        // API credential configuration issue
                                        $_SESSION['success'] = "Child successfully registered with ID: " . $childID . ".";
                                        $_SESSION['error'] = "Africa's Talking API credentials are not properly configured. SMS notification was not sent. Please update the API credentials in the .env file.";
                                    } else if (isset($smsResult['details']) && strpos($smsResult['details'], 'API credentials') !== false) {
                                        // Authentication failed with API
                                        $_SESSION['success'] = "Child successfully registered with ID: " . $childID . ".";
                                        $_SESSION['error'] = "Authentication failed with Africa's Talking API. SMS notification was not sent. Please check your credentials in the .env file.";
                                    } else if (isset($smsResult['details']) && strpos($smsResult['details'], 'SMS service') !== false) {
                                        // SMS service not running
                                        $_SESSION['success'] = "Child successfully registered with ID: " . $childID . ".";
                                        $_SESSION['warning'] = "SMS notification could not be sent: SMS service is not running. Please start the SMS service.";
                                    } else {
                                        // Generic error
                                        $_SESSION['success'] = "Child successfully registered with ID: " . $childID . ".";
                                        $_SESSION['warning'] = "SMS notification could not be sent: " . 
                                            (isset($smsResult['message']) ? $smsResult['message'] : 
                                            (isset($smsResult['error']) ? $smsResult['error'] : 'Unknown error')) . 
                                            ". Please check the SMS service logs for details.";
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Exception while sending registration SMS for child {$childID}: " . $e->getMessage());
                                $_SESSION['success'] = "Child successfully registered with ID: " . $childID . ".";
                                $_SESSION['warning'] = "There was an error sending the SMS notification: " . $e->getMessage() . 
                                    ". Please check that the SMS service is running.";
                            }
                        } else {
                            $error = "Error registering child. Please try again.";
                        }

                        // Set a flag to prevent resubmission on refresh
                        $_SESSION['form_submitted'] = true;
                        
                        // Generate a new form token for future submissions
                        $_SESSION['form_token'] = bin2hex(random_bytes(32));
                        
                        header("Location: children.php");
                        exit();
                    } catch (PDOException $e) {
                        // Log the error but don't prevent child registration if schedule generation fails
                        error_log("Failed to generate vaccination schedule: " . $e->getMessage());
                        // Still redirect with basic success message
                        $_SESSION['success'] = "Child registered successfully!";
                        
                        // Set a flag to prevent resubmission on refresh
                        $_SESSION['form_submitted'] = true;
                        
                        // Generate a new form token for future submissions
                        $_SESSION['form_token'] = bin2hex(random_bytes(32));
                        
                        header("Location: children.php");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error = "Failed to register child. Please try again.";
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

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40"></div>

    <!-- Registration Modal -->
    <div id="registrationModal" class="hidden fixed inset-0 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg shadow-2xl w-full max-w-2xl animate__animated">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                        </path>
                    </svg>
                    Register New Child
                </h3>
                <button id="closeModal" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
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
                                <label for="address" class="block text-gray-300 text-sm font-medium mb-2">Residential Address</label>
                                <textarea id="address" name="address" rows="2" required
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Medical History -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-green-400 uppercase tracking-wider">Medical History</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="birth_complications" class="block text-gray-300 text-sm font-medium mb-2">Birth Complications</label>
                                <textarea id="birth_complications" name="birth_complications" rows="2"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"
                                          placeholder="Describe any complications during birth (if any)"></textarea>
                            </div>
                            <div>
                                <label for="allergies" class="block text-gray-300 text-sm font-medium mb-2">Known Allergies</label>
                                <textarea id="allergies" name="allergies" rows="2"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"
                                          placeholder="List any known allergies"></textarea>
                            </div>
                            <div>
                                <label for="previous_vaccinations" class="block text-gray-300 text-sm font-medium mb-2">Previous Vaccinations</label>
                                <textarea id="previous_vaccinations" name="previous_vaccinations" rows="2"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"
                                          placeholder="List any previous vaccinations"></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 
                                   text-white py-3 rounded-lg transition duration-300 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Register Child
                    </button>
                </form>
            </div>
        </div>
    </div>

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
    <div class="ml-64 mt-16 p-6">
        <!-- Page Header with Search -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-white">Children Management</h1>
                <p class="text-blue-200">Register and manage children's immunization records</p>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Search Bar -->
                <div class="w-64">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search children..." 
                               class="w-full pl-10 pr-4 py-2 bg-gray-700/50 border border-gray-600 text-white rounded-lg 
                                      focus:ring-2 focus:ring-blue-500 transition duration-300">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                <!-- Register Button -->
                <button id="showFormButton" 
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-300 
                               flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span>Register Child</span>
                </button>
            </div>
        </div>

        <!-- Registration Form (Hidden by default) -->
        <div id="registrationForm" class="hidden mb-6">
            <div class="bg-gray-800 rounded-lg shadow-lg p-6 animate__animated animate__fadeIn">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                            </path>
                        </svg>
                        Register New Child
                    </h2>
                    <button type="button" id="closeForm" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

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
                                <label for="address" class="block text-gray-300 text-sm font-medium mb-2">Residential Address</label>
                                <textarea id="address" name="address" rows="2" required
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Medical History -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-green-400 uppercase tracking-wider">Medical History</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="birth_complications" class="block text-gray-300 text-sm font-medium mb-2">Birth Complications</label>
                                <textarea id="birth_complications" name="birth_complications" rows="2"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"
                                          placeholder="Describe any complications during birth (if any)"></textarea>
                            </div>
                            <div>
                                <label for="allergies" class="block text-gray-300 text-sm font-medium mb-2">Known Allergies</label>
                                <textarea id="allergies" name="allergies" rows="2"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"
                                          placeholder="List any known allergies"></textarea>
                            </div>
                            <div>
                                <label for="previous_vaccinations" class="block text-gray-300 text-sm font-medium mb-2">Previous Vaccinations</label>
                                <textarea id="previous_vaccinations" name="previous_vaccinations" rows="2"
                                          class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                 focus:ring-2 focus:ring-blue-500 transition duration-300"
                                          placeholder="List any previous vaccinations"></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 
                                   text-white py-3 rounded-lg transition duration-300 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Register Child
                    </button>
                </form>
            </div>
        </div>

        <!-- Children List -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="max-h-[calc(100vh-12rem)] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Child Info</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Gender</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Age & Weight</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Parent Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700" id="childrenTable">
                        <?php foreach ($children as $child): 
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
                        ?>
                            <tr class="hover:bg-gray-700/50 transition duration-300 cursor-pointer" 
                                onclick="window.location.href='child_profile.php?id=<?php echo htmlspecialchars($child['child_id']); ?>'">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-blue-500/10 flex items-center justify-center">
                                                <?php if ($child['gender'] === 'Male'): ?>
                                                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-6 h-6 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-white">
                                                <?php echo htmlspecialchars($child['full_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                ID: <?php echo htmlspecialchars($child['child_id']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php echo $child['gender'] === 'Male' ? 'bg-blue-500/10 text-blue-400' : 'bg-pink-500/10 text-pink-400'; ?>">
                                        <?php echo htmlspecialchars($child['gender']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-white"><?php echo htmlspecialchars($ageString); ?></div>
                                    <div class="text-sm text-gray-400">
                                        <?php echo htmlspecialchars($child['birth_weight']); ?> kg
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-white"><?php echo htmlspecialchars($child['guardian_name']); ?></div>
                                    <div class="text-sm text-gray-400"><?php echo htmlspecialchars($child['phone']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Update the form toggle functionality
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
        <?php if (isset($success)): ?>
        registrationForm.classList.add('hidden');
        <?php endif; ?>

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const childrenTable = document.getElementById('childrenTable').getElementsByTagName('tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = searchInput.value.toLowerCase();
            Array.from(childrenTable).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Auto-hide notifications
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.classList.remove('animate__fadeInRight');
                notification.classList.add('animate__fadeOutRight');
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 1000);
            }, 5000);
        });

        // Clear success parameter from URL
        if (window.location.search.includes('success')) {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url);
        }

        // Date of birth validation
        const dateOfBirthInput = document.getElementById('date_of_birth');
        
        // Set max date to today
        const today = new Date();
        const dd = String(today.getDate()).padStart(2, '0');
        const mm = String(today.getMonth() + 1).padStart(2, '0'); // January is 0!
        const yyyy = today.getFullYear();
        const maxDate = yyyy + '-' + mm + '-' + dd;
        
        dateOfBirthInput.setAttribute('max', maxDate);
        
        // Add event listener to validate date
        dateOfBirthInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            if (selectedDate > today) {
                alert('Date of birth cannot be in the future');
                this.value = ''; // Clear the invalid date
            }
        });

        // JavaScript for form validation and functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Clear success parameter from URL
            if (window.location.search.includes('success')) {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, '', url);
            }
            
            // Hide notifications after 5 seconds
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('animate__fadeInRight');
                    notification.classList.add('animate__fadeOutRight');
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 1000);
                }, 5000);
            });

            // Hide form after successful registration
            <?php if (isset($_SESSION['success']) || isset($success)): ?>
            // Hide registration modal if open
            document.querySelectorAll('.modal-close').forEach(button => {
                button.click();
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
