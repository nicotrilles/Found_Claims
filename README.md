# School Lost and Found System

A web-based Lost and Found Management System for Aces Polytechnic College. The system helps staff manage found items, student reports, ownership claims, and claim verification.

## Features

- **Found Item Reporting** — Record found items and the students who report them.
- **Item Management** — Store item descriptions, categories, locations, images, and statuses.
- **Student Records** — Manage student information associated with reports and claims.
- **Claim Processing** — Record ownership claims and their verification details.
- **Claim Decisions** — Approve or reject claims and update item statuses.
- **Category Management** — Classify found items into categories.
- **Staff Management** — Maintain staff information used to process reports and claims.

## Technologies Used

- PHP
- MySQL / MariaDB
- HTML and CSS
- XAMPP for local development
- Git and GitHub for version control

## Project Structure

```text
Found_Claims/
├── config/
├── css/
├── uploads/
├── student/
├── database/
│   ├── schema.sql
│   └── README.md
└── README.md
```

*This is a guide to the intended organization. Adjust it to match the actual files in the repository.*

## Requirements

- XAMPP or another compatible local PHP and MySQL/MariaDB environment
- A web browser
- A database management tool, such as phpMyAdmin
- Git (optional, for cloning the repository)

## Local Installation

1. Clone or download this repository into your local web server directory, such as `C:\xampp\htdocs\Found_Claims`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin and create or select the database named `school_lost_found`.
4. Configure the database connection in the project's configuration file. Use your local database credentials.
5. If you need to create the database structure from scratch, review [`database/schema.sql`](database/schema.sql) and follow the instructions in [`database/README.md`](database/README.md).
6. Open the application through your local server, for example `http://localhost/Found_Claims/`, and navigate to the appropriate page.

**Important:** Do not import the schema into an existing working database without backing it up and checking for conflicts first.

## Database

The database is designed to organize student information, staff records, item categories, reports, found items, claims, and item comments.

See [`database/README.md`](database/README.md) for information about the SQL file.

## Security Notes

- Do not commit database passwords or other secrets to a public repository.
- Store staff passwords as secure password hashes, not plaintext.
- Validate user input and use prepared statements for database operations.
- Restrict access to administrative actions and sensitive student information.

## Project Status

Developed as an academic project. Features and documentation may change as development continues.

## License

No license has been specified yet. Unless a license is added, reuse and redistribution are subject to the applicable copyright rules.