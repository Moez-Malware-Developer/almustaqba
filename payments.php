<?php
// Start a new session or resume the existing one
session_start();

// Database connection details
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

// Fetch all expenses from the database to display them
$sql    = "SELECT * FROM expenses ORDER BY expense_date DESC";
$result = $conn->query($sql);

// Get the message from the session and clear it
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل المدفوعات والمصروفات - إدارة الورش</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /*
        * -----------------------------------------------------------
        * Custom CSS Styles
        * -----------------------------------------------------------
        */
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

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(162, 10, 10, 0.2);
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-table th, .payment-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
        }

        .payment-table th {
            background-color: #f9fafb;
            font-weight: 700;
        }

        .payment-table tr:hover {
            background-color: #f9fafb;
        }

        .modal {
            transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out;
        }

        .modal.hidden {
            opacity: 0;
            visibility: hidden;
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">
    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden no-print">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">سجل المدفوعات والمصروفات</h2>
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
                <a href="payments.php" class="flex items-center gap-4 p-3 rounded-lg bg-[#FF6347] text-[#A20A0A] font-semibold sidebar-link">
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
                <i class="fas fa-money-bill-wave accent-color"></i>
                <span>سجل المدفوعات والمصروفات</span>
            </h1>
            <button id="addPaymentBtn" class="bg-[#A20A0A] text-white px-4 py-2 rounded-lg hover:bg-[#C02020] mt-4 md:mt-0">
                <i class="fas fa-plus-circle ml-2"></i> إضافة مدفوع/مصروف
            </button>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">نجاح!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-700">قائمة المدفوعات والمصروفات</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>المصدر / المصروف</th>
                            <th>المبلغ (د.ل)</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsList">
                        <?php
                      
// ... (Your existing PHP code) ...

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
        echo "<td>" . number_format($row["amount"], 2) . " د.ل</td>";
        echo "<td>" . htmlspecialchars($row["expense_date"]) . "</td>";
        // التحقق من وجود مفتاح "notes" قبل استخدامه
        echo "<td>" . (isset($row["notes"]) ? htmlspecialchars($row["notes"]) : '') . "</td>";
        echo "<td>";
        echo "<button class='edit-btn text-blue-600 hover:text-blue-800 ml-2' data-id='{$row["expense_id"]}' data-source='{$row["description"]}' data-amount='{$row["amount"]}' data-date='{$row["expense_date"]}' data-notes='" . (isset($row["notes"]) ? htmlspecialchars($row["notes"]) : '') . "'><i class='fas fa-edit'></i></button>";
        echo "<button class='delete-btn text-red-600 hover:text-red-800' data-id='{$row["expense_id"]}'><i class='fas fa-trash'></i></button>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-center py-4 text-gray-500'>لا توجد بيانات حتى الآن</td></tr>";
}
$conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <div id="paymentFormModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-3/4 lg:w-2/3 max-h-screen overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800" id="formTitle">إضافة مدفوع/مصروف</h2>
            </div>
            <form id="paymentForm" class="p-6 space-y-4" method="POST" action="process_payment.php">
                <input type="hidden" id="expense_id" name="expense_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="source" class="block text-sm font-medium text-gray-700 mb-1">المصدر / المصروف</label>
                        <select id="source" name="source" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                            <option value="">اختر النوع</option>
                            <option value="مالك المحل">مالك المحل</option>
                            <option value="كهرباء">كهرباء</option>
                            <option value="إيجار">إيجار</option>
                            <option value="مصروف آخر">مصروف آخر</option>
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">المبلغ (د.ل)</label>
                        <input type="number" id="amount" name="amount" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                        <input type="date" id="date" name="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]" required>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                        <input type="text" id="notes" name="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg form-input focus:outline-none focus:border-[#A20A0A]">
                    </div>
                </div>
                <div class="flex justify-end space-x-2 space-x-reverse pt-4">
                    <button type="button" id="cancelPayment" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-[#A20A0A] text-white px-4 py-2 rounded-lg hover:bg-[#C02020]">حفظ</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/3 p-6 text-center">
            <h3 class="text-xl font-bold text-gray-800 mb-4">تأكيد الحذف</h3>
            <p class="text-gray-700 mb-6">هل أنت متأكد من حذف هذا السجل بشكل نهائي؟</p>
            <div class="flex justify-center gap-4">
                <a href="#" id="confirmDeleteLink" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors">
                    <i class="fas fa-trash"></i> حذف
                </a>
                <button id="cancelDeleteBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const addPaymentBtn     = document.getElementById('addPaymentBtn');
            const paymentFormModal  = document.getElementById('paymentFormModal');
            const cancelPayment     = document.getElementById('cancelPayment');
            const formTitle         = document.getElementById('formTitle');
            const sidebar           = document.getElementById('sidebar');
            const menuBtn           = document.getElementById('menuBtn');
            const closeSidebarBtn   = document.getElementById('closeSidebarBtn');
            const logoutBtn         = document.getElementById('logoutBtn');
            const closeAppBtn       = document.getElementById('closeAppBtn');
            
            // Delete Modal elements
            const deleteModal       = document.getElementById('deleteModal');
            const confirmDeleteLink = document.getElementById('confirmDeleteLink');
            const cancelDeleteBtn   = document.getElementById('cancelDeleteBtn');

            // Helper function to format date to YYYY-MM-DD
            const formatDate = (date) => {
                const year  = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day   = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            // --- Sidebar and Navigation Logic ---
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

            // --- Payments and Expenses Logic ---
            addPaymentBtn.addEventListener('click', () => {
                formTitle.textContent = 'إضافة مدفوع/مصروف';
                document.getElementById('expense_id').value = '';
                document.getElementById('source').value     = '';
                document.getElementById('amount').value     = '';
                document.getElementById('date').value       = formatDate(new Date());
                document.getElementById('notes').value      = '';
                paymentFormModal.classList.remove('hidden');
            });

            cancelPayment.addEventListener('click', () => {
                paymentFormModal.classList.add('hidden');
            });

            // Handle edit button click
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id     = e.currentTarget.getAttribute('data-id');
                    const source = e.currentTarget.getAttribute('data-source');
                    const amount = e.currentTarget.getAttribute('data-amount');
                    const date   = e.currentTarget.getAttribute('data-date');
                    const notes  = e.currentTarget.getAttribute('data-notes');

                    formTitle.textContent                       = 'تعديل المدفوع/المصروف';
                    document.getElementById('expense_id').value = id;
                    document.getElementById('source').value     = source;
                    document.getElementById('amount').value     = amount;
                    document.getElementById('date').value       = date;
                    document.getElementById('notes').value      = notes;
                    paymentFormModal.classList.remove('hidden');
                });
            });

            // Handle delete button click
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id            = e.currentTarget.getAttribute('data-id');
                    confirmDeleteLink.href = `delete_expense.php?id=${id}`;
                    deleteModal.classList.remove('hidden');
                });
            });

            cancelDeleteBtn.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
        });
    </script>
</body>
</html>