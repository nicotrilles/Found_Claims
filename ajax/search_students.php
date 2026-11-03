
<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$search = trim($_GET['q'] ?? '');

if (!preg_match('/^[0-9]{1,6}$/', $search)) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT
        student_id,
        student_no,
        full_name,
        contact_no
    FROM students
    WHERE student_no LIKE CONCAT(?, '%')
    ORDER BY student_no ASC
    LIMIT 8
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $search);
$stmt->execute();

$result = $stmt->get_result();

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();

echo json_encode($students);
