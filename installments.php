<?php

header('Content-Type: text/html; charset=utf-8');

// Step 1: Database connection configuration
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "workshop_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("<html><body><p>Connection failed: " . $conn->connect_error . "</p></body></html>");
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

// Step 2: Handle POST requests for adding payments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_payment') {
        if (!isset($_POST['credit_sale_id']) || !isset($_POST['payment_amount'])) {
            die("<html><body><p>Required data missing.</p></body></html>");
        }

        $credit_sale_id = intval($_POST['credit_sale_id']);
        $payment_amount = floatval($_POST['payment_amount']);

        if ($payment_amount <= 0) {
            die("<html><body><p>Invalid payment amount.</p></body></html>");
        }

        // Server-side check for remaining amount before processing
        $sql_check_remaining = "SELECT remaining_amount FROM credit_sales WHERE credit_sale_id = ?";
        $stmt_check = $conn->prepare($sql_check_remaining);
        $stmt_check->bind_param("i", $credit_sale_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        
        if (!$row_check) {
            die("<html><body><p>Invoice not found.</p></body></html>");
        }
        
        $current_remaining = $row_check['remaining_amount'];

        if ($payment_amount > $current_remaining) {
            die("<html><body><p>Error: The payment amount cannot exceed the remaining balance.</p></body></html>");
        }
        
        // Begin transaction for data consistency
        $conn->begin_transaction();

        try {
            // Update the credit_sales table
            $sql_update = "UPDATE credit_sales SET remaining_amount = remaining_amount - ?, initial_payment = initial_payment + ? WHERE credit_sale_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ddi", $payment_amount, $payment_amount, $credit_sale_id);
            $stmt_update->execute();

            // Check if the update was successful
            if ($stmt_update->affected_rows === 0) {
                $conn->rollback();
                die("<html><body><p>Invoice not found or no change made.</p></body></html>");
            }

            // Insert new payment into installment_payments table
            $sql_insert = "INSERT INTO installment_payments (credit_sale_id, payment_amount) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("id", $credit_sale_id, $payment_amount);
            $stmt_insert->execute();

            $conn->commit();
            // Redirect to the same page to show the updated data
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            die("<html><body><p>Error processing payment: " . $e->getMessage() . "</p></body></html>");
        } finally {
            $conn->close();
        }
    }
}

// Step 3: Fetch data for both tables with new overdue logic and filters
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("<html><body><p>Connection failed: " . $conn->connect_error . "</p></body></html>");
}
$conn->set_charset("utf8mb4");

// Dynamic date filter logic
$date_filter = $_GET['date_filter'] ?? 'all';
$start_date = null;
$end_date = null;

if ($date_filter === 'last_week') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
} elseif ($date_filter === 'last_month') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
} elseif ($date_filter === 'last_year') {
    $start_date = date('Y-m-d', strtotime('-1 year'));
} elseif ($date_filter === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

$date_condition = '';
if ($start_date) {
    if ($end_date) {
        $date_condition = " AND sale_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    } else {
        $date_condition = " AND sale_date >= '$start_date 00:00:00'";
    }
}

// Query for the outstanding installments table, with overdue logic
$sql_installments = "
    SELECT 
        cs.*, 
        (SELECT MAX(payment_date) FROM installment_payments WHERE credit_sale_id = cs.credit_sale_id) AS last_payment_date
    FROM 
        credit_sales cs
    WHERE 
        cs.remaining_amount > 0 
        AND cs.technician_id = 'repair' /* فقط مبيعات الورشة */
        $date_condition
    ORDER BY 
        cs.sale_date ASC";

$result_installments = $conn->query($sql_installments);

// Query for the full credit sales table with filters
$sql_credit_sales = "SELECT credit_sale_id, customer_name,  customer_phone, total_amount, initial_payment, remaining_amount, sale_date FROM credit_sales WHERE 1=1 $date_condition ORDER BY sale_date DESC";
$result_credit_sales = $conn->query($sql_credit_sales);

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>إدارة الأقساط والآجل - إدارة الورش</title>
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
        .installments-table {
            width: 100%;
            border-collapse: collapse;
        }
        .installments-table th, .installments-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
        }
        .installments-table th {
            background-color: #f9fafb;
            font-weight: 700;
        }
        .installments-table tr:hover {
            background-color: #f9fafb;
        }
        .overdue-row {
            background-color: #fde8e8 !important; /* Use !important to override other styles */
            border-left: 5px solid #ef4444 !important;
        }
        .action-buttons-container {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem; /* Space between buttons */
            align-items: center;
        }
        .action-buttons-container .btn {
            font-size: 0.875rem; /* text-sm */
            padding: 0.25rem 0.5rem; /* px-2 py-1 */
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
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">
    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">الأقساط والآجل</h2>
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
                <a href="products.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-box text-lg"></i>
                    <span>إدارة المنتجات</span>
                </a>
                <a href="technicians.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-users-cog text-lg"></i>
                    <span>إدارة الفنيين</span>
                </a>
                <a href="payments.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-hand-holding-usd text-lg"></i>
                    <span>المدفوعات والمصروفات</span>
                </a>
                <a href="installments.php" class="flex items-center gap-4 p-3 rounded-lg bg-[#FF6347] text-[#A20A0A] font-semibold sidebar-link">
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
                <i class="fas fa-money-check-alt accent-color"></i>
                <span>الأقساط والآجل</span>
            </h1>
        </div>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="searchCustomer" class="block text-sm font-medium text-gray-700 mb-1">بحث</label>
                    <input type="text" id="searchCustomer" placeholder="ابحث باسم العميل، رقم الفاتورة أو رقم الزبون" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                </div>
                <div>
                    <label for="filterStatus" class="block text-sm font-medium text-gray-700 mb-1">فلترة حسب الحالة</label>
                    <select id="filterStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                        <option value="all">جميعها</option>
                        <option value="due">مستحقة (غير متأخرة)</option>
                        <option value="overdue">متأخرة</option>
                    </select>
                </div>
                <div>
                    <label for="dateFilter" class="block text-sm font-medium text-gray-700 mb-1">فلترة حسب التاريخ</label>
                    <select id="dateFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                        <option value="all" <?= ($date_filter === 'all' ? 'selected' : '') ?>>جميع التواريخ</option>
                        <option value="last_week" <?= ($date_filter === 'last_week' ? 'selected' : '') ?>>آخر أسبوع</option>
                        <option value="last_month" <?= ($date_filter === 'last_month' ? 'selected' : '') ?>>آخر شهر</option>
                        <option value="last_year" <?= ($date_filter === 'last_year' ? 'selected' : '') ?>>آخر سنة</option>
                        <option value="custom" <?= ($date_filter === 'custom' ? 'selected' : '') ?>>مدة مخصصة</option>
                    </select>
                </div>
            </div>
            <div id="customDates" class="flex gap-2 items-center mt-4 <?= ($date_filter === 'custom' ? '' : 'hidden') ?>">
                <input type="date" id="startDate" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" class="px-4 py-2 border border-gray-300 rounded-lg form-input">
                <span>إلى</span>
                <input type="date" id="endDate" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" class="px-4 py-2 border border-gray-300 rounded-lg form-input">
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden mb-8">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-700">جدول الأقساط المستحقة</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="installments-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>اسم العميل</th>
                            <th>رقم الزبون</th>
                            <th>إجمالي الفاتورة</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>القسط الشهري</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="installmentsList">
                        <?php
                        // Check if there are any results for installments
                        if ($result_installments->num_rows > 0) {
                            // Loop through the results and generate HTML rows
                            while ($row = $result_installments->fetch_assoc()) {
                                $overdue_class = '';
                                
                                // New Overdue Logic: Check based on last payment date
                                $reference_date = null;
                                if ($row['last_payment_date'] !== null) {
                                    $reference_date = new DateTime($row['last_payment_date']);
                                } else {
                                    $reference_date = new DateTime($row['sale_date']);
                                }

                                $now = new DateTime();
                                $interval = $now->diff($reference_date);
                                if ($interval->days > 30) {
                                    $overdue_class = 'overdue-row';
                                }
                                ?>
                               <tr class="<?= $overdue_class ?>" 
    data-status="<?= ($overdue_class === 'overdue-row' ? 'overdue' : 'due') ?>" 
    data-customer-name="<?= htmlspecialchars($row['customer_name']) ?>" 
    data-customer-number="<?= htmlspecialchars($row['customer_phone'] ?? '') ?>" 
    data-invoice-id="<?= $row['credit_sale_id'] ?>">

                                    <td><?= $row['credit_sale_id'] ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($row['customer_phone'] ?? 'غير محدد') ?></td>
                                    <td><?= number_format($row['total_amount'], 2) ?> د.ل</td>
                                    <td><?= number_format($row['initial_payment'], 2) ?> د.ل</td>
                                    <td class="font-bold"><?= number_format($row['remaining_amount'], 2) ?> د.ل</td>
                                    <td><?= number_format($row['monthly_installment'], 2) ?> د.ل</td>
                                    <td>
                                        <div class="action-buttons-container">
                                            <button class="pay-btn btn bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-200" 
                                                    data-id="<?= $row['credit_sale_id'] ?>" 
                                                    data-remaining="<?= $row['remaining_amount'] ?>"
                                                    data-monthly="<?= $row['monthly_installment'] ?>">
                                                <i class="fas fa-hand-holding-usd"></i> تسديد دفعة
                                            </button>
                                            <button class="pay-full-btn btn bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors duration-200" 
                                                    data-id="<?= $row['credit_sale_id'] ?>" 
                                                    data-remaining="<?= $row['remaining_amount'] ?>">
                                                <i class="fas fa-money-check-alt"></i> تسديد كامل
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            // Display a message if no records are found
                            echo '<tr><td colspan="8" class="text-center py-4 text-gray-500">لا توجد أقساط مستحقة حالياً.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-700">جدول فواتير الآجل</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="installments-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>اسم العميل</th>
                            <th>رقم الزبون</th>
                            <th>إجمالي الفاتورة</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="fullSalesList">
                        <?php
                        // Check if there are any results for all credit sales
                        if ($result_credit_sales->num_rows > 0) {
                            // Loop through the results and generate HTML rows
                            while ($row = $result_credit_sales->fetch_assoc()) {
                                ?>
                                <tr data-customer-name="<?= htmlspecialchars($row['customer_name']) ?>" data-customer-number="<?= htmlspecialchars($row['customer_phone']) ?? '' ?>"
 data-invoice-id="<?= $row['credit_sale_id'] ?>">
                                    <td><?= $row['credit_sale_id'] ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($row['customer_phone'] ?? 'غير محدد') ?></td>
                                    <td><?= number_format($row['total_amount'], 2) ?> د.ل</td>
                                    <td><?= number_format($row['initial_payment'], 2) ?> د.ل</td>
                                    <td class="font-bold"><?= number_format($row['remaining_amount'], 2) ?> د.ل</td>
                                    <td class="space-x-2 space-x-reverse">
                                        <?php if ($row['remaining_amount'] > 0): ?>
                                            <div class="action-buttons-container">
                                                <button class="pay-btn btn bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-200" 
                                                        data-id="<?= $row['credit_sale_id'] ?>" 
                                                        data-remaining="<?= $row['remaining_amount'] ?>"
                                                        data-monthly="<?= $row['monthly_installment'] ?? '0' ?>">
                                                    <i class="fas fa-hand-holding-usd"></i> تسديد دفعة
                                                </button>
                                                <button class="pay-full-btn btn bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors duration-200" 
                                                        data-id="<?= $row['credit_sale_id'] ?>" 
                                                        data-remaining="<?= $row['remaining_amount'] ?>">
                                                    <i class="fas fa-money-check-alt"></i> تسديد كامل
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            // Display a message if no records are found
                            echo '<tr><td colspan="7" class="text-center py-4 text-gray-500">لا توجد فواتير آجل مسجلة حالياً.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-h-screen overflow-y-auto p-6">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-bold text-gray-800">تسجيل دفعة جديدة</h3>
                <button id="closePaymentModal" class="text-gray-500 hover:text-gray-800 text-2xl"><i class="fas fa-times"></i></button>
            </div>
            <form id="paymentForm" method="POST" action="">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" id="invoiceIdToPay" name="credit_sale_id">
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700">الفاتورة رقم: <span id="paymentInvoiceId" class="font-bold"></span></p>
                    <p class="text-sm font-medium text-gray-700">المبلغ المتبقي: <span id="paymentRemainingAmount" class="font-bold"></span></p>
                </div>
                <div class="mb-4">
                    <label for="paymentAmount" class="block text-sm font-medium text-gray-700 mb-1">المبلغ المدفوع</label>
                    <input type="number" id="paymentAmount" name="payment_amount" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                </div>
                <div class="flex justify-end space-x-2 space-x-reverse">
                    <button type="button" id="cancelPayment" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-[#A20A0A] text-white px-4 py-2 rounded-lg hover:bg-[#C02020]">
                        تسديد
                    </button>
                </div>
            </form>
            <div id="toastContainer" class="fixed bottom-4 right-4 z-50"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuBtn');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');
            const closeAppBtn = document.getElementById('closeAppBtn');
            const logoutBtn = document.getElementById('logoutBtn');
            const searchCustomerInput = document.getElementById('searchCustomer');
            const filterStatusSelect = document.getElementById('filterStatus');
            const paymentModal = document.getElementById('paymentModal');
            const closePaymentModalBtn = document.getElementById('closePaymentModal');
            const paymentForm = document.getElementById('paymentForm');
            const paymentInvoiceId = document.getElementById('paymentInvoiceId');
            const paymentRemainingAmount = document.getElementById('paymentRemainingAmount');
            const paymentAmountInput = document.getElementById('paymentAmount');
            const invoiceIdToPay = document.getElementById('invoiceIdToPay');
            const toastContainer = document.getElementById('toastContainer');
            const dateFilterSelect = document.getElementById('dateFilter');
            const customDatesContainer = document.getElementById('customDates');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            
            const currency = 'د.ل';
            
            // Toast Notification
            const showToast = (message, type = 'info') => {
                const toast = document.createElement('div');
                let bgColor, icon;

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

                toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-md flex items-center gap-2 transition-all duration-300 transform -translate-x-full opacity-0 mt-2`;
                toast.innerHTML = `${icon}<span>${message}</span>`;
                toastContainer.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                    toast.style.opacity = '1';
                }, 100);

                setTimeout(() => {
                    toast.style.transform = 'translateX(-150%)';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            };

            // Add Payment Functionality
            const addPayment = (id, remaining, monthly) => {
                invoiceIdToPay.value = id;
                paymentInvoiceId.textContent = id;
                paymentRemainingAmount.textContent = `${parseFloat(remaining).toFixed(2)} ${currency}`;
                paymentAmountInput.value = monthly && monthly !== '0' ? parseFloat(monthly) : '';
                paymentAmountInput.max = parseFloat(remaining);
                paymentModal.classList.remove('hidden');
            };

            const payFullAmount = (id, remaining) => {
                if (confirm(`هل أنت متأكد من تسديد المبلغ المتبقي بالكامل (${parseFloat(remaining).toLocaleString()} ${currency}) للفاتورة رقم ${id}؟`)) {
                    invoiceIdToPay.value = id;
                    paymentAmountInput.value = parseFloat(remaining);
                    paymentForm.submit();
                }
            };
            
            // Attach event listeners to buttons for both tables
            document.querySelectorAll('.pay-btn').forEach(btn => {
                btn.addEventListener('click', (e) => addPayment(e.currentTarget.dataset.id, e.currentTarget.dataset.remaining, e.currentTarget.dataset.monthly));
            });
            
            document.querySelectorAll('.pay-full-btn').forEach(btn => {
                btn.addEventListener('click', (e) => payFullAmount(e.currentTarget.dataset.id, e.currentTarget.dataset.remaining));
            });
            
            closePaymentModalBtn.addEventListener('click', () => paymentModal.classList.add('hidden'));
            document.getElementById('cancelPayment').addEventListener('click', () => paymentModal.classList.add('hidden'));

            // Filter & Search Logic (Client-side) for both tables
            const filterTables = () => {
                const searchTerm = searchCustomerInput.value.toLowerCase();
                const statusFilter = filterStatusSelect.value;

                // Filter Outstanding Installments Table
                const installmentsRows = document.querySelectorAll('#installmentsList tr');
                installmentsRows.forEach(row => {
                    const matchesSearch = 
                        (row.dataset.customerName && row.dataset.customerName.includes(searchTerm)) ||
                        (row.dataset.customerNumber && row.dataset.customerNumber.includes(searchTerm)) ||
                        (row.dataset.invoiceId && row.dataset.invoiceId.includes(searchTerm));
                    const matchesStatus = (statusFilter === 'all') || (statusFilter === row.dataset.status);
                                          
                    if (matchesSearch && matchesStatus) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Filter Full Credit Sales Table
                const fullSalesRows = document.querySelectorAll('#fullSalesList tr');
                fullSalesRows.forEach(row => {
                    const matchesSearch = 
                        (row.dataset.customerName && row.dataset.customerName.includes(searchTerm)) ||
                        (row.dataset.customerNumber && row.dataset.customerNumber.includes(searchTerm)) ||
                        (row.dataset.invoiceId && row.dataset.invoiceId.includes(searchTerm));

                    // No status filter on this table
                    if (matchesSearch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            };

            searchCustomerInput.addEventListener('input', filterTables);
            filterStatusSelect.addEventListener('change', filterTables);
            
            // Date filter logic (Server-side)
            dateFilterSelect.addEventListener('change', () => {
                if (dateFilterSelect.value === 'custom') {
                    customDatesContainer.classList.remove('hidden');
                } else {
                    window.location.href = `installments.php?date_filter=${dateFilterSelect.value}`;
                }
            });

            startDateInput.addEventListener('change', () => {
                const start = startDateInput.value;
                const end = endDateInput.value;
                if (start && end) {
                    window.location.href = `installments.php?date_filter=custom&start_date=${start}&end_date=${end}`;
                }
            });
            endDateInput.addEventListener('change', () => {
                const start = startDateInput.value;
                const end = endDateInput.value;
                if (start && end) {
                    window.location.href = `installments.php?date_filter=custom&start_date=${start}&end_date=${end}`;
                }
            });

            // Sidebar and Auth Functions
            menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', () => sidebar.classList.remove('open'));
            if (closeAppBtn) closeAppBtn.addEventListener('click', () => window.location.href = 'login.html');
            if (logoutBtn) logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                localStorage.removeItem('userToken');
                window.location.href = 'login.html';
            });
        });
    </script>
</body>
</html>