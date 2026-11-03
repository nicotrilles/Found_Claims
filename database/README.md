# Database Setup and Migrations

This directory contains the SQL resources used to create and update the database for the School Lost and Found Management System.

The application uses **MySQL / MariaDB** and is intended for local development through XAMPP and phpMyAdmin.

## Database Files

| File | Description |
|---|---|
| `schema.sql` | Creates the initial database structure and inserts initial item categories. |
| `migrations/2026_10_optional_reporters_nonstudent_claimants.sql` | Updates the existing database to support anonymous and non-student reporters and claimants. |

## Database Tables

The database contains the following tables:

| Table | Description |
|---|---|
| `students` | Stores student information. |
| `staff` | Stores staff information. |
| `categories` | Stores item categories. |
| `reports` | Stores found-item reports and reporter information. |
| `items` | Stores found-item records. |
| `claims` | Stores ownership claims and claimant information. |
| `item_comments` | Stores comments associated with found items. |

## Initial Database Setup

Follow these instructions when creating a new development database.

### 1. Start XAMPP

1. Open the XAMPP Control Panel.
2. Start Apache.
3. Start MySQL.

### 2. Create a Database

1. Open phpMyAdmin.
2. Create a new database.
3. Use the database name you intend to configure in the application.

### 3. Import the Initial Schema

1. Select your newly created database.
2. Open the **Import** tab.
3. Choose `schema.sql`.
4. Execute the import.
5. Check that the expected tables and category records were created.

Review the SQL file before importing it. Check its database-selection statements to ensure that it targets the intended database.

### 4. Apply the Migration

After importing the initial schema, apply the migration:

```text
migrations/2026_10_optional_reporters_nonstudent_claimants.sql
```

Before executing it, check the `USE` statement at the beginning of the file and make sure it targets your intended database.

The migration expects the initial `reports` and `claims` tables to exist with their original `student_id` columns.

## Migration: Optional Reporters and Non-Student Claimants

The migration adds support for individuals who report or claim found items without requiring a student record.

### Changes to the `reports` Table

The migration:

- Makes `student_id` nullable.
- Adds `reporter_type`.
- Adds `reporter_name`.
- Adds `reporter_contact`.

Supported reporter types:

- Student
- Faculty
- Guard
- Guest
- Anonymous

Anonymous reports do not require personal information.

### Changes to the `claims` Table

The migration:

- Makes `student_id` nullable.
- Adds `claimant_type`.
- Adds `claimant_name`.
- Adds `claimant_contact`.
- Adds `id_type`.
- Adds `id_verified`, with a default value of `0`.

Supported claimant types:

- Student
- Faculty
- Guard
- Guest

Non-student claimants must provide their identifying information and have their physical ID inspected by staff.

The system does not store the claimant's ID number or an image of their ID.

### Existing Record Preservation

The migration updates the existing database structure without recreating the tables.

It also:

- Labels existing reports as Student reports.
- Labels existing linked student claims as Student claims.
- Copies the linked student's name and contact number into the claim snapshot fields.
- Preserves existing records and their identifiers.

The migration should be executed only once per database.

## Applying a Migration to an Existing Database

Before applying any migration:

1. Export the database using phpMyAdmin.
2. Save the SQL backup in a safe location.
3. Test the migration against a separate database first.
4. Review the migration's `USE` statement.
5. Confirm that the migration has not already been applied.

To execute the migration:

1. Open phpMyAdmin.
2. Select the intended database.
3. Open the **SQL** tab.
4. Paste the migration SQL or import the migration file.
5. Execute it.
6. Review the result for errors.

**Important:** Do not execute the same migration more than once. It adds columns and changes existing table definitions.

## Verifying the Migration

After applying the migration, check the table structures:

```sql
DESCRIBE reports;
DESCRIBE claims;
```

Confirm that:

- `reports.student_id` accepts `NULL`.
- `claims.student_id` accepts `NULL`.
- The new reporter columns exist in `reports`.
- The new claimant columns exist in `claims`.
- `id_verified` has a default value of `0`.

You can also verify that the existing records remain in the database:

```sql
SELECT COUNT(*) FROM students;
SELECT COUNT(*) FROM staff;
SELECT COUNT(*) FROM categories;
SELECT COUNT(*) FROM reports;
SELECT COUNT(*) FROM items;
SELECT COUNT(*) FROM claims;
SELECT COUNT(*) FROM item_comments;
```

Compare the results with your pre-migration backup.

## Important Notes

- `schema.sql` is intended for initial database setup, not for updating an existing database with records.
- Migration files modify an existing database structure and should be reviewed before execution.
- Always use a separate test database when validating migrations.
- Never run a migration against the wrong database.
- Keep database backups before making structural changes.
- Update the database connection in the application's `config/database.php` file to point to the intended database.

