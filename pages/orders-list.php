<?php
/**
 * MN Order Panel - Orders List
 * لیست سفارشات
 */

session_start();

require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$page_title = 'لیست سفارشات - پنل ثبت سفارش';
$extra_css = '
    <style>
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .orders-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .badge-pending { background: #ffc107; }
        .badge-syncing { background: #17a2b8; }
        .badge-synced { background: #28a745; }
        .badge-failed { background: #dc3545; }
        
        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #ffc107; color: white; }
        .btn-sync { background: #28a745; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stats-card h3 {
            font-size: 2rem;
            margin: 10px 0;
            color: #667eea;
        }
        
        .stats-card p {
            color: #666;
            margin: 0;
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
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .table thead {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
    </style>
';


ob_start();
?>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">در حال بارگذاری...</span>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shopping-cart"></i> پنل ثبت سفارش
            </a>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> سفارش جدید
                </a>
                <button class="btn btn-outline-light btn-sm" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> خروج
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- آمار سریع -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <p>در انتظار Sync</p>
                    <h3 id="stat-pending">0</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <p>درحال Sync</p>
                    <h3 id="stat-syncing">0</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <p>Sync شده</p>
                    <h3 id="stat-synced">0</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <p>خطا</p>
                    <h3 id="stat-failed">0</h3>
                </div>
            </div>
        </div>

        <!-- فیلترها -->
        <div class="filter-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">جستجو</label>
                    <input type="text" class="form-control" id="search" placeholder="شماره سفارش، نام یا موبایل...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" id="filter-status">
                        <option value="">همه</option>
                        <option value="pending">در انتظار</option>
                        <option value="syncing">درحال Sync</option>
                        <option value="synced">Sync شده</option>
                        <option value="failed">خطا</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">از تاریخ</label>
                    <input type="date" class="form-control" id="filter-date-from">
                </div>
                <div class="col-md-2">
                    <label class="form-label">تا تاریخ</label>
                    <input type="date" class="form-control" id="filter-date-to">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary" onclick="loadOrders()">
                        <i class="fas fa-search"></i> جستجو
                    </button>
                    <button class="btn btn-secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> ریست
                    </button>
                    <button class="btn btn-success" onclick="syncMultiple()">
                        <i class="fas fa-sync"></i> Sync انتخاب شده
                    </button>
                </div>
            </div>
        </div>

        <!-- جدول سفارشات -->
        <div class="orders-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th>شماره</th>
                            <th>مشتری</th>
                            <th>موبایل</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت</th>
                            <th>Sync شده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody">
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                <p class="mt-2 text-muted">در حال بارگذاری...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="p-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div id="pagination-info">نمایش 0 از 0</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="pagination">
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

<?php
$content = ob_get_clean();

$extra_js = <<<'JS'

<script>
const MN_CONFIG = {
        apiUrl: '../ajax/'
    };
    
    let currentPage = 1;
    let ordersPerPage = 20;
    let selectedOrders = [];
    
    // بارگذاری سفارشات
    function loadOrders(page = 1) {
        currentPage = page;
        $('#loadingOverlay').addClass('active');
        
        const filters = {
            page: page,
            per_page: ordersPerPage,
            search: $('#search').val(),
            status: $('#filter-status').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val()
        };
        
        $.ajax({
            url: MN_CONFIG.apiUrl + 'get-orders.php',
            type: 'GET',
            data: filters,
            success: function(response) {
                if (response.success) {
                    renderOrders(response.orders);
                    renderPagination(response.pagination);
                    updateStats(response.stats);
                } else {
                    showError('خطا در بارگذاری سفارشات');
                }
            },
            error: function() {
                showError('خطا در ارتباط با سرور');
            },
            complete: function() {
                $('#loadingOverlay').removeClass('active');
            }
        });
    }
    
    // رندر سفارشات
    function renderOrders(orders) {
        const tbody = $('#orders-tbody');
        tbody.empty();
        
        if (orders.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">هیچ سفارشی یافت نشد</p>
                    </td>
                </tr>
            `);
            return;
        }
        
        orders.forEach(order => {
            const statusBadge = getStatusBadge(order.status);
            const syncBadge = order.wc_order_id ? 
                `<span class="badge badge-synced"><i class="fas fa-check"></i> #${order.wc_order_id}</span>` :
                `<span class="badge badge-pending"><i class="fas fa-clock"></i> خیر</span>`;
            
            tbody.append(`
                <tr>
                    <td>
                        <input type="checkbox" class="order-checkbox" value="${order.id}">
                    </td>
                    <td>#${order.id}</td>
                    <td>${order.customer_name}</td>
                    <td>${order.customer_phone}</td>
                    <td>${formatPrice(order.total_amount)} تومان</td>
                    <td>${statusBadge}</td>
                    <td>${formatDateTime(order.created_at)}</td>
                    <td>${syncBadge}</td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewOrder(${order.id})" title="مشاهده">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${!order.wc_order_id ? `
                            <button class="action-btn btn-edit" onclick="editOrder(${order.id})" title="ویرایش">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-sync" onclick="syncOrder(${order.id})" title="Sync">
                                <i class="fas fa-sync"></i>
                            </button>
                        ` : ''}
                        ${order.status === 'failed' ? `
                            <button class="action-btn btn-delete" onclick="deleteOrder(${order.id})" title="حذف">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `);
        });
    }
    
    // رندر صفحه‌بندی
    function renderPagination(pagination) {
        const paginationEl = $('#pagination');
        paginationEl.empty();
        
        $('#pagination-info').text(`نمایش ${pagination.from} تا ${pagination.to} از ${pagination.total}`);
        
        if (pagination.total_pages <= 1) return;
        
        // دکمه قبلی
        paginationEl.append(`
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadOrders(${pagination.current_page - 1}); return false;">قبلی</a>
            </li>
        `);
        
        // شماره صفحات
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === 1 || i === pagination.total_pages || 
                (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                paginationEl.append(`
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadOrders(${i}); return false;">${i}</a>
                    </li>
                `);
            } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                paginationEl.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }
        }
        
        // دکمه بعدی
        paginationEl.append(`
            <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadOrders(${pagination.current_page + 1}); return false;">بعدی</a>
            </li>
        `);
    }
    
    // بروزرسانی آمار
    function updateStats(stats) {
        $('#stat-pending').text(stats.pending || 0);
        $('#stat-syncing').text(stats.syncing || 0);
        $('#stat-synced').text(stats.synced || 0);
        $('#stat-failed').text(stats.failed || 0);
    }
    
    // مشاهده جزئیات سفارش
    function viewOrder(orderId) {
        window.open('order-details.php?id=' + orderId, '_blank');
    }
    
    // ویرایش سفارش
    function editOrder(orderId) {
        window.location.href = 'edit-order.php?id=' + orderId;
    }
    
    // Sync سفارش
    function syncOrder(orderId) {
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
                    data: JSON.stringify({ order_id: orderId }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('موفق!', 'سفارش با موفقیت همگام‌سازی شد', 'success');
                            loadOrders(currentPage);
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
    function deleteOrder(orderId) {
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
                    data: JSON.stringify({ order_id: orderId }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('حذف شد!', 'سفارش با موفقیت حذف شد', 'success');
                            loadOrders(currentPage);
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
    
    // Sync چند سفارش
    function syncMultiple() {
        const selected = $('.order-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selected.length === 0) {
            Swal.fire('توجه', 'لطفاً حداقل یک سفارش انتخاب کنید', 'warning');
            return;
        }
        
        Swal.fire({
            title: `همگام‌سازی ${selected.length} سفارش`,
            text: 'آیا مطمئن هستید؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'بله، Sync کن',
            cancelButtonText: 'انصراف'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#loadingOverlay').addClass('active');
                
                $.ajax({
                    url: MN_CONFIG.apiUrl + 'sync-multiple-orders.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ order_ids: selected }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('موفق!', `${response.synced_count} سفارش همگام‌سازی شد`, 'success');
                            loadOrders(currentPage);
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
    
    // ریست فیلترها
    function resetFilters() {
        $('#search').val('');
        $('#filter-status').val('');
        $('#filter-date-from').val('');
        $('#filter-date-to').val('');
        loadOrders(1);
    }
    
    // انتخاب همه
    $('#select-all').on('change', function() {
        $('.order-checkbox').prop('checked', $(this).is(':checked'));
    });
    
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
    
    function logout() {
        fetch('../ajax/logout.php', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function (res) { window.location.href = res.redirect || 'login.php'; })
            .catch(function () { window.location.href = 'login.php'; });
    } 
    
    // بارگذاری اولیه
    $(document).ready(function() {
        loadOrders();
        
        // جستجوی لحظه‌ای
        let searchTimeout;
        $('#search').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadOrders(1), 500);
        });
        
        // // بروزرسانی خودکار هر 30 ثانیه
        // setInterval(() => loadOrders(currentPage), 60000);
    });
</script>
JS;

require_once __DIR__ . '/layout.php';
?>
