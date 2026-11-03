
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$message = '';
$categories = [];

// Reporter information
$reporter_type = 'Student';
$student_no = '';
$full_name = '';
$contact_no = '';

// Found item information
$item_name = '';
$category_id = '';
$description = '';
$color = '';
$brand = '';
$location_found = '';

// Image information
$image_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get reporter information
    $reporter_type = trim($_POST['reporter_type'] ?? '');
    $student_no = trim($_POST['student_no'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');

    // Get found item information
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $location_found = trim($_POST['location_found'] ?? '');

    // Variables used during image upload
    $upload_tmp = null;
    $upload_extension = null;
    $uploaded_file_path = null;

    // Validate reporter type
    $allowed_reporter_types = [
        'Student',
        'Anonymous',
        'Faculty',
        'Guard',
        'Guest'
    ];

    if (!in_array($reporter_type, $allowed_reporter_types, true)) {
        $message = 'Please select a valid reporter type.';
    }

    // Anonymous reporters must not provide or store personal information.
    if ($reporter_type === 'Anonymous') {
        $student_no = '';
        $full_name = '';
        $contact_no = '';
    }

    // Validate reporter information
    if ($message === '') {

        if ($reporter_type === 'Student') {

            if (!preg_match('/^[0-9]{6}$/', $student_no)) {
                $message = 'Student number must contain exactly 6 digits.';
            } elseif ($full_name === '' || $contact_no === '') {
                $message = 'Please complete the student information.';
            } elseif (!preg_match('/^[0-9]{1,11}$/', $contact_no)) {
                $message = 'Contact number must contain 1 to 11 digits.';
            }

        } elseif (
            in_array($reporter_type, ['Faculty', 'Guard', 'Guest'], true)
        ) {

            if ($full_name === '') {
                $message = 'Please enter the reporter\'s full name.';
            } elseif (
                $contact_no !== '' &&
                !preg_match('/^[0-9]{1,11}$/', $contact_no)
            ) {
                $message = 'Contact number must contain 1 to 11 digits.';
            }
        }
    }

    // Validate found item information
    if ($message === '') {

        if (
            $item_name === '' ||
            $category_id === '' ||
            $description === '' ||
            $color === '' ||
            $brand === '' ||
            $location_found === ''
        ) {
            $message = 'Please complete all required item fields.';
        } elseif (!ctype_digit($category_id)) {
            $message = 'Please select a valid category.';
        }
    }

    // Validate the optional image upload
    if (
        $message === '' &&
        isset($_FILES['item_image']) &&
        $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
            $message = 'The image upload failed. Please try again.';
        } elseif ($_FILES['item_image']['size'] > 5 * 1024 * 1024) {
            $message = 'The image must not exceed 5 MB.';
        } else {

            $image_info = getimagesize($_FILES['item_image']['tmp_name']);

            if ($image_info === false) {
                $message = 'Please upload a valid image.';
            } else {

                $allowed_types = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $mime_type = $image_info['mime'];

                if (!isset($allowed_types[$mime_type])) {
                    $message = 'Only JPG, PNG, and WebP images are allowed.';
                } else {
                    $upload_tmp = $_FILES['item_image']['tmp_name'];
                    $upload_extension = $allowed_types[$mime_type];
                }
            }
        }
    }

    // Continue only if all initial validation passed
    if ($message === '') {

        $staff_id = (int) $_SESSION['staff_id'];

        // Default values for nullable database fields
        $student_id = null;
        $reporter_name = null;
        $reporter_contact = null;

        // Student reporters
        if ($reporter_type === 'Student') {
            $reporter_name = $full_name;
            $reporter_contact = $contact_no;
        }

        // Faculty, Guard, and Guest reporters
        elseif (
            in_array($reporter_type, ['Faculty', 'Guard', 'Guest'], true)
        ) {
            $reporter_name = $full_name;
            $reporter_contact = $contact_no !== '' ? $contact_no : null;
        }

        // Anonymous reporters retain NULL personal information.
        $remarks = "Item reported by {$reporter_type}.";

        $transactionStarted = false;

        try {

            // Verify that the selected category exists.
            $sql = "
                SELECT category_id
                FROM categories
                WHERE category_id = ?
            ";

            $stmt = $conn->prepare($sql);

            $selected_category = (int) $category_id;

            $stmt->bind_param('i', $selected_category);
            $stmt->execute();

            $categoryResult = $stmt->get_result();
            $categoryExists = $categoryResult->num_rows > 0;

            $stmt->close();

            if (!$categoryExists) {
                throw new RuntimeException(
                    'The selected category does not exist.'
                );
            }

            // Start transaction before creating any database records.
            $conn->begin_transaction();
            $transactionStarted = true;

            // Only Student reporters require a student record.
            if ($reporter_type === 'Student') {

                $sql = "
                    SELECT student_id, full_name
                    FROM students
                    WHERE student_no = ?
                    FOR UPDATE
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $student_no);
                $stmt->execute();

                $studentResult = $stmt->get_result();
                $student = $studentResult->fetch_assoc();

                $stmt->close();

                if ($student) {

                    if (
                        strcasecmp($student['full_name'], $full_name) !== 0
                    ) {
                        throw new RuntimeException(
                            'Student number already exists with a different full name.'
                        );
                    }

                    $student_id = (int) $student['student_id'];

                } else {

                    $sql = "
                        INSERT INTO students
                            (student_no, full_name, contact_no)
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

                    $student_id = (int) $conn->insert_id;

                    $stmt->close();
                }
            }

            // Prepare and store the optional image.
            if ($upload_tmp !== null) {

                $upload_directory = __DIR__ . '/uploads/items/';

                if (
                    !is_dir($upload_directory) ||
                    !is_writable($upload_directory)
                ) {
                    throw new RuntimeException(
                        'The image upload directory is unavailable.'
                    );
                }

                $image_filename = bin2hex(random_bytes(16))
                    . '.' . $upload_extension;

                $uploaded_file_path = $upload_directory . $image_filename;

                $image_path = 'uploads/items/' . $image_filename;

                if (!move_uploaded_file(
                    $upload_tmp,
                    $uploaded_file_path
                )) {
                    throw new RuntimeException(
                        'Unable to store the uploaded image.'
                    );
                }
            }

            // Create the report.
            $sql = "
                INSERT INTO reports (
                    student_id,
                    staff_id,
                    reporter_type,
                    reporter_name,
                    reporter_contact,
                    report_date,
                    remarks
                )
                VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                'iissss',
                $student_id,
                $staff_id,
                $reporter_type,
                $reporter_name,
                $reporter_contact,
                $remarks
            );

            $stmt->execute();

            $report_id = (int) $conn->insert_id;

            $stmt->close();

            // Create the found item.
            $sql = "
                INSERT INTO items (
                    report_id,
                    item_name,
                    category_id,
                    description,
                    color,
                    brand,
                    location_found,
                    item_status,
                    image_path
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $item_status = 'Found';

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                'isissssss',
                $report_id,
                $item_name,
                $selected_category,
                $description,
                $color,
                $brand,
                $location_found,
                $item_status,
                $image_path
            );

            $stmt->execute();

            $stmt->close();

            // Save all database changes.
            $conn->commit();
            $transactionStarted = false;

            header('Location: item.php');
            exit();

        } catch (Throwable $e) {

            // Roll back database changes if a transaction was started.
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                    // Preserve the original error.
                }
            }

            // Remove the uploaded image if database saving failed.
            if (
                $uploaded_file_path !== null &&
                is_file($uploaded_file_path)
            ) {
                unlink($uploaded_file_path);
            }

            error_log(
                'Found item report error: ' . $e->getMessage()
            );

            // Show expected validation errors to the user.
            if (
                $e instanceof RuntimeException &&
                in_array(
                    $e->getMessage(),
                    [
                        'The selected category does not exist.',
                        'Student number already exists with a different full name.'
                    ],
                    true
                )
            ) {
                $message = $e->getMessage();
            } else {
                $message = 'Unable to save the report. Please check the information and try again.';
            }
        }
    }
}

// Load categories for the form.
$categoryResult = $conn->query(
    'SELECT category_id, category_name
     FROM categories
     ORDER BY category_name'
);

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

function report_escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report Found Item | School Lost &amp; Found</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>

    <section class="page-intro">
        <h1>Report a Found Item</h1>

        <p class="muted">
            Record the person who submitted the found item and the details
            needed to identify and safeguard it.
        </p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="message" role="alert">
            <?= report_escape($message) ?>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="report_item.php"
        enctype="multipart/form-data"
    >

        <!-- Reporter Information -->
        <section class="card">

            <h2>1. Reporter Information</h2>

            <p class="muted">
                Select the type of person who submitted the found item.
            </p>

            <label for="reporter_type">Reporter Type</label>

            <select
                id="reporter_type"
                name="reporter_type"
                required
            >
                <?php
                $reporter_options = [
                    'Student',
                    'Anonymous',
                    'Faculty',
                    'Guard',
                    'Guest'
                ];
                ?>

                <?php foreach ($reporter_options as $option): ?>
                    <option
                        value="<?= report_escape($option) ?>"
                        <?= $reporter_type === $option ? 'selected' : '' ?>
                    >
                        <?= report_escape($option) ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <!-- Student-only information -->
            <div id="student_fields">

                <label for="student_no">Student Number</label>

                <input
                    type="text"
                    id="student_no"
                    name="student_no"
                    maxlength="6"
                    minlength="6"
                    pattern="[0-9]{6}"
                    maxlength="11"
                    inputmode="numeric"
                    placeholder="Enter 6 digits"
                    value="<?= report_escape($student_no) ?>"
                >
                    <div id="student_suggestions" class="autocomplete-list"></div>
            </div>

            <!-- Information for students and identified non-students -->
            <div id="personal_fields">

                <label for="full_name">Full Name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= report_escape($full_name) ?>"
                >

                <label for="contact_no">Contact Number</label>

                <input
                    type="tel"
                    id="contact_no"
                    name="contact_no"
                    inputmode="numeric"
                    pattern="[0-9]+"
                    maxlength="11"
                    title="Enter up to 11 digits."
                    placeholder="Enter 11 digits"
                    value="<?= report_escape($contact_no) ?>"
                >

                <p class="muted" id="contact_note">
                    Contact number is required for students but optional
                    for faculty, guards, and guests.
                </p>

            </div>

            <p class="muted" id="anonymous_note" hidden>
                No personal information will be collected or stored
                for an anonymous reporter.
            </p>

        </section>

        <!-- Found Item Information -->
        <section class="card">

            <h2>2. Found Item Information</h2>

            <p class="muted">
                Provide enough detail to help identify the item and verify
                future claims.
            </p>

            <label for="item_name">Item Name</label>

            <input
                type="text"
                id="item_name"
                name="item_name"
                value="<?= report_escape($item_name) ?>"
                required
            >

            <label for="category_id">Category</label>

            <select
                id="category_id"
                name="category_id"
                required
            >

                <option value="">Select a category</option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category['category_id'] ?>"
                        <?= (string) $category_id === (string) $category['category_id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= report_escape($category['category_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                rows="4"
                required
            ><?= report_escape($description) ?></textarea>

            <label for="color">Color</label>

            <input
                type="text"
                id="color"
                name="color"
                value="<?= report_escape($color) ?>"
                required
            >

            <label for="brand">Brand</label>

            <input
                type="text"
                id="brand"
                name="brand"
                value="<?= report_escape($brand) ?>"
                required
            >

            <label for="location_found">Location Found</label>

            <input
                type="text"
                id="location_found"
                name="location_found"
                value="<?= report_escape($location_found) ?>"
                required
            >

            <label for="item_image">Item Image (Optional)</label>

            <input
                type="file"
                id="item_image"
                name="item_image"
                accept="image/jpeg,image/png,image/webp"
            >

            <p class="muted">
                JPG, PNG, or WebP. Maximum size: 5 MB.
            </p>

        </section>

        <!-- Submit -->
        <section class="card">

            <h2>3. Save Report</h2>

            <p class="muted">
                After saving successfully, the item will be marked as Found
                and appear in the item records.
            </p>

            <button type="submit">Submit Report</button>

            <a href="item.php" class="button button-secondary">
                Cancel
            </a>

        </section>

    </form>

</main>

<script>
    const reporterType = document.getElementById('reporter_type');

    const studentFields = document.getElementById('student_fields');
    const personalFields = document.getElementById('personal_fields');

    const studentNo = document.getElementById('student_no');
    const fullName = document.getElementById('full_name');
    const contactNo = document.getElementById('contact_no');

    const anonymousNote = document.getElementById('anonymous_note');
    const contactNote = document.getElementById('contact_note');

    function updateReporterFields() {

        const type = reporterType.value;

        const isStudent = type === 'Student';

        const isAnonymous = type === 'Anonymous';

        const isIdentified = isStudent ||
            ['Faculty', 'Guard', 'Guest'].includes(type);

        // Show student number only for students.
        studentFields.hidden = !isStudent;

        // Hide all personal information for anonymous reporters.
        personalFields.hidden = !isIdentified;

        anonymousNote.hidden = !isAnonymous;

        // Update required fields.
        studentNo.required = isStudent;

        fullName.required = isIdentified;

        contactNo.required = isStudent;

        // Contact number is optional for non-students.
        if (isStudent) {
            contactNo.setAttribute('pattern', '[0-9]+');
        } else {
            contactNo.setAttribute('pattern', '[0-9]*');
        }

        // Update explanatory text.
        if (isStudent) {
            contactNote.textContent =
                'Contact number is required for students.';
        } else {
            contactNote.textContent =
                'Contact number is optional for faculty, guards, and guests.';
        }
    }

    reporterType.addEventListener('change', updateReporterFields);

    // Apply the correct fields on initial page load.
    updateReporterFields();



    
const suggestionsBox = document.getElementById('student_suggestions');

let searchTimeout;

studentNo.addEventListener('input', function () {
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

            // Ignore results if the input has changed.
            if (studentNo.value.trim() !== query) {
                return;
            }

            suggestionsBox.innerHTML = '';

            students.forEach(function (student) {
                const option = document.createElement('button');

                option.type = 'button';
                option.className = 'autocomplete-option';

                option.textContent =
                    student.student_no + ' — ' + student.full_name;

                option.addEventListener('click', function () {
                    studentNo.value = student.student_no;
                    fullName.value = student.full_name;
                    contactNo.value = student.contact_no || '';

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
        !studentNo.contains(event.target) &&
        !suggestionsBox.contains(event.target)
    ) {
        suggestionsBox.innerHTML = '';
    }
});

</script>

</body>
</html>
