<?php
// ----------------------------------------------------
// بيانات الاتصال بقاعدة البيانات - قم بتعديلها
// ----------------------------------------------------
$servername = "localhost";
$username = "root"; // اسم المستخدم الخاص بك
$password = ""; // كلمة المرور الخاصة بك
$dbname = "workshop_management"; // اسم قاعدة البيانات
// ----------------------------------------------------

// الاتصال بقاعدة البيانات
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من وجود أخطاء في الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================================================
// استعلامات SQL لسحب البيانات
// =========================================================

// KPI: إجمالي المبيعات
$sql_total_sales = "SELECT SUM(total_amount) AS total FROM sales";
$result_total_sales = $conn->query($sql_total_sales);
$total_sales = $result_total_sales->fetch_assoc()['total'] ?? 0;

// KPI: إجمالي المصروفات
$sql_total_expenses = "SELECT SUM(amount) AS total FROM expenses";
$result_total_expenses = $conn->query($sql_total_expenses);
$total_expenses = $result_total_expenses->fetch_assoc()['total'] ?? 0;

// KPI: عدد طلبات الصيانة
$sql_total_repairs = "SELECT COUNT(*) AS total FROM repairs";
$result_total_repairs = $conn->query($sql_total_repairs);
$total_repairs = $result_total_repairs->fetch_assoc()['total'] ?? 0;

// KPI: عدد العملاء (الفريدين)
$sql_total_customers = "SELECT COUNT(DISTINCT customer_phone) AS total FROM repairs";
$result_total_customers = $conn->query($sql_total_customers);
$total_customers = $result_total_customers->fetch_assoc()['total'] ?? 0;

// البيانات الشهرية للمبيعات
$sql_monthly_sales = "SELECT MONTH(sale_date) AS month, SUM(total_amount) AS total FROM sales WHERE YEAR(sale_date) = YEAR(CURDATE()) GROUP BY month ORDER BY month";
$result_monthly_sales = $conn->query($sql_monthly_sales);
$monthly_sales_data = array_fill(0, 12, 0); // تهيئة مصفوفة لـ 12 شهراً
while($row = $result_monthly_sales->fetch_assoc()) {
    $monthly_sales_data[$row['month'] - 1] = (float) $row['total'];
}

// البيانات الشهرية للمصروفات
$sql_monthly_expenses = "SELECT MONTH(expense_date) AS month, SUM(amount) AS total FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) GROUP BY month ORDER BY month";
$result_monthly_expenses = $conn->query($sql_monthly_expenses);
$monthly_expenses_data = array_fill(0, 12, 0); // تهيئة مصفوفة لـ 12 شهراً
while($row = $result_monthly_expenses->fetch_assoc()) {
    $monthly_expenses_data[$row['month'] - 1] = (float) $row['total'];
}

// إغلاق الاتصال بقاعدة البيانات
$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرئيسية - إدارة الورش</title>
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
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">

    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden no-print">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">الرئيسية</h2>
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
                <a href="index.html" class="flex items-center gap-4 p-3 rounded-lg bg-[#FF6347] text-[#A20A0A] font-semibold sidebar-link">
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
        <h1 class="text-3xl font-bold text-gray-800 mb-6">الرئيسية</h1>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-500">إجمالي المبيعات</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($total_sales, 2); ?> د.ل</p>
                </div>
                <i class="fas fa-dollar-sign text-4xl text-[#A20A0A]"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-500">إجمالي المصروفات</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($total_expenses, 2); ?> د.ل</p>
                </div>
                <i class="fas fa-hand-holding-usd text-4xl text-gray-500"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-500">عدد طلبات الصيانة</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_repairs; ?></p>
                </div>
                <i class="fas fa-tools text-4xl text-blue-500"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-500">عدد العملاء</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_customers; ?></p>
                </div>
                <i class="fas fa-users text-4xl text-green-500"></i>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-700 mb-4">المبيعات الشهرية</h3>
                <canvas id="monthlySalesChart"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-700 mb-4">المصروفات الشهرية</h3>
                <canvas id="monthlyExpensesChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuBtn');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');
            const closeAppBtn = document.getElementById('closeAppBtn');
            const logoutBtn = document.getElementById('logoutBtn');

            // Sidebar and Navigation Logic
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
            
            // Render Charts
            const monthlySales = <?php echo json_encode($monthly_sales_data); ?>;
            const monthlyExpenses = <?php echo json_encode($monthly_expenses_data); ?>;
            const labels = ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"];

            // Monthly Sales Chart
            const salesCtx = document.getElementById('monthlySalesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'المبيعات (د.ل)',
                        data: monthlySales,
                        backgroundColor: '#A20A0A',
                        borderColor: '#A20A0A',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Monthly Expenses Chart
            const expensesCtx = document.getElementById('monthlyExpensesChart').getContext('2d');
            new Chart(expensesCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'المصروفات (د.ل)',
                        data: monthlyExpenses,
                        backgroundColor: '#FF6347',
                        borderColor: '#FF6347',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>