
<?php
session_start();
require_once __DIR__ . '/config/database.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';

if (isset($_SESSION['staff_id'])) {
    header('Location: item.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT staff_id, full_name, password_hash
             FROM staff WHERE username = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($staff && !empty($staff['password_hash'])
            && password_verify($password, $staff['password_hash'])) {

            session_regenerate_id(true);
            $_SESSION['staff_id'] = (int) $staff['staff_id'];
            $_SESSION['staff_name'] = $staff['full_name'];

            header('Location: item.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>
    <section class="card">
        <h1>Staff Login</h1>
        <p>Sign in to manage found items and claims.</p>

        <?php if ($error !== ''): ?>
            <div class="message"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Username</label>
            <input id="username" name="username" maxlength="50" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
            <a class="button button-secondary"
               href="staff_signup.php">Create Account</a>
        </form>
    </section>
</main>
</body>
</html>
