-- Script to create admin and nurse users in the immunization system
USE immunization_system;

-- Check if users already exist
SET @admin_exists = (SELECT COUNT(*) FROM users WHERE email = 'admin@example.com');
SET @nurse_exists = (SELECT COUNT(*) FROM users WHERE email = 'nurse@example.com');

-- Only insert if they don't exist
-- Password: admin123 (bcrypt hashed)
INSERT INTO users (username, email, password, role, created_at)
SELECT 'admin', 'admin@example.com', '$2y$10$qNCY7MFZhUyb9UVxrE.cDuUBn92YP/J/jFvzqAjQwIbkBB3X3yZOa', 'Admin', NOW()
WHERE @admin_exists = 0;

-- Password: nurse123 (bcrypt hashed)
INSERT INTO users (username, email, password, role, created_at)  
SELECT 'nurse', 'nurse@example.com', '$2y$10$0JsNLmZxR5C5W5FXcgHvx.Km3OyZUvHHkMaRGIE8eyM2KLzxO8Tm2', 'Nurse', NOW()
WHERE @nurse_exists = 0;

-- Show the created users
SELECT id, username, email, role, created_at FROM users 
WHERE email IN ('admin@example.com', 'nurse@example.com'); 