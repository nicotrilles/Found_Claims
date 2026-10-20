
<?php
require_once __DIR__ . '/config/database.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$error = '';
$success = '';

if (!$item_id || $item_id < 1) {
    http_response_code(400);
    exit('Invalid item ID.');
}

// Only allow comments on items that are still available.
$stmt = $conn->prepare(
    "SELECT items.item_id, items.item_name, items.description,
            items.color, items.brand, items.location_found,
            reports.report_date, categories.category_name
     FROM items
     INNER JOIN reports ON items.report_id = reports.report_id
     INNER JOIN categories ON items.category_id = categories.category_id
     WHERE items.item_id = ? AND items.item_status = 'Found'"
);
$stmt->bind_param('i', $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    http_response_code(404);
    exit('This found item is unavailable or does not exist.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $comment_text = trim($_POST['comment_text'] ?? '');

    if ($student_name === '' || $comment_text === '') {
        $error = 'Please enter your name and comment.';
    } elseif (strlen($student_name) > 100) {
        $error = 'Your name must be 100 characters or fewer.';
    } elseif (strlen($comment_text) > 2000) {
        $error = 'Your comment must be 2,000 characters or fewer.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO item_comments
                (item_id, student_name, comment_text)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param(
            'iss',
            $item_id,
            $student_name,
            $comment_text
        );

        if ($stmt->execute()) {
            $stmt->close();
            $success = 'Your comment was submitted to the Lost and Found staff.';
        } else {
            $stmt->close();
            $error = 'Unable to submit your comment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comment on Found Item</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="container">
    <section class="card">
        <h1><?= e($item['item_name']) ?></h1>

        <p><strong>Category:</strong>
            <?= e($item['category_name']) ?></p>

        <p><strong>Description:</strong>
            <?= e($item['description'] ?: 'Not provided') ?></p>

        <p><strong>Color:</strong>
            <?= e($item['color'] ?: 'Not provided') ?></p>

        <p><strong>Brand:</strong>
            <?= e($item['brand'] ?: 'Not provided') ?></p>

        <p><strong>Location found:</strong>
            <?= e($item['location_found'] ?: 'Not provided') ?></p>

        <p><strong>Date reported:</strong>
            <?= e($item['report_date']) ?></p>
    </section>

    <section class="card">
        <h2>Leave a Private Comment</h2>
        <p>Your comment will only be visible to authorized staff.
           Do not include passwords or other sensitive information.</p>

        <?php if ($error !== ''): ?>
            <p class="message"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <p class="message"><?= e($success) ?></p>
        <?php else: ?>
            <form method="POST"
                  action="item_comments.php?item_id=<?= (int) $item_id ?>">
                <label for="student_name">Your Name</label>
                <input id="student_name" name="student_name"
                       maxlength="100" required>

                <label for="comment_text">Comment</label>
                <textarea id="comment_text" name="comment_text"
                          maxlength="2000" rows="5" required></textarea>

                <button type="submit">Submit Comment</button>
            </form>
        <?php endif; ?>

        <p><a href="public_items.php" style="text-decoration: none; color: #003c7b;">Back to Browse Found Items</a></p>
    </section>
</main>
</body>
</html>
