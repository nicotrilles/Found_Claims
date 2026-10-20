
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['search'] ?? '');
$items = [];

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
    WHERE
        items.item_status = 'Found'
        AND (
            items.item_name LIKE ?
            OR items.description LIKE ?
            OR items.color LIKE ?
            OR items.brand LIKE ?
            OR items.location_found LIKE ?
            OR categories.category_name LIKE ?
        )
    ORDER BY items.item_id DESC
";

$stmt = $conn->prepare($sql);
$term = '%' . $search . '%';

$stmt->bind_param(
    "ssssss",
    $term,
    $term,
    $term,
    $term,
    $term,
    $term
);

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Found Items</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>
    <section class="page-intro">
        <h1>Search Found Items</h1>
        <p class="muted">
            Find an item by name, description, color, brand,
            category, or the location where it was found.
        </p>
    </section>

    <section class="card">
        <form method="GET" action="search_item.php">
            <label for="search">Search items</label>

            <input
                type="search"
                id="search"
                name="search"
                placeholder="e.g. black wallet, ID card, library..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit">Search</button>

            <a class="button button-secondary"
               href="search_item.php">
                Clear Search
            </a>
        </form>

        <p class="muted">
            <?= count($items) ?>
            available item<?= count($items) === 1 ? '' : 's' ?> found.
        </p>
    </section>

    <?php if (count($items) > 0): ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Location Found</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['color'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['location_found'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <a class="button"
                                   href="claim_item.php?item_id=<?= (int) $item['item_id'] ?>">
                                    Select Item
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <div class="empty-state">
            <h2>No available items found</h2>
            <p>
                <?php if ($search !== ''): ?>
                    Try a different keyword or clear your search.
                <?php else: ?>
                    There are currently no items available for claiming.
                <?php endif; ?>
            </p>
        </div>

    <?php endif; ?>
</main>

</body>
</html>
