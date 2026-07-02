# 🚀 راهنمای نصب کامل MN Order Panel

## 📋 پیش‌نیازها

- ✅ PHP 7.4 یا بالاتر
- ✅ MySQL 5.7 یا بالاتر
- ✅ WordPress + WooCommerce
- ✅ دسترسی به سرور و دیتابیس

---

## 📂 ساختار نهایی پروژه

```
mn-order-panel/
│
├── config/
│   ├── database.php          ← کلاس اتصال دیتابیس
│   ├── settings.php          ← مدیریت تنظیمات
│   └── wp-bridge.php         ← (اختیاری)
│
├── pages/
│   ├── login.php             ← ✅ صفحه ورود
│   ├── index.php             ← ثبت سفارش جدید
│   ├── orders-list.php       ← ✅ لیست سفارشات
│   ├── order-details.php     ← ✅ جزئیات سفارش
│   └── edit-order.php        ← ✅ ویرایش سفارش
│
├── ajax/
│   ├── login.php             ← ✅ API ورود
│   ├── logout.php            ← ✅ API خروج
│   ├── get-orders.php        ← ✅ دریافت لیست
│   ├── get-order-details.php ← ✅ جزئیات سفارش
│   ├── sync-order.php        ← ✅ همگام‌سازی تک
│   ├── sync-multiple-orders.php ← ✅ همگام‌سازی چند
│   ├── update-order.php      ← ✅ بروزرسانی سفارش
│   ├── delete-order.php      ← ✅ حذف سفارش
│   ├── product-search.php    ← جستجوی محصولات
│   ├── get-product-details.php ← جزئیات محصول
│   ├── check-customer.php    ← ✅ چک مشتری
│   ├── create-order.php      ← ثبت سفارش
│   └── calculate-tiered-price.php ← ✅ قیمت پله‌ای
│
├── sync/
│   ├── class-sync-manager.php    ← ✅ مدیریت صف
│   ├── class-order-sync.php      ← ✅ همگام‌سازی سفارش
│   ├── class-customer-sync.php   ← همگام‌سازی مشتری
│   └── cron-sync.php             ← کرون جاب
│
├── includes/
│   ├── auth-check.php            ← ✅ Middleware لاگین
│   ├── class-tiered-pricing.php  ← ✅ قیمت‌گذاری پله‌ای
│   ├── class-order.php           ← مدیریت سفارش
│   ├── class-customer.php        ← مدیریت مشتری
│   └── functions.php             ← توابع کمکی
│
├── assets/
│   ├── css/
│   └── js/
│
└── database/
    └── schema.sql                ← ✅ ساختار دیتابیس کامل
```

---

## 🔧 مرحله 1: ایجاد دیتابیس

### روش 1: از طریق phpMyAdmin
1. وارد phpMyAdmin شو
2. بساز دیتابیس جدید: `mn_panel_db`
3. وارد دیتابیس شو
4. رفتن به تب SQL
5. کپی کردن محتوای `schema.sql`
6. اجرا (Go)

### روش 2: از طریق CLI
```bash
mysql -u root -p < /path/to/schema.sql
```

### ✅ چک کن:
```sql
USE mn_panel_db;
SHOW TABLES;
-- باید 10 جدول نمایش داده بشه
```

---

## 📁 مرحله 2: آپلود فایل‌ها

### فایل‌های ضروری برای شروع:

#### 1. Config (پوشه config/)
```
✅ database.php
✅ settings.php
```

#### 2. Pages (پوشه pages/)
```
✅ login.php          ← اولویت اول
✅ orders-list.php    ← اولویت دوم
✅ order-details.php
✅ edit-order.php
□ index.php (ثبت سفارش - قبلی موجود)
```

#### 3. AJAX (پوشه ajax/)
```
✅ login.php          ← ضروری
✅ logout.php         ← ضروری
✅ get-orders.php
✅ get-order-details.php
✅ sync-order.php
✅ sync-multiple-orders.php
✅ update-order.php
✅ delete-order.php
✅ check-customer.php
✅ calculate-tiered-price.php
□ سایر فایل‌های موجود قبلی
```

#### 4. Sync (پوشه sync/)
```
✅ class-sync-manager.php  ← بروز شده
✅ class-order-sync.php    ← بروز شده
□ class-customer-sync.php (قبلی موجود)
□ cron-sync.php (قبلی موجود)
```

#### 5. Includes (پوشه includes/)
```
✅ auth-check.php          ← جدید - ضروری
✅ class-tiered-pricing.php
□ سایر فایل‌های موجود قبلی
```

---

## 🔐 مرحله 3: فعال‌سازی احراز هویت

### در همه صفحات pages/ این خط رو اضافه کن:

```php
<?php
/**
 * نام صفحه
 */

// ✅ این خط رو اضافه کن
require_once __DIR__ . '/../includes/auth-check.php';

// بقیه کد...
?>
```

### فایل‌هایی که نیاز به این خط دارن:
- ✅ `pages/index.php` (ثبت سفارش)
- ✅ `pages/orders-list.php`
- ✅ `pages/order-details.php`
- ✅ `pages/edit-order.php`

### در فایل‌های AJAX:

```php
<?php
/**
 * نام API
 */

header('Content-Type: application/json; charset=utf-8');

// ✅ این خط رو اضافه کن
require_once __DIR__ . '/../includes/auth-check.php';

// بقیه کد...
```

**نکته:** فایل‌های `login.php` و `logout.php` نیاز به auth-check ندارن!

---

## ⚙️ مرحله 4: تنظیمات

### چک کردن تنظیمات در دیتابیس:

```sql
SELECT * FROM mn_settings WHERE setting_key IN (
    'wp_load_path',
    'wp_site_url',
    'wp_db_host',
    'wp_db_name'
);
```

### در صورت نیاز به تغییر:

```sql
UPDATE mn_settings 
SET setting_value = '/var/www/html/wp-load.php' 
WHERE setting_key = 'wp_load_path';

UPDATE mn_settings 
SET setting_value = 'https://www.puonak.com' 
WHERE setting_key = 'wp_site_url';
```

---

## 🧪 مرحله 5: تست سیستم

### تست 1: ورود
```
URL: https://puonak.com/mn_order_panel/pages/login.php
Username: admin
Password: admin123
```
✅ باید وارد بشی و به orders-list منتقل بشی

### تست 2: لیست سفارشات
```
URL: https://puonak.com/mn_order_panel/pages/orders-list.php
```
✅ باید لیست سفارشات نمایش داده بشه

### تست 3: ثبت سفارش
```
URL: https://puonak.com/mn_order_panel/pages/index.php
```
✅ باید بتونی سفارش جدید ثبت کنی

### تست 4: همگام‌سازی
```
1. لیست سفارشات → یه سفارش pending انتخاب کن
2. دکمه Sync بزن
3. وضعیت باید به "synced" تغییر کنه
```

### تست 5: خروج
```
1. کلیک روی نام کاربر (بالا سمت راست)
2. کلیک روی "خروج"
3. باید به صفحه login منتقل بشی
```

---

## 🔒 مرحله 6: امنیت (ضروری!)

### 1. تغییر رمز پیش‌فرض

**⚠️ بلافاصله بعد از نصب:**

```php
<?php
// تولید hash رمز جدید
$new_password = 'YOUR_STRONG_PASSWORD';
$hash = password_hash($new_password, PASSWORD_DEFAULT);
echo $hash;
?>
```

سپس در دیتابیس:
```sql
UPDATE mn_panel_users 
SET password = '$2y$10$...' -- hash تولید شده
WHERE username = 'admin';
```

### 2. محافظت از فایل‌های config

`.htaccess` در پوشه `config/`:
```apache
Order Deny,Allow
Deny from all
```

### 3. فعال‌سازی HTTPS
مطمئن شو سایت روی HTTPS هست.

---

## 📊 مانیتورینگ و نگهداری

### چک کردن لاگ‌ها:

```sql
-- لاگ ورود
SELECT * FROM mn_login_log 
ORDER BY created_at DESC 
LIMIT 20;

-- لاگ همگام‌سازی
SELECT * FROM mn_sync_log 
ORDER BY created_at DESC 
LIMIT 20;

-- سفارشات failed
SELECT * FROM mn_orders 
WHERE status = 'failed';
```

### پاکسازی دوره‌ای:

```sql
-- پاک کردن لاگ‌های قدیمی (بیش از 30 روز)
DELETE FROM mn_login_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DELETE FROM mn_sync_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- پاک کردن صف completed
DELETE FROM mn_sync_queue 
WHERE status = 'completed' 
AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

## ❗ عیب‌یابی مشکلات رایج

### مشکل 1: خطای "Class not found"
**راه‌حل:**
- مطمئن شو همه فایل‌های کلاس آپلود شدن
- چک کن مسیر `require_once` درسته

### مشکل 2: نمیشه لاگین کرد
**راه‌حل:**
```sql
-- چک کردن کاربر admin
SELECT * FROM mn_panel_users WHERE username = 'admin';

-- ریست تلاش‌های ناموفق
UPDATE mn_panel_users SET failed_login_attempts = 0;
```

### مشکل 3: Sync کار نمیکنه
**راه‌حل:**
```sql
-- چک تنظیمات WordPress
SELECT * FROM mn_settings WHERE setting_key = 'wp_load_path';

-- چک وضعیت سفارش
SELECT id, status, last_sync_error 
FROM mn_orders 
WHERE id = YOUR_ORDER_ID;
```

### مشکل 4: Session timeout خیلی زوده
**راه‌حل:**
```sql
UPDATE mn_settings 
SET setting_value = '36000' -- 10 ساعت
WHERE setting_key = 'session_lifetime';
```

---

## 📝 چک‌لیست نهایی

```
☐ دیتابیس ساخته شد
☐ جداول ایجاد شدن
☐ کاربر admin موجود هست
☐ فایل‌های ضروری آپلود شدن
☐ auth-check.php به صفحات اضافه شد
☐ تنظیمات wp_load_path صحیح هست
☐ میتونم لاگین کنم
☐ لیست سفارشات کار میکنه
☐ Sync کار میکنه
☐ رمز admin تغییر کرد ✅
☐ HTTPS فعال هست ✅
```

---

## 🎉 تمام!

سیستم آماده استفاده هست. موفق باشی! 🚀

**پشتیبانی:**
- اگه مشکلی بود، لاگ‌های PHP و MySQL رو چک کن
- Console مرورگر (F12) رو چک کن
- جداول لاگ دیتابیس رو بررسی کن