
<?php
session_start();

if (isset($_SESSION['staff_id'])) {
    header('Location: item.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$active_tab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // STAFF SIGN UP
    if ($action === 'signup') {
        $active_tab = 'signup';

        $full_name = trim($_POST['full_name'] ?? '');
        $contact_no = trim($_POST['contact_no'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($full_name === '' || $contact_no === '' || $username === '') {
            $error = 'Please complete all fields.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
            $error = 'Username must be 4–50 characters using letters, numbers, or underscores.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must contain at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO staff
                    (full_name, contact_no, username, password_hash)
                    VALUES (?, ?, ?, ?)'
                );

                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt->bind_param(
                    'ssss',
                    $full_name,
                    $contact_no,
                    $username,
                    $password_hash
                );

                $stmt->execute();
                $stmt->close();

                // Log in the newly created staff account.
                session_regenerate_id(true);

                $stmt = $conn->prepare(
                    'SELECT staff_id, full_name
                     FROM staff WHERE username = ?'
                );
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $staff = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $_SESSION['staff_id'] = (int) $staff['staff_id'];
                $_SESSION['staff_name'] = $staff['full_name'];

                header('Location: item.php');
                exit;

            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }

                $error = 'Unable to create the account. The username may already exist.';
            }
        }
    }

    // STAFF LOGIN
    elseif ($action === 'login') {
        $active_tab = 'login';

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

            if (
                $staff &&
                !empty($staff['password_hash']) &&
                password_verify($password, $staff['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['staff_id'] = (int) $staff['staff_id'];
                $_SESSION['staff_name'] = $staff['full_name'];

                header('Location: item.php');
                exit;
            }

            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Access | Found Items System</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .access-container {
            max-width: 460px;
            margin: 40px auto;
            padding: 28px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
        }

        .access-tabs {
            display: flex;
            gap: 8px;
            margin: 20px 0;
        }

        .access-tabs button {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f5f5f5;
            color: #222;
            cursor: pointer;
        }

        .access-tabs button.active {
            background: #111;
            color: #fff;
        }

        .access-form label {
            display: block;
            margin: 12px 0 6px;
        }

        .access-form input {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .access-form button[type="submit"] {
            width: 100%;
            margin-top: 20px;
        }

        .access-message {
            padding: 12px;
            margin-bottom: 15px;
            background: #fff0f0;
            color: #a11;
            border-radius: 6px;
        }

        .access-form[hidden] {
            display: none;
        }
    </style>
</head>
<body>
<main class="access-container">
    <h1>Found Items &amp; Claims</h1>
    <p>Staff access portal</p>

    <?php if ($error !== ''): ?>
        <div class="access-message"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="access-tabs">
        <button type="button" id="loginTab"
                class="<?= $active_tab === 'login' ? 'active' : '' ?>"
                onclick="showTab('login')">
            Login
        </button>

        <button type="button" id="signupTab"
                class="<?= $active_tab === 'signup' ? 'active' : '' ?>"
                onclick="showTab('signup')">
            Sign Up
        </button>
    </div>

    <form method="POST" id="loginForm" class="access-form"
          <?= $active_tab === 'login' ? '' : 'hidden' ?>>
        <input type="hidden" name="action" value="login">

        <label for="login_username">Username</label>
        <input id="login_username" name="username"
               maxlength="50" required>

        <label for="login_password">Password</label>
        <input type="password" id="login_password"
               name="password" required>

        <button type="submit">Login</button>
    </form>

    <form method="POST" id="signupForm" class="access-form"
          <?= $active_tab === 'signup' ? '' : 'hidden' ?>>
        <input type="hidden" name="action" value="signup">

        <label for="full_name">Full Name</label>
        <input id="full_name" name="full_name"
               maxlength="100" required>

        <label for="contact_no">Contact Number</label>
        <input id="contact_no" name="contact_no"
               maxlength="20" required>

        <label for="signup_username">Username</label>
        <input id="signup_username" name="username"
               minlength="4" maxlength="50"
               pattern="[a-zA-Z0-9_]{4,50}" required>

        <label for="signup_password">Password (at least 8 characters)</label>
        <input type="password" id="signup_password" name="password"
               minlength="8" required>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password"
               name="confirm_password" minlength="8" required>

        <button type="submit">Create Account</button>
    </form>
</main>

<script>
function showTab(tab) {
    const login = tab === 'login';

    document.getElementById('loginForm').hidden = !login;
    document.getElementById('signupForm').hidden = login;

    document.getElementById('loginTab').classList.toggle('active', login);
    document.getElementById('signupTab').classList.toggle('active', !login);
}
</script>
</body>
</html>
