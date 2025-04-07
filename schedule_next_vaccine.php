<?php
/**
 * Function to determine and schedule the next vaccine in sequence
 * @param PDO $conn Database connection
 * @param string $childId Child ID
 * @param string $dateOfBirth Child's date of birth
 * @param string $currentVaccineName Current vaccine name
 * @param int $currentDoseNumber Current dose number
 * @return array|null Information about the next scheduled vaccine or null if none
 */
function scheduleNextVaccine($conn, $childId, $dateOfBirth, $currentVaccineName, $currentDoseNumber) {
    // Get child's date of birth
    if (!$dateOfBirth) {
        $stmt = $conn->prepare("SELECT date_of_birth FROM children WHERE child_id = :child_id");
        $stmt->execute([':child_id' => $childId]);
        $dateOfBirth = $stmt->fetchColumn();
        
        if (!$dateOfBirth) {
            return null; // Can't proceed without DOB
        }
    }
    
    $birthDate = new DateTime($dateOfBirth);
    $today = new DateTime();
    
    // First, check if there's a next dose for the current vaccine
    $stmt = $conn->prepare("
        SELECT vs.*, v.name as vaccine_name, v.id as vaccine_id, v.target_disease, v.administration_method, v.dosage 
        FROM vaccine_schedule vs
        JOIN vaccines v ON vs.vaccine_id = v.id
        WHERE v.name = :vaccine_name 
        AND vs.dose_number = :next_dose_number
        ORDER BY vs.age_value ASC
    ");
    $stmt->execute([
        ':vaccine_name' => $currentVaccineName,
        ':next_dose_number' => $currentDoseNumber + 1
    ]);
    $nextDose = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no next dose for this vaccine, find the next vaccine in the schedule
    if (!$nextDose) {
        // Get all vaccines this child has already received
        $stmt = $conn->prepare("
            SELECT DISTINCT vaccine_name, MAX(dose_number) as max_dose
            FROM vaccinations 
            WHERE child_id = :child_id AND status = 'Administered'
            GROUP BY vaccine_name
        ");
        $stmt->execute([':child_id' => $childId]);
        $administeredVaccines = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get all scheduled vaccines
        $stmt = $conn->prepare("
            SELECT DISTINCT vaccine_name, MAX(dose_number) as max_dose
            FROM vaccinations 
            WHERE child_id = :child_id AND status = 'Scheduled'
            GROUP BY vaccine_name
        ");
        $stmt->execute([':child_id' => $childId]);
        $scheduledVaccines = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get the next vaccine in the schedule based on age
        $stmt = $conn->prepare("
            SELECT vs.*, v.name as vaccine_name, v.id as vaccine_id, v.target_disease, v.administration_method, v.dosage 
            FROM vaccine_schedule vs
            JOIN vaccines v ON vs.vaccine_id = v.id
            WHERE (v.name, vs.dose_number) NOT IN (
                SELECT vaccine_name, dose_number FROM vaccinations 
                WHERE child_id = :child_id AND status = 'Administered'
            )
            AND (v.name, vs.dose_number) NOT IN (
                SELECT vaccine_name, dose_number FROM vaccinations 
                WHERE child_id = :child_id AND status = 'Scheduled'
            )
            ORDER BY vs.age_value ASC, vs.dose_number ASC
            LIMIT 1
        ");
        $stmt->execute([':child_id' => $childId]);
        $nextVaccine = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nextVaccine) {
            return null; // No more vaccines to schedule
        }
        
        $nextDose = $nextVaccine;
    }
    
    // Calculate the scheduled date based on child's age
    $scheduledDate = clone $birthDate;
    
    try {
        // Calculate the scheduled date based on child's age
        switch ($nextDose['age_unit']) {
            case 'days':
                $scheduledDate->add(new DateInterval("P{$nextDose['age_value']}D"));
                break;
            case 'weeks':
                $scheduledDate->add(new DateInterval("P{$nextDose['age_value']}W"));
                break;
            case 'months':
                $scheduledDate->add(new DateInterval("P{$nextDose['age_value']}M"));
                break;
            case 'years':
                $scheduledDate->add(new DateInterval("P{$nextDose['age_value']}Y"));
                break;
            default:
                throw new Exception("Invalid age_unit: {$nextDose['age_unit']}");
        }
        
        // If the calculated date is in the past, schedule for next week
        if ($scheduledDate < $today) {
            $scheduledDate = clone $today;
            $scheduledDate->add(new DateInterval("P7D")); // Schedule for 1 week from today
        }
        
        $formattedDate = $scheduledDate->format('Y-m-d');
        
        // Start transaction
        $conn->beginTransaction();
        
        // Create the appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                child_id, scheduled_date, status, notes
            ) VALUES (
                :child_id, :scheduled_date, 'scheduled', :notes
            )
        ");
        $stmt->execute([
            ':child_id' => $childId,
            ':scheduled_date' => $formattedDate,
            ':notes' => "Auto-scheduled vaccination: {$nextDose['vaccine_name']} (Dose {$nextDose['dose_number']})"
        ]);
        $appointmentId = $conn->lastInsertId();
        
        // Create the vaccination record
        $stmt = $conn->prepare("
            INSERT INTO vaccinations (
                child_id, vaccine_id, vaccine_name, dose_number, scheduled_date, status, notes
            ) VALUES (
                :child_id, :vaccine_id, :vaccine_name, :dose_number, :scheduled_date, 'Scheduled', :notes
            )
        ");
        $stmt->execute([
            ':child_id' => $childId,
            ':vaccine_id' => $nextDose['vaccine_id'],
            ':vaccine_name' => $nextDose['vaccine_name'],
            ':dose_number' => $nextDose['dose_number'],
            ':scheduled_date' => $formattedDate,
            ':notes' => "Auto-scheduled after {$currentVaccineName} (Dose {$currentDoseNumber})"
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
            ':vaccine_id' => $nextDose['vaccine_id'],
            ':vaccine_name' => $nextDose['vaccine_name'],
            ':dose_number' => $nextDose['dose_number']
        ]);
        
        // Commit transaction
        $conn->commit();
        
        return [
            'vaccine_name' => $nextDose['vaccine_name'],
            'dose_number' => $nextDose['dose_number'],
            'scheduled_date' => $formattedDate,
            'appointment_id' => $appointmentId
        ];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error scheduling next vaccine: " . $e->getMessage());
        return null;
    }
}
