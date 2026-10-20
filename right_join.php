<?php
require_once 'config/database.php';

// RIGHT JOIN: Display all categories, even if they have no items.
$sql = "
    SELECT
        categories.category_id,
        categories.category_name,
        items.item_id,
        items.item_name,
        items.item_status
    FROM items
    RIGHT JOIN categories
        ON items.category_id = categories.category_id
    ORDER BY categories.category_name
";

$result = $conn->query($sql);

function e($value): string
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
    <title>RIGHT JOIN Demonstration</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <h1>RIGHT JOIN Demonstration</h1>

    <p>
        This page displays all categories, including categories
        that do not have any associated items.
    </p>

    <?php if ($result && $result->num_rows > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>Category ID</th>
                    <th>Category Name</th>
                    <th>Item ID</th>
                    <th>Item Name</th>
                    <th>Item Status</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($row['category_id']) ?></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= e($row['item_id']) ?></td>
                        <td><?= e($row['item_name']) ?></td>
                        <td><?= e($row['item_status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php elseif ($result): ?>

        <p>No categories found.</p>

    <?php else: ?>

        <p style="color: red;">
            Unable to retrieve categories.
        </p>

    <?php endif; ?>

    <p><a href="item.php">Back to Found Items</a></p>

</body>
</html>