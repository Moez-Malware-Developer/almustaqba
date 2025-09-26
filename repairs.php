<?php
// index.php - ملف متكامل لإدارة الورش
// ------------------------------------
// 1. منطق معالجة طلبات POST و GET
// ------------------------------------

// db_connect.php
$servername = "localhost";
$username = "root"; // استبدل باسم المستخدم الخاص بقاعدة البيانات
$password = ""; // استبدل بكلمة المرور الخاصة بقاعدة البيانات
$dbname = "workshop_management";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    // يمكنك طباعة رسالة خطأ بسيطة بدلاً من json
    die("Connection failed: " . $conn->connect_error);
}

// تعيين ترميز الأحرف
$conn->set_charset("utf8mb4");

// معالجة طلبات AJAX
if (isset($_GET['action'])) {
    // لا تستخدم json
    header('Content-Type: text/plain');
    $action = $_GET['action'];

    if ($action == 'add_repair') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "Error: Invalid request method.";
            exit;
        }

        $customer_name = $conn->real_escape_string($_POST['customerName']);
        $customer_phone = $conn->real_escape_string($_POST['customerPhone']);
        $device_type = $conn->real_escape_string($_POST['deviceType']);
        $fault_type = $conn->real_escape_string($_POST['faultType']);
        $agreed_price = floatval($_POST['agreedPrice']);
        $advance_payment = floatval($_POST['advancePayment']);
        $additional_notes = $conn->real_escape_string($_POST['additionalNotes']);
        $status = 'مستلمة';

        $sql = "INSERT INTO repairs (customer_name, customer_phone, device_type, fault_type, agreed_price, advance_payment, additional_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ssssddss", $customer_name, $customer_phone, $device_type, $fault_type, $agreed_price, $advance_payment, $additional_notes, $status);
            if ($stmt->execute()) {
                $repair_id = $stmt->insert_id;
                echo "success," . $repair_id;
            } else {
                echo "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error: Failed to prepare statement: " . $conn->error;
        }
        $conn->close();
        exit;
    }

    if ($action == 'fetch_repairs') {
        $sql = "SELECT r.*, t.technician_name FROM repairs r LEFT JOIN technicians t ON r.assigned_to = t.technician_id ORDER BY r.received_at DESC";
        $result = $conn->query($sql);
        $output = "success\n";
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output .= implode(",", $row) . "\n";
            }
        }
        echo rtrim($output, "\n"); // لإزالة سطر فارغ في النهاية
        $conn->close();
        exit;
    }

    if ($action == 'fetch_technicians') {
        $sql = "SELECT technician_id, technician_name FROM technicians";
        $result = $conn->query($sql);
        $output = "success\n";
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output .= $row['technician_id'] . "," . $row['technician_name'] . "\n";
            }
        }
        echo rtrim($output, "\n");
        $conn->close();
        exit;
    }

    if ($action == 'update_repair_status') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "Error: Invalid request method.";
            exit;
        }

        $repair_id = intval($_POST['repair_id']);
        $new_status = $conn->real_escape_string($_POST['status']);
        $notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : NULL;

        $conn->begin_transaction();

        try {
            $sql_update = "UPDATE repairs SET status = ? WHERE repair_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $new_status, $repair_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            $sql_log = "INSERT INTO repair_log (repair_id, status_change, notes) VALUES (?, ?, ?)";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("iss", $repair_id, $new_status, $notes);
            $stmt_log->execute();
            $stmt_log->close();

            $conn->commit();
            echo "success,تم تحديث حالة الصيانة بنجاح.";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            echo "Error: Failed to update status: " . $e->getMessage();
        }
        $conn->close();
        exit;
    }

    if ($action == 'assign_technician') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "Error: Invalid request method.";
            exit;
        }
        $repair_id = intval($_POST['repair_id']);
        $technician_id = intval($_POST['technician_id']);

        $sql = "UPDATE repairs SET assigned_to = ?, status = 'جاري الصيانة' WHERE repair_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ii", $technician_id, $repair_id);
            if ($stmt->execute()) {
                echo "success,تم تعيين الفني بنجاح.";
            } else {
                echo "Error: Failed to assign technician: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error: Failed to prepare statement: " . $conn->error;
        }
        $conn->close();
        exit;
    }
    
    if ($action == 'finish_repair') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "Error: Invalid request method.";
            exit;
        }

        $repair_id = intval($_POST['repairId']);
        $parts_cost = floatval($_POST['partsCost']);

        $conn->begin_transaction();

        try {
            $sql_repair = "SELECT agreed_price, advance_payment, assigned_to FROM repairs WHERE repair_id = ?";
            $stmt_repair = $conn->prepare($sql_repair);
            $stmt_repair->bind_param("i", $repair_id);
            $stmt_repair->execute();
            $result_repair = $stmt_repair->get_result();
            $repair_details = $result_repair->fetch_assoc();
            $stmt_repair->close();

            if (!$repair_details) {
                throw new Exception("Repair not found.");
            }
            
            $agreed_price = $repair_details['agreed_price'];
            $technician_id = $repair_details['assigned_to'];
            $advance_payment = $repair_details['advance_payment'];

            $profit = $agreed_price - $parts_cost;

            $status = 'تم الانتهاء';
            $sql_update_repair = "UPDATE repairs SET status = ?, profit = ? WHERE repair_id = ?";
            $stmt_update_repair = $conn->prepare($sql_update_repair);
            $stmt_update_repair->bind_param("sdi", $status, $profit, $repair_id);
            $stmt_update_repair->execute();
            $stmt_update_repair->close();

            $log_notes = "تم إنهاء الصيانة بنجاح. الربح: " . $profit . " د.ل";
            $sql_log = "INSERT INTO repair_log (repair_id, status_change, notes) VALUES (?, ?, ?)";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("iss", $repair_id, $status, $log_notes);
            $stmt_log->execute();
            $stmt_log->close();

            $payment_method = 'cash';
            $sql_sale = "INSERT INTO sales (total_amount, payment_method, technician_id) VALUES (?, ?, ?)";
            $stmt_sale = $conn->prepare($sql_sale);
            $stmt_sale->bind_param("dsi", $agreed_price, $payment_method, $technician_id);
            $stmt_sale->execute();
            $sale_id = $stmt_sale->insert_id;
            $stmt_sale->close();

            $repair_service_product_id = 1;
            $sql_sale_item = "INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $stmt_sale_item = $conn->prepare($sql_sale_item);
            $quantity = 1;
            $price = $agreed_price;
            $stmt_sale_item->bind_param("iiid", $sale_id, $repair_service_product_id, $quantity, $price);
            $stmt_sale_item->execute();
            $stmt_sale_item->close();

            $conn->commit();
            echo "success," . $profit;
        } catch (Exception $e) {
            $conn->rollback();
            echo "Error: Failed to finalize repair: " . $e->getMessage();
        }
        $conn->close();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>إدارة الصيانة - قيد التطوير</title>
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
            color: #A20A0A;
        }
        .bg-accent {
            background-color: #A20A0A;
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">
    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">إدارة الورش</h2>
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
                <a href="index.php" class="flex items-center gap-4 p-3 rounded-lg bg-[#FF6347] text-[#A20A0A] font-semibold sidebar-link">
                    <i class="fas fa-tachometer-alt text-lg"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="sales.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-cash-register text-lg"></i>
                    <span>نقطة البيع (الكاشير)</span>
                </a>
                <a href="repairs.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-boxes text-lg"></i>
                    <span>إدارة الصيانة</span>
                </a>
                <a href="products.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-box text-lg"></i>
                    <span>إدارة المنتجات</span>
                </a>
                <a href="technicians.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-users-cog text-lg"></i>
                    <span>إدارة الفنيين</span>
                </a>
                <a href="payments.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-hand-holding-usd text-lg"></i>
                    <span>المدفوعات والمصروفات</span>
                </a>
                <a href="installments.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-money-check-alt text-lg"></i>
                    <span>الأقساط / الآجل</span>
                </a>
                <a href="reports.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
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

    <main class="flex-1 p-4 lg:p-8 pt-[64px] lg:pt-8 flex items-center justify-center">
        <div class="text-center bg-white rounded-lg shadow-xl border border-gray-200 p-12 max-w-lg">
            <i class="fas fa-code text-6xl accent-color mb-4"></i>
            <h1 class="text-4xl font-extrabold text-gray-800 mb-4">الصفحة قيد التطوير</h1>
            <p class="text-lg text-gray-600 mb-6">
                نحن نعمل بجد لإكمال هذه الميزة. يرجى العودة قريباً!
            </p>
            <div class="mt-8 text-gray-500 text-sm">
                <p>⚠️ تم نقل جميع وظائف "إدارة الصيانة" السابقة إلى ملف **repairs.php**.</p>
                <p>يمكنك استخدام الشريط الجانبي للتنقل بين الصفحات.</p>
            </div>
        </div>
    </main>
    
    <script>
        // منطق إظهار وإخفاء الشريط الجانبي للأجهزة الصغيرة
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.getElementById('menuBtn');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');

        if (menuBtn) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.add('open');
            });
        }
        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        }
        
        // يمكن إضافة المزيد من منطق JavaScript الأساسي هنا إذا لزم الأمر
        // مثل زر "تسجيل الخروج" أو "إغلاق التطبيق"
        document.getElementById('logoutBtn').addEventListener('click', (e) => {
            e.preventDefault();
            alert('تم تسجيل الخروج (إجراء وهمي)');
        });

        document.getElementById('closeAppBtn')?.addEventListener('click', () => {
            alert('إغلاق التطبيق (إجراء وهمي)');
            // window.close() قد لا تعمل حسب متصفح المستخدم وإعداداته
        });
    </script>
</body>
</html>