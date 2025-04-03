<?php
require_once 'db.php';

/**
 * Get all vaccines from the database
 * 
 * @return array Array of vaccine records
 */
function getAllVaccines() {
    global $conn;
    try {
        $stmt = $conn->query("SELECT * FROM vaccines ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching vaccines: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a single vaccine by ID
 * 
 * @param int $id Vaccine ID
 * @return array|false Vaccine record or false if not found
 */
function getVaccineById($id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM vaccines WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching vaccine: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a vaccine by name
 * 
 * @param string $name Vaccine name
 * @return array|false Vaccine record or false if not found
 */
function getVaccineByName($name) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM vaccines WHERE name = :name");
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get vaccine schedule for a specific vaccine
 * 
 * @param int $vaccineId Vaccine ID
 * @return array Array of schedule records
 */
function getVaccineSchedule($vaccineId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT vs.*, v.name as vaccine_name 
        FROM vaccine_schedule vs
        JOIN vaccines v ON vs.vaccine_id = v.id
        WHERE vs.vaccine_id = :vaccine_id
        ORDER BY vs.age_value
    ");
    $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all vaccine schedules with vaccine information
 * 
 * @return array Array of schedule records with vaccine information
 */
function getAllVaccineSchedules() {
    global $conn;
    $stmt = $conn->prepare("
        SELECT vs.*, v.name as vaccine_name, v.target_disease, v.description
        FROM vaccine_schedule vs
        JOIN vaccines v ON vs.vaccine_id = v.id
        ORDER BY vs.age_unit, vs.age_value, v.name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get vaccine schedule by age (useful for determining which vaccines a child should have)
 * 
 * @param int $ageInDays Age in days
 * @return array Array of vaccine schedules that apply to the given age
 */
function getVaccineScheduleByAge($ageInDays) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT vs.*, v.name as vaccine_name, v.target_disease 
        FROM vaccine_schedule vs
        JOIN vaccines v ON vs.vaccine_id = v.id
        WHERE 
            (vs.age_unit = 'days' AND vs.age_value <= :days) OR
            (vs.age_unit = 'weeks' AND vs.age_value * 7 <= :days) OR
            (vs.age_unit = 'months' AND vs.age_value * 30 <= :days) OR
            (vs.age_unit = 'years' AND vs.age_value * 365 <= :days)
        ORDER BY 
            CASE vs.age_unit
                WHEN 'days' THEN vs.age_value
                WHEN 'weeks' THEN vs.age_value * 7
                WHEN 'months' THEN vs.age_value * 30
                WHEN 'years' THEN vs.age_value * 365
            END
    ");
    $stmt->bindParam(':days', $ageInDays, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get inventory items for a specific vaccine
 * 
 * @param int $vaccineId Vaccine ID
 * @return array Array of inventory records
 */
function getVaccineInventory($vaccineId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT i.*, v.name as vaccine_name 
        FROM inventory i
        JOIN vaccines v ON i.vaccine_id = v.id
        WHERE i.vaccine_id = :vaccine_id
        ORDER BY i.expiry_date
    ");
    $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all inventory items (now using consolidated vaccines table)
 * 
 * @return array Array of vaccine records with inventory information
 */
function getAllInventory() {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT * FROM vaccines 
            ORDER BY name, expiry_date
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching inventory: " . $e->getMessage());
        return [];
    }
}

/**
 * Add new inventory item
 * 
 * @param int $vaccineId Vaccine ID
 * @param string $batchNumber Batch number
 * @param int $quantity Quantity
 * @param string $expiryDate Expiry date (YYYY-MM-DD)
 * @return bool True if successful, false otherwise
 */
function addInventoryItem($vaccineId, $batchNumber, $quantity, $expiryDate) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            INSERT INTO inventory (vaccine_id, batch_number, quantity, expiry_date)
            VALUES (:vaccine_id, :batch_number, :quantity, :expiry_date)
        ");
        $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
        $stmt->bindParam(':batch_number', $batchNumber, PDO::PARAM_STR);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':expiry_date', $expiryDate, PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        // Handle unique constraint violation
        if ($e->getCode() == 23000) {
            // Update quantity instead if batch already exists
            $stmt = $conn->prepare("
                UPDATE inventory 
                SET quantity = quantity + :quantity
                WHERE vaccine_id = :vaccine_id AND batch_number = :batch_number
            ");
            $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
            $stmt->bindParam(':batch_number', $batchNumber, PDO::PARAM_STR);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            return $stmt->execute();
        } else {
            throw $e;
        }
    }
}

/**
 * Update inventory item quantity
 * 
 * @param int $inventoryId Inventory ID
 * @param int $quantity New quantity
 * @return bool True if successful, false otherwise
 */
function updateInventoryQuantity($inventoryId, $quantity) {
    global $conn;
    $stmt = $conn->prepare("
        UPDATE inventory 
        SET quantity = :quantity
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $inventoryId, PDO::PARAM_INT);
    $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Get child's vaccination history
 * 
 * @param string $childId Child ID
 * @return array Array of vaccination records
 */
function getChildVaccinations($childId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT 
            v.*, 
            vac.name as vaccine_name,
            vac.target_disease,
            u.username as administered_by_name,
            vac.batch_number
        FROM vaccinations v
        JOIN vaccines vac ON v.vaccine_id = vac.id
        LEFT JOIN users u ON v.administered_by = u.id
        WHERE v.child_id = :child_id
        ORDER BY v.administered_date
    ");
    $stmt->bindParam(':child_id', $childId, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get child's upcoming vaccinations based on age and vaccination history
 * 
 * @param string $childId Child ID
 * @param int $ageInDays Child's age in days
 * @return array Array of upcoming vaccinations
 */
function getChildUpcomingVaccinations($childId, $ageInDays) {
    // First, get all vaccines scheduled up to the child's current age
    $scheduledVaccines = getVaccineScheduleByAge($ageInDays);
    
    // Then, get all vaccines already administered to the child
    global $conn;
    $stmt = $conn->prepare("
        SELECT vaccine_id, dose_number
        FROM vaccinations
        WHERE child_id = :child_id AND status = 'Administered'
    ");
    $stmt->bindParam(':child_id', $childId, PDO::PARAM_STR);
    $stmt->execute();
    $administeredVaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert administered vaccines to a lookup array for quick checking
    $administeredLookup = [];
    foreach ($administeredVaccines as $administered) {
        $key = $administered['vaccine_id'] . '-' . $administered['dose_number'];
        $administeredLookup[$key] = true;
    }
    
    // Filter out vaccines that have already been administered
    $upcomingVaccines = [];
    foreach ($scheduledVaccines as $scheduled) {
        $key = $scheduled['vaccine_id'] . '-' . $scheduled['dose_number'];
        if (!isset($administeredLookup[$key])) {
            $upcomingVaccines[] = $scheduled;
        }
    }
    
    return $upcomingVaccines;
}

/**
 * Record a vaccination for a child
 * 
 * @param string $childId Child ID
 * @param int $vaccineId Vaccine ID
 * @param int $doseNumber Dose number
 * @param int $appointmentId Appointment ID (optional)
 * @param string $administeredDate Administration date (YYYY-MM-DD)
 * @param int $administeredBy User ID who administered the vaccine
 * @param string $administrationSite Body site of administration
 * @param string $notes Additional notes
 * @param string $sideEffects Side effects
 * @param string $status Status (Administered, Missed, Cancelled)
 * @return bool True if successful, false otherwise
 */
function recordVaccination($childId, $vaccineId, $doseNumber, $appointmentId, 
                          $administeredDate, $administeredBy, $administrationSite, 
                          $notes, $sideEffects, $status = 'Administered') {
    global $conn;
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert vaccination record
        $stmt = $conn->prepare("
            INSERT INTO vaccinations (
                child_id, vaccine_id, dose_number, appointment_id,
                administered_date, administered_by, administration_site,
                notes, side_effects, status
            ) VALUES (
                :child_id, :vaccine_id, :dose_number, :appointment_id,
                :administered_date, :administered_by, :administration_site,
                :notes, :side_effects, :status
            )
        ");
        
        // Bind parameters
        $stmt->bindParam(':child_id', $childId, PDO::PARAM_STR);
        $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
        $stmt->bindParam(':dose_number', $doseNumber, PDO::PARAM_INT);
        
        if ($appointmentId) {
            $stmt->bindParam(':appointment_id', $appointmentId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':appointment_id', null, PDO::PARAM_NULL);
        }
        
        $stmt->bindParam(':administered_date', $administeredDate, PDO::PARAM_STR);
        $stmt->bindParam(':administered_by', $administeredBy, PDO::PARAM_INT);
        $stmt->bindParam(':administration_site', $administrationSite, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->bindParam(':side_effects', $sideEffects, PDO::PARAM_STR);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        // If successful and status is 'Administered', update vaccine quantity
        if ($result && $status === 'Administered') {
            $stmt = $conn->prepare("
                UPDATE vaccines 
                SET quantity = quantity - 1
                WHERE id = :vaccine_id
            ");
            $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // If appointment provided, update appointment_vaccines status
        if ($appointmentId) {
            $stmt = $conn->prepare("
                UPDATE appointment_vaccines 
                SET status = :status
                WHERE appointment_id = :appointment_id 
                AND vaccine_id = :vaccine_id 
                AND dose_number = :dose_number
            ");
            
            $appointmentStatus = ($status === 'Administered') ? 'administered' : 'missed';
            $stmt->bindParam(':status', $appointmentStatus, PDO::PARAM_STR);
            $stmt->bindParam(':appointment_id', $appointmentId, PDO::PARAM_INT);
            $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
            $stmt->bindParam(':dose_number', $doseNumber, PDO::PARAM_INT);
            $stmt->execute();
            
            // Check if all appointment vaccines are completed/missed
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
                FROM appointment_vaccines
                WHERE appointment_id = :appointment_id
            ");
            $stmt->bindParam(':appointment_id', $appointmentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update appointment status based on vaccines status
            if ($result['scheduled'] == 0) {
                $appointmentStatus = 'completed';
                $stmt = $conn->prepare("
                    UPDATE appointments 
                    SET status = 'completed'
                    WHERE id = :appointment_id
                ");
                $stmt->bindParam(':appointment_id', $appointmentId, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Error recording vaccination: " . $e->getMessage());
        return false;
    }
}

/**
 * Schedule an appointment for a child
 * 
 * @param string $childId Child ID
 * @param string $scheduledDate Scheduled date (YYYY-MM-DD)
 * @param array $vaccines Array of vaccines to schedule [['vaccine_id' => x, 'dose_number' => y], ...]
 * @param string $notes Additional notes
 * @return int|false Appointment ID if successful, false otherwise
 */
function scheduleAppointment($childId, $scheduledDate, $vaccines, $notes = '') {
    global $conn;
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (child_id, scheduled_date, notes)
            VALUES (:child_id, :scheduled_date, :notes)
        ");
        $stmt->bindParam(':child_id', $childId, PDO::PARAM_STR);
        $stmt->bindParam(':scheduled_date', $scheduledDate, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->execute();
        
        $appointmentId = $conn->lastInsertId();
        
        // Add vaccines to appointment
        $stmt = $conn->prepare("
            INSERT INTO appointment_vaccines (appointment_id, vaccine_id, dose_number)
            VALUES (:appointment_id, :vaccine_id, :dose_number)
        ");
        
        foreach ($vaccines as $vaccine) {
            $stmt->bindParam(':appointment_id', $appointmentId, PDO::PARAM_INT);
            $stmt->bindParam(':vaccine_id', $vaccine['vaccine_id'], PDO::PARAM_INT);
            $stmt->bindParam(':dose_number', $vaccine['dose_number'], PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        return $appointmentId;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Error scheduling appointment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get upcoming appointments
 * 
 * @param string $fromDate Start date for range (YYYY-MM-DD, defaults to today)
 * @param string $toDate End date for range (YYYY-MM-DD, defaults to 7 days from now)
 * @return array Array of upcoming appointments with child and vaccine information
 */
function getUpcomingAppointments($fromDate = null, $toDate = null) {
    global $conn;
    
    if (!$fromDate) {
        $fromDate = date('Y-m-d');
    }
    
    if (!$toDate) {
        $toDate = date('Y-m-d', strtotime('+7 days'));
    }
    
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            c.full_name as child_name,
            c.date_of_birth,
            c.guardian_name,
            c.phone,
            COUNT(av.id) as vaccine_count
        FROM appointments a
        JOIN children c ON a.child_id = c.child_id
        LEFT JOIN appointment_vaccines av ON a.id = av.appointment_id
        WHERE 
            a.scheduled_date BETWEEN :from_date AND :to_date
            AND a.status IN ('scheduled')
        GROUP BY a.id
        ORDER BY a.scheduled_date
    ");
    $stmt->bindParam(':from_date', $fromDate, PDO::PARAM_STR);
    $stmt->bindParam(':to_date', $toDate, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get vaccines scheduled for an appointment
 * 
 * @param int $appointmentId Appointment ID
 * @return array Array of vaccines scheduled for the appointment
 */
function getAppointmentVaccines($appointmentId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            av.*,
            v.name as vaccine_name,
            v.target_disease,
            v.description,
            v.administration_method,
            v.dosage
        FROM appointment_vaccines av
        JOIN vaccines v ON av.vaccine_id = v.id
        WHERE av.appointment_id = :appointment_id
        ORDER BY v.name, av.dose_number
    ");
    $stmt->bindParam(':appointment_id', $appointmentId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate vaccination report
 * 
 * @param string $startDate Start date (YYYY-MM-DD)
 * @param string $endDate End date (YYYY-MM-DD)
 * @param string $reportType Type of report ('vaccine', 'age_group', 'location', etc.)
 * @return array Report data
 */
function generateVaccinationReport($startDate, $endDate, $reportType = 'vaccine') {
    global $conn;
    
    switch ($reportType) {
        case 'vaccine':
            $query = "
                SELECT 
                    v.name as vaccine_name,
                    COUNT(*) as count
                FROM vaccinations vac
                JOIN vaccines v ON vac.vaccine_id = v.id
                WHERE 
                    vac.administered_date BETWEEN :start_date AND :end_date
                    AND vac.status = 'Administered'
                GROUP BY v.id
                ORDER BY count DESC
            ";
            break;
            
        case 'age_group':
            $query = "
                SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(MONTH, c.date_of_birth, vac.administered_date) < 6 THEN 'Under 6 months'
                        WHEN TIMESTAMPDIFF(MONTH, c.date_of_birth, vac.administered_date) < 12 THEN '6-11 months'
                        WHEN TIMESTAMPDIFF(MONTH, c.date_of_birth, vac.administered_date) < 24 THEN '12-23 months'
                        WHEN TIMESTAMPDIFF(MONTH, c.date_of_birth, vac.administered_date) < 36 THEN '24-35 months'
                        ELSE 'Over 3 years'
                    END as age_group,
                    COUNT(*) as count
                FROM vaccinations vac
                JOIN children c ON vac.child_id = c.child_id
                WHERE 
                    vac.administered_date BETWEEN :start_date AND :end_date
                    AND vac.status = 'Administered'
                GROUP BY age_group
                ORDER BY 
                    CASE age_group
                        WHEN 'Under 6 months' THEN 1
                        WHEN '6-11 months' THEN 2
                        WHEN '12-23 months' THEN 3
                        WHEN '24-35 months' THEN 4
                        ELSE 5
                    END
            ";
            break;
            
        default:
            return [];
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Log an SMS message
 * 
 * @param string $childId Child ID (optional)
 * @param int $vaccineId Vaccine ID (optional)
 * @param int $doseNumber Dose number (optional)
 * @param string $recipient Recipient phone number
 * @param string $message Message text
 * @param string $response Response from SMS provider
 * @param string $status Status ('success' or 'failed')
 * @param string $messageType Type of message ('registration', 'reminder', 'missed', 'rescheduled')
 * @return bool True if successful, false otherwise
 */
function logSmsMessage($childId, $vaccineId, $doseNumber, $recipient, $message, $response, 
                       $status = 'success', $messageType = 'reminder') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO sms_logs (
                child_id, vaccine_id, dose_number, recipient, message, 
                response, status, message_type
            ) VALUES (
                :child_id, :vaccine_id, :dose_number, :recipient, :message, 
                :response, :status, :message_type
            )
        ");
        
        // Bind parameters
        if ($childId) {
            $stmt->bindParam(':child_id', $childId, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':child_id', null, PDO::PARAM_NULL);
        }
        
        if ($vaccineId) {
            $stmt->bindParam(':vaccine_id', $vaccineId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':vaccine_id', null, PDO::PARAM_NULL);
        }
        
        if ($doseNumber) {
            $stmt->bindParam(':dose_number', $doseNumber, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':dose_number', null, PDO::PARAM_NULL);
        }
        
        $stmt->bindParam(':recipient', $recipient, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':response', $response, PDO::PARAM_STR);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':message_type', $messageType, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error logging SMS: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete an inventory item
 * 
 * @param int $inventoryId Inventory item ID to delete
 * @return bool True if successful, false otherwise
 */
function deleteInventoryItem($inventoryId) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            DELETE FROM inventory 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $inventoryId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error deleting inventory item: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a new vaccine to the database
 * 
 * @param string $name Vaccine name
 * @param string $target_disease Target disease
 * @param string $manufacturer Manufacturer
 * @param string $batch_number Batch number
 * @param int $quantity Quantity
 * @param string $expiry_date Expiry date
 * @param int $max_doses Maximum doses
 * @param string $administration_method Administration method
 * @param string $dosage Dosage
 * @param string $storage_requirements Storage requirements
 * @param string $contraindications Contraindications
 * @param string $side_effects Side effects
 * @return bool Success status
 */
function addVaccine($name, $target_disease, $manufacturer, $batch_number, $quantity, $expiry_date, 
                   $max_doses, $administration_method, $dosage, $storage_requirements, 
                   $contraindications, $side_effects) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            INSERT INTO vaccines (
                name, target_disease, manufacturer, batch_number, quantity, 
                expiry_date, max_doses, administration_method, dosage, 
                storage_requirements, contraindications, side_effects
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        return $stmt->execute([
            $name, $target_disease, $manufacturer, $batch_number, $quantity,
            $expiry_date, $max_doses, $administration_method, $dosage,
            $storage_requirements, $contraindications, $side_effects
        ]);
    } catch (PDOException $e) {
        error_log("Error adding vaccine: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a vaccine's quantity
 * 
 * @param int $id Vaccine ID
 * @param int $quantity New quantity
 * @return bool Success status
 */
function updateVaccineQuantity($id, $quantity) {
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE vaccines SET quantity = ? WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    } catch (PDOException $e) {
        error_log("Error updating vaccine quantity: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a vaccine from the database
 * 
 * @param int $id Vaccine ID
 * @return bool Success status
 */
function deleteVaccine($id) {
    global $conn;
    try {
        $stmt = $conn->prepare("DELETE FROM vaccines WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error deleting vaccine: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all vaccines with low stock (less than 10)
 * 
 * @return array Array of low stock vaccines
 */
function getLowStockVaccines() {
    global $conn;
    try {
        $stmt = $conn->query("SELECT * FROM vaccines WHERE quantity < 10 ORDER BY quantity ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching low stock vaccines: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all expired vaccines
 * 
 * @return array Array of expired vaccines
 */
function getExpiredVaccines() {
    global $conn;
    try {
        $stmt = $conn->query("SELECT * FROM vaccines WHERE expiry_date < CURDATE() ORDER BY expiry_date ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching expired vaccines: " . $e->getMessage());
        return [];
    }
}

/**
 * Update a vaccine's details
 * 
 * @param int $id Vaccine ID
 * @param string $batch_number New batch number
 * @param int $quantity New quantity
 * @param string $expiry_date New expiry date
 * @param string $manufacturer New manufacturer
 * @param string $target_disease New target disease
 * @param int $max_doses New max doses
 * @param string $administration_method New administration method
 * @param string $dosage New dosage
 * @param string $storage_requirements New storage requirements
 * @param string $contraindications New contraindications
 * @param string $side_effects New side effects
 * @return bool Success status
 */
function updateVaccine($id, $batch_number, $quantity, $expiry_date, $manufacturer, 
                      $target_disease = null, $max_doses = null, $administration_method = null,
                      $dosage = null, $storage_requirements = null, $contraindications = null, 
                      $side_effects = null) {
    global $conn;
    try {
        // Build the query dynamically based on which fields are provided
        $updateFields = [
            'batch_number = ?',
            'quantity = ?',
            'expiry_date = ?',
            'manufacturer = ?'
        ];
        $params = [$batch_number, $quantity, $expiry_date, $manufacturer];
        
        // Add optional fields if they're provided
        if ($target_disease !== null) {
            $updateFields[] = 'target_disease = ?';
            $params[] = $target_disease;
        }
        
        if ($max_doses !== null) {
            $updateFields[] = 'max_doses = ?';
            $params[] = $max_doses;
        }
        
        if ($administration_method !== null) {
            $updateFields[] = 'administration_method = ?';
            $params[] = $administration_method;
        }
        
        if ($dosage !== null) {
            $updateFields[] = 'dosage = ?';
            $params[] = $dosage;
        }
        
        if ($storage_requirements !== null) {
            $updateFields[] = 'storage_requirements = ?';
            $params[] = $storage_requirements;
        }
        
        if ($contraindications !== null) {
            $updateFields[] = 'contraindications = ?';
            $params[] = $contraindications;
        }
        
        if ($side_effects !== null) {
            $updateFields[] = 'side_effects = ?';
            $params[] = $side_effects;
        }
        
        // Add ID to params
        $params[] = $id;
        
        // Create the final query
        $query = "UPDATE vaccines SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating vaccine: " . $e->getMessage());
        return false;
    }
}
?> 