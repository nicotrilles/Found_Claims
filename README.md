# School Lost and Found Management System

A web-based Lost and Found Management System developed for **Aces Polytechnic College**. The system helps the school's Lost and Found Office record found items, manage item information, process ownership claims, and monitor the return of items to their rightful owners.

The system is operated by authorized staff. Students and other individuals can report found items or submit claims without needing their own system accounts.

## Features

### Found Item Reporting
- Record found items submitted to the Lost and Found Office.
- Support different reporter types:
  - Student
  - Faculty
  - Guard
  - Guest
  - Anonymous
- Allow existing students to be searched and selected through student autocomplete.
- Store item details, descriptions, categories, colors, brands, and locations found.
- Support image uploads for found items.

### Item Management
- View and manage recorded found items.
- Organize items by category.
- Track item statuses, including Found, Claimed, and Returned.
- View item details and related records.

### Claim Processing
- Accept claims from students, faculty, guards, and guests.
- Search for existing student records or register a student when necessary.
- Require non-student claimants to provide their name, contact information, and ID type.
- Require staff to confirm that a non-student claimant's physical ID was inspected.
- Record ownership verification details.
- Keep submitted claims pending until staff review.

### Claim Verification and Decisions
- Allow authorized staff to review submitted claims.
- Approve or reject claims based on the ownership verification process.
- Update item status when a claim is approved.
- Maintain claim records for reference.

### Staff and Records Management
- Provide staff authentication.
- Store student and staff information.
- Maintain item categories, reports, claims, and item comments.

## Technologies Used

- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS, JavaScript
- **Local Development:** XAMPP
- **Database Management:** phpMyAdmin
- **Version Control:** Git and GitHub

## Database Structure

The system uses the following main database tables:

| Table | Purpose |
|---|---|
| `students` | Stores student information. |
| `staff` | Stores staff information. |
| `categories` | Stores found-item categories. |
| `reports` | Records found-item reports and reporter information. |
| `items` | Stores information about found items. |
| `claims` | Records ownership claims and verification details. |
| `item_comments` | Stores comments associated with found items. |

For more information about database installation and migrations, see [`database/README.md`](database/README.md).

## Project Structure

```text
Found_Claims/
├── ajax/
│   └── search_students.php
├── config/
│   └── database.php
├── css/
│   └── style.css
├── database/
│   ├── migrations/
│   │   └── 2026_10_optional_reporters_nonstudent_claimants.sql
│   ├── README.md
│   └── schema.sql
├── includes/
│   ├── auth.php
│   └── navigation.php
├── uploads/
├── claim.php
├── claim_item.php
├── index.php
├── item.php
├── item_comments.php
├── public_items.php
├── report_item.php
├── right_join.php
├── search_item.php
├── staff_comments.php
├── staff_login.php
├── staff_logout.php
├── staff_signup.php
└── README.md
```

*This structure highlights the main application files. Some folders may contain additional files.*

## Requirements

Before running the application, prepare the following:

- XAMPP or another compatible PHP and MySQL/MariaDB environment.
- PHP version compatible with the project's code.
- MySQL or MariaDB.
- A modern web browser.
- phpMyAdmin or another database management tool.
- Git (optional, for cloning the repository).

## Installation and Setup

### 1. Clone the Repository

Open a terminal and run:

```bash
git clone https://github.com/nicotrilles/Found_Claims.git
```

Place the project inside your XAMPP `htdocs` directory.

Example:

```text
C:\xampp\htdocs\Found_Claims
```

### 2. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Create the Database

1. Open phpMyAdmin.
2. Create a database for the application.
3. Review [`database/schema.sql`](database/schema.sql).
4. Import the schema into the database.

If you are setting up a fresh database, follow the additional instructions in [`database/README.md`](database/README.md), including the migration required for the optional reporter and non-student claimant features.

### 4. Configure the Database Connection

Open:

```text
config/database.php
```

Set the database host, username, password, and database name according to your local environment.

Do not commit database credentials or other sensitive information to the repository.

### 5. Open the Application

Open your browser and visit:

```text
http://localhost/Found_Claims/
```

Navigate to the available application pages.

## Security and Data Handling

- Staff authentication is required for protected staff operations.
- Passwords should be stored using secure password hashing.
- Database operations should use prepared statements.
- Validate and sanitize user input.
- Validate uploaded files and restrict accepted file types and sizes.
- Do not store non-student ID numbers or ID images.
- The `id_verified` field indicates that staff physically inspected a claimant's ID. It does not independently establish ownership of an item.
- Keep database backups before applying schema changes or migrations.

## Project Status

This project is developed as an academic system for Aces Polytechnic College. Its features and documentation may be updated as development continues.

## License

No license has been specified yet. Unless a license is added, reuse and redistribution are subject to applicable copyright rules.