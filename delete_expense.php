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
    die("Connection failed: " . $conn->connect_error);
}

// -----------------------------------------------------------
// Process expense deletion
// -----------------------------------------------------------
// Check if the expense ID is provided in the URL
if (isset($_GET['id'])) {
    // Get and sanitize the expense ID from the URL
    $expense_id = intval($_GET['id']);

    // Prepare a SQL statement to prevent SQL injection
    $sql = "DELETE FROM expenses WHERE expense_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $expense_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Set a success message in the session
        $_SESSION['message'] = "تم حذف السجل بنجاح!";
    } else {
        // Set an error message in the session
        $_SESSION['message'] = "حدث خطأ أثناء الحذف: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
} else {
    // If no ID is provided, set an error message
    $_SESSION['message'] = "لم يتم تحديد السجل المراد حذفه.";
}

// Close the database connection
$conn->close();

// -----------------------------------------------------------
// Redirect back to the payments page
// -----------------------------------------------------------
header("Location: payments.php");
exit();
?>