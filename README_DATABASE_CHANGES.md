# Database Schema Update Guide

This guide explains the changes made to the database schema to improve consistency and reduce redundancy in the Child Immunization System.

## Database Changes Overview

The database schema has been optimized to:

1. Eliminate redundant tables
2. Consolidate vaccine-related data
3. Improve foreign key relationships
4. Ensure data integrity
5. Support comprehensive reporting

## Main Changes

1. Created a single consolidated `vaccines` table that stores all vaccine information
2. Created a normalized `vaccine_schedule` table that references the vaccines table
3. Created an improved `inventory` table that tracks batches for specific vaccines
4. Updated the `vaccinations` table to properly reference other tables
5. Updated the `sms_logs` table to reference vaccine IDs instead of vaccine names
6. Added proper foreign key constraints between tables

## How to Implement These Changes

Follow these steps to implement the schema changes:

1. **Backup Your Database**: Always create a backup of your database before making schema changes
   ```sql
   mysqldump -u [username] -p immunization_system > immunization_system_backup.sql
   ```

2. **Execute the Schema Update Script**: Access the database update script through your web browser:
   ```
   http://localhost/Child-Immunization-v2/backend/db_schema_update.php
   ```

3. **Verify the Schema Changes**: After running the update script, verify that all tables were created correctly by checking the output messages.

4. **Update Your PHP Code**: Make sure to update your application code to use the helper functions provided in `vaccine_helpers.php`.

## Key New Relationships

- One vaccine can have multiple schedule entries (doses)
- One vaccine can have multiple inventory batches
- One child can receive many vaccinations
- One appointment can schedule multiple vaccines
- Vaccinations are linked to specific inventory batches
- All SMS notifications can reference specific vaccine IDs

## Using the Helper Functions

The new `vaccine_helpers.php` file provides functions for interacting with the updated schema:

- `getAllVaccines()` - Gets all vaccines
- `getVaccineById($id)` - Gets a vaccine by ID
- `getVaccineSchedule($vaccineId)` - Gets scheduled doses for a vaccine
- `getAllInventory()` - Gets all inventory items
- `getChildVaccinations($childId)` - Gets a child's vaccination history
- `recordVaccination(...)` - Records a vaccination for a child
- `scheduleAppointment(...)` - Schedules a vaccination appointment
- And many more...

## Future Maintenance

If you need to make changes to the database schema in the future, follow these guidelines:

1. Always make a backup first
2. Update all related tables together
3. Update foreign key constraints
4. Update application code to work with the new schema

For any questions or issues, please contact the system administrator. 