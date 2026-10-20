
<?php
require_once __DIR__ . '/config/database.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $contact_no === '' || $username === '') {
        $error = 'Please complete all required fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
        $error = 'Username must be 4–50 characters using letters, numbers, or underscores.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $check = $conn->prepare(
                'SELECT staff_id FROM staff WHERE username = ?'
            );
            $check->bind_param('s', $username);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $error = 'That username is already taken.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    'INSERT INTO staff
                    (full_name, contact_no, username, password_hash)
                    VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'ssss',
                    $full_name,
                    $contact_no,
                    $username,
                    $password_hash
                );
                $stmt->execute();
                $stmt->close();

                $success = 'Staff account created. You can now log in.';
            }
        } catch (Throwable $ex) {
            $error = 'Unable to create the account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Sign Up</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>
    <section class="card">
        <h1>Staff Sign Up</h1>
        <p>Create an account for the Lost and Found system.</p>

        <?php if ($error !== ''): ?>
            <div class="message"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="message"><?= e($success) ?></div>
            <p><a class="button" href="staff_login.php">Go to Login</a></p>
        <?php else: ?>
            <form method="POST">
                <label for="full_name">Full Name</label>
                <input id="full_name" name="full_name"
                       maxlength="100" required>

                <label for="contact_no">Contact Number</label>
                <input id="contact_no" name="contact_no"
                       maxlength="20" required>

                <label for="username">Username</label>
                <input id="username" name="username"
                       minlength="4" maxlength="50"
                       pattern="[a-zA-Z0-9_]{4,50}" required>

                <label for="password">Password (minimum 8 characters)</label>
                <input type="password" id="password" name="password"
                       minlength="8" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password"
                       name="confirm_password" minlength="8" required>

                <button type="submit">Create Staff Account</button>
                <a class="button button-secondary"
                   href="staff_login.php">Already registered? Login</a>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
