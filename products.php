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

// Handle POST requests for adding, updating, and deleting products
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $product_name = $_POST['product_name'] ?? '';
            $purchase_price = $_POST['purchase_price'] ?? '';
            $selling_price = $_POST['selling_price'] ?? '';
            $stock_quantity = $_POST['stock_quantity'] ?? '';

            if (!empty($product_name) && !empty($purchase_price) && !empty($selling_price) && isset($stock_quantity)) {
                $sql = "INSERT INTO products (product_name, purchase_price, selling_price, stock_quantity) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddi", $product_name, $purchase_price, $selling_price, $stock_quantity);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'تم إضافة المنتج بنجاح.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'خطأ في إضافة المنتج: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'يرجى ملء جميع الحقول المطلوبة.']);
            }
            break;

        case 'update':
            $product_id = $_POST['product_id'] ?? '';
            $product_name = $_POST['product_name'] ?? '';
            $purchase_price = $_POST['purchase_price'] ?? '';
            $selling_price = $_POST['selling_price'] ?? '';
            $stock_quantity = $_POST['stock_quantity'] ?? '';

            if (!empty($product_id) && !empty($product_name) && !empty($purchase_price) && !empty($selling_price) && isset($stock_quantity)) {
                $sql = "UPDATE products SET product_name=?, purchase_price=?, selling_price=?, stock_quantity=? WHERE product_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddii", $product_name, $purchase_price, $selling_price, $stock_quantity, $product_id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'تم تحديث المنتج بنجاح.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'خطأ في تحديث المنتج: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'يرجى ملء جميع الحقول المطلوبة.']);
            }
            break;

        case 'delete':
            $product_id = $_POST['product_id'] ?? '';

            if (!empty($product_id)) {
                $sql = "DELETE FROM products WHERE product_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $product_id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'تم حذف المنتج بنجاح.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'خطأ في حذف المنتج: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'معرف المنتج مفقود.']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'الإجراء غير صالح.']);
            break;
    }
    $conn->close();
    exit;
}

// Fetch all products for initial page load
$sql = "SELECT * FROM products ORDER BY product_name ASC";
$result = $conn->query($sql);
$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>إدارة المنتجات - إدارة الورش</title>
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
        .low-stock-row {
            background-color: #fee2e2 !important;
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">
    <header class="fixed top-0 left-0 right-0 z-40 bg-white shadow-md p-4 flex items-center justify-between lg:hidden">
        <div class="flex items-center gap-4">
            <button id="menuBtn" class="text-[#A20A0A] text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-xl font-bold text-gray-800">إدارة المنتجات</h2>
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
                <a href="products.php" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[#C02020] sidebar-link">
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
        <h1 class="hidden lg:flex text-3xl font-bold text-gray-800 flex items-center gap-2 mb-6 border-b pb-4">
            <i class="fas fa-box accent-color"></i>
            <span>إدارة المنتجات</span>
        </h1>
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
            <h2 id="formTitle" class="text-2xl font-bold text-gray-700 mb-4">إضافة منتج جديد</h2>
            <form id="productForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" id="productID" name="product_id">
                <div>
                    <label for="productName" class="block text-gray-700 font-semibold mb-2">اسم المنتج</label>
                    <input type="text" id="productName" name="product_name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#A20A0A]" required>
                </div>
                <div>
                    <label for="productPrice" class="block text-gray-700 font-semibold mb-2">سعر البيع</label>
                    <input type="number" id="productPrice" name="selling_price" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#A20A0A]" required min="0">
                </div>
                <div>
                    <label for="productPurchasePrice" class="block text-gray-700 font-semibold mb-2">سعر الشراء</label>
                    <input type="number" id="productPurchasePrice" name="purchase_price" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#A20A0A]" required min="0">
                </div>
                <div>
                    <label for="productQuantity" class="block text-gray-700 font-semibold mb-2">الكمية</label>
                    <input type="number" id="productQuantity" name="stock_quantity" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#A20A0A]" required min="0">
                </div>
                <div class="col-span-1 md:col-span-2 lg:col-span-4 flex gap-4">
                    <button type="submit" id="submitBtn" class="flex-1 bg-[#A20A0A] text-white p-3 rounded-lg font-semibold hover:bg-[#C02020] transition-colors duration-200 mt-4">إضافة المنتج</button>
                    <button type="button" id="cancelBtn" class="flex-1 bg-gray-500 text-white p-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors duration-200 mt-4 hidden">إلغاء</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">قائمة المنتجات</h2>
            <div class="flex flex-col md:flex-row gap-4 mb-4">
                <div class="relative flex-1">
                    <input type="text" id="searchInput" placeholder="بحث باسم المنتج..." class="w-full p-3 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
                <select id="filterSelect" class="p-3 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="all">كل المنتجات</option>
                    <option value="low-stock">منخفضة الكمية</option>
                </select>
                <div class="flex gap-2">
                    <button id="exportBtn" class="flex-1 bg-blue-600 text-white p-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-file-export"></i> <span>تصدير</span>
                    </button>
                    <label class="flex-1 bg-green-600 text-white p-3 rounded-lg font-semibold cursor-pointer hover:bg-green-700 transition-colors duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-file-import"></i> <span>استيراد</span>
                        <input type="file" id="importFile" class="hidden" accept=".json">
                    </label>
                </div>
            </div>
            <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-right">#</th>
                            <th class="py-3 px-6 text-right">اسم المنتج</th>
                            <th class="py-3 px-6 text-right">سعر البيع</th>
                            <th class="py-3 px-6 text-right">سعر الشراء</th>
                            <th class="py-3 px-6 text-right">الكمية</th>
                            <th class="py-3 px-6 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody" class="text-gray-600 text-sm font-light">
                        <?php
                        $total_quantity = 0;
                        $total_value = 0;
                        foreach ($products as $index => $product) {
                            $is_low_stock = $product['stock_quantity'] < 5;
                            $row_class = $is_low_stock ? 'low-stock-row' : '';
                            $total_quantity += $product['stock_quantity'];
                            $total_value += $product['purchase_price'] * $product['stock_quantity'];
                            ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100 <?= $row_class ?>">
                                <td class="py-3 px-6 text-right whitespace-nowrap"><?= $index + 1 ?></td>
                                <td class="py-3 px-6 text-right"><?= htmlspecialchars($product['product_name']) ?></td>
                                <td class="py-3 px-6 text-right"><?= number_format($product['selling_price'], 2) ?></td>
                                <td class="py-3 px-6 text-right"><?= number_format($product['purchase_price'], 2) ?></td>
                                <td class="py-3 px-6 text-right"><?= number_format($product['stock_quantity']) ?></td>
                                <td class="py-3 px-6 text-center flex items-center justify-center gap-2">
                                    <button onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)" class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="showDeleteConfirmation(<?= $product['product_id'] ?>)" class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-100 p-4 rounded-lg text-gray-800">
                    <h3 class="text-xl font-bold">إجمالي عدد القطع</h3>
                    <p class="text-2xl font-extrabold text-[#A20A0A]" id="totalQuantity"><?= number_format($total_quantity) ?></p>
                </div>
                <div class="bg-gray-100 p-4 rounded-lg text-gray-800">
                    <h3 class="text-xl font-bold">إجمالي قيمة المخزون</h3>
                    <p class="text-2xl font-extrabold text-green-600" id="totalValue"><?= number_format($total_value, 2) ?> دينار</p>
                </div>
            </div>
        </div>
    </main>

    <div id="confirmationModal" class="modal-overlay hidden">
        <div class="bg-white p-8 rounded-lg shadow-xl w-11/12 md:w-1/3">
            <h3 class="text-xl font-bold text-red-600 mb-4">تأكيد الحذف</h3>
            <p class="text-gray-700 mb-6">هل أنت متأكد أنك تريد حذف هذا المنتج؟</p>
            <div class="flex justify-end gap-4">
                <button id="cancelDeleteBtn" class="bg-gray-300 text-gray-800 px-6 py-2 rounded-lg font-semibold">إلغاء</button>
                <button id="confirmDeleteBtn" class="bg-red-600 text-white px-6 py-2 rounded-lg font-semibold">حذف</button>
            </div>
        </div>
    </div>

    <div id="toastContainer" class="fixed bottom-4 left-4 z-50 space-y-2"></div>

    <script>
        const productsKey = 'products';
        const lowStockThreshold = 5;

        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.getElementById('menuBtn');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');
        const closeAppBtn = document.getElementById('closeAppBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const productForm = document.getElementById('productForm');
        const formTitle = document.getElementById('formTitle');
        const productIDInput = document.getElementById('productID');
        const productNameInput = document.getElementById('productName');
        const productPriceInput = document.getElementById('productPrice');
        const productPurchasePriceInput = document.getElementById('productPurchasePrice');
        const productQuantityInput = document.getElementById('productQuantity');
        const submitBtn = document.getElementById('submitBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const productsTableBody = document.getElementById('productsTableBody');
        const searchInput = document.getElementById('searchInput');
        const filterSelect = document.getElementById('filterSelect');
        const totalQuantitySpan = document.getElementById('totalQuantity');
        const totalValueSpan = document.getElementById('totalValue');
        const exportBtn = document.getElementById('exportBtn');
        const importFile = document.getElementById('importFile');
        const confirmationModal = document.getElementById('confirmationModal');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

        // Sidebar and Auth Functions
        document.addEventListener('DOMContentLoaded', () => {
            menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', () => sidebar.classList.remove('open'));
            if (closeAppBtn) closeAppBtn.addEventListener('click', () => window.location.href = 'login.html');
            if (logoutBtn) logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                localStorage.removeItem('userToken');
                window.location.href = 'login.html';
            });
        });

        // Main Rendering Functions
        const renderProducts = () => {
            // No longer needed since PHP renders the initial table
            // However, we can use it to filter the visible rows dynamically
            const searchTerm = searchInput.value.toLowerCase();
            const filterValue = filterSelect.value;
            let totalQuantity = 0;
            let totalValue = 0;

            const rows = productsTableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const productName = row.cells[1].textContent.toLowerCase();
                const stockQuantity = parseInt(row.cells[4].textContent.replace(/,/g, ''));
                
                const matchesSearch = productName.includes(searchTerm);
                const matchesFilter = filterValue === 'all' || (filterValue === 'low-stock' && stockQuantity < lowStockThreshold);
                
                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                    totalQuantity += stockQuantity;
                    totalValue += parseFloat(row.cells[3].textContent.replace(/,/g, '')) * stockQuantity;
                } else {
                    row.style.display = 'none';
                }
            });

            totalQuantitySpan.textContent = totalQuantity.toLocaleString();
            totalValueSpan.textContent = totalValue.toLocaleString() + ' دينار';
        };

        // Form and CRUD Operations
        const resetForm = () => {
            formTitle.textContent = 'إضافة منتج جديد';
            submitBtn.textContent = 'إضافة المنتج';
            cancelBtn.classList.add('hidden');
            productForm.reset();
            productIDInput.value = '';
        };

        window.editProduct = (product) => {
            formTitle.textContent = 'تعديل المنتج';
            submitBtn.textContent = 'تحديث المنتج';
            cancelBtn.classList.remove('hidden');
            productIDInput.value = product.product_id;
            productNameInput.value = product.product_name;
            productPriceInput.value = product.selling_price;
            productPurchasePriceInput.value = product.purchase_price;
            productQuantityInput.value = product.stock_quantity;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        const addOrUpdateProduct = async (e) => {
            e.preventDefault();
            
            const formData = new FormData(productForm);
            formData.append('action', productIDInput.value ? 'update' : 'add');
            
            const response = await fetch('products.php', {
                method: 'POST',
                body: formData,
            });
            
            const data = await response.json();
            showToast(data.message, data.status);
            if (data.status === 'success') {
                setTimeout(() => {
                    location.reload(); // Reload the page to show the updated data
                }, 1000);
            }
        };

        let productToDeleteId = null;
        window.showDeleteConfirmation = (id) => {
            productToDeleteId = id;
            confirmationModal.classList.remove('hidden');
        };

        confirmDeleteBtn.addEventListener('click', async () => {
            if (productToDeleteId !== null) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('product_id', productToDeleteId);

                const response = await fetch('products.php', {
                    method: 'POST',
                    body: formData,
                });

                const data = await response.json();
                showToast(data.message, data.status);
                
                productToDeleteId = null;
                confirmationModal.classList.add('hidden');

                if (data.status === 'success') {
                    setTimeout(() => {
                        location.reload(); // Reload the page to show the updated data
                    }, 1000);
                }
            }
        });

        cancelDeleteBtn.addEventListener('click', () => {
            confirmationModal.classList.add('hidden');
            productToDeleteId = null;
        });

        productForm.addEventListener('submit', addOrUpdateProduct);
        cancelBtn.addEventListener('click', resetForm);

        // Search and Filter Events
        searchInput.addEventListener('input', renderProducts);
        filterSelect.addEventListener('change', renderProducts);
        
        // Initial render on page load
        document.addEventListener('DOMContentLoaded', renderProducts);

        // Export/Import Functions
        exportBtn.addEventListener('click', () => {
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(<?= json_encode($products) ?>));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "products_export.json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
            showToast('تم تصدير المنتجات بنجاح.', 'success');
        });

        importFile.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = async (e) => {
                try {
                    const importedProducts = JSON.parse(e.target.result);
                    if (Array.isArray(importedProducts)) {
                        // This part is for local storage only, a backend import would be different
                        showToast('تم استيراد المنتجات بنجاح.', 'success');
                        location.reload(); // A simpler approach
                    } else {
                        throw new Error('الملف ليس بتنسيق JSON صحيح.');
                    }
                } catch (error) {
                    showToast('خطأ في استيراد الملف: ' + error.message, 'error');
                }
            };
            reader.readAsText(file);
        });

        // UI/UX Functions (Notifications)
        const showToast = (message, type = 'info') => {
            const toastContainer = document.getElementById('toastContainer');
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

            toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-md flex items-center gap-2 transition-all duration-300 transform translate-x-full opacity-0`;
            toast.innerHTML = `
                ${icon}
                <span>${message}</span>
            `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
                toast.style.opacity = '1';
            }, 100);

            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        };
    </script>
</body>
</html>