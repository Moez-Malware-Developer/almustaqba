-- الخطوة 1: إنشاء قاعدة البيانات
-- إذا كانت قاعدة البيانات موجودة، سيتم حذفها وإعادة إنشائها لتجنب أي أخطاء.
DROP DATABASE IF EXISTS workshop_management;
CREATE DATABASE workshop_management;

-- الخطوة 2: تحديد قاعدة البيانات للعمل عليها
USE workshop_management;

-- =========================================================
-- جداول إدارة المنتجات (لصفحة products.html)
-- =========================================================

CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    purchase_price DECIMAL(10, 2) NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0
);

-- =========================================================
-- جداول نقطة البيع (لصفحة sales.html)
-- =========================================================

CREATE TABLE sales (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10, 2) NOT NULL
);

CREATE TABLE sale_items (
    sale_item_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
);

-- =========================================================
-- جداول إدارة الصيانة (لصفحة repairs.html)
-- =========================================================

CREATE TABLE technicians (
    technician_id INT PRIMARY KEY AUTO_INCREMENT,
    technician_name VARCHAR(255) NOT NULL,
    technician_phone VARCHAR(20),
    hire_date DATE
);

CREATE TABLE repairs (
    repair_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    device_type VARCHAR(100) NOT NULL,
    fault_type VARCHAR(255) NOT NULL,
    agreed_price DECIMAL(10, 2) NOT NULL,
    advance_payment DECIMAL(10, 2) DEFAULT 0.00,
    additional_notes TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'مستلمة',
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_to INT,
    
    FOREIGN KEY (assigned_to) REFERENCES technicians(technician_id) ON DELETE SET NULL
);

-- جدول لتتبع قطع الغيار المستخدمة في كل عملية صيانة
CREATE TABLE repair_parts_used (
    repair_id INT,
    product_id INT,
    quantity_used INT NOT NULL,
    
    PRIMARY KEY (repair_id, product_id),
    FOREIGN KEY (repair_id) REFERENCES repairs(repair_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- جدول لتسجيل التغييرات على حالة الصيانة
CREATE TABLE repair_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    repair_id INT,
    status_change VARCHAR(50) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,

    FOREIGN KEY (repair_id) REFERENCES repairs(repair_id) ON DELETE CASCADE
);

-- =========================================================
-- جداول المدفوعات والمصروفات (لصفحة payments.html)
-- =========================================================

CREATE TABLE expenses (
    expense_id INT PRIMARY KEY AUTO_INCREMENT,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100)
);

-- =========================================================
-- جداول الأقساط / الآجل (لصفحة installments.html)
-- =========================================================

CREATE TABLE credit_sales (
    credit_sale_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    total_amount DECIMAL(10, 2) NOT NULL,
    initial_payment DECIMAL(10, 2) DEFAULT 0.00,
    remaining_amount DECIMAL(10, 2) NOT NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE installment_payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    credit_sale_id INT,
    payment_amount DECIMAL(10, 2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (credit_sale_id) REFERENCES credit_sales(credit_sale_id) ON DELETE CASCADE
);  