

<?php
require_once __DIR__ . '/config/database.php';

$selected_date = trim($_GET['report_date'] ?? '');
$message = '';
$result = false;

// Validate the selected date.
if ($selected_date !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $selected_date);

    if (!$date || $date->format('Y-m-d') !== $selected_date) {
        $message = 'Please select a valid date.';
    }
}

$sql = "
    SELECT
        i.item_id,
        i.image_path,
        i.item_name,
        i.description,
        i.color,
        i.brand,
        i.location_found,
        r.report_date,
        c.category_name
    FROM items i
    INNER JOIN reports r
        ON i.report_id = r.report_id
    INNER JOIN categories c
        ON i.category_id = c.category_id
    WHERE i.item_status = 'Found'
";

if ($message === '' && $selected_date !== '') {
    $sql .= " AND r.report_date = ?";
}

$sql .= " ORDER BY r.report_date DESC, i.item_id DESC";

if ($message === '') {
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if ($selected_date !== '') {
            $stmt->bind_param('s', $selected_date);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
        } else {
            $message = 'Unable to load items. Please try again.';
        }

        $stmt->close();
    } else {
        $message = 'Unable to load items. Please try again.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Found Items Catalog</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<main class="container">
    <h1>Found Items Catalog</h1>
    <p>
        Browse items reported to the Lost and Found Office.
        If you recognize an item, contact the office to claim it.
    </p>

    
<section class="card">
    <h2>Find Items by Date</h2>
    <p>Select the date when an item was reported.</p>

    <form method="GET" action="public_items.php">
        <label for="report_date">Report Date</label>

        <input
            type="date"
            id="report_date"
            name="report_date"
            value="<?= htmlspecialchars($selected_date, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit">View Items</button>

        <a href="public_items.php" class="button button-secondary">
            Clear Date
        </a>
    </form>
</section>

<?php if ($message !== ''): ?>
    <div class="message" role="alert">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>


    <?php if ($result->num_rows > 0): ?>
        <div class="items-grid">
            <?php while ($item = $result->fetch_assoc()): ?>
                <article class="item-card">

                
                <!-- <article class="item-card"> -->
                    <?php
                    $image_path = $item['image_path'] ?? '';

                    // Only display files from the expected upload directory.
                    $show_image = str_starts_with($image_path, 'uploads/items/')
                        && basename($image_path) === substr(
                            $image_path,
                            strlen('uploads/items/')
                        )
                        && is_file(__DIR__ . '/' . $image_path);
                    ?>

                    <?php if ($show_image): ?>
                        <img
                            src="<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>"
                            class="item-image"
                        >
                    <?php else: ?>
                        <div class="item-image-placeholder">
                            No image available
                        </div>
                    <?php endif; ?>
                    <!----->
                    <h2>
                        <?= htmlspecialchars($item['item_name']) ?>
                    </h2>

                    <p>
                        <strong>Category:</strong>
                        <?= htmlspecialchars($item['category_name']) ?>
                    </p>

                    <p>
                        <strong>Description:</strong>
                        <?= htmlspecialchars(
                            $item['description'] ?? 'No description provided'
                        ) ?>
                    </p>

                    <p>
                        <strong>Color:</strong>
                        <?= htmlspecialchars($item['color'] ?? 'Not specified') ?>
                    </p>

                    <p>
                        <strong>Brand:</strong>
                        <?= htmlspecialchars($item['brand'] ?? 'Not specified') ?>
                    </p>

                    <p>
                        <strong>Location found:</strong>
                        <?= htmlspecialchars($item['location_found'] ?? 'Not specified') ?>
                    </p>

                    <p>
                        <strong>Date reported:</strong>
                        <?= htmlspecialchars($item['report_date']) ?>
                    </p>

                    <a href="item_comments.php?item_id=<?= (int) $item['item_id'] ?>">
                        Leave a private comment
                    </a>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>
    <?php if ($selected_date !== '' && $message === ''): ?>
        No found items were reported on
        <?= htmlspecialchars($selected_date, ENT_QUOTES, 'UTF-8') ?>.
        Try another date or clear the filter.
    <?php else: ?>
        No found items are currently available for public viewing.
    <?php endif; ?>
</p>
    <?php endif; ?>

    <p><a href="index.php">Staff login</a></p>
</main>

</body>
</html>
