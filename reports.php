<?php
// Database connection configuration
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "workshop_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8 for Arabic support
$conn->set_charset("utf8mb4");

// Get date filters from URL parameters, defaulting to the last month
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-1 month'));
$endDate   = $_GET['endDate'] ?? date('Y-m-d');

// ------------------- SALES REPORT -------------------
function renderSalesReport($conn, $startDate, $endDate) {
    $sql = "
        SELECT 
            s.sale_id,
            s.sale_date,
            s.total_amount,
            si.quantity,
            p.product_name,
            p.purchase_price
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        ORDER BY s.sale_date DESC;
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $saleId = $row['sale_id'];
            if (!isset($sales[$saleId])) {
                $sales[$saleId] = [
                    'invoice_number' => 'INV' . $saleId,
                    'customer_name'  => 'N/A',
                    'total_amount'   => $row['total_amount'],
                    'cost_amount'    => 0,
                    'payment_method' => 'غير محدد', 
                    'sale_date'      => date('Y-m-d', strtotime($row['sale_date']))
                ];
            }
            $sales[$saleId]['cost_amount'] += $row['purchase_price'] * $row['quantity'];
        }

        foreach ($sales as $sale) {
            $profit = $sale['total_amount'] - $sale['cost_amount'];
            echo "<tr>";
            echo "<td>{$sale['invoice_number']}</td>";
            echo "<td>{$sale['customer_name']}</td>"; 
            echo "<td>" . number_format($sale['total_amount'], 2) . " د.ل</td>";
            echo "<td>" . number_format($sale['cost_amount'], 2) . " د.ل</td>";
            echo "<td>" . number_format($profit, 2) . " د.ل</td>";
            echo "<td>{$sale['payment_method']}</td>"; 
            echo "<td>{$sale['sale_date']}</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='text-center py-4 text-gray-500'>لا توجد بيانات</td></tr>";
    }
}

// ------------------- EXPENSES REPORT -------------------
function renderExpensesReport($conn, $startDate, $endDate) {
    $sql = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['description']}</td>";
            echo "<td>" . number_format($row['amount'], 2) . " د.ل</td>";
            echo "<td>{$row['category']}</td>";
            echo "<td>{$row['expense_date']}</td>";
            echo "<td>{$row['notes']}</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='text-center py-4 text-gray-500'>لا توجد بيانات</td></tr>";
    }
}

// ------------------- INSTALLMENTS REPORT -------------------
function renderInstallmentsReport($conn, $startDate, $endDate) {
    $sql = "SELECT * FROM credit_sales WHERE DATE(sale_date) BETWEEN ? AND ? ORDER BY sale_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status = ($row['remaining_amount'] == 0) ? 'تم الدفع' : (($row['initial_payment'] > 0) ? 'دفع جزئي' : 'لم يتم الدفع');
            echo "<tr>";
            echo "<td>INV{$row['credit_sale_id']}</td>";
            echo "<td>{$row['customer_name']}</td>";
            echo "<td>" . number_format($row['total_amount'], 2) . " د.ل</td>";
            echo "<td>" . number_format($row['initial_payment'], 2) . " د.ل</td>";
            echo "<td>" . number_format($row['remaining_amount'], 2) . " د.ل</td>";
            echo "<td>" . date('Y-m-d', strtotime($row['sale_date'])) . "</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='text-center py-4 text-gray-500'>لا توجد بيانات</td></tr>";
    }
}

// ------------------- CUSTOMER PAYMENTS REPORT -------------------
function renderCustomerPaymentsReport($conn, $startDate, $endDate) {
    $sql = "
        SELECT 
            cs.customer_name,
            SUM(cs.total_amount) AS total_purchases,
            SUM(cs.remaining_amount) AS remaining_debt
        FROM credit_sales cs
        WHERE DATE(cs.sale_date) BETWEEN ? AND ?
        GROUP BY cs.customer_name
        ORDER BY cs.customer_name;
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status = ($row['remaining_debt'] > 0) ? 'مستحق' : 'تم الدفع';
            echo "<tr>";
            echo "<td>{$row['customer_name']}</td>";
            echo "<td>" . number_format($row['total_purchases'], 2) . " د.ل</td>";
            echo "<td>" . number_format($row['remaining_debt'], 2) . " د.ل</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center py-4 text-gray-500'>لا توجد بيانات</td></tr>";
    }
}

// ------------------- OVERVIEW DATA -------------------
function getOverviewData($conn, $startDate, $endDate) {
    // Total Sales
    $sql_sales = "SELECT SUM(total_amount) AS total_sales FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?";
    $stmt_sales = $conn->prepare($sql_sales);
    $stmt_sales->bind_param("ss", $startDate, $endDate);
    $stmt_sales->execute();
    $result_sales = $stmt_sales->get_result();
    $total_sales = $result_sales->fetch_assoc()['total_sales'] ?? 0;

    // Total Expenses
    $sql_expenses = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?";
    $stmt_expenses = $conn->prepare($sql_expenses);
    $stmt_expenses->bind_param("ss", $startDate, $endDate);
    $stmt_expenses->execute();
    $result_expenses = $stmt_expenses->get_result();
    $total_expenses = $result_expenses->fetch_assoc()['total_expenses'] ?? 0;

    // Total Debt
    $sql_debt = "SELECT SUM(remaining_amount) AS total_debt FROM credit_sales WHERE DATE(sale_date) BETWEEN ? AND ?";
    $stmt_debt = $conn->prepare($sql_debt);
    $stmt_debt->bind_param("ss", $startDate, $endDate);
    $stmt_debt->execute();
    $result_debt = $stmt_debt->get_result();
    $total_debt = $result_debt->fetch_assoc()['total_debt'] ?? 0;

    // Total Profit
    $sql_profit = "
        SELECT 
            SUM(s.total_amount) AS total_revenue, 
            SUM(si.quantity * p.purchase_price) AS total_cost
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ";
    $stmt_profit = $conn->prepare($sql_profit);
    $stmt_profit->bind_param("ss", $startDate, $endDate);
    $stmt_profit->execute();
    $result_profit = $stmt_profit->get_result();
    $data = $result_profit->fetch_assoc();
    $total_profit = ($data['total_revenue'] ?? 0) - ($data['total_cost'] ?? 0);

    return [
        'total_sales'    => $total_sales,
        'total_expenses' => $total_expenses,
        'total_debt'     => $total_debt,
        'total_profit'   => $total_profit
    ];
}

// ------------------- CHART DATA -------------------
function getChartData($conn, $startDate, $endDate) {
    // Payment method data (غير موجود → رجع غير محدد)
    $sql_payment = "
        SELECT 
            'غير محدد' AS payment_method,
            SUM(s.total_amount) AS total_amount
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ";
    $stmt_payment = $conn->prepare($sql_payment);
    $stmt_payment->bind_param("ss", $startDate, $endDate);
    $stmt_payment->execute();
    $result_payment = $stmt_payment->get_result();
    
    $paymentData = [];
    while ($row = $result_payment->fetch_assoc()) {
        $paymentData[$row['payment_method']] = $row['total_amount'];
    }

    // Technician sales data
    $sql_technician = "
        SELECT 
            t.technician_name AS technician_name,
            SUM(s.total_amount) AS total_sales
        FROM sales s
        LEFT JOIN technicians t ON s.technician_id = t.technician_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY t.technician_name
    ";
    $stmt_technician = $conn->prepare($sql_technician);
    $stmt_technician->bind_param("ss", $startDate, $endDate);
    $stmt_technician->execute();
    $result_technician = $stmt_technician->get_result();
    
    $technicianData = [];
    while ($row = $result_technician->fetch_assoc()) {
        $technicianData[$row['technician_name'] ?? 'غير محدد'] = $row['total_sales'];
    }

    return [
        'payment_methods'  => $paymentData,
        'technician_sales' => $technicianData
    ];
}

$overviewData = getOverviewData($conn, $startDate, $endDate);
$chartData    = getChartData($conn, $startDate, $endDate);

// Close connection

?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>التقارير والحسابات - إدارة الورش</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th, .report-table td {
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
            text-align: right;
        }
        .report-table th {
            background-color: #f9fafb;
            font-weight: 600;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">

    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden no-print">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">التقارير والحسابات</h2>
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
                <a href="installments.html" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
                    <i class="fas fa-money-check-alt text-lg"></i>
                    <span>الأقساط / الآجل</span>
                </a>
                <a href="reports.html" class="flex items-center gap-4 p-3 rounded-lg bg-[#FF6347] text-[#A20A0A] font-semibold sidebar-link">
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
        <h1 class="hidden lg:flex text-3xl font-bold text-gray-800 flex items-center gap-2 mb-6 border-b pb-4">
            <i class="fas fa-chart-bar accent-color"></i>
            <span>التقارير المالية</span>
        </h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-4 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">إجمالي الإيرادات</p>
                        <h3 id="totalRevenue" class="text-2xl font-bold text-gray-800"><?php echo number_format($overviewData['total_sales'], 2); ?> <span class="text-sm">دينار</span></h3>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-4 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">إجمالي المصروفات</p>
                        <h3 id="totalExpenses" class="text-2xl font-bold text-gray-800"><?php echo number_format($overviewData['total_expenses'], 2); ?> <span class="text-sm">دينار</span></h3>
                    </div>
                    <div class="bg-red-100 p-3 rounded-full">
                        <i class="fas fa-minus-circle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-4 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">صافي الأرباح</p>
                        <h3 id="netProfit" class="text-2xl font-bold text-gray-800"><?php echo number_format($overviewData['total_profit'], 2); ?> <span class="text-sm">دينار</span></h3>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-4 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">المستحق (الآجل)</p>
                        <h3 id="totalInstallments" class="text-2xl font-bold text-gray-800"><?php echo number_format($overviewData['total_debt'], 2); ?> <span class="text-sm">دينار</span></h3>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-money-check-alt text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6 no-print">
            <h2 class="text-xl font-bold text-gray-700 mb-4">فلترة وتحديث البيانات</h2>
            <form method="GET" action="" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="dateFilter" class="block text-sm font-medium text-gray-700 mb-1">الفترة الزمنية</label>
                        <select id="dateFilter" name="dateFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                            <option value="today">اليوم</option>
                            <option value="lastWeek">آخر أسبوع</option>
                            <option value="lastMonth" selected>آخر شهر</option>
                            <option value="lastYear">آخر سنة</option>
                            <option value="custom">فترة مخصصة</option>
                        </select>
                    </div>
                    <div>
                        <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">من تاريخ</label>
                        <input type="date" id="startDate" name="startDate" value="<?php echo $startDate; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#A20A0A]" disabled>
                    </div>
                    <div>
                        <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">إلى تاريخ</label>
                        <input type="date" id="endDate" name="endDate" value="<?php echo $endDate; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#A20A0A]" disabled>
                    </div>
                    <div>
                        <button type="submit" id="applyFiltersBtn" class="w-full bg-[#A20A0A] text-white px-6 py-2 rounded-lg hover:bg-[#C02020] transition-colors">
                            <i class="fas fa-filter ml-2"></i> تطبيق الفلاتر
                        </button>
                    </div>
                </div>
            </form>
            <div class="mt-4">
                <button id="printReportBtn" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                    <i class="fas fa-print"></i> طباعة التقرير
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-700 mb-4">توزيع الإيرادات حسب طريقة الدفع</h3>
                <canvas id="paymentMethodChart" height="250"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-700 mb-4">أداء المبيعات حسب الفني</h3>
                <canvas id="technicianSalesChart" height="250"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-700 mb-4">تقرير المبيعات</h2>
            <div class="overflow-x-auto">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>الإجمالي</th>
                            <th>التكاليف</th>
                            <th>الأرباح</th>
                            <th>طريقة الدفع</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody id="salesReportTable">
                        <?php renderSalesReport($conn, $startDate, $endDate); ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-700 mb-4">تقرير المصروفات</h2>
            <div class="overflow-x-auto">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>المصدر</th>
                            <th>المبلغ</th>
                            <th>التصنيف</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody id="expensesReportTable">
                        <?php renderExpensesReport($conn, $startDate, $endDate); ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-700 mb-4">تقرير الأقساط / الآجل</h2>
            <div class="overflow-x-auto">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>المبلغ الإجمالي</th>
                            <th>المبلغ المدفوع</th>
                            <th>المبلغ المتبقي</th>
                            <th>تاريخ الاستحقاق</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="installmentsReportTable">
                        <?php renderInstallmentsReport($conn, $startDate, $endDate); ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-700 mb-4">تقرير مدفوعات العملاء</h2>
            <div class="overflow-x-auto">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>اسم العميل</th>
                            <th>إجمالي المشتريات</th>
                            <th>المبلغ المتبقي</th>
                            <th>حالة المدفوعات</th>
                        </tr>
                    </thead>
                    <tbody id="customersReportTable">
                        <?php renderCustomerPaymentsReport($conn, $startDate, $endDate); ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Sidebar and Navigation
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuBtn');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');
            const closeAppBtn = document.getElementById('closeAppBtn');
            const logoutBtn = document.getElementById('logoutBtn');

            menuBtn.addEventListener('click', () => {
                sidebar.classList.add('open');
            });
            closeSidebarBtn.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
            closeAppBtn.addEventListener('click', () => {
                window.close();
            });
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                localStorage.removeItem('userToken');
                window.location.href = 'login.html';
            });

            // Report elements
            const dateFilterSelect = document.getElementById('dateFilter');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const printReportBtn = document.getElementById('printReportBtn');
            const filterForm = document.getElementById('filterForm');

            // Handle date filter changes
            dateFilterSelect.addEventListener('change', () => {
                const now = new Date();
                const today = now.toISOString().split('T')[0];

                startDateInput.disabled = true;
                endDateInput.disabled = true;

                switch (dateFilterSelect.value) {
                    case 'today':
                        const todayDate = new Date().toISOString().split('T')[0];
                        startDateInput.value = todayDate;
                        endDateInput.value = todayDate;
                        break;
                    case 'lastWeek':
                        const lastWeek = new Date(now.setDate(now.getDate() - 7)).toISOString().split('T')[0];
                        startDateInput.value = lastWeek;
                        endDateInput.value = today;
                        break;
                    case 'lastMonth':
                        const lastMonth = new Date(now.setMonth(now.getMonth() - 1)).toISOString().split('T')[0];
                        startDateInput.value = lastMonth;
                        endDateInput.value = today;
                        break;
                    case 'lastYear':
                        const lastYear = new Date(now.setFullYear(now.getFullYear() - 1)).toISOString().split('T')[0];
                        startDateInput.value = lastYear;
                        endDateInput.value = today;
                        break;
                    case 'custom':
                        startDateInput.disabled = false;
                        endDateInput.disabled = false;
                        startDateInput.value = '';
                        endDateInput.value = '';
                        break;
                }
            });

            // Print function
            printReportBtn.addEventListener('click', () => {
                window.print();
            });
            
            // Render charts with PHP data
            function renderCharts() {
                // Payment Method Chart (Doughnut Chart)
                const paymentMethods = <?php echo json_encode($chartData['payment_methods']); ?>;
                const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
                const paymentMethodChart = new Chart(paymentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(paymentMethods),
                        datasets: [{
                            data: Object.values(paymentMethods),
                            backgroundColor: ['#A20A0A', '#FF6347', '#4A5568', '#10B981'],
                            borderColor: '#fff',
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            title: { display: false }
                        }
                    }
                });

                // Technician Sales Chart (Bar Chart)
                const technicianSales = <?php echo json_encode($chartData['technician_sales']); ?>;
                const technicianCtx = document.getElementById('technicianSalesChart').getContext('2d');
                const technicianSalesChart = new Chart(technicianCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(technicianSales),
                        datasets: [{
                            label: 'إجمالي المبيعات (د.ل)',
                            data: Object.values(technicianSales),
                            backgroundColor: '#A20A0A',
                            borderColor: '#A20A0A',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            title: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }

            // Initialize charts
            renderCharts();
        });
    </script>

</body>
</html>