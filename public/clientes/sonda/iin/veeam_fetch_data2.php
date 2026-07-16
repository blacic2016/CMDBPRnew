<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once 'veeam_db_connection.php';
require_once 'veeam_functions.php';

// Get the selected start and end dates from the request
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Default to current month if dates are not provided (though JS will provide them)
if (is_null($startDate) || is_null($endDate)) {
    $startDate = date('Y-m-01'); // First day of current month
    $endDate = date('Y-m-d');   // Current day
}

$data = fetchData($conn, $startDate, $endDate, 'job');

$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
?>
