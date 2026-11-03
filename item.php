
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$message = '';

$is_valid_date = function ($date) {
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
};

if (
    ($start_date !== '' && !$is_valid_date($start_date)) ||
    ($end_date !== '' && !$is_valid_date($end_date))
) {
    $message = 'Please enter valid dates.';
} elseif (
    $start_date !== '' &&
    $end_date !== '' &&
    $start_date > $end_date
) {
    $message = 'The start date cannot be later than the end date.';
}

$sql = "
    SELECT
        items.item_id,
        items.item_name,
        categories.category_name,
        reports.reporter_type,
        COALESCE(reports.reporter_name, students.full_name, 'Anonymous') AS reporter,
        staff.full_name AS processed_by,
        items.location_found,
        items.item_status,
        reports.report_date
    FROM items
    INNER JOIN categories
        ON items.category_id = categories.category_id
    INNER JOIN reports
        ON items.report_id = reports.report_id
    LEFT JOIN students
        ON reports.student_id = students.student_id
    INNER JOIN staff
        ON reports.staff_id = staff.staff_id
";

$params = [];
$types = '';
$sql .= " WHERE 1=1";

if ($message === '' && $start_date !== '') {
    $sql .= " AND reports.report_date >= ?";
    $types .= 's';
    $params[] = $start_date;
}

if ($message === '' && $end_date !== '') {
    $sql .= " AND reports.report_date <= ?";
    $types .= 's';
    $params[] = $end_date;
}

$sql .= " ORDER BY items.item_id DESC";

$result = false;

if ($message === '') {
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
        } else {
            $message = 'Unable to retrieve item records. Please try again.';
        }

        $stmt->close();
    } else {
        $message = 'Unable to retrieve item records. Please try again.';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Found Items | School Lost &amp; Found</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>
    <section class="page-intro">
        <h1>Found Items</h1>
        <p class="muted">
            View and monitor items reported to the Lost and Found Office.
        </p>
    </section>

    <section class="card">
        <h2>Item Records</h2>
        <p class="muted">
            Review item details, reporter information, their current status, and the staff member
            who processed each report.
        </p>

        <p>
            <a href="report_item.php" class="button">
                + Report Found Item
            </a>
        </p>
    </section>

        
    <section class="card">
        <h2>Filter by Report Date</h2>

        <form method="GET" action="item.php">
            <label for="start_date">Start Date</label>
            <input
                type="date"
                id="start_date"
                name="start_date"
                value="<?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="end_date">End Date</label>
            <input
                type="date"
                id="end_date"
                name="end_date"
                value="<?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit">Filter</button>
            <a href="item.php" class="button button-secondary">
                Clear Filter
            </a>
        </form>
    </section>

    <?php if ($message !== ''): ?>
        <div class="message" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>


    <?php if ($result && $result->num_rows > 0): ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Reported By</th>
                        <th>Processed By</th>
                        <th>Location Found</th>
                        <th>Date Reported</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?= (int) $row['item_id'] ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['reporter'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['processed_by'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['location_found'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['report_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <span class="status">
                                    <?= htmlspecialchars($row['item_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <div class="empty-state">
            <h2>No item records yet</h2>
            <p>
                Found items will appear here after staff records a report.
            </p>
            <a href="report_item.php" class="button">
                Report the First Item
            </a>
        </div>

    <?php endif; ?>
</main>

</body>
</html>
