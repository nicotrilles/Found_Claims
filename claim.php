
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$claim_id = $_GET['claim_id'] ?? $_POST['claim_id'] ?? '';
$error = '';
$success = '';
$action = $_POST['action'] ?? '';

$is_detail_page = $claim_id !== '';

// PROCESS APPROVAL OR REJECTION
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($action, ['approve_claim', 'reject_claim'], true)
) {
    if (!ctype_digit((string) $claim_id) || (int) $claim_id < 1) {
        $error = 'Invalid claim ID.';
    } else {
        $id = (int) $claim_id;

        try {
            $conn->begin_transaction();

            // Check the claim and item statuses.
            $sql = "
                SELECT
                    claims.claim_id,
                    claims.claim_status,
                    claims.item_id,
                    items.item_status
                FROM claims
                INNER JOIN items
                    ON claims.item_id = items.item_id
                WHERE claims.claim_id = ?
                FOR UPDATE
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $claimToProcess = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$claimToProcess) {
                throw new Exception('Claim not found.');
            }

            if ($claimToProcess['claim_status'] !== 'Pending') {
                throw new Exception('Only Pending claims can be processed.');
            }

            if ($claimToProcess['item_status'] !== 'Found') {
                throw new Exception('This item is no longer available for processing.');
            }

            $newStatus = ($action === 'approve_claim')
                ? 'Approved'
                : 'Rejected';

            // Update the claim status and record the staff member who processed it.
            $sql = "
                UPDATE claims
                SET claim_status = ?, decision_staff_id = ?
                WHERE claim_id = ?
                AND claim_status = 'Pending'
            ";

            $decision_staff_id = (int) $_SESSION['staff_id'];

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'sii',
                $newStatus,
                $decision_staff_id,
                $id
            );
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception('Failed to update claim status.');
            }

            $stmt->close();

            // An approved claim marks the item as Claimed.
            // A rejected claim leaves the item as Found.
            if ($action === 'approve_claim') {
                $item_id = (int) $claimToProcess['item_id'];

                $sql = "
                    UPDATE items
                    SET item_status = 'Claimed'
                    WHERE item_id = ?
                      AND item_status = 'Found'
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $item_id);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new Exception('Failed to update item status.');
                }

                $stmt->close();
            }

            $conn->commit();

            $resultMessage = ($action === 'approve_claim')
                ? 'approved'
                : 'rejected';

            header(
                'Location: claim.php?claim_id=' . $id
                . '&result=' . $resultMessage
            );
            exit();

        } catch (Throwable $ex) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // No active transaction remains.
            }

            $error = $ex->getMessage();
        }
    }
}

// SUCCESS MESSAGES
if (isset($_GET['result'])) {
    if ($_GET['result'] === 'approved') {
        $success = 'Claim approved. The item is now marked as Claimed.';
    } elseif ($_GET['result'] === 'rejected') {
        $success = 'Claim rejected. The item remains marked as Found.';
    }
}

$claims = null;
$claim = null;

// LOAD CLAIM LIST
if (!$is_detail_page) {
    $sql = "
        SELECT
            claims.claim_id,
            claims.claimant_type,
            claims.claimant_name,
            students.student_no,
            COALESCE(claims.claimant_name, students.full_name, 'Unknown claimant') AS claimant,
            items.item_name,
            claims.claim_date,
            claims.claim_status,
            staff.full_name AS processed_by
        FROM claims
        LEFT JOIN students
            ON claims.student_id = students.student_id
        LEFT JOIN items
            ON claims.item_id = items.item_id
        LEFT JOIN staff
            ON claims.decision_staff_id = staff.staff_id
        ORDER BY claims.claim_id DESC
    ";

    $claims = $conn->query($sql);

    if (!$claims) {
        $error = 'Unable to load claims.';
    }

// LOAD A SPECIFIC CLAIM
} elseif (
    ctype_digit((string) $claim_id)
    && (int) $claim_id > 0
) {
    $id = (int) $claim_id;

    $sql = "
        SELECT
            claims.claim_id,
            claims.claim_date,
            claims.claim_status,
            claims.verification_details,
            claims.remarks,
            claims.claimant_type,
            claims.claimant_name,
            claims.claimant_contact,
            claims.id_type,
            claims.id_verified,
            students.student_no,
            COALESCE(claims.claimant_name, students.full_name, 'Unknown claimant') AS claimant,
            COALESCE(claims.claimant_contact, students.contact_no) AS student_contact,
            items.item_id,
            items.item_name,
            items.description AS item_description,
            items.color,
            items.brand,
            items.location_found,
            items.item_status,
            categories.category_name,
            staff.full_name AS processed_by
        FROM claims
        LEFT JOIN students
            ON claims.student_id = students.student_id
        LEFT JOIN items
            ON claims.item_id = items.item_id
        LEFT JOIN categories
            ON items.category_id = categories.category_id
        LEFT JOIN staff
            ON claims.decision_staff_id = staff.staff_id
        WHERE claims.claim_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$claim) {
        $error = 'Claim not found.';
    }
} else {
    $error = 'Invalid claim ID.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claims Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navigation.php'; ?>

<main>
    <div class="page-intro">
        <h1>Claims Management</h1>
        <p class="muted">
            Review submitted claims and manage claim decisions.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="message"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="message"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!$is_detail_page): ?>

        <section class="card">
            <h2>Submitted Claims</h2>

            <?php if ($claims && $claims->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Claimant Type</th>
                                <th>Student No.</th>
                                <th>Claimant</th>
                                <th>Item</th>
                                <th>Claim Date</th>
                                <th>Status</th>
                                <th>Processed By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $claims->fetch_assoc()): ?>
                                <tr>
                                    <td><?= e($row['claim_id']) ?></td>
                                    <td><?= e($row['claimant_type'] ?? 'Student') ?></td>
                                    <td><?= e($row['student_no']) ?></td>
                                    <td><?= e($row['claimant']) ?></td>
                                    <td><?= e($row['item_name']) ?></td>
                                    <td><?= e($row['claim_date']) ?></td>
                                    <td>
                                        <span class="status">
                                            <?= e($row['claim_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($row['processed_by']) ?></td>
                                    <td>
                                        <a class="button button-secondary"
                                           href="claim.php?claim_id=<?= (int) $row['claim_id'] ?>">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No claims have been submitted yet.</p>
                    <p>Claims will appear here after they are created.</p>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($claim): ?>

        <p>
            <a class="button button-secondary" href="claim.php">
                Back to Claims List
            </a>
        </p>

        <section class="card">
            <h2>Claim #<?= e($claim['claim_id']) ?></h2>
            <p><strong>Claim Date:</strong> <?= e($claim['claim_date']) ?></p>
            <p>
                <strong>Claim Status:</strong>
                <span class="status"><?= e($claim['claim_status']) ?></span>
            </p>
        </section>

        <section class="card">
            <h2>Claimant Information</h2>
            <p><strong>Claimant Type:</strong> <?= e($claim['claimant_type'] ?? 'Student') ?></p>
            <?php if (($claim['claimant_type'] ?? 'Student') === 'Student'): ?>
                <p><strong>Student Number:</strong> <?= e($claim['student_no']) ?></p>
            <?php endif; ?>
            <p><strong>Full Name:</strong> <?= e($claim['claimant']) ?></p>
            <p><strong>Contact Number:</strong> <?= e($claim['student_contact']) ?></p>
            <?php if (($claim['claimant_type'] ?? 'Student') !== 'Student'): ?>
                <p><strong>ID Type:</strong> <?= e($claim['id_type']) ?></p>
                <p><strong>ID Checked by Staff:</strong> <?= !empty($claim['id_verified']) ? 'Yes' : 'No' ?></p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Item Information</h2>
            <p><strong>Item:</strong> <?= e($claim['item_name']) ?></p>
            <p><strong>Category:</strong> <?= e($claim['category_name']) ?></p>
            <p><strong>Description:</strong> <?= e($claim['item_description']) ?></p>
            <p><strong>Color:</strong> <?= e($claim['color']) ?></p>
            <p><strong>Brand:</strong> <?= e($claim['brand']) ?></p>
            <p><strong>Location Found:</strong> <?= e($claim['location_found']) ?></p>
            <p>
                <strong>Item Status:</strong>
                <span class="status"><?= e($claim['item_status']) ?></span>
            </p>
        </section>

        <section class="card">
            <h2>Ownership Verification</h2>
            <p><strong>Verification Details:</strong></p>
            <p><?= nl2br(e($claim['verification_details'])) ?></p>

            <p><strong>Remarks:</strong></p>
            <p><?= nl2br(e($claim['remarks'])) ?></p>

            <p><strong>Processed By:</strong> <?= e($claim['processed_by']) ?></p>
        </section>

        <section class="card">
            <h2>Claim Decision</h2>

            <?php if (
                $claim['claim_status'] === 'Pending'
                && $claim['item_status'] === 'Found'
            ): ?>
                <p>Review the ownership verification before making a decision.</p>

                <form method="POST"
                      action="claim.php?claim_id=<?= (int) $claim['claim_id'] ?>"
                      onsubmit="return confirm('Approve this claim and mark the item as Claimed?');">
                    <input type="hidden" name="claim_id"
                           value="<?= (int) $claim['claim_id'] ?>">
                    <button type="submit" name="action" value="approve_claim">
                        Approve Claim
                    </button>
                </form>

                <form method="POST"
                      action="claim.php?claim_id=<?= (int) $claim['claim_id'] ?>"
                      onsubmit="return confirm('Reject this claim? The item will remain Found.');">
                    <input type="hidden" name="claim_id"
                           value="<?= (int) $claim['claim_id'] ?>">
                    <button type="submit" name="action" value="reject_claim">
                        Reject Claim
                    </button>
                </form>

            <?php elseif ($claim['claim_status'] !== 'Pending'): ?>
                <p>This claim has already been processed.</p>
            <?php else: ?>
                <p>This item is no longer available for approval.</p>
            <?php endif; ?>
        </section>

        <p><a class="button button-secondary" href="claim.php">Back to Claims List</a></p>

    <?php endif; ?>
</main>

</body>
</html>
