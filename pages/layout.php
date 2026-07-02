<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'پنل مدیریت'; ?></title>
    
    <link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sweetalert2.css">
    <link rel="stylesheet" href="../assets/css/main.css">

    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>
                <i class="fas fa-shopping-cart"></i>
                پنل مدیریت
            </h3>
        </div>
        
        <nav class="sidebar-menu">

       <nav class="sidebar-menu">
            <!-- منو سفارشات -->
            <div class="menu-section">
                <div class="menu-section-title">سفارشات</div>
                <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>ثبت سفارش جدید</span>
                </a>
                <a href="orders-list.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders-list.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>لیست سفارشات</span>
                </a>
            </div>
            
            <!-- منو محصولات -->
            <div class="menu-section">
                <div class="menu-section-title">محصولات</div>
                <a href="products-page.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'products-page.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>لیست محصولات</span>
                </a>
                <a href="create-product.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'create-product.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>افزودن محصول</span>
                </a>
                <a href="import-products.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'import-products.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>افزودن دسته ای محصول </span>
                </a>
                <a href="panel-categories.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'panel-categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>دسته بندی ها</span>
                </a>
            </div>
            <!-- ── فروش ── -->
            <div class="menu-section">
                <div class="menu-section-title">فاکتورها</div>
                <a href="wc-sales-page.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'wc-sales-page.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>فاکتور فروش</span>
                </a>
                <a href="invoices.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>فاکتور خرید</span>
                </a>
                <a href="create-invoice.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'create-invoice.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>ثبت فاکتور خرید</span>
                </a>
            </div>

            <!-- ── سیستم ── -->
            <div class="menu-section">
                <div class="menu-section-title">سیستم</div>
                <a href="scheduler-settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'scheduler-settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    <span>زمان‌بندی خودکار</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>خروج</span>
                </a>
            </div>

        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?php echo $page_title ?? 'پنل مدیریت'; ?></h1>
            </div>
            
            <div class="top-actions">
                <?php if (isset($top_actions)): ?>
                    <?php echo $top_actions; ?>
                <?php endif; ?>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?php 
                        $user = get_panel_user();
                        echo mb_substr($user['name'] ?? 'A', 0, 1); 
                        ?>
                    </div>
                    <span class="user-name"><?php echo $user['name'] ?? 'ادمین'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <?php echo $content ?? ''; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.js"></script>
    <script src="../assets/js/mn-category-picker.js"></script>
    
    <script>
        function toggleSidebar() {
            $('#sidebar').toggleClass('active');
        }
        
        $(document).on('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!$(e.target).closest('.sidebar, .mobile-toggle').length) {
                    $('#sidebar').removeClass('active');
                }
            }
        });
    </script>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>

    <?php
    // ── Scheduler tick ───────────────────────
    // فقط برای صفحات HTML (نه ajax)
    if (!defined('DOING_AJAX')) {
        try {
            require_once __DIR__ . '/../includes/class-scheduler.php';
            $scheduler = MN_Scheduler::get_instance();
            // ثبت jobها (اگر هنوز نیستن)
            $scheduler->register('wc_sales_sync', 1800); // هر 30 دقیقه
            // چک و اجرا در پس‌زمینه
            $scheduler->tick();
        } catch (Exception $e) {
            error_log('Scheduler error in layout: ' . $e->getMessage());
        }
    }
    // ─────────────────────────────────────────
    ?>

</body>
</html>