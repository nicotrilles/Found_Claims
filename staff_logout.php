
<?php
session_start();
$_SESSION = [];
session_destroy();

header('Location: staff_login.php');
exit;
