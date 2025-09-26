<?php
// Start a new session or resume the existing one
session_start();

// -----------------------------------------------------------
// Database connection details
// -----------------------------------------------------------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "workshop_management";

// Create a new database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check for connection errors
if ($conn->connect_error) {
    // Terminate script if connection fails
    die("Connection failed: " . $conn->connect_error);
}

// -----------------------------------------------------------
// Process form submission
// -----------------------------------------------------------
// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get and sanitize input data to prevent SQL injection
    $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
    $source     = $conn->real_escape_string($_POST['source']);
    $amount     = floatval($_POST['amount']);
    $date       = $conn->real_escape_string($_POST['date']);
    $notes      = $conn->real_escape_string($_POST['notes']);

    // Check if it's an update (expense_id exists) or an insert operation
    if ($expense_id > 0) {
        // Prepare SQL statement for updating an existing record
        $sql = "UPDATE expenses SET description = ?, amount = ?, expense_date = ?, notes = ? WHERE expense_id = ?";
        $stmt = $conn->prepare($sql);
        // 's' for string, 'd' for double, 's' for string, 's' for string, 'i' for integer
        $stmt->bind_param("sdssi", $source, $amount, $date, $notes, $expense_id);
    } else {
        // Prepare SQL statement for inserting a new record
        $sql = "INSERT INTO expenses (description, amount, expense_date, notes) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // 's' for string, 'd' for double, 's' for string, 's' for string
        $stmt->bind_param("sdss", $source, $amount, $date, $notes);
    }

    // Execute the prepared statement
    if ($stmt->execute()) {
        // Set success message in session
        $_SESSION['message'] = "تم الحفظ بنجاح!";
    } else {
        // Set error message in session
        $_SESSION['message'] = "حدث خطأ: " . $stmt->error;
    }

    // Close the statement and database connection
    $stmt->close();
    $conn->close();

    // Redirect the user back to the payments page
    header("Location: payments.php");
    exit();
}
?>