<?php
/**
 * MN Order Panel - Order Details
 * جزئیات سفارش
 */

require_once __DIR__ . '/../includes/auth-check.php';

session_start();

// چک احراز هویت - موقتاً غیرفعال
// TODO: بعداً فعال کن
// if (!isset($_SESSION['panel_user_id'])) {
//     header('Location: login.php');
//     exit;
// }

if (!isset($_GET['id'])) {
    header('Location: orders-list.php');
    exit;
}

require_once __DIR__ . '/../config/settings.php';
$order_id = intval($_GET['id']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات سفارش #<?php echo $order_id; ?> - پنل ثبت سفارش</title>
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css">
    
    <style>
        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .detail-card h5 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            flex: 0 0 150px;
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .badge-pending { background: #ffc107; color: #000; }
        .badge-syncing { background: #17a2b8; color: white; }
        .badge-synced { background: #28a745; color: white; }
        .badge-failed { background: #dc3545; color: white; }
        
        .items-table {
            width: 100%;
            margin-top: 15px;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: right;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .summary-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
            border-top: 2px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-buttons .btn {
            flex: 1;
        }
        
        .log-item {
            padding: 10px;
            border-right: 3px solid #ddd;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .log-success {
            border-right-color: #28a745;
        }
        
        .log-failed {
            border-right-color: #dc3545;
        }
        
        .log-time {
            font-size: 0.85rem;
            color: #666;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .detail-card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">در حال بارگذاری...</span>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-dark mb-4 no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="orders-list.php">
                <i class="fas fa-arrow-right"></i> بازگشت به لیست سفارشات
            </a>
            <div class="d-flex gap-2">
                <button class="btn btn-light btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> چاپ
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- ستون اصلی -->
            <div class="col-lg-8">
                <!-- اطلاعات سفارش -->
                <div class="detail-card">
                    <h5><i class="fas fa-shopping-cart"></i> اطلاعات سفارش</h5>
                    <div id="order-info">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="mt-2 text-muted">در حال بارگذاری...</p>
                        </div>
                    </div>
                </div>
                
                <!-- محصولات -->
                <div class="detail-card">
                    <h5><i class="fas fa-box"></i> محصولات سفارش</h5>
                    <div id="order-items">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <!-- یادداشت‌ها -->
                <div class="detail-card" id="notes-section" style="display: none;">
                    <h5><i class="fas fa-sticky-note"></i> یادداشت‌ها</h5>
                    <div id="order-notes" class="alert alert-info"></div>
                </div>
            </div>
            
            <!-- ستون کناری -->
            <div class="col-lg-4">
                <!-- اطلاعات مشتری -->
                <div class="detail-card">
                    <h5><i class="fas fa-user"></i> اطلاعات مشتری</h5>
                    <div id="customer-info">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <!-- خلاصه مالی -->
                <div class="detail-card">
                    <h5><i class="fas fa-calculator"></i> خلاصه مالی</h5>
                    <div id="order-summary">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <!-- دکمه‌های عملیات -->
                <div class="detail-card no-print">
                    <h5><i class="fas fa-cog"></i> عملیات</h5>
                    <div class="action-buttons" id="action-buttons">
                        <!-- دکمه‌ها به صورت داینامیک اضافه می‌شوند -->
                    </div>
                </div>
                
                <!-- تاریخچه همگام‌سازی -->
                <div class="detail-card">
                    <h5><i class="fas fa-history"></i> تاریخچه Sync</h5>
                    <div id="sync-logs">
                        <p class="text-muted text-center">بدون تاریخچه</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="../assets/js/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js"></script>
    
    <script>
        const MN_CONFIG = {
            apiUrl: '../ajax/',
            orderId: <?php echo $order_id; ?>
        };
        
        let orderData = null;
        
        // بارگذاری جزئیات سفارش
        function loadOrderDetails() {
            $('#loadingOverlay').addClass('active');
            
            $.ajax({
                url: MN_CONFIG.apiUrl + 'get-order-details.php',
                type: 'GET',
                data: { id: MN_CONFIG.orderId },
                success: function(response) {
                    if (response.success) {
                        orderData = response.order;
                        renderOrderDetails(response.order);
                        renderSyncLogs(response.sync_logs);
                    } else {
                        showError(response.message);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 404) {
                        showError('سفارش یافت نشد');
                    } else {
                        showError('خطا در بارگذاری اطلاعات');
                    }
                },
                complete: function() {
                    $('#loadingOverlay').removeClass('active');
                }
            });
        }
        
        // رندر جزئیات سفارش
        function renderOrderDetails(order) {
            // اطلاعات سفارش
            const statusBadge = getStatusBadge(order.status);
            const syncInfo = order.wc_order_id ? 
                `<span class="badge badge-synced">شماره WooCommerce: #${order.wc_order_id}</span>` :
                `<span class="badge badge-pending">هنوز همگام‌سازی نشده</span>`;
            
            $('#order-info').html(`
                <div class="info-row">
                    <div class="info-label">شماره سفارش:</div>
                    <div class="info-value"><strong>#${order.id}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">وضعیت:</div>
                    <div class="info-value">${statusBadge}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">همگام‌سازی:</div>
                    <div class="info-value">${syncInfo}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">تاریخ ثبت:</div>
                    <div class="info-value">${formatDateTime(order.created_at)}</div>
                </div>
                ${order.synced_at ? `
                <div class="info-row">
                    <div class="info-label">تاریخ Sync:</div>
                    <div class="info-value">${formatDateTime(order.synced_at)}</div>
                </div>
                ` : ''}
                <div class="info-row">
                    <div class="info-label">ثبت شده توسط:</div>
                    <div class="info-value">${order.created_by.name || order.created_by.username || 'نامشخص'}</div>
                </div>
                ${order.last_sync_error ? `
                <div class="info-row">
                    <div class="info-label">آخرین خطا:</div>
                    <div class="info-value"><span class="badge bg-danger">${order.last_sync_error}</span></div>
                </div>
                ` : ''}
            `);
            
            // اطلاعات مشتری
            $('#customer-info').html(`
                <div class="info-row">
                    <div class="info-label">نام:</div>
                    <div class="info-value"><strong>${order.customer.full_name}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">موبایل:</div>
                    <div class="info-value"><a href="tel:${order.customer.phone}">${order.customer.phone}</a></div>
                </div>
                ${order.customer.email ? `
                <div class="info-row">
                    <div class="info-label">ایمیل:</div>
                    <div class="info-value"><a href="mailto:${order.customer.email}">${order.customer.email}</a></div>
                </div>
                ` : ''}
                ${order.customer.address ? `
                <div class="info-row">
                    <div class="info-label">آدرس:</div>
                    <div class="info-value">${order.customer.address}</div>
                </div>
                ` : ''}
                ${order.customer.city || order.customer.state ? `
                <div class="info-row">
                    <div class="info-label">شهر/استان:</div>
                    <div class="info-value">${order.customer.city || ''} ${order.customer.state || ''}</div>
                </div>
                ` : ''}
                ${order.customer.postcode ? `
                <div class="info-row">
                    <div class="info-label">کد پستی:</div>
                    <div class="info-value">${order.customer.postcode}</div>
                </div>
                ` : ''}
            `);
            
            // محصولات
            let itemsHtml = '<table class="items-table"><thead><tr>';
            itemsHtml += '<th>ردیف</th><th>محصول</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th>';
            itemsHtml += '</tr></thead><tbody>';
            
            order.items.forEach((item, index) => {
                itemsHtml += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>${item.product_name}</strong>
                            ${item.product_sku ? `<br><small class="text-muted">SKU: ${item.product_sku}</small>` : ''}
                        </td>
                        <td>${formatPrice(item.price)} تومان</td>
                        <td>${item.quantity}</td>
                        <td><strong>${formatPrice(item.total)} تومان</strong></td>
                    </tr>
                `;
            });
            
            itemsHtml += '</tbody></table>';
            $('#order-items').html(itemsHtml);
            
            // خلاصه مالی
            const sh = order.shipping || {};
            const shippingCost = parseFloat(sh.cost || 0);
            const shippingMethod = sh.method || '';

            let shippingRow = '';
            if (shippingCost > 0) {
                shippingRow = `
                    <div class="summary-row">
                        <span>🚚 حمل و نقل${shippingMethod ? ' (' + shippingMethod + ')' : ''}:</span>
                        <span>${formatPrice(shippingCost)} تومان</span>
                    </div>`;
            } else if (shippingMethod) {
                shippingRow = `
                    <div class="summary-row text-success">
                        <span>🚚 حمل و نقل (${shippingMethod}):</span>
                        <span style="color:#10b981;font-weight:bold;">رایگان 🎉</span>
                    </div>`;
            }

            $('#order-summary').html(`
                <div class="summary-box">
                    <div class="summary-row">
                        <span>تعداد اقلام:</span>
                        <span>${order.summary.items_count}</span>
                    </div>
                    <div class="summary-row">
                        <span>جمع محصولات:</span>
                        <span>${formatPrice(order.summary.subtotal)} تومان</span>
                    </div>
                    ${order.summary.discount > 0 ? `
                    <div class="summary-row" style="color:#10b981;">
                        <span>تخفیف:</span>
                        <span>-${formatPrice(order.summary.discount)} تومان</span>
                    </div>` : ''}
                    ${shippingRow}
                    <div class="summary-row summary-total">
                        <span>جمع کل:</span>
                        <span>${formatPrice(order.summary.total)} تومان</span>
                    </div>
                </div>
            `);
            
            // یادداشت‌ها
            if (order.order_notes) {
                $('#notes-section').show();
                $('#order-notes').text(order.order_notes);
            }
            
            // دکمه‌های عملیات
            renderActionButtons(order);
        }
        
        // دکمه‌های عملیات
        function renderActionButtons(order) {
            let buttonsHtml = '';
            
            // ویرایش (فقط اگه sync نشده)
            if (!order.wc_order_id) {
                buttonsHtml += `
                    <button class="btn btn-warning" onclick="editOrder()">
                        <i class="fas fa-edit"></i> ویرایش
                    </button>
                `;
            }
            
            // Sync (فقط اگه sync نشده یا failed)
            if (!order.wc_order_id || order.status === 'failed') {
                buttonsHtml += `
                    <button class="btn btn-success" onclick="syncOrder()">
                        <i class="fas fa-sync"></i> همگام‌سازی
                    </button>
                `;
            }
            
            // حذف (فقط اگه failed)
            if (order.status === 'failed' && !order.wc_order_id) {
                buttonsHtml += `
                    <button class="btn btn-danger" onclick="deleteOrder()">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                `;
            }
            
            // لینک WooCommerce
            if (order.wc_order_id) {
                const wcUrl = '<?php echo MN_Settings::get("wp_site_url"); ?>/wp-admin/post.php?post=' + order.wc_order_id + '&action=edit';
                buttonsHtml += `
                    <a href="${wcUrl}" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> مشاهده در WC
                    </a>
                `;
            }
            
            $('#action-buttons').html(buttonsHtml);
        }
        
        // رندر لاگ‌های sync
        function renderSyncLogs(logs) {
            if (!logs || logs.length === 0) {
                $('#sync-logs').html('<p class="text-muted text-center">بدون تاریخچه</p>');
                return;
            }
            
            let logsHtml = '';
            logs.forEach(log => {
                const logClass = log.action === 'sync_completed' ? 'log-success' : 'log-failed';
                const icon = log.action === 'sync_completed' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                
                logsHtml += `
                    <div class="log-item ${logClass}">
                        <div><i class="fas ${icon}"></i> ${log.message || log.action}</div>
                        <div class="log-time">${formatDateTime(log.created_at)}</div>
                        ${log.execution_time ? `<div class="log-time">زمان اجرا: ${log.execution_time}s</div>` : ''}
                    </div>
                `;
            });
            
            $('#sync-logs').html(logsHtml);
        }
        
        // ویرایش سفارش
        function editOrder() {
            window.location.href = 'edit-order.php?id=' + MN_CONFIG.orderId;
        }
        
        // همگام‌سازی
        function syncOrder() {
            Swal.fire({
                title: 'همگام‌سازی سفارش',
                text: 'آیا مطمئن هستید؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'بله، Sync کن',
                cancelButtonText: 'انصراف'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#loadingOverlay').addClass('active');
                    
                    $.ajax({
                        url: MN_CONFIG.apiUrl + 'sync-order.php',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ order_id: MN_CONFIG.orderId }),
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('موفق!', 'سفارش با موفقیت همگام‌سازی شد', 'success');
                                loadOrderDetails(); // رفرش
                            } else {
                                Swal.fire('خطا!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('خطا!', 'خطا در ارتباط با سرور', 'error');
                        },
                        complete: function() {
                            $('#loadingOverlay').removeClass('active');
                        }
                    });
                }
            });
        }
        
        // حذف سفارش
        function deleteOrder() {
            Swal.fire({
                title: 'حذف سفارش',
                text: 'آیا مطمئن هستید؟ این عمل قابل بازگشت نیست!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'بله، حذف کن',
                cancelButtonText: 'انصراف'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: MN_CONFIG.apiUrl + 'delete-order.php',
                        type: 'DELETE',
                        contentType: 'application/json',
                        data: JSON.stringify({ order_id: MN_CONFIG.orderId }),
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('حذف شد!', 'سفارش با موفقیت حذف شد', 'success');
                                setTimeout(() => {
                                    window.location.href = 'orders-list.php';
                                }, 1500);
                            } else {
                                Swal.fire('خطا!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('خطا!', 'خطا در ارتباط با سرور', 'error');
                        }
                    });
                }
            });
        }
        
        // Helper functions
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="badge badge-pending"><i class="fas fa-clock"></i> در انتظار</span>',
                'syncing': '<span class="badge badge-syncing"><i class="fas fa-spinner fa-spin"></i> درحال Sync</span>',
                'synced': '<span class="badge badge-synced"><i class="fas fa-check"></i> Sync شده</span>',
                'failed': '<span class="badge badge-failed"><i class="fas fa-times"></i> خطا</span>'
            };
            return badges[status] || status;
        }
        
        function formatPrice(price) {
            return parseFloat(price).toLocaleString('fa-IR');
        }
        
        function formatDateTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
        }
        
        function showError(message) {
            Swal.fire('خطا', message, 'error');
        }
        
        // بارگذاری اولیه
        $(document).ready(function() {
            loadOrderDetails();
        });
    </script>
</body>
</html>