<?php
/**
 * SMS Templates for Child Immunization System
 * 
 * This file contains templates for different types of SMS messages sent by the system.
 * You can customize these templates to suit your needs while keeping the placeholders intact.
 * 
 * Available placeholders:
 * - {guardian_name} - Name of the child's guardian/parent
 * - {child_name} - Name of the child
 * - {vaccine_name} - Name of the vaccine
 * - {dose_number} - Dose number for the vaccine
 * - {due_date} - Date of the scheduled vaccination
 * - {new_date} - New date for rescheduled vaccinations
 * - {date_of_birth} - Child's date of birth
 */

return [
    // Template for registration notifications
    'registration' => "Dear {guardian_name}, thank you for registering {child_name} in the Immunization Program. We will send you reminders for upcoming vaccinations.",
    
    // Template for upcoming vaccination reminders
    'upcoming' => "Dear {guardian_name}, {child_name} is due for {vaccine_name} dose {dose_number} on {due_date}. Please visit your nearest health facility.",
    
    // Template for missed vaccination notifications
    'missed' => "Dear {guardian_name}, {child_name} has missed {vaccine_name} dose {dose_number} scheduled for {due_date}. Please visit your nearest health facility as soon as possible.",
    
    // Template for rescheduled vaccination notifications
    'rescheduled' => "Dear {guardian_name}, {child_name}'s appointment for {vaccine_name} dose {dose_number} has been rescheduled. Please visit your nearest health facility on {new_date}."
]; 