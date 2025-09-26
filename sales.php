<?php
// تفاصيل الاتصال بقاعدة البيانات
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "workshop_management";

// إنشاء اتصال
$conn = new mysqli($servername, $username, $password, $dbname);


// التحقق من نجاح الاتصال
if ($conn->connect_error) {
    // توقف التنفيذ واعرض رسالة خطأ واضحة للمستخدم
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------------------------------------------------------
// هذا الجزء من الكود يعالج طلب POST عند إتمام عملية البيع
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استقبال البيانات من النموذج مباشرة (بدون JSON)
    $total_amount_str = $_POST['total_amount'] ?? '0';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $received_amount_str = $_POST['received_amount'] ?? '0';
    $client_name = $_POST['client_name'] ?? null;
    $client_phone = $_POST['client_phone'] ?? null;
    $down_payment_str = $_POST['down_payment'] ?? '0';
    $monthly_installment_str = $_POST['monthly_installment'] ?? '0';
    
    // البيانات التي تم إرسالها من JavaScript كـ form data
    $cart_items_json = $_POST['cart_items'] ?? '[]';
    $cart_items = json_decode($cart_items_json, true); // استخدام JSON لفك التشفير فقط بعد الإرسال

    // تحويل القيم إلى أرقام قبل إجراء أي عملية حسابية
    $total_amount = floatval($total_amount_str);
    $received_amount = floatval($received_amount_str);
    $down_payment = floatval($down_payment_str);
    $monthly_installment = floatval($monthly_installment_str);

    // بدء المعاملة (transaction) لضمان عدم حدوث أخطاء
    $conn->begin_transaction();

    try {
        // إدخال سجل البيع
        $sql_sale = "INSERT INTO sales (total_amount) VALUES (?)";
        $stmt_sale = $conn->prepare($sql_sale);
        $stmt_sale->bind_param("d", $total_amount);
        $stmt_sale->execute();
        $sale_id = $conn->insert_id;

        // إدخال بنود البيع وتحديث المخزون
        $sql_item = "INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $sql_update_stock = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";

        $stmt_item = $conn->prepare($sql_item);
        $stmt_update_stock = $conn->prepare($sql_update_stock);

        foreach ($cart_items as $item) {
            // التحقق من المخزون قبل المتابعة
            $sql_check_stock = "SELECT stock_quantity FROM products WHERE product_id = ?";
            $stmt_check = $conn->prepare($sql_check_stock);
            $stmt_check->bind_param("i", $item['id']);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            $product = $result->fetch_assoc();

            if ($product['stock_quantity'] < $item['quantity']) {
                $conn->rollback();
                // إرجاع رسالة خطأ
                echo "<script>alert('Not enough stock for product ID " . $item['id'] . "'); window.location.href = 'sales.php';</script>";
                exit;
            }

            // إدخال في جدول sale_items
            $stmt_item->bind_param("iidd", $sale_id, $item['id'], $item['quantity'], $item['price']);
            $stmt_item->execute();

            // تحديث المخزون
            $stmt_update_stock->bind_param("ii", $item['quantity'], $item['id']);
            $stmt_update_stock->execute();
        }

        // معالجة المبيعات الآجلة / الأقساط
        if ($payment_method == 'credit' || $payment_method == 'installments') {
            $remaining_amount = $total_amount - $down_payment;
            $sql_credit = "INSERT INTO credit_sales (customer_name, customer_phone, total_amount, initial_payment, remaining_amount, monthly_installment) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_credit = $conn->prepare($sql_credit);
            $stmt_credit->bind_param("ssdddd", $client_name, $client_phone, $total_amount, $down_payment, $remaining_amount, $monthly_installment);
            $stmt_credit->execute();
        }

        if ($product['stock_quantity'] < $item['quantity']) {
    $conn->rollback();
    echo "<script>alert('Not enough stock for product ID " . $item['id'] . "'); window.location.href = 'sales.php';</script>";
    exit;
}


        // تأكيد المعاملة
        $conn->commit();
        echo "<script>alert('تم إتمام البيع بنجاح!'); window.location.href = 'sales.php';</script>";

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo "<script>alert('Transaction failed: " . $exception->getMessage() . "'); window.location.href = 'sales.php';</script>";
    }
    exit;
}
// ----------------------------------------------------------------------
// نهاية جزء معالجة POST
// ----------------------------------------------------------------------

// ----------------------------------------------------------------------
// هذا الجزء من الكود يعالج طلب GET عند تحميل الصفحة
// ----------------------------------------------------------------------
// تحميل المنتجات من قاعدة البيانات للعرض الأولي
// --- التعديل: استعلام لا يجلب إلا المنتجات التي كميتها أكبر من 0 ---
$sql = "SELECT product_id, product_name, selling_price, stock_quantity FROM products WHERE stock_quantity > 0";
$result = $conn->query($sql);

$products_data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products_data[] = [
            'id' => $row['product_id'],
            'name' => $row['product_name'],
            'price' => (float)$row['selling_price'],
            'quantity' => (int)$row['stock_quantity'],
            'barcode' => (string)$row['product_id']
        ];
    }
}
$products_json = json_encode($products_data);
$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>نقطة البيع - إدارة الورش</title>
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
        .autocomplete-container {
            position: relative;
        }
        .autocomplete-list {
            position: absolute;
            z-index: 10;
            top: 100%;
            right: 0;
            left: 0;
            background-color: #fff;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 250px;
            overflow-y: auto;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .autocomplete-list-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .autocomplete-list-item:hover, .autocomplete-list-item.active {
            background-color: #f3f4f6;
        }
        .autocomplete-list-item:last-child {
            border-bottom: none;
        }
        .highlight {
            background-color: yellow;
            font-weight: bold;
        }
        .low-stock {
            border: 2px solid #f97316;
            box-shadow: 0 0 8px rgba(249, 115, 22, 0.4);
        }
        .product-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 1rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease-in-out;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .product-card .price {
            font-size: 1.75rem;
            color: #10B981;
            font-weight: 700;
            margin-top: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .invoice-container {
            direction: rtl;
            font-family: 'Tajawal', sans-serif;
            padding: 2rem;
            max-width: 800px;
            margin: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">
    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">نقطة البيع</h2>
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
                <a href="sales.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
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

    <main class="flex-1 p-4 lg:p-8 pt-[64px] lg:pt-8 flex flex-col-reverse lg:flex-row gap-8">
        <section class="flex-1">
            <h1 class="hidden lg:flex text-3xl font-bold text-gray-800 flex items-center gap-2 mb-6 border-b pb-4">
                <i class="fas fa-cash-register accent-color"></i>
                <span>نقطة البيع</span>
            </h1>

            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-700 mb-4">أضف منتجًا للبيع</h2>
                <div class="autocomplete-container">
                    <input type="text" id="searchInput" placeholder="ابحث بالاسم أو امسح الباركود (F2)..." class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#A20A0A] text-lg">
                    <div id="autocompleteList" class="autocomplete-list hidden"></div>
                </div>
            </div>

            <div id="products-list" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-5 gap-4">
            </div>
            
            <p id="noResultsMessage" class="hidden text-center text-gray-500 mt-8 text-xl">لا توجد نتائج مطابقة لبحثك.</p>
        </section>

        <section class="w-full lg:w-96 flex-shrink-0">
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 sticky lg:top-8 z-20">
                <h2 class="text-2xl font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-shopping-cart"></i>
                    <span>سلة المبيعات</span>
                </h2>
                <div id="cartItems" class="space-y-4 max-h-96 overflow-y-auto mb-4">
                    <p id="emptyCartMessage" class="text-center text-gray-500">السلة فارغة</p>
                </div>
                <div class="border-t pt-4">
                    <div class="flex justify-between items-center text-lg text-gray-800 mb-2">
                        <span>الخصم:</span>
                        <input type="number" id="discountInput" class="w-24 text-right p-1 border border-gray-300 rounded-md" value="0" min="0">
                        <span>%</span>
                    </div>
                    <div class="flex justify-between items-center text-lg font-bold text-gray-800 mt-4 mb-2">
                        <span>الإجمالي:</span>
                        <span id="cartTotal">0.00 د.ل</span>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button id="clearCartBtn" class="bg-red-500 text-white p-2 rounded-lg font-semibold hover:bg-red-600 transition-colors duration-200"><i class="fas fa-trash"></i> مسح السلة</button>
                    </div>
                    <button id="checkoutBtn" class="w-full bg-green-600 text-white p-4 rounded-lg font-bold text-lg hover:bg-green-700 transition-colors duration-200 mt-4 relative">
                        إتمام البيع (F9) <span id="cartItemCount" class="bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center absolute -top-2 right-2 hidden">0</span>
                    </button>
                </div>
            </div>
        </section>
    </main>
<div id="checkoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex justify-center items-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-11/12 md:w-2/3">
        <h3 class="text-2xl font-bold text-gray-800 mb-4">إتمام البيع</h3>
        <div id="modalCartSummary" class="mb-4"></div>
        
        <form id="checkoutForm" method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">طريقة الدفع</label>
                <select id="paymentMethod" name="payment_method" class="w-full p-3 border border-gray-300 rounded-lg">
                    <option value="cash">كاش</option>
                    <option value="card">بطاقة</option>
                    <option value="credit">آجل</option>
                    <option value="installments">أقساط</option>
                </select>
            </div>
            
            <div id="creditFields" class="hidden mb-4">
                <h4 class="font-bold text-gray-700 border-b pb-2 mb-3">بيانات العميل</h4>
                <div class="flex flex-col md:flex-row gap-4">
                    <input type="text" id="clientName" name="client_name" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="اسم العميل">
                    <input type="tel" id="clientPhone" name="client_phone" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="رقم هاتف العميل">
                </div>
            </div>

            <div id="installmentsFields" class="hidden mb-4">
                <h4 class="font-bold text-gray-700 border-b pb-2 mb-3">بيانات الأقساط</h4>
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <label class="block text-gray-700 text-sm font-semibold mb-1">عدد الأشهر</label>
                        <input type="number" id="installmentsMonths" name="installments_months" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="عدد الأشهر" min="1">
                    </div>
                    <div class="flex-1">
                        <label class="block text-gray-700 text-sm font-semibold mb-1">الدفعة المقدمة</label>
                        <input type="number" id="downPayment" name="down_payment" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="الدفعة المقدمة" min="0">
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">القسط الشهري: <span id="monthlyInstallment">0.00 د.ل</span></p>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">المبلغ المستلم</label>
                <input type="number" id="receivedAmount" name="received_amount" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-lg" placeholder="0.00" required>
            </div>
            <div class="flex justify-between items-center mb-4 font-bold text-gray-700 text-lg">
                <span>المبلغ المتبقي:</span>
                <span id="changeDue" class="text-green-600">0.00 د.ل</span>
            </div>
            
            <input type="hidden" id="cartTotalInput" name="total_amount">
            <input type="hidden" id="cartItemsInput" name="cart_items">
            <input type="hidden" id="monthlyInstallmentInput" name="monthly_installment">

            <div class="flex justify-end gap-4">
                <button type="button" id="cancelCheckoutBtn" class="bg-gray-300 text-gray-800 px-6 py-2 rounded-lg font-semibold hover:bg-gray-400">إلغاء</button>
                <button type="submit" id="confirmCheckoutBtn" class="bg-green-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-green-700">تأكيد البيع</button>
            </div>
        </form>
    </div>
</div>

<div id="invoiceModal" class="modal fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50 hidden">
    <div class="modal-content relative bg-white p-8 rounded-lg shadow-xl w-11/12 md:w-3/5 my-8 mx-auto">
        <button id="closeInvoiceBtn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl"><i class="fas fa-times"></i></button>
        <div id="invoiceContent" class="invoice-container"></div>
    </div>
</div>

<div id="invoiceModal" class="modal fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50 hidden">
    <div class="modal-content relative bg-white p-8 rounded-lg shadow-xl w-11/12 md:w-3/5 my-8 mx-auto">
        <button id="closeInvoiceBtn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl"><i class="fas fa-times"></i></button>
        <div id="invoiceContent" class="invoice-container"></div>
    </div>
</div>
    <div id="toastContainer" class="fixed bottom-4 left-4 z-50 space-y-2"></div>

    <script>
        // تحميل بيانات المنتجات من PHP مباشرة عند تحميل الصفحة
        const products = <?php echo $products_json; ?>;
        const currencySymbol = 'د.ل';
        const lowStockThreshold = 5;

        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.getElementById('menuBtn');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');
        const closeAppBtn = document.getElementById('closeAppBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const searchInput = document.getElementById('searchInput');
        const autocompleteList = document.getElementById('autocompleteList');
        const productsList = document.getElementById('products-list');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const cartItemsContainer = document.getElementById('cartItems');
        const emptyCartMessage = document.getElementById('emptyCartMessage');
        const cartItemCountSpan = document.getElementById('cartItemCount');
        const cartTotalSpan = document.getElementById('cartTotal');
        const discountInput = document.getElementById('discountInput');
        const clearCartBtn = document.getElementById('clearCartBtn');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const checkoutModal = document.getElementById('checkoutModal');
        const modalCartSummary = document.getElementById('modalCartSummary');
        const paymentMethodSelect = document.getElementById('paymentMethod');
        const creditFields = document.getElementById('creditFields');
        const clientNameInput = document.getElementById('clientName');
        const clientPhoneInput = document.getElementById('clientPhone');
        const installmentsFields = document.getElementById('installmentsFields');
        const installmentsMonthsInput = document.getElementById('installmentsMonths');
        const downPaymentInput = document.getElementById('downPayment');
        const monthlyInstallmentSpan = document.getElementById('monthlyInstallment');
        const receivedAmountInput = document.getElementById('receivedAmount');
        const changeDueSpan = document.getElementById('changeDue');
        const cancelCheckoutBtn = document.getElementById('cancelCheckoutBtn');
        const confirmCheckoutBtn = document.getElementById('confirmCheckoutBtn');
        const invoiceModal = document.getElementById('invoiceModal');
        const invoiceContent = document.getElementById('invoiceContent');
        const closeInvoiceBtn = document.getElementById('closeInvoiceBtn');
        const checkoutForm = document.getElementById('checkoutForm');
        const cartTotalInput = document.getElementById('cartTotalInput');
        const cartItemsInput = document.getElementById('cartItemsInput');
        const monthlyInstallmentInput = document.getElementById('monthlyInstallmentInput');

        const cart = [];

        // --- Sidebar and Auth Functions ---
        document.addEventListener('DOMContentLoaded', () => {
            menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', () => sidebar.classList.remove('open'));
            if (closeAppBtn) closeAppBtn.addEventListener('click', () => window.location.href = 'login.html');
            if (logoutBtn) logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.location.href = 'login.html';
            });
            renderProductCards(products);
            // إضافة رسالة للمستخدم إذا لم تكن هناك منتجات
            if (products.length === 0) {
                noResultsMessage.textContent = 'لا توجد منتجات متوفرة في المخزون.';
                noResultsMessage.classList.remove('hidden');
            }
        });

        // --- Product Search & Autocomplete Logic ---
        const renderAutocompleteList = (matches) => {
            autocompleteList.innerHTML = '';
            if (matches.length > 0 && searchInput.value) {
                matches.forEach(product => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-list-item';
                    const searchTerm = searchInput.value;
                    const nameRegex = new RegExp(searchTerm, 'gi');
                    const highlightedName = product.name.replace(nameRegex, `<span class="highlight">$&</span>`);
                    
                    item.innerHTML = `
                        <div>
                            <p class="font-semibold">${highlightedName}</p>
                            <span class="text-gray-500 text-sm"> (${product.id}) - الكمية: ${product.quantity}</span>
                        </div>
                        <span class="font-bold text-green-600">${parseFloat(product.price).toLocaleString()} ${currencySymbol}</span>
                    `;
                    item.addEventListener('click', () => {
                        addProductToCart(product);
                        searchInput.value = '';
                        autocompleteList.classList.add('hidden');
                    });
                    autocompleteList.appendChild(item);
                });
                autocompleteList.classList.remove('hidden');
            } else {
                autocompleteList.classList.add('hidden');
            }
        };

        const filterAndRenderProducts = () => {
            const searchTerm = searchInput.value.toLowerCase();
            const filteredProducts = products.filter(product =>
                (product.name.toLowerCase().includes(searchTerm) || product.id.toString().includes(searchTerm)) && product.quantity > 0
            );
            
            renderProductCards(filteredProducts);
            renderAutocompleteList(filteredProducts);

            if (filteredProducts.length === 0 && searchTerm.length > 0) {
                noResultsMessage.textContent = 'لا توجد نتائج مطابقة لبحثك.';
                noResultsMessage.classList.remove('hidden');
            } else {
                noResultsMessage.classList.add('hidden');
            }
        };

        searchInput.addEventListener('input', filterAndRenderProducts);

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchTerm = searchInput.value;
                const matches = products.filter(p => p.id == searchTerm && p.quantity > 0);
                if (matches.length === 1) {
                    addProductToCart(matches[0]);
                } else {
                    const firstMatch = products.find(p => p.name.toLowerCase().includes(searchTerm.toLowerCase()) && p.quantity > 0);
                    if (firstMatch) {
                        addProductToCart(firstMatch);
                    } else {
                        showToast('لا توجد منتجات مطابقة لهذا الباركود أو الاسم أو نفدت الكمية.', 'error');
                    }
                }
                searchInput.value = '';
                autocompleteList.classList.add('hidden');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F2') {
                e.preventDefault();
                searchInput.focus();
            }
            if (e.key === 'F9') {
                e.preventDefault();
                checkoutBtn.click();
            }
        });

        // --- Product Display Functions ---
        const renderProductCards = (list) => {
            productsList.innerHTML = '';
            list.forEach(product => {
                const isLowStock = product.quantity > 0 && product.quantity <= lowStockThreshold;
                const card = document.createElement('div');
                card.className = `product-card cursor-pointer ${isLowStock ? 'low-stock' : ''}`;
                
                card.innerHTML = `
                    <i class="fas fa-box text-5xl text-gray-400 mb-3"></i>
                    <h3 class="text-xl font-bold text-gray-800 mb-1">${product.name}</h3>
                    <p class="text-gray-600 text-sm mb-2">باركود: ${product.id}</p>
                    <p class="price">${parseFloat(product.price).toLocaleString()} ${currencySymbol}</p>
                    <p class="text-gray-500 text-sm mt-2">المخزون: <span class="font-semibold">${product.quantity}</span></p>
                `;
                card.addEventListener('click', () => addProductToCart(product));
                productsList.appendChild(card);
            });
        };

        // --- Cart Management Functions ---
        const updateCartTotals = () => {
            let subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const discount = parseFloat(discountInput.value) || 0;
            
            const finalTotal = subtotal - (subtotal * discount / 100);

            cartTotalSpan.textContent = `${finalTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currencySymbol}`;
            cartItemCountSpan.textContent = cart.length;
            cartItemCountSpan.classList.toggle('hidden', cart.length === 0);
        };
        
        discountInput.addEventListener('input', updateCartTotals);

        const renderCart = () => {
            cartItemsContainer.innerHTML = '';
            if (cart.length === 0) {
                emptyCartMessage.classList.remove('hidden');
            } else {
                emptyCartMessage.classList.add('hidden');
                cart.forEach((item, index) => {
                    const cartItemEl = document.createElement('div');
                    cartItemEl.className = 'flex items-center gap-4 p-3 bg-gray-50 rounded-lg shadow-sm';
                    cartItemEl.innerHTML = `
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800">${item.name}</h4>
                            <p class="text-sm text-gray-600">الكمية: ${item.quantity} x ${parseFloat(item.price).toLocaleString()} ${currencySymbol}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="decrementQuantity(${index})" class="text-gray-600 hover:text-red-500"><i class="fas fa-minus-circle"></i></button>
                            <span class="font-bold w-6 text-center">${item.quantity}</span>
                            <button type="button" onclick="incrementQuantity(${index})" class="text-green-600 hover:text-green-700"><i class="fas fa-plus-circle"></i></button>
                            <button type="button" onclick="removeFromCart(${index})" class="text-red-600 hover:text-red-700"><i class="fas fa-trash"></i></button>
                        </div>
                    `;
                    cartItemsContainer.appendChild(cartItemEl);
                });
            }
            updateCartTotals();
        };

        const addProductToCart = (productToAdd) => {
            const existingItem = cart.find(item => item.id === productToAdd.id);
            if (existingItem) {
                if (existingItem.quantity < productToAdd.quantity) {
                    existingItem.quantity++;
                    showToast('تمت زيادة الكمية بنجاح.', 'success');
                } else {
                    showToast('لا يمكن إضافة المزيد من هذا المنتج. نفد المخزون.', 'error');
                }
            } else {
                if (productToAdd.quantity > 0) {
                    cart.push({ ...productToAdd, quantity: 1 });
                    showToast('تمت إضافة المنتج إلى السلة.', 'success');
                } else {
                    showToast('لا يمكن إضافة المنتج، نفد المخزون.', 'error');
                }
            }
            renderCart();
        };

        window.incrementQuantity = (index) => {
            const cartItem = cart[index];
            const stockProduct = products.find(p => p.id === cartItem.id);
            if (cartItem.quantity < stockProduct.quantity) {
                cartItem.quantity++;
                renderCart();
            } else {
                showToast('لا يمكن زيادة الكمية، نفد المخزون.', 'error');
            }
        };

        window.decrementQuantity = (index) => {
            if (cart[index].quantity > 1) {
                cart[index].quantity--;
            } else {
                cart.splice(index, 1);
            }
            renderCart();
        };

        window.removeFromCart = (index) => {
            cart.splice(index, 1);
            renderCart();
            showToast('تم حذف المنتج من السلة.', 'success');
        };
        
        clearCartBtn.addEventListener('click', () => {
            cart.length = 0;
            renderCart();
            showToast('تم مسح السلة.', 'success');
        });

        // --- Checkout Logic ---
        checkoutBtn.addEventListener('click', () => {
            if (cart.length === 0) {
                showToast('السلة فارغة، يرجى إضافة منتجات.', 'error');
                return;
            }
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const discount = parseFloat(discountInput.value) || 0;
            const finalTotal = total - (total * discount / 100);

            modalCartSummary.innerHTML = `
                <p><strong>الإجمالي قبل الخصم:</strong> ${total.toLocaleString()} ${currencySymbol}</p>
                <p><strong>الخصم:</strong> ${discount}%</p>
                <p><strong>الإجمالي النهائي:</strong> ${finalTotal.toLocaleString()} ${currencySymbol}</p>
            `;
            
            receivedAmountInput.value = '';
            changeDueSpan.textContent = `0.00 ${currencySymbol}`;
            
            paymentMethodSelect.value = 'cash';
            creditFields.classList.add('hidden');
            installmentsFields.classList.add('hidden');
            receivedAmountInput.readOnly = false;
            
            // إعداد الحقول المخفية قبل فتح النافذة
            cartTotalInput.value = finalTotal;
            cartItemsInput.value = JSON.stringify(cart);
            monthlyInstallmentInput.value = 0; // Reset hidden field

            checkoutModal.classList.remove('hidden');
            receivedAmountInput.focus();
        });
        
        paymentMethodSelect.addEventListener('change', (e) => {
            const method = e.target.value;
            creditFields.classList.toggle('hidden', method !== 'credit' && method !== 'installments');
            installmentsFields.classList.toggle('hidden', method !== 'installments');
            receivedAmountInput.readOnly = method === 'credit' || method === 'installments';
            
            if (method === 'credit' || method === 'installments') {
                receivedAmountInput.value = method === 'installments' ? (parseFloat(downPaymentInput.value) || 0) : '0';
                changeDueSpan.textContent = `0.00 ${currencySymbol}`;
            }
            if (method === 'installments') {
                calculateInstallment();
            } else {
                monthlyInstallmentInput.value = 0; // Clear value if not installments
            }
        });
        
        installmentsMonthsInput.addEventListener('input', calculateInstallment);
        downPaymentInput.addEventListener('input', calculateInstallment);
        
        function calculateInstallment() {
            const months = parseInt(installmentsMonthsInput.value);
            const downPayment = parseFloat(downPaymentInput.value) || 0;
            const finalTotal = parseFloat(cartTotalSpan.textContent.replace(/[^\d.]/g, '')) || 0;
            
            let monthly = 0;
            if (months > 0) {
                const remaining = finalTotal - downPayment;
                monthly = remaining / months;
                monthlyInstallmentSpan.textContent = `${monthly.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currencySymbol}`;
            } else {
                monthlyInstallmentSpan.textContent = `0.00 ${currencySymbol}`;
            }
            receivedAmountInput.value = downPayment;
            receivedAmountInput.dispatchEvent(new Event('input'));

            // حفظ قيمة القسط في الحقل المخفي
            monthlyInstallmentInput.value = monthly.toFixed(2);
        }

        receivedAmountInput.addEventListener('input', () => {
            const received = parseFloat(receivedAmountInput.value) || 0;
            const finalTotal = parseFloat(cartTotalSpan.textContent.replace(/[^\d.]/g, '')) || 0;
            const change = received - finalTotal;
            changeDueSpan.textContent = `${change.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currencySymbol}`;
            changeDueSpan.classList.toggle('text-red-500', change < 0);
            changeDueSpan.classList.toggle('text-green-600', change >= 0);
        });

        cancelCheckoutBtn.addEventListener('click', () => {
            checkoutModal.classList.add('hidden');
        });

        // Helper for toast notifications
        function showToast(message, type) {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            toast.className = `p-4 rounded-lg text-white font-semibold flex items-center gap-3 shadow-lg ${bgColor}`;
            toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html> 