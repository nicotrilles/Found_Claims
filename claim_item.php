
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$item_id = $_GET['item_id'] ?? $_POST['item_id'] ?? '';
$student_no = trim($_POST['student_no'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$contact_no = trim($_POST['contact_no'] ?? '');
$error = '';
$success = '';
$student = null;
$student_found = false;
$student_registered = false;
$student_confirmed = false;
$action = $_POST['action'] ?? '';

if (
    !is_scalar($item_id) ||
    !ctype_digit((string) $item_id) ||
    (int) $item_id < 1
) {
    die('Invalid item selected. <a href="search_item.php">Return to Search Items</a>');
}

$item_id = (int) $item_id;

$item_id = (int) $item_id;

// Load the selected item.
$sql = "
    SELECT
        items.item_id,
        items.item_name,
        items.description,
        items.color,
        items.brand,
        items.location_found,
        items.item_status,
        categories.category_name
    FROM items
    INNER JOIN categories
        ON items.category_id = categories.category_id
    WHERE items.item_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    die('Item not found. <a href="item.php">Return to Found Items</a>');
}

// Only Found items may receive new claims.
if ($item['item_status'] !== 'Found' && $action !== 'create_claim') {
    die('This item is no longer available for claiming. <a href="item.php">Return to Found Items</a>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'find_student') {
            if (!preg_match('/^[0-9]{6}$/', $student_no)) {
                throw new Exception('Student number must contain exactly 6 digits.');
            }

            $sql = "
                SELECT student_id, student_no, full_name, contact_no
                FROM students
                WHERE student_no = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $student_no);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $student_found = ($student !== null);

        } elseif ($action === 'register_student') {
            if (!preg_match('/^[0-9]{6}$/', $student_no)) {
                throw new Exception('Student number must contain exactly 6 digits.');
            }

            if ($full_name === '' || $contact_no === '') {
                throw new Exception('Student name and contact number are required.');
            }

            $sql = "SELECT student_id FROM students WHERE student_no = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $student_no);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                throw new Exception('This student number is already registered. Use Find Student instead.');
            }

            $sql = "
                INSERT INTO students (student_no, full_name, contact_no)
                VALUES (?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $student_no, $full_name, $contact_no);
            $stmt->execute();
            $new_student_id = $conn->insert_id;
            $stmt->close();

            $sql = "
                SELECT student_id, student_no, full_name, contact_no
                FROM students
                WHERE student_id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $new_student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $student_registered = true;

        } elseif ($action === 'confirm_student') {
            $student_id = $_POST['student_id'] ?? '';

            if (!ctype_digit((string) $student_id) || (int) $student_id < 1) {
                throw new Exception('Invalid student.');
            }

            $sql = "
                SELECT student_id, student_no, full_name, contact_no
                FROM students
                WHERE student_id = ?
            ";
            $stmt = $conn->prepare($sql);
            $student_id = (int) $student_id;
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$student) {
                throw new Exception('Student not found.');
            }

            $student_confirmed = true;

        } elseif ($action === 'create_claim') {
            $student_id = $_POST['student_id'] ?? '';
            $verification_details = trim($_POST['verification_details'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if (!ctype_digit((string) $student_id) || (int) $student_id < 1) {
                throw new Exception('Invalid student.');
            }

            if ($verification_details === '') {
                throw new Exception('Ownership verification details are required.');
            }

            // Confirm the student exists.
            $student_id = (int) $student_id;
            $sql = "SELECT student_id FROM students WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $student_exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$student_exists) {
                throw new Exception('Student not found.');
            }

            // Recheck item availability before creating the claim.
            $conn->begin_transaction();

            $sql = "SELECT item_status FROM items WHERE item_id = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $current_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$current_item || $current_item['item_status'] !== 'Found') {
                throw new Exception('This item is no longer available for claiming.');
            }

            // Temporary staff ID until staff login is implemented.
            // $staff_id = 1;
            $staff_id = (int) $_SESSION['staff_id'];
            $claim_status = 'Pending';
            $claim_date = date('Y-m-d');

            $sql = "
                INSERT INTO claims (
                    item_id, student_id, staff_id, claim_date,
                    claim_status, verification_details, remarks
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'iiissss',
                $item_id,
                $student_id,
                $staff_id,
                $claim_date,
                $claim_status,
                $verification_details,
                $remarks
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header('Location: claim.php');
            exit();
        }
    } catch (Throwable $ex) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // No active transaction to roll back.
        }
        $error = $ex->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Found Item</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>
    <div class="page-intro">
        <h1>Claim Found Item</h1>
        <p class="muted">Review the item, identify the claimant, and record ownership verification.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="message"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="message"><?= e($success) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Selected Item</h2>
        <p><strong>Item:</strong> <?= e($item['item_name']) ?></p>
        <p><strong>Category:</strong> <?= e($item['category_name']) ?></p>
        <p><strong>Description:</strong> <?= e($item['description']) ?></p>
        <p><strong>Color:</strong> <?= e($item['color']) ?></p>
        <p><strong>Brand:</strong> <?= e($item['brand']) ?></p>
        <p><strong>Location Found:</strong> <?= e($item['location_found']) ?></p>
        <p><strong>Status:</strong> <span class="status"><?= e($item['item_status']) ?></span></p>
    </section>

    <section class="card">
        <h2>Find Claimant</h2>
        <form method="POST" action="claim_item.php?item_id=<?= $item_id ?>">
            <label for="student_no">Student Number (6 digits)</label>
            <input
                type="text"
                id="student_no"
                name="student_no"
                maxlength="6"
                minlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                value="<?= e($student_no) ?>"
                required
            >
            <button type="submit" name="action" value="find_student">Find Student</button>
        </form>
    </section>

    <?php if ($action === 'find_student' && $student_found && $student): ?>
        <section class="card">
            <h2>Student Found</h2>
            <p><strong>Student Number:</strong> <?= e($student['student_no']) ?></p>
            <p><strong>Name:</strong> <?= e($student['full_name']) ?></p>
            <p><strong>Contact:</strong> <?= e($student['contact_no']) ?></p>
            <p>Confirm that this is the correct claimant.</p>

            <form method="POST" action="claim_item.php?item_id=<?= $item_id ?>">
                <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                <button type="submit" name="action" value="confirm_student">Confirm Student</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($action === 'find_student' && !$student_found && $error === ''): ?>
        <section class="card">
            <h2>Student Not Found</h2>
            <p>No record matches this student number. Enter the student's information to register them.</p>

            <form method="POST" action="claim_item.php?item_id=<?= $item_id ?>">
                <input type="hidden" name="student_no" value="<?= e($student_no) ?>">

                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>

                <label for="contact_no">Contact Number</label>
                <input type="text" id="contact_no" name="contact_no" required>

                <button type="submit" name="action" value="register_student">Register Student</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($action === 'register_student' && $student_registered && $student): ?>
        <section class="card">
            <h2>Student Registered</h2>
            <p><strong>Student Number:</strong> <?= e($student['student_no']) ?></p>
            <p><strong>Full Name:</strong> <?= e($student['full_name']) ?></p>
            <p><strong>Contact Number:</strong> <?= e($student['contact_no']) ?></p>

            <form method="POST" action="claim_item.php?item_id=<?= $item_id ?>">
                <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                <button type="submit" name="action" value="confirm_student">Confirm Student</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($student_confirmed && $student): ?>
        <section class="card">
            <h2>Confirmed Claimant</h2>
            <p><strong>Student Number:</strong> <?= e($student['student_no']) ?></p>
            <p><strong>Full Name:</strong> <?= e($student['full_name']) ?></p>
            <p><strong>Contact Number:</strong> <?= e($student['contact_no']) ?></p>
        </section>

        <section class="card">
            <h2>Claim Details</h2>
            <form method="POST" action="claim_item.php?item_id=<?= $item_id ?>">
                <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">

                <label for="verification_details">Ownership Verification Details</label>
                <textarea
                    id="verification_details"
                    name="verification_details"
                    rows="4"
                    placeholder="Describe how ownership was verified..."
                    required
                ></textarea>

                <label for="remarks">Remarks (Optional)</label>
                <textarea
                    id="remarks"
                    name="remarks"
                    rows="3"
                    placeholder="Additional remarks..."
                ></textarea>

                <button type="submit" name="action" value="create_claim">Create Claim</button>
                <a class="button button-secondary" href="item.php">Cancel</a>
            </form>
        </section>
    <?php endif; ?>

    <p><a class="button button-secondary" href="item.php">Back to Found Items</a></p>
</main>

</body>
</html>
