
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

$claimant_type = trim($_POST['claimant_type'] ?? 'Student');

// Validate item ID.
if (
    !is_scalar($item_id) ||
    !ctype_digit((string) $item_id) ||
    (int) $item_id < 1
) {
    die('Invalid item selected. <a href="search_item.php">Return to Search Items</a>');
}

$item_id = (int) $item_id;

// Load selected item.
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

$item = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$item) {
    die('Item not found. <a href="item.php">Return to Found Items</a>');
}

// Only Found items may receive new claims.
if (
    $item['item_status'] !== 'Found' &&
    $action !== 'create_claim' &&
    $action !== 'create_nonstudent_claim'
) {
    die('This item is no longer available for claiming. <a href="item.php">Return to Found Items</a>');
}

// Process submitted forms.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $transactionStarted = false;

    try {

        // FIND EXISTING STUDENT
        if ($action === 'find_student') {

            if (!preg_match('/^[0-9]{6}$/', $student_no)) {
                throw new Exception(
                    'Student number must contain exactly 6 digits.'
                );
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

        // REGISTER NEW STUDENT
        } elseif ($action === 'register_student') {

            if (!preg_match('/^[0-9]{6}$/', $student_no)) {
                throw new Exception(
                    'Student number must contain exactly 6 digits.'
                );
            }

            if ($full_name === '' || $contact_no === '') {
                throw new Exception(
                    'Student name and contact number are required.'
                );
            }

            if (!preg_match('/^[0-9]{1,15}$/', $contact_no)) {
                throw new Exception(
                    'Contact number must contain 1 to 15 digits.'
                );
            }

            $sql = "
                SELECT student_id
                FROM students
                WHERE student_no = ?
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $student_no);
            $stmt->execute();

            $existing = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($existing) {
                throw new Exception(
                    'This student number is already registered. Use Find Student instead.'
                );
            }

            $sql = "
                INSERT INTO students (
                    student_no,
                    full_name,
                    contact_no
                )
                VALUES (?, ?, ?)
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                'sss',
                $student_no,
                $full_name,
                $contact_no
            );

            $stmt->execute();

            $new_student_id = (int) $conn->insert_id;

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

        // CONFIRM STUDENT
        } elseif ($action === 'confirm_student') {

            $student_id = $_POST['student_id'] ?? '';

            if (
                !ctype_digit((string) $student_id) ||
                (int) $student_id < 1
            ) {
                throw new Exception('Invalid student.');
            }

            $student_id = (int) $student_id;

            $sql = "
                SELECT student_id, student_no, full_name, contact_no
                FROM students
                WHERE student_id = ?
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $student_id);
            $stmt->execute();

            $student = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$student) {
                throw new Exception('Student not found.');
            }

            $student_confirmed = true;

        // CREATE STUDENT CLAIM
        } elseif ($action === 'create_claim') {

            $student_id = $_POST['student_id'] ?? '';

            $verification_details = trim(
                $_POST['verification_details'] ?? ''
            );

            $remarks = trim($_POST['remarks'] ?? '');

            if (
                !ctype_digit((string) $student_id) ||
                (int) $student_id < 1
            ) {
                throw new Exception('Invalid student.');
            }

            if ($verification_details === '') {
                throw new Exception(
                    'Ownership verification details are required.'
                );
            }

            $student_id = (int) $student_id;

            // Retrieve the student record again.
            // Never rely only on hidden form values.
            $sql = "
                SELECT student_id, full_name, contact_no
                FROM students
                WHERE student_id = ?
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $student_id);
            $stmt->execute();

            $student_record = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$student_record) {
                throw new Exception('Student not found.');
            }

            $claimant_type = 'Student';
            $claimant_name = $student_record['full_name'];
            $claimant_contact = $student_record['contact_no'];

            $id_type = null;
            $id_verified = 0;

        // CREATE NON-STUDENT CLAIM
        } elseif ($action === 'create_nonstudent_claim') {

            $allowed_types = [
                'Faculty',
                'Guard',
                'Guest'
            ];

            $full_name = trim($_POST['full_name'] ?? '');
            $contact_no = trim($_POST['contact_no'] ?? '');

            $id_type = trim($_POST['id_type'] ?? '');

            $id_verified = $_POST['id_verified'] ?? '';

            $verification_details = trim(
                $_POST['verification_details'] ?? ''
            );

            $remarks = trim($_POST['remarks'] ?? '');

            if (!in_array($claimant_type, $allowed_types, true)) {
                throw new Exception('Invalid claimant type.');
            }

            if ($full_name === '') {
                throw new Exception('Claimant full name is required.');
            }

            if (
                !preg_match('/^[0-9]{1,15}$/', $contact_no)
            ) {
                throw new Exception(
                    'A valid contact number containing 1 to 15 digits is required.'
                );
            }

            if ($id_type === '') {
                throw new Exception('Please select an ID type.');
            }

            if ($id_verified !== '1') {
                throw new Exception(
                    'Staff must confirm that the claimant\'s physical ID was inspected.'
                );
            }

            if ($verification_details === '') {
                throw new Exception(
                    'Ownership verification details are required.'
                );
            }

            // Non-students do not receive student records.
            $student_id = null;

            $claimant_name = $full_name;
            $claimant_contact = $contact_no;

            $id_verified = 1;

        } else {
            throw new Exception('Invalid form action.');
        }

        // Only claim creation actions continue to the transaction.
        if (
            $action === 'create_claim' ||
            $action === 'create_nonstudent_claim'
        ) {

            // Recheck availability and lock the item.
            $conn->begin_transaction();
            $transactionStarted = true;

            $sql = "
                SELECT item_status
                FROM items
                WHERE item_id = ?
                FOR UPDATE
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $item_id);
            $stmt->execute();

            $current_item = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (
                !$current_item ||
                $current_item['item_status'] !== 'Found'
            ) {
                throw new Exception(
                    'This item is no longer available for claiming.'
                );
            }

            // Staff member handling the claim.
            $staff_id = (int) $_SESSION['staff_id'];

            $claim_status = 'Pending';
            $claim_date = date('Y-m-d');

            // Insert the claim and claimant information.
            $sql = "
                INSERT INTO claims (
                    item_id,
                    student_id,
                    staff_id,
                    claim_date,
                    claim_status,
                    verification_details,
                    remarks,
                    claimant_type,
                    claimant_name,
                    claimant_contact,
                    id_type,
                    id_verified
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                'iiissssssssi',
                $item_id,
                $student_id,
                $staff_id,
                $claim_date,
                $claim_status,
                $verification_details,
                $remarks,
                $claimant_type,
                $claimant_name,
                $claimant_contact,
                $id_type,
                $id_verified
            );

            $stmt->execute();

            $stmt->close();

            // Save the claim.
            $conn->commit();
            $transactionStarted = false;

            header('Location: claim.php');
            exit();
        }

    } catch (Throwable $ex) {

        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // Preserve the original error.
            }
        }

        error_log(
            'Claim submission error: ' . $ex->getMessage()
        );

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

        <p class="muted">
            Review the item, identify the claimant, and record
            ownership verification.
        </p>

    </div>

    <?php if ($error !== ''): ?>

        <div class="message" role="alert">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ''): ?>

        <div class="message">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <!-- Selected Item -->
    <section class="card">

        <h2>Selected Item</h2>

        <p>
            <strong>Item:</strong>
            <?= e($item['item_name']) ?>
        </p>

        <p>
            <strong>Category:</strong>
            <?= e($item['category_name']) ?>
        </p>

        <p>
            <strong>Description:</strong>
            <?= e($item['description']) ?>
        </p>

        <p>
            <strong>Color:</strong>
            <?= e($item['color']) ?>
        </p>

        <p>
            <strong>Brand:</strong>
            <?= e($item['brand']) ?>
        </p>

        <p>
            <strong>Location Found:</strong>
            <?= e($item['location_found']) ?>
        </p>

        <p>
            <strong>Status:</strong>
            <span class="status">
                <?= e($item['item_status']) ?>
            </span>
        </p>

    </section>

    <!-- Claimant Type -->
    <section class="card">

        <h2>Identify Claimant</h2>

        <label for="claimant_type">Claimant Type</label>

        <select id="claimant_type">

            <option value="Student">Student</option>
            <option value="Faculty">Faculty</option>
            <option value="Guard">Guard</option>
            <option value="Guest">Guest</option>

        </select>

    </section>

    <!-- Student Workflow -->
    <div id="student_section">

        <section class="card">

            <h2>Find Student</h2>

            <form
                method="POST"
                action="claim_item.php?item_id=<?= $item_id ?>"
            >

                <label for="student_no">
                    Student Number (6 digits)
                </label>

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
                <div id="student_suggestions" class="autocomplete-list"></div>
                <button
                    type="submit"
                    name="action"
                    value="find_student"
                >
                    Find Student
                </button>

            </form>

        </section>

        <?php if (
            $action === 'find_student' &&
            $student_found &&
            $student
        ): ?>

            <section class="card">

                <h2>Student Found</h2>

                <p>
                    <strong>Student Number:</strong>
                    <?= e($student['student_no']) ?>
                </p>

                <p>
                    <strong>Name:</strong>
                    <?= e($student['full_name']) ?>
                </p>

                <p>
                    <strong>Contact:</strong>
                    <?= e($student['contact_no']) ?>
                </p>

                <p>Confirm that this is the correct claimant.</p>

                <form
                    method="POST"
                    action="claim_item.php?item_id=<?= $item_id ?>"
                >

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?= (int) $student['student_id'] ?>"
                    >

                    <button
                        type="submit"
                        name="action"
                        value="confirm_student"
                    >
                        Confirm Student
                    </button>

                </form>

            </section>

        <?php endif; ?>

        <?php if (
            $action === 'find_student' &&
            !$student_found &&
            $error === ''
        ): ?>

            <section class="card">

                <h2>Student Not Found</h2>

                <p>
                    No record matches this student number.
                    Enter the student's information to register them.
                </p>

                <form
                    method="POST"
                    action="claim_item.php?item_id=<?= $item_id ?>"
                >

                    <input
                        type="hidden"
                        name="student_no"
                        value="<?= e($student_no) ?>"
                    >

                    <label for="register_full_name">Full Name</label>

                    <input
                        type="text"
                        id="register_full_name"
                        name="full_name"
                        required
                    >

                    <label for="register_contact_no">
                        Contact Number
                    </label>

                    <input
                        type="tel"
                        id="register_contact_no"
                        name="contact_no"
                        maxlength="15"
                        pattern="[0-9]{1,15}"
                        inputmode="numeric"
                        required
                    >

                    <button
                        type="submit"
                        name="action"
                        value="register_student"
                    >
                        Register Student
                    </button>

                </form>

            </section>

        <?php endif; ?>

        <?php if (
            $action === 'register_student' &&
            $student_registered &&
            $student
        ): ?>

            <section class="card">

                <h2>Student Registered</h2>

                <p>
                    <strong>Student Number:</strong>
                    <?= e($student['student_no']) ?>
                </p>

                <p>
                    <strong>Full Name:</strong>
                    <?= e($student['full_name']) ?>
                </p>

                <p>
                    <strong>Contact Number:</strong>
                    <?= e($student['contact_no']) ?>
                </p>

                <form
                    method="POST"
                    action="claim_item.php?item_id=<?= $item_id ?>"
                >

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?= (int) $student['student_id'] ?>"
                    >

                    <button
                        type="submit"
                        name="action"
                        value="confirm_student"
                    >
                        Confirm Student
                    </button>

                </form>

            </section>

        <?php endif; ?>

        <?php if ($student_confirmed && $student): ?>

            <section class="card">

                <h2>Confirmed Claimant</h2>

                <p>
                    <strong>Student Number:</strong>
                    <?= e($student['student_no']) ?>
                </p>

                <p>
                    <strong>Full Name:</strong>
                    <?= e($student['full_name']) ?>
                </p>

                <p>
                    <strong>Contact Number:</strong>
                    <?= e($student['contact_no']) ?>
                </p>

            </section>

            <section class="card">

                <h2>Ownership Verification</h2>

                <p class="muted">
                    Record the details used by staff to verify
                    that the student owns this particular item.
                </p>

                <form
                    method="POST"
                    action="claim_item.php?item_id=<?= $item_id ?>"
                >

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?= (int) $student['student_id'] ?>"
                    >

                    <label for="student_verification_details">
                        Ownership Verification Details
                    </label>

                    <textarea
                        id="student_verification_details"
                        name="verification_details"
                        rows="4"
                        placeholder="Describe how ownership was verified..."
                        required
                    ></textarea>

                    <label for="student_remarks">
                        Remarks (Optional)
                    </label>

                    <textarea
                        id="student_remarks"
                        name="remarks"
                        rows="3"
                        placeholder="Additional remarks..."
                    ></textarea>

                    <button
                        type="submit"
                        name="action"
                        value="create_claim"
                    >
                        Create Claim
                    </button>

                    <a
                        class="button button-secondary"
                        href="item.php"
                    >
                        Cancel
                    </a>

                </form>

            </section>

        <?php endif; ?>

    </div>

    <!-- Non-Student Workflow -->
    <div id="nonstudent_section" hidden>

        <section class="card">

            <h2>Non-Student Claimant Information</h2>

            <p class="muted">
                Record the claimant's details and inspect their
                physical identification before proceeding.
            </p>

            <form
                method="POST"
                action="claim_item.php?item_id=<?= $item_id ?>"
            >

                <input
                    type="hidden"
                    name="claimant_type"
                    id="nonstudent_claimant_type"
                    value="Faculty"
                >

                <label for="nonstudent_full_name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="nonstudent_full_name"
                    name="full_name"
                    required
                >

                <label for="nonstudent_contact_no">
                    Contact Number
                </label>

                <input
                    type="tel"
                    id="nonstudent_contact_no"
                    name="contact_no"
                    maxlength="15"
                    pattern="[0-9]{1,15}"
                    inputmode="numeric"
                    required
                >

                <label for="id_type">
                    Type of Identification
                </label>

                <select
                    id="id_type"
                    name="id_type"
                    required
                >

                    <option value="">Select ID type</option>

                    <option value="School ID">School ID</option>
                    <option value="Government ID">Government ID</option>
                    <option value="Driver's License">Driver's License</option>
                    <option value="Passport">Passport</option>
                    <option value="Other Valid ID">Other Valid ID</option>

                </select>

                <div class="checkbox-group">
                    <label for="id_verified">
                        <input
                            type="checkbox"
                            id="id_verified"
                            name="id_verified"
                            value="1"
                            required
                        >
                        <span>
                            I confirm that I physically inspected
                            the claimant's identification.
                        </span>
                    </label>
                </div>

                <p class="muted">
                    Do not record the ID number or upload an image
                    of the identification document.
                </p>

                <hr>

                <h3>Ownership Verification</h3>

                <p class="muted">
                    Inspecting identification confirms the claimant's
                    identity, not ownership of the found item.
                </p>

                <label for="nonstudent_verification_details">
                    Ownership Verification Details
                </label>

                <textarea
                    id="nonstudent_verification_details"
                    name="verification_details"
                    rows="4"
                    placeholder="Describe how the claimant demonstrated ownership..."
                    required
                ></textarea>

                <label for="nonstudent_remarks">
                    Remarks (Optional)
                </label>

                <textarea
                    id="nonstudent_remarks"
                    name="remarks"
                    rows="3"
                    placeholder="Additional remarks..."
                ></textarea>

                <button
                    type="submit"
                    name="action"
                    value="create_nonstudent_claim"
                >
                    Create Claim
                </button>

                <a
                    class="button button-secondary"
                    href="item.php"
                >
                    Cancel
                </a>

            </form>

        </section>

    </div>

    <p>
        <a class="button button-secondary" href="item.php">
            Back to Found Items
        </a>
    </p>

</main>

<script>

    const claimantTypeSelect = document.getElementById('claimant_type');

    const studentSection = document.getElementById('student_section');

    const nonstudentSection = document.getElementById('nonstudent_section');

    const nonstudentClaimantType = document.getElementById(
        'nonstudent_claimant_type'
    );

    function updateClaimantFields() {

        const selectedType = claimantTypeSelect.value;

        const isStudent = selectedType === 'Student';

        studentSection.hidden = !isStudent;

        nonstudentSection.hidden = isStudent;

        nonstudentClaimantType.value = selectedType;

    }

    claimantTypeSelect.addEventListener(
        'change',
        updateClaimantFields
    );

    updateClaimantFields();


const studentNoInput = document.getElementById('student_no');
const suggestionsBox = document.getElementById('student_suggestions');

let searchTimeout;

studentNoInput.addEventListener('input', function () {
    const query = this.value.trim();

    clearTimeout(searchTimeout);
    suggestionsBox.innerHTML = '';

    if (!/^[0-9]{1,6}$/.test(query)) {
        return;
    }

    searchTimeout = setTimeout(async function () {
        try {
            const response = await fetch(
                'ajax/search_students.php?q=' + encodeURIComponent(query)
            );

            if (!response.ok) {
                throw new Error('Student search failed.');
            }

            const students = await response.json();

            suggestionsBox.innerHTML = '';

            students.forEach(function (student) {
                const option = document.createElement('button');

                option.type = 'button';
                option.className = 'autocomplete-option';

                option.textContent =
                    student.student_no + ' — ' +
                    student.full_name + ' (' +
                    student.contact_no + ')';

                option.addEventListener('click', function () {
                    studentNoInput.value = student.student_no;
                    suggestionsBox.innerHTML = '';
                });

                suggestionsBox.appendChild(option);
            });

        } catch (error) {
            console.error(error);
        }
    }, 250);
});

document.addEventListener('click', function (event) {
    if (
        !studentNoInput.contains(event.target) &&
        !suggestionsBox.contains(event.target)
    ) {
        suggestionsBox.innerHTML = '';
    }
});



</script>

</body>
</html>
