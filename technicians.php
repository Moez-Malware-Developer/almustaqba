<?php

// Database connection details
$servername = "localhost";
$username = "root"; // Your database username
$password = ""; // Your database password
$dbname = "workshop_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8 for Arabic support
$conn->set_charset("utf8mb4");

// Function to calculate dues based on repairs and payments
// This function needs a 'technician_payments' table to function correctly.
// The `agreed_price - total_cost` calculation is commented out as `total_cost` is not a column in your `repairs` table.
// Dues will show as 0 until you implement the logic to calculate the cost of parts used in a repair.
function calculateDues($conn, $technician) {
    if ($technician['agreement_type'] !== 'percentage') {
        return 0;
    }

    $technicianId = $technician['technician_id'];
    $percentage = $technician['value'];

    // Get total profit from completed repairs.
    // This part is simplified as 'total_cost' is not in the 'repairs' table.
    // To properly calculate this, you would need to link repairs to a parts-used table and calculate costs.
    $sql_repairs = "SELECT SUM(agreed_price) AS total_profit FROM repairs WHERE assigned_to = ? AND status = 'مكتملة'";
    $stmt_repairs = $conn->prepare($sql_repairs);
    if (!$stmt_repairs) {
        error_log("SQL Prepare Error: " . $conn->error);
        return 0;
    }
    $stmt_repairs->bind_param("i", $technicianId);
    $stmt_repairs->execute();
    $result_repairs = $stmt_repairs->get_result();
    $row_repairs = $result_repairs->fetch_assoc();
    $total_profit = $row_repairs['total_profit'] ?? 0;
    $stmt_repairs->close();

    // Get total payments made to the technician
    $sql_payments = "SELECT SUM(amount) AS total_payments FROM technician_payments WHERE technician_id = ?";
    $stmt_payments = $conn->prepare($sql_payments);
    if (!$stmt_payments) {
        error_log("SQL Prepare Error: " . $conn->error);
        return 0;
    }
    $stmt_payments->bind_param("i", $technicianId);
    $stmt_payments->execute();
    $result_payments = $stmt_payments->get_result();
    $row_payments = $result_payments->fetch_assoc();
    $total_payments = $row_payments['total_payments'] ?? 0;
    $stmt_payments->close();
    
    $dues = ($total_profit * ($percentage / 100)) - $total_payments;
    return $dues;
}

// Handle POST and GET requests for actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_REQUEST['action'] ?? '';
    $message = '';
    $status = '';

    // Action to create technician_payments table if it doesn't exist
    $sql_create_table = "CREATE TABLE IF NOT EXISTS `technician_payments` (
      `payment_id` int(11) NOT NULL AUTO_INCREMENT,
      `technician_id` int(11) DEFAULT NULL,
      `amount` decimal(10,2) NOT NULL,
      `payment_date` datetime DEFAULT current_timestamp(),
      PRIMARY KEY (`payment_id`),
      KEY `technician_id` (`technician_id`),
      CONSTRAINT `technician_payments_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    if ($conn->query($sql_create_table) === FALSE) {
        error_log("Error creating technician_payments table: " . $conn->error);
    }
    
    switch ($action) {
        case 'add':
            $technicianName = $_POST['technicianName'] ?? '';
            $technicianPhone = $_POST['technicianPhone'] ?? '';
            $nationality = $_POST['nationality'] ?? '';
            $specialty = $_POST['specialty'] ?? '';
            $agreementType = $_POST['agreementType'] ?? '';
            $value = ($agreementType === 'percentage') ? ($_POST['percentage'] ?? null) : ($_POST['salary'] ?? null);

            if (!empty($technicianName) && !empty($technicianPhone) && !empty($agreementType) && isset($value)) {
                $sql = "INSERT INTO technicians (technician_name, technician_phone, nationality, specialty, agreement_type, value) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssd", $technicianName, $technicianPhone, $nationality, $specialty, $agreementType, $value);
                    if ($stmt->execute()) {
                        $message = 'تم إضافة الفني بنجاح.';
                        $status = 'success';
                    } else {
                        $message = 'خطأ في إضافة الفني: ' . $stmt->error;
                        $status = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = 'خطأ في إعداد الاستعلام: ' . $conn->error;
                    $status = 'error';
                }
            } else {
                $message = 'يرجى ملء جميع الحقول المطلوبة.';
                $status = 'error';
            }
            break;

        case 'update':
            $technicianId = $_POST['technicianId'] ?? '';
            $technicianName = $_POST['technicianName'] ?? '';
            $technicianPhone = $_POST['technicianPhone'] ?? '';
            $nationality = $_POST['nationality'] ?? '';
            $specialty = $_POST['specialty'] ?? '';
            $agreementType = $_POST['agreementType'] ?? '';
            $value = ($agreementType === 'percentage') ? ($_POST['percentage'] ?? null) : ($_POST['salary'] ?? null);

            if (!empty($technicianId) && !empty($technicianName) && !empty($technicianPhone) && !empty($agreementType) && isset($value)) {
                $sql = "UPDATE technicians SET technician_name=?, technician_phone=?, nationality=?, specialty=?, agreement_type=?, value=? WHERE technician_id=?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssdi", $technicianName, $technicianPhone, $nationality, $specialty, $agreementType, $value, $technicianId);
                    if ($stmt->execute()) {
                        $message = 'تم تحديث بيانات الفني بنجاح.';
                        $status = 'success';
                    } else {
                        $message = 'خطأ في تحديث بيانات الفني: ' . $stmt->error;
                        $status = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = 'خطأ في إعداد الاستعلام: ' . $conn->error;
                    $status = 'error';
                }
            } else {
                $message = 'يرجى ملء جميع الحقول المطلوبة.';
                $status = 'error';
            }
            break;
            
        case 'delete':
            $technicianId = $_REQUEST['technicianId'] ?? '';
            if (!empty($technicianId)) {
                $conn->begin_transaction();
                try {
                    // No need to delete from technician_payments explicitly due to ON DELETE CASCADE
                    $sql_technician = "DELETE FROM technicians WHERE technician_id = ?";
                    $stmt_technician = $conn->prepare($sql_technician);
                    if ($stmt_technician) {
                        $stmt_technician->bind_param("i", $technicianId);
                        $stmt_technician->execute();
                        $stmt_technician->close();
                        $conn->commit();
                        $message = 'تم حذف الفني وجميع بياناته بنجاح.';
                        $status = 'success';
                    } else {
                        throw new mysqli_sql_exception("Failed to prepare technician deletion statement.");
                    }
                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    $message = 'خطأ في حذف الفني: ' . $exception->getMessage();
                    $status = 'error';
                }
            } else {
                $message = 'معرف الفني مفقود.';
                $status = 'error';
            }
            break;

        case 'payDues':
            $technicianId = $_POST['technicianId'] ?? '';
            $amount = $_POST['amount'] ?? '';

            if (!empty($technicianId) && !empty($amount)) {
                $sql = "INSERT INTO technician_payments (technician_id, amount) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("id", $technicianId, $amount);
                    if ($stmt->execute()) {
                        $message = 'تم صرف المستحقات بنجاح.';
                        $status = 'success';
                    } else {
                        $message = 'خطأ في صرف المستحقات: ' . $stmt->error;
                        $status = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = 'خطأ في إعداد الاستعلام: ' . $conn->error;
                    $status = 'error';
                }
            } else {
                $message = 'بيانات الدفع غير مكتملة.';
                $status = 'error';
            }
            break;

        case 'getPayments': // This is a special case that still needs to return JSON
            $technicianId = $_POST['technicianId'] ?? '';
            if (!empty($technicianId)) {
                $sql = "SELECT amount, payment_date FROM technician_payments WHERE technician_id = ? ORDER BY payment_date DESC";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $technicianId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $payments = [];
                    while ($row = $result->fetch_assoc()) {
                        $payments[] = $row;
                    }
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'success', 'payments' => $payments]);
                    $stmt->close();
                    $conn->close();
                    exit; // Exit to prevent the rest of the HTML from being rendered
                } else {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'error', 'message' => 'خطأ في إعداد الاستعلام: ' . $conn->error]);
                    $conn->close();
                    exit;
                }
            } else {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'معرف الفني مفقود.']);
                $conn->close();
                exit;
            }
            break;
    }

    // Redirect to self with message parameters to avoid form resubmission
    if (!empty($message)) {
        header("Location: technicians.php?status=$status&message=" . urlencode($message));
        exit;
    }
}

// Fetch all technicians for initial page load or after an action
$sql = "SELECT * FROM technicians ORDER BY technician_name ASC";
$result = $conn->query($sql);
$technicians = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
}

// Get message from URL parameters
$toast_message = $_GET['message'] ?? '';
$toast_status = $_GET['status'] ?? '';

// End of PHP block
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>إدارة الفنيين - إدارة الورش</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f3f4f6;
        }
        #sidebar {
            transition: transform 0.3s ease-in-out;
            background-color: #A20A0A;
        }
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(100%);
            }
            #sidebar.open {
                transform: translateX(0);
            }
        }
        .sidebar-link {
            transition: all 0.2s ease;
        }
        .sidebar-link:hover {
            background-color: #C02020;
        }
        .accent-color {
            color: #FF6347;
        }
        .bg-accent {
            background-color: #FF6347;
        }
        .form-input {
            transition: all 0.3s ease;
        }
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(162, 10, 10, 0.2);
        }
        .agreement-type {
            display: none;
        }
        .agreement-type.active {
            display: block;
        }
        .technician-table {
            width: 100%;
            border-collapse: collapse;
        }
        .technician-table th, .technician-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
        }
        .technician-table th {
            background-color: #f9fafb;
            font-weight: 700;
        }
        .technician-table tr:hover {
            background-color: #f9fafb;
        }
        #toastContainer {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 1000;
        }
        .toast {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">
    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">إدارة الفنيين</h2>
        </div>
        <button id="closeAppBtn" class="text-gray-500 hover:text-gray-800 text-2xl lg:hidden">
            <i class="fas fa-times"></i>
        </button>
    </header>

    <aside id="sidebar" class="fixed inset-y-0 right-0 z-50 w-64 text-white shadow-lg lg:relative lg:translate-x-0 transform no-print">
        <div class="p-6 h-full flex flex-col">
            <button id="closeSidebarBtn" class="absolute top-4 left-4 text-white text-xl lg:hidden">
                <i class="fas fa-times"></i>
            </button>
            <h1 class="text-3xl font-bold mb-8 text-center border-b border-[#C02020] pb-4">إدارة الورش</h1>
            <nav class="space-y-3 flex-1">
                <a href="index.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-tachometer-alt text-lg"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="sales.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-cash-register text-lg"></i>
                    <span>نقطة البيع (الكاشير)</span>
                </a>
                <a href="repairs.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-boxes text-lg"></i>
                    <span>إدارة الصيانة</span>
                </a>
                <a href="products.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-box text-lg"></i>
                    <span>إدارة المنتجات</span>
                </a>
                <a href="technicians.php" class="flex items-center gap-4 p-3 rounded-lg bg-[#FF6347] text-[#A20A0A] font-semibold sidebar-link">
                    <i class="fas fa-users-cog text-lg"></i>
                    <span>إدارة الفنيين</span>
                </a>
                <a href="payments.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-hand-holding-usd text-lg"></i>
                    <span>المدفوعات والمصروفات</span>
                </a>
                <a href="installments.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-money-check-alt text-lg"></i>
                    <span>الأقساط / الآجل</span>
                </a>
                <a href="reports.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-chart-bar text-lg"></i>
                    <span>التقارير</span>
                </a>
            </nav>
            <a href="#" id="logoutBtn" class="flex items-center gap-4 p-3 rounded-lg text-red-300 hover:text-red-100 hover:bg-[#C02020] sidebar-link mt-auto">
                <i class="fas fa-sign-out-alt text-lg"></i>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 p-4 lg:p-8 pt-[64px] lg:pt-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-users-cog accent-color"></i>
                <span>إدارة الفنيين</span>
            </h1>
            <button id="addTechnicianBtn" class="bg-[#A20A0A] text-white px-4 py-2 rounded-lg hover:bg-[#C02020] transition-colors duration-200 mt-4 md:mt-0">
                <i class="fas fa-plus-circle ml-2"></i>
                إضافة فني جديد
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="searchTechnician" class="block text-sm font-medium text-gray-700 mb-1">بحث</label>
                    <input type="text" id="searchTechnician" placeholder="ابحث بالاسم، الهاتف، أو التخصص" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                </div>
                <div class="flex-1">
                    <label for="filterSpecialty" class="block text-sm font-medium text-gray-700 mb-1">تخصص</label>
                    <input type="text" id="filterSpecialty" placeholder="فلترة حسب التخصص" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                </div>
            </div>
        </div>

        <div id="technicianFormModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-2/3 max-h-screen overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800" id="formTitle">إضافة فني جديد</h2>
                </div>
                <form id="technicianForm" action="technicians.php" method="POST" class="p-6 space-y-4">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="technicianId" name="technicianId">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="technicianName" class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                            <input type="text" id="technicianName" name="technicianName" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                        </div>
                        <div>
                            <label for="technicianPhone" class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                            <input type="tel" id="technicianPhone" name="technicianPhone" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                        </div>
                        <div>
                            <label for="nationality" class="block text-sm font-medium text-gray-700 mb-1">الجنسية</label>
                            <input type="text" id="nationality" name="nationality" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                        </div>
                        <div>
                            <label for="specialty" class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
                            <input type="text" id="specialty" name="specialty" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                        </div>
                        <div>
                            <label for="agreementType" class="block text-sm font-medium text-gray-700 mb-1">نوع الاتفاق</label>
                            <select id="agreementType" name="agreementType" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                                <option value="">اختر نوع الاتفاق</option>
                                <option value="percentage">نسبة</option>
                                <option value="salary">راتب ثابت</option>
                            </select>
                        </div>
                        <div id="percentageField" class="agreement-type">
                            <label for="percentage" class="block text-sm font-medium text-gray-700 mb-1">النسبة %</label>
                            <input type="number" id="percentage" name="percentage" min="0" max="100" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                        </div>
                        <div id="salaryField" class="agreement-type">
                            <label for="salary" class="block text-sm font-medium text-gray-700 mb-1">الراتب الشهري</label>
                            <input type="number" id="salary" name="salary" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-2 space-x-reverse pt-4">
                        <button type="button" id="cancelForm" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
                            إلغاء
                        </button>
                        <button type="submit" class="bg-[#A20A0A] text-white px-4 py-2 rounded-lg hover:bg-[#C02020]">
                            حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-700">قائمة الفنيين</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="technician-table">
                    <thead>
                        <tr>
                            <th>الاسم الكامل</th>
                            <th>رقم الهاتف</th>
                            <th>الجنسية</th>
                            <th>التخصص</th>
                            <th>نوع الاتفاق</th>
                            <th>القيمة</th>
                            <th>المستحقات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="techniciansList">
                        <?php
                        if (empty($technicians)) {
                            echo '<tr><td colspan="8" class="text-center py-4 text-gray-500">لا يوجد فنيين مسجلين أو مطابقين للبحث</td></tr>';
                        } else {
                            foreach ($technicians as $tech) {
                                $dues = calculateDues($conn, $tech);
                                $agreementValue = ($tech['agreement_type'] === 'percentage') ? $tech['value'] . '%' : $tech['value'] . ' د.ل';
                                $duesClass = ($dues > 0) ? 'text-green-600' : 'text-gray-500';
                                ?>
                                <tr data-id="<?= $tech['technician_id'] ?>">
                                    <td><?= htmlspecialchars($tech['technician_name']) ?></td>
                                    <td><?= htmlspecialchars($tech['technician_phone']) ?></td>
                                    <td><?= htmlspecialchars($tech['nationality']) ?></td>
                                    <td><?= htmlspecialchars($tech['specialty']) ?></td>
                                    <td><?= ($tech['agreement_type'] === 'percentage') ? 'نسبة' : 'راتب ثابت' ?></td>
                                    <td><?= htmlspecialchars($agreementValue) ?></td>
                                    <td><span class="font-bold <?= $duesClass ?>"><?= number_format($dues, 2) ?> د.ل</span></td>
                                    <td>
                                        <form action="technicians.php" method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من صرف هذا المبلغ؟');">
                                            <input type="hidden" name="action" value="payDues">
                                            <input type="hidden" name="technicianId" value="<?= $tech['technician_id'] ?>">
                                            <input type="hidden" name="amount" value="<?= $dues ?>">
                                            <button type="submit" class="pay-dues-btn bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600 transition-colors duration-200" <?= $dues <= 0 ? 'disabled' : '' ?>>
                                                <i class="fas fa-money-bill-wave"></i> صرف
                                            </button>
                                        </form>
                                        <button class="view-payments-btn bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600 transition-colors duration-200" data-id="<?= $tech['technician_id'] ?>">
                                            <i class="fas fa-history"></i> السجل
                                        </button>
                                        <button class="edit-btn text-blue-600 hover:text-blue-800 ml-2" data-id="<?= $tech['technician_id'] ?>"
                                            data-technician_name="<?= htmlspecialchars($tech['technician_name']) ?>"
                                            data-technician_phone="<?= htmlspecialchars($tech['technician_phone']) ?>"
                                            data-nationality="<?= htmlspecialchars($tech['nationality']) ?>"
                                            data-specialty="<?= htmlspecialchars($tech['specialty']) ?>"
                                            data-agreement_type="<?= htmlspecialchars($tech['agreement_type']) ?>"
                                            data-value="<?= htmlspecialchars($tech['value']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="technicians.php?action=delete&technicianId=<?= $tech['technician_id'] ?>" 
                                            class="delete-btn text-red-600 hover:text-red-800" 
                                            onclick="return confirm('هل أنت متأكد من حذف هذا الفني؟ سيتم حذف جميع بياناته.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="paymentHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-2/3 max-h-screen overflow-y-auto p-6">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-bold text-gray-800">سجل المدفوعات لـ: <span id="technicianNameForPayments"></span></h3>
                <button id="closePaymentHistoryModal" class="text-gray-500 hover:text-gray-800 text-2xl"><i class="fas fa-times"></i></button>
            </div>
            <table class="w-full text-right table-auto border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2 border border-gray-300">التاريخ</th>
                        <th class="p-2 border border-gray-300">المبلغ</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody"></tbody>
            </table>
            <p id="noPaymentsMessage" class="text-center text-gray-500 mt-4 hidden">لا توجد مدفوعات مسجلة لهذا الفني.</p>
        </div>
    </div>
    
    <div id="toastContainer" class="fixed bottom-4 right-4 z-50"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuBtn');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');
            const closeAppBtn = document.getElementById('closeAppBtn');
            const logoutBtn = document.getElementById('logoutBtn');
            const addTechnicianBtn = document.getElementById('addTechnicianBtn');
            const technicianFormModal = document.getElementById('technicianFormModal');
            const technicianForm = document.getElementById('technicianForm');
            const cancelForm = document.getElementById('cancelForm');
            const agreementType = document.getElementById('agreementType');
            const percentageField = document.getElementById('percentageField');
            const salaryField = document.getElementById('salaryField');
            const searchTechnicianInput = document.getElementById('searchTechnician');
            const filterSpecialtyInput = document.getElementById('filterSpecialty');
            const paymentHistoryModal = document.getElementById('paymentHistoryModal');
            const closePaymentHistoryModalBtn = document.getElementById('closePaymentHistoryModal');
            const technicianNameForPayments = document.getElementById('technicianNameForPayments');
            const paymentsTableBody = document.getElementById('paymentsTableBody');
            const noPaymentsMessage = document.getElementById('noPaymentsMessage');
            const formTitle = document.getElementById('formTitle');
            const technicianIdInput = document.getElementById('technicianId');
            const technicianNameInput = document.getElementById('technicianName');
            const technicianPhoneInput = document.getElementById('technicianPhone');
            const nationalityInput = document.getElementById('nationality');
            const specialtyInput = document.getElementById('specialty');
            const percentageInput = document.getElementById('percentage');
            const salaryInput = document.getElementById('salary');
            const actionInput = document.getElementById('action');
            const techniciansList = document.getElementById('techniciansList');

            const currency = 'د.ل';

            // --- PHP-based Toast Notifications ---
            const showToast = (message, type = 'info') => {
                const toastContainer = document.getElementById('toastContainer');
                if (!message) return;
                
                const toast = document.createElement('div');
                let bgColor;
                let icon;

                if (type === 'success') {
                    bgColor = 'bg-green-500';
                    icon = '<i class="fas fa-check-circle"></i>';
                } else if (type === 'error') {
                    bgColor = 'bg-red-500';
                    icon = '<i class="fas fa-exclamation-triangle"></i>';
                } else {
                    bgColor = 'bg-blue-500';
                    icon = '<i class="fas fa-info-circle"></i>';
                }

                toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-md flex items-center gap-2 transition-all duration-300 transform translate-x-full opacity-0 mt-2`;
                toast.innerHTML = `${icon}<span>${decodeURIComponent(message)}</span>`;
                toastContainer.appendChild(toast);
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                    toast.style.opacity = '1';
                }, 100);

                setTimeout(() => {
                    toast.style.transform = 'translateX(150%)';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            };

            const urlParams = new URLSearchParams(window.location.search);
            const messageFromUrl = urlParams.get('message');
            const statusFromUrl = urlParams.get('status');
            if (messageFromUrl) {
                showToast(messageFromUrl, statusFromUrl);
            }

            // --- Modal and Form Handling ---
            const showModal = () => {
                technicianFormModal.classList.remove('hidden');
            };

            const hideModal = () => {
                technicianFormModal.classList.add('hidden');
                technicianForm.reset();
                percentageField.classList.remove('active');
                salaryField.classList.remove('active');
                formTitle.textContent = 'إضافة فني جديد';
                actionInput.value = 'add';
                technicianIdInput.value = '';
            };

            addTechnicianBtn.addEventListener('click', showModal);
            cancelForm.addEventListener('click', hideModal);

            agreementType.addEventListener('change', (e) => {
                const type = e.target.value;
                percentageField.classList.remove('active');
                salaryField.classList.remove('active');
                percentageInput.required = false;
                salaryInput.required = false;

                if (type === 'percentage') {
                    percentageField.classList.add('active');
                    percentageInput.required = true;
                } else if (type === 'salary') {
                    salaryField.classList.add('active');
                    salaryInput.required = true;
                }
            });

            // --- Edit Technician ---
            techniciansList.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.edit-btn');
                if (editBtn) {
                    const data = editBtn.dataset;
                    formTitle.textContent = 'تعديل بيانات فني';
                    actionInput.value = 'update';
                    technicianIdInput.value = data.id;
                    technicianNameInput.value = data.technician_name;
                    technicianPhoneInput.value = data.technician_phone;
                    nationalityInput.value = data.nationality;
                    specialtyInput.value = data.specialty;
                    agreementType.value = data.agreement_type;

                    percentageField.classList.remove('active');
                    salaryField.classList.remove('active');
                    percentageInput.required = false;
                    salaryInput.required = false;

                    if (data.agreement_type === 'percentage') {
                        percentageField.classList.add('active');
                        percentageInput.value = data.value;
                        percentageInput.required = true;
                    } else {
                        salaryField.classList.add('active');
                        salaryInput.value = data.value;
                        salaryInput.required = true;
                    }
                    showModal();
                }
            });

            // --- Search and Filter ---
            const filterTechnicians = () => {
                const searchTerm = searchTechnicianInput.value.toLowerCase();
                const specialtyTerm = filterSpecialtyInput.value.toLowerCase();
                const rows = techniciansList.querySelectorAll('tr');

                rows.forEach(row => {
                    const name = row.cells[0].textContent.toLowerCase();
                    const phone = row.cells[1].textContent.toLowerCase();
                    const specialty = row.cells[3].textContent.toLowerCase();
                    
                    const matchesSearch = name.includes(searchTerm) || phone.includes(searchTerm) || specialty.includes(searchTerm);
                    const matchesSpecialty = specialty.includes(specialtyTerm) || specialtyTerm === '';

                    if (matchesSearch && matchesSpecialty) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            };

            searchTechnicianInput.addEventListener('input', filterTechnicians);
            filterSpecialtyInput.addEventListener('input', filterTechnicians);

            // --- Payment History Modal ---
            techniciansList.addEventListener('click', (e) => {
                const viewPaymentsBtn = e.target.closest('.view-payments-btn');
                if (viewPaymentsBtn) {
                    const technicianId = viewPaymentsBtn.dataset.id;
                    const technicianName = viewPaymentsBtn.closest('tr').cells[0].textContent;
                    
                    technicianNameForPayments.textContent = technicianName;
                    paymentsTableBody.innerHTML = '';
                    noPaymentsMessage.classList.add('hidden');
                    
                    fetch('technicians.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=getPayments&technicianId=${technicianId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.payments.length > 0) {
                            data.payments.forEach(payment => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td class="p-2 border border-gray-300">${new Date(payment.payment_date).toLocaleString('ar-SA')}</td>
                                    <td class="p-2 border border-gray-300">${parseFloat(payment.amount).toFixed(2)} ${currency}</td>
                                `;
                                paymentsTableBody.appendChild(row);
                            });
                        } else {
                            noPaymentsMessage.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching payments:', error);
                        noPaymentsMessage.classList.remove('hidden');
                        noPaymentsMessage.textContent = 'حدث خطأ أثناء تحميل البيانات.';
                    });

                    paymentHistoryModal.classList.remove('hidden');
                }
            });

            closePaymentHistoryModalBtn.addEventListener('click', () => {
                paymentHistoryModal.classList.add('hidden');
            });
            
            // --- Sidebar Toggling ---
            menuBtn.addEventListener('click', () => {
                sidebar.classList.add('open');
            });

            closeSidebarBtn.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        });
    </script>
</body>
</html>