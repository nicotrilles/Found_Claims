
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$message = '';
$categories = [];

$student_no = '';
$full_name = '';
$contact_no = '';
$item_name = '';
$category_id = '';
$description = '';
$color = '';
$brand = '';
$location_found = '';
$image_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_no = trim($_POST['student_no'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');

    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $location_found = trim($_POST['location_found'] ?? '');
    // Validate the optional image upload.
    $upload_tmp = null;
    $upload_extension = null;

    if (isset($_FILES['item_image']) &&
        $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {

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


    if (!preg_match('/^[0-9]{6}$/', $student_no)) {
        $message = 'Student number must contain exactly 6 digits.';
    } elseif (
        $full_name === '' || $contact_no === '' ||
        $item_name === '' || $category_id === '' ||
        $description === '' || $color === '' ||
        $brand === '' || $location_found === ''
    ) {
        $message = 'Please complete all required fields.';
    } elseif (!preg_match('/^[0-9]+$/', $contact_no)) {
        $message = 'Contact number must contain numbers only.';
    } elseif (!ctype_digit($category_id)) {
        $message = 'Please select a valid category.';
    } else {
        // $staff_id = 1;
        $staff_id = (int) $_SESSION['staff_id'];

        try {
            // Check whether the student already exists.
            $sql = "
                SELECT student_id, full_name
                FROM students
                WHERE student_no = ?
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $student_no);
            $stmt->execute();
            $studentResult = $stmt->get_result();
            $student = $studentResult->fetch_assoc();
            $stmt->close();

            if ($student) {
                if (strcasecmp($student['full_name'], $full_name) !== 0) {
                    $message = 'Student number already exists with a different full name.';
                } else {
                    $student_id = (int) $student['student_id'];
                }
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
                $student_id = $conn->insert_id;
                $stmt->close();
            }

            if ($message === '') {
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
                    $message = 'The selected category does not exist.';
                }
            }

            if ($message === '') {
                $conn->begin_transaction();

                // Create the report.
                $sql = "
                    INSERT INTO reports
                        (student_id, staff_id, report_date, remarks)
                    VALUES (?, ?, CURDATE(), ?)
                ";

                $remarks = 'Item reported by student.';

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    'iis',
                    $student_id,
                    $staff_id,
                    $remarks
                );
                $stmt->execute();
                $report_id = $conn->insert_id;
                $stmt->close();

                // Create the found item.
    
                // Prepare the optional image file.
$image_path = null;
$uploaded_file_path = null;

if ($upload_tmp !== null && $message === '') {
    $upload_directory = __DIR__ . '/uploads/items/';

    if (!is_dir($upload_directory) ||
        !is_writable($upload_directory)) {
        throw new RuntimeException('The image upload directory is unavailable.');
    }

    $image_filename = bin2hex(random_bytes(16))
        . '.' . $upload_extension;

    $uploaded_file_path = $upload_directory . $image_filename;
    $image_path = 'uploads/items/' . $image_filename;

    if (!move_uploaded_file($upload_tmp, $uploaded_file_path)) {
        throw new RuntimeException('Unable to store the uploaded image.');
    }
}

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
$selected_category = (int) $category_id;

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

                $conn->commit();

                header('Location: item.php');
                exit();
            }
        } catch (Throwable $e) {
            if ($conn->errno || $conn->connect_errno === 0) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                    // Nothing else to roll back.
                }
            }
            // Clean up the uploaded file if it was moved.
            if ($uploaded_file_path !== null &&
                is_file($uploaded_file_path)) {
                unlink($uploaded_file_path);
            }

            error_log('Found item report error: ' . $e->getMessage());
            $message = 'Unable to save the report. Please check the information and try again.';
        }
    }
}

// Load categories for the form.
$categoryResult = $conn->query(
    'SELECT category_id, category_name FROM categories ORDER BY category_name'
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
            Record the student who submitted the item and the details
            needed to identify and safeguard it.
        </p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="message" role="alert">
            <?= report_escape($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="report_item.php" enctype="multipart/form-data">

        <section class="card">
            <h2>1. Student Information</h2>
            <p class="muted">
                Enter the details of the student who brought the found item
                to the office.
            </p>

            <label for="student_no">Student Number</label>
            <input
                type="text"
                id="student_no"
                name="student_no"
                maxlength="6"
                minlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                placeholder="Enter 6 digits"
                value="<?= report_escape($student_no) ?>"
                required
            >

            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                value="<?= report_escape($full_name) ?>"
                required
            >

            <label for="contact_no">Contact Number</label>
            <input
                type="tel"
                id="contact_no"
                name="contact_no"
                inputmode="numeric"
                pattern="[0-9]+"
                title="Please enter numbers only."
                value="<?= report_escape($contact_no) ?>"
                required
            >
        </section>

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
            <select id="category_id" name="category_id" required>
                <option value="">Select a category</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['category_id'] ?>"
                        <?= (string) $category_id === (string) $category['category_id'] ? 'selected' : '' ?>
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
            <p class="muted">JPG, PNG, or WebP. Maximum size: 5 MB.</p>
        </section>

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

</body>
</html>
