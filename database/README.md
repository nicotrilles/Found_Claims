# Database Setup

This folder contains SQL resources for the School Lost and Found System.

## Files

- [`schema.sql`](schema.sql) — SQL script intended to create the database structure and insert initial item categories.

## Before Running the Script

**The SQL file does not execute automatically.** Saving it in VS Code or pushing it to GitHub does not change your database.

Before importing or executing it:

1. Back up your current database using phpMyAdmin's **Export** feature.
2. Review the SQL statements to understand what they create or insert.
3. Prefer testing the script against a separate test database.
4. Check whether the database, tables, or category records already exist before running it against an existing database.

## Importing the Schema

If you are setting up a fresh development database:

1. Start MySQL in XAMPP.
2. Open phpMyAdmin.
3. Select the intended test database, or create one if needed.
4. Open the **Import** tab.
5. Choose `schema.sql` and execute the import.
6. Review the result for errors and confirm that the expected tables and category records exist.

The exact steps depend on the statements inside `schema.sql`. Check whether the script creates its own database before selecting a target.

## Important

The schema is a reconstructed setup script. It should not be treated as a backup of existing records or as a way to recover deleted data.

Do not run it against a working database until you have reviewed it and confirmed that it is appropriate for that database.