
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);

    if (!$comment_id || $comment_id < 1) {
        $error = 'Invalid comment.';
    } else {
        $stmt = $conn->prepare(
            "UPDATE item_comments
             SET comment_status = 'Reviewed'
             WHERE comment_id = ?"
        );
        $stmt->bind_param('i', $comment_id);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $success = 'Comment marked as reviewed.';
        } else {
            $error = 'Unable to update the comment.';
        }

        $stmt->close();
    }
}

$sql = "
    SELECT item_comments.comment_id,
           item_comments.student_name,
           item_comments.comment_text,
           item_comments.comment_date,
           item_comments.comment_status,
           items.item_id,
           items.item_name
    FROM item_comments
    INNER JOIN items ON item_comments.item_id = items.item_id
    ORDER BY
        CASE WHEN item_comments.comment_status = 'New'
             THEN 0 ELSE 1 END,
        item_comments.comment_date DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Comments</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main class="container">
    <section class="card">
        <h1>Student Comments</h1>
        <p>Only logged-in staff can access this page.</p>

        <?php if ($error !== ''): ?>
            <p class="message"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <p class="message"><?= e($success) ?></p>
        <?php endif; ?>

        <?php if ($result->num_rows === 0): ?>
            <p>No student comments have been submitted yet.</p>
        <?php else: ?>
            <?php while ($comment = $result->fetch_assoc()): ?>
                <article class="card">
                    <h2><?= e($comment['item_name']) ?></h2>

                    <p><strong>Comment ID:</strong>
                        <?= (int) $comment['comment_id'] ?></p>

                    <p><strong>Student name:</strong>
                        <?= e($comment['student_name']) ?></p>

                    <p><strong>Submitted:</strong>
                        <?= e($comment['comment_date']) ?></p>

                    <p><strong>Status:</strong>
                        <?= e($comment['comment_status']) ?></p>

                    <p><strong>Comment:</strong></p>
                    <p><?= nl2br(e($comment['comment_text'])) ?></p>

                    <?php if ($comment['comment_status'] === 'New'): ?>
                        <form method="POST">
                            <input type="hidden" name="comment_id"
                                   value="<?= (int) $comment['comment_id'] ?>">
                            <button type="submit">Mark as Reviewed</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
