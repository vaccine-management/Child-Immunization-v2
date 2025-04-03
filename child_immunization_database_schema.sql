-- Child Immunization System Database Schema
-- This script creates all tables and relationships required for the Child Immunization System
-- Compatible with MySQL Workbench

-- Drop database if it exists (BE CAREFUL with this in production!)
-- DROP DATABASE IF EXISTS immunization_system;

-- Create database
CREATE DATABASE IF NOT EXISTS immunization_system;
USE immunization_system;

-- Disable foreign key checks temporarily for easier table creation
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Nurse') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Children table
CREATE TABLE IF NOT EXISTS children (
    child_id VARCHAR(50) PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    birth_weight DECIMAL(5,2) NOT NULL,
    place_of_birth VARCHAR(50) NOT NULL,
    guardian_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT NOT NULL,
    birth_complications TEXT,
    allergies TEXT,
    previous_vaccinations TEXT,
    registration_date DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires DATETIME NOT NULL
);

-- 4. Vaccines table
CREATE TABLE IF NOT EXISTS vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    max_doses INT NOT NULL,
    target_disease VARCHAR(255) NOT NULL,
    description TEXT,
    administration_method VARCHAR(100),
    dosage VARCHAR(50),
    manufacturer VARCHAR(255),
    storage_requirements TEXT,
    contraindications TEXT,
    side_effects TEXT,
    batch_number VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_batch (name, batch_number)
);

-- 5. Vaccine schedule table
CREATE TABLE IF NOT EXISTS vaccine_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_id INT NOT NULL,
    vaccine_name VARCHAR(255) NOT NULL,
    dose_number INT NOT NULL,
    age_unit ENUM('days', 'weeks', 'months', 'years') NOT NULL,
    age_value INT NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    notes TEXT,
    administration_method VARCHAR(100),
    dosage VARCHAR(50),
    target_disease VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vaccine_dose (vaccine_id, dose_number),
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
);

-- 6. Inventory table
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_id INT NOT NULL,
    transaction_type ENUM('received', 'administered', 'expired', 'damaged') NOT NULL,
    quantity INT NOT NULL,
    transaction_date DATE NOT NULL,
    batch_number VARCHAR(255),
    expiry_date DATE,
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 7. Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id VARCHAR(50) NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME DEFAULT '09:00:00',
    status ENUM('scheduled', 'completed', 'missed', 'rescheduled', 'cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE
);

-- 8. Vaccinations table
CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id VARCHAR(50) NOT NULL,
    vaccine_id INT,
    vaccine_name VARCHAR(255) NOT NULL,
    dose_number INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME,
    administered_date DATE,
    status ENUM('Scheduled', 'Administered', 'Missed', 'Rescheduled', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
    administered_by INT,
    notes TEXT,
    adverse_events TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE SET NULL,
    FOREIGN KEY (administered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 9. Appointment Vaccines (Junction Table)
CREATE TABLE IF NOT EXISTS appointment_vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    vaccine_id INT,
    vaccine_name VARCHAR(255) NOT NULL,
    dose_number INT NOT NULL,
    status ENUM('scheduled', 'administered', 'missed') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE SET NULL
);

-- 10. SMS Logs table
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id VARCHAR(50),
    vaccine_id INT,
    dose_number INT,
    recipient VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    response TEXT,
    status ENUM('success', 'failed') DEFAULT 'success',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    message_type ENUM('registration', 'reminder', 'missed', 'rescheduled') NOT NULL,
    FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE SET NULL,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE SET NULL,
    INDEX idx_child (child_id),
    INDEX idx_recipient (recipient),
    INDEX idx_sent_at (sent_at),
    INDEX idx_message_type (message_type),
    INDEX idx_status (status)
);

-- 11. Medical Records table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id VARCHAR(50) NOT NULL,
    birth_complications TEXT,
    allergies TEXT,
    previous_vaccinations TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE
);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Populate vaccines with initial data
INSERT INTO vaccines (name, max_doses, target_disease, description, administration_method, dosage, batch_number, quantity, expiry_date) VALUES
('BCG', 1, 'Tuberculosis', 'Bacillus Calmette-Guerin', 'intradermal injection', '0.05ml', 'BCG-2023-001', 100, '2024-12-31'),
('Polio Vaccine', 4, 'Poliomyelitis', 'Oral Polio Vaccine', 'oral', '2 drops', 'OPV-2023-001', 100, '2024-12-31'),
('Pentavalent', 3, 'Diphtheria, Pertussis, Tetanus, Hepatitis B, Haemophilus Influenza', 'Pentavalent Vaccine (or Hexaxim/Infanrix)', 'intramuscular injection', '0.5ml', 'PENTA-2023-001', 100, '2024-12-31'),
('Pneumococcal', 3, 'Pneumococcal disease', 'Pneumococcal Vaccine (Prevenar 13)', 'intramuscular injection', '0.5ml', 'PCV-2023-001', 100, '2024-12-31'),
('Rotavirus', 3, 'Rotavirus infection', 'Rota Virus Vaccine (Rotarix)', 'oral', '1.5ml', 'ROTA-2023-001', 100, '2024-12-31'),
('Vitamin A', 5, 'Vitamin A deficiency', 'Vitamin A supplementation', 'oral', '100,000/200,000 IU', 'VITA-2023-001', 100, '2024-12-31'),
('Flu', 2, 'Influenza', 'Influenza Vaccine', 'injection', '0.5ml', 'FLU-2023-001', 100, '2024-12-31'),
('Measles-Rubella', 2, 'Measles and Rubella', 'Measles Rubella Vaccine', 'subcutaneous injection', '0.5ml', 'MR-2023-001', 100, '2024-12-31'),
('Meningococcal', 2, 'Meningococcal disease', 'Meningococcal ACYW Conjugate Vaccine', 'injection', '0.5ml', 'MENING-2023-001', 100, '2024-12-31'),
('Deworming', 4, 'Intestinal worms', 'Deworming with Albendazole', 'oral', '200mg', 'DEWORM-2023-001', 100, '2024-12-31'),
('Hepatitis-A', 1, 'Hepatitis A', 'Hepatitis A Vaccine', 'injection', '0.5ml', 'HEPA-2023-001', 100, '2024-12-31'),
('Varicella', 1, 'Chickenpox', 'Varicella (Chickenpox) Vaccine', 'injection', '0.5ml', 'VAR-2023-001', 100, '2024-12-31'),
('MMR', 2, 'Measles, Mumps, and Rubella', 'Measles, Mumps, Rubella Vaccine', 'injection', '0.5ml', 'MMR-2023-001', 100, '2024-12-31'),
('Typhoid', 1, 'Typhoid fever', 'Typhoid Vaccine', 'injection', '0.5ml', 'TYP-2023-001', 100, '2024-12-31');

-- Populate vaccine schedule
INSERT INTO vaccine_schedule (vaccine_id, vaccine_name, dose_number, age_unit, age_value, notes, administration_method, dosage, target_disease)
SELECT 
    v.id as vaccine_id,
    v.name as vaccine_name,
    s.dose_number,
    s.age_unit,
    s.age_value,
    s.notes,
    v.administration_method,
    v.dosage,
    v.target_disease
FROM 
(
    -- BCG at birth
    SELECT 'BCG' as name, 1 as dose_number, 'days' as age_unit, 0 as age_value, 'Administered in left forearm' as notes
    
    -- Polio doses
    UNION SELECT 'Polio Vaccine', 1, 'days', 0, 'Oral administration'
    UNION SELECT 'Polio Vaccine', 2, 'weeks', 6, 'Oral administration'
    UNION SELECT 'Polio Vaccine', 3, 'weeks', 10, 'Oral administration'
    UNION SELECT 'Polio Vaccine', 4, 'weeks', 14, 'Oral administration'
    
    -- Pentavalent doses
    UNION SELECT 'Pentavalent', 1, 'weeks', 6, 'Administered in left outer thigh'
    UNION SELECT 'Pentavalent', 2, 'weeks', 10, 'Administered in left outer thigh'
    UNION SELECT 'Pentavalent', 3, 'weeks', 14, 'Administered in left outer thigh'
    
    -- Pneumococcal doses
    UNION SELECT 'Pneumococcal', 1, 'weeks', 6, 'Administered in right outer thigh'
    UNION SELECT 'Pneumococcal', 2, 'weeks', 10, 'Administered in right outer thigh'
    UNION SELECT 'Pneumococcal', 3, 'weeks', 14, 'Administered in right outer thigh'
    
    -- Rotavirus doses
    UNION SELECT 'Rotavirus', 1, 'weeks', 6, 'Oral administration'
    UNION SELECT 'Rotavirus', 2, 'weeks', 10, 'Oral administration'
    UNION SELECT 'Rotavirus', 3, 'weeks', 14, 'Oral administration'
    
    -- Vitamin A doses
    UNION SELECT 'Vitamin A', 1, 'months', 6, 'Oral administration'
    UNION SELECT 'Vitamin A', 2, 'months', 12, 'Oral administration'
    UNION SELECT 'Vitamin A', 3, 'months', 18, 'Oral administration'
    UNION SELECT 'Vitamin A', 4, 'years', 2, NULL
    UNION SELECT 'Vitamin A', 5, 'years', 5, NULL
    
    -- Flu doses
    UNION SELECT 'Flu', 1, 'months', 7, NULL
    UNION SELECT 'Flu', 2, 'years', 2, 'Annual vaccination'
    
    -- Measles-Rubella doses
    UNION SELECT 'Measles-Rubella', 1, 'months', 9, 'Administered in right upper arm'
    UNION SELECT 'Measles-Rubella', 2, 'months', 18, 'Administered in right upper arm'
    
    -- Meningococcal doses
    UNION SELECT 'Meningococcal', 1, 'months', 10, 'Protects against strains A, C, Y, and W'
    UNION SELECT 'Meningococcal', 2, 'months', 15, NULL
    
    -- Deworming doses
    UNION SELECT 'Deworming', 1, 'months', 12, 'Oral administration'
    UNION SELECT 'Deworming', 2, 'months', 18, 'Oral administration'
    UNION SELECT 'Deworming', 3, 'years', 2, NULL
    UNION SELECT 'Deworming', 4, 'years', 5, NULL
    
    -- Hepatitis-A dose
    UNION SELECT 'Hepatitis-A', 1, 'months', 12, NULL
    
    -- Varicella dose
    UNION SELECT 'Varicella', 1, 'months', 12, NULL
    
    -- MMR doses
    UNION SELECT 'MMR', 1, 'months', 15, NULL
    UNION SELECT 'MMR', 2, 'months', 18, NULL
    
    -- Typhoid dose
    UNION SELECT 'Typhoid', 1, 'years', 2, NULL
) as s
JOIN vaccines v ON v.name = s.name;

-- Create default admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@example.com', '$2y$10$qNCY7MFZhUyb9UVxrE.cDuUBn92YP/J/jFvzqAjQwIbkBB3X3yZOa', 'Admin');

-- Display all tables created
SHOW TABLES;

-- Success message
SELECT 'Child Immunization Database Setup Complete' as 'Success'; 