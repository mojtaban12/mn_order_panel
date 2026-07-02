<?php
/**
 * MN Order Panel - Edit Order
 * ویرایش سفارش با محاسبه حمل و نقل
 */

require_once __DIR__ . '/../includes/auth-check.php';

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
    <title>ویرایش سفارش #<?php echo $order_id; ?></title>
    
    <link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sweetalert2.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    
    <style>
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-total {
            font-size: 1.1rem;
            font-weight: bold;
            color: #667eea;
            border-top: 2px solid #ddd;
            padding-top: 10px !important;
            margin-top: 10px;
        }
    </style>
</head>
<body class="orders-list">
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">در حال بارگذاری...</span>
        </div>
    </div>

    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="order-details.php?id=<?php echo $order_id; ?>">
                <i class="fas fa-arrow-right"></i> بازگشت به جزئیات سفارش
            </a>
            <div class="d-flex gap-2">
                <button class="btn btn-success" onclick="saveOrder()">
                    <i class="fas fa-save"></i> ذخیره تغییرات
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <!-- جستجو -->
                <div class="edit-card">
                    <h5><i class="fas fa-search"></i> افزودن محصول</h5>
                    <div class="product-search-box">
                        <input type="text" 
                               class="form-control form-control-lg" 
                               id="product-search" 
                               placeholder="جستجوی محصول (نام، SKU، شناسه)..."
                               autocomplete="off">
                        <div class="search-results" id="search-results"></div>
                    </div>
                </div>
                
                <!-- محصولات -->
                <div class="edit-card">
                    <h5><i class="fas fa-shopping-cart"></i> محصولات سفارش</h5>
                    <div id="cart-items">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="mt-2 text-muted">در حال بارگذاری...</p>
                        </div>
                    </div>
                </div>
                
                <!-- یادداشت -->
                <div class="edit-card">
                    <h5><i class="fas fa-sticky-note"></i> یادداشت سفارش</h5>
                    <textarea class="form-control" 
                              id="order-notes" 
                              rows="4" 
                              placeholder="یادداشت‌های اضافی..."></textarea>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- مشتری -->
                <div class="edit-card">
                    <h5><i class="fas fa-user"></i> اطلاعات مشتری</h5>
                    <div id="customer-form">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <!-- خلاصه -->
                <div class="edit-card">
                    <h5><i class="fas fa-calculator"></i> خلاصه سفارش</h5>
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>تعداد اقلام:</span>
                            <span id="summary-items">0</span>
                        </div>
                        <div class="summary-row">
                            <span>جمع جزء:</span>
                            <span id="summary-subtotal">0 تومان</span>
                        </div>
                        <div class="summary-row text-success">
                            <span>تخفیف:</span>
                            <span id="summary-discount">0 تومان</span>
                        </div>
                        <div class="summary-row">
                            <span>حمل و نقل:</span>
                            <span id="summary-shipping">0 تومان</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>جمع کل:</span>
                            <span id="summary-total">0 تومان</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.js"></script>
    
    <script>
        const MN_CONFIG = {
            apiUrl: '../ajax/',
            orderId: <?php echo $order_id; ?>
        };
        
        const SHIPPING_CONFIG = {
            BASE_COST: 99000,           // هزینه ثابت
            PER_KG_COST: 18000,         // به ازای هر کیلو
            FREE_SHIPPING_THRESHOLD: 1000000  // بالای 1 میلیون رایگان
        };
        
        let cart = [];
        let customer = null;
        let currentWpUserId = null;
        let searchTimeout = null;
        
        // بارگذاری اطلاعات
        function loadOrderData() {
            $('#loadingOverlay').addClass('active');
            
            $.ajax({
                url: MN_CONFIG.apiUrl + 'get-order-details.php',
                type: 'GET',
                data: { id: MN_CONFIG.orderId },
                success: function(response) {
                    if (response.success) {
                        const order = response.order;
                        
                        if (order.wc_order_id) {
                            Swal.fire({
                                title: 'خطا',
                                text: 'سفارش همگام‌سازی شده قابل ویرایش نیست',
                                icon: 'error'
                            }).then(() => {
                                window.location.href = 'order-details.php?id=' + MN_CONFIG.orderId;
                            });
                            return;
                        }
                        
                        customer = order.customer;
                        currentWpUserId = customer.wp_user_id;
                        renderCustomerForm();
                        
                        cart = order.items.map(item => ({
                            id: item.product_id,
                            name: item.product_name,
                            sku: item.product_sku,
                            quantity: item.quantity,
                            price: parseFloat(item.price),
                            original_price: parseFloat(item.price),
                            weight: parseFloat(item.weight) || 0,
                            total: parseFloat(item.total),
                            stock: 999
                        }));
                        
                        $('#order-notes').val(order.order_notes || '');
                        
                        updateCartUI();
                        recalculateAllPrices();
                    } else {
                        showError(response.message);
                    }
                },
                error: function() {
                    showError('خطا در بارگذاری اطلاعات');
                },
                complete: function() {
                    $('#loadingOverlay').removeClass('active');
                }
            });
        }
        
        // رندر فرم مشتری
        function renderCustomerForm() {
            $('#customer-form').html(`
                <div class="mb-3">
                    <label class="form-label">نام</label>
                    <input type="text" class="form-control" id="first_name" value="${customer.first_name || ''}">
                </div>
                <div class="mb-3">
                    <label class="form-label">نام خانوادگی</label>
                    <input type="text" class="form-control" id="last_name" value="${customer.last_name || ''}">
                </div>
                <div class="mb-3">
                    <label class="form-label">موبایل</label>
                    <input type="text" class="form-control" value="${customer.phone}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">ایمیل</label>
                    <input type="email" class="form-control" id="email" value="${customer.email || ''}">
                </div>
                <div class="mb-3">
                    <label class="form-label">آدرس</label>
                    <textarea class="form-control" id="address" rows="2">${customer.address || ''}</textarea>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label">شهر</label>
                        <input type="text" class="form-control" id="city" value="${customer.city || ''}">
                    </div>
                    <div class="col-6">
                        <label class="form-label">استان</label>
                        <input type="text" class="form-control" id="state" value="${customer.state || ''}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">کد پستی</label>
                    <input type="text" class="form-control" id="postcode" value="${customer.postcode || ''}">
                </div>
            `);
        }
        
        // جستجو
        $('#product-search').on('keyup', function() {
            const query = $(this).val().trim();
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                $('#search-results').removeClass('active').empty();
                return;
            }
            
            searchTimeout = setTimeout(() => searchProducts(query), 300);
        });
        
        function searchProducts(query) {
            $.ajax({
                url: MN_CONFIG.apiUrl + 'product-search.php',
                type: 'GET',
                data: { q: query },
                success: function(response) {
                    if (response.success && response.items.length > 0) {
                        renderSearchResults(response.items);
                    } else {
                        $('#search-results').html('<div class="p-3 text-muted text-center">محصولی یافت نشد</div>').addClass('active');
                    }
                }
            });
        }
        
        function renderSearchResults(products) {
            let html = '';
            products.forEach(product => {
                const exists = cart.find(item => item.id === product.id);
                const disabled = exists ? 'opacity-50' : '';
                
                html += `
                    <div class="search-result-item ${disabled}" onclick="addProductToCart(${product.id}, '${product.text.replace(/'/g, "\\'")}', '${product.sku}', ${product.price}, ${product.weight || 0}, ${product.stock_quantity || 999})">
                        <div>${product.text}</div>
                        <div style="font-size: 12px; color: #999;">
                            ${product.sku ? `SKU: ${product.sku} | ` : ''}
                            قیمت: ${formatPrice(product.price)} تومان
                            ${exists ? ' | <strong>قبلاً اضافه شده</strong>' : ''}
                        </div>
                    </div>
                `;
            });
            $('#search-results').html(html).addClass('active');
        }
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.product-search-box').length) {
                $('#search-results').removeClass('active');
            }
        });
        
        // افزودن محصول
        function addProductToCart(id, name, sku, price, weight, stock) {
            if (cart.find(item => item.id === id)) {
                showError('این محصول قبلاً اضافه شده');
                return;
            }
            
            const item = {
                id, name, sku,
                quantity: 1,
                price, 
                original_price: price,
                weight: parseFloat(weight) || 0,
                total: price,
                stock
            };
            
            cart.push(item);
            calculateTieredPrice(item, true);
            
            $('#product-search').val('');
            $('#search-results').removeClass('active').empty();
        }
        
        // محاسبه قیمت پله‌ای
        function calculateTieredPrice(item, updateUI = false) {
            $.ajax({
                url: MN_CONFIG.apiUrl + 'calculate-tiered-price.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    product_id: item.id,
                    quantity: item.quantity,
                    base_price: item.original_price,
                    wp_user_id: currentWpUserId,
                    force_calculation: !currentWpUserId
                }),
                success: function(response) {
                    if (response.success && response.pricing) {
                        item.price = response.pricing.final_price;
                        item.discount_percent = response.pricing.discount_percent;
                        item.discount_amount = response.pricing.discount_amount;
                        item.total = response.pricing.final_price * item.quantity;
                        
                        if (updateUI) updateCartUI();
                    }
                }
            });
        }
        
        // محاسبه مجدد همه
        function recalculateAllPrices() {
            if (cart.length === 0) return;
            
            let completed = 0;
            cart.forEach(item => {
                calculateTieredPriceSync(item).then(() => {
                    completed++;
                    if (completed === cart.length) updateCartUI();
                });
            });
        }
        
        function calculateTieredPriceSync(item) {
            return new Promise(resolve => {
                $.ajax({
                    url: MN_CONFIG.apiUrl + 'calculate-tiered-price.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        product_id: item.id,
                        quantity: item.quantity,
                        base_price: item.original_price,
                        wp_user_id: currentWpUserId,
                        force_calculation: !currentWpUserId
                    }),
                    success: function(response) {
                        if (response.success && response.pricing) {
                            item.price = response.pricing.final_price;
                            item.discount_percent = response.pricing.discount_percent;
                            item.total = response.pricing.final_price * item.quantity;
                        }
                        resolve();
                    },
                    error: () => resolve()
                });
            });
        }
        
        // رندر سبد
        function updateCartUI() {
            const cartEl = $('#cart-items');
            cartEl.empty();
            
            if (cart.length === 0) {
                cartEl.html('<div class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">سبد خرید خالی است</p></div>');
                updateSummary();
                return;
            }
            
            cart.forEach((item, index) => {
                const hasDiscount = item.discount_percent && item.discount_percent > 0;
                
                cartEl.append(`
                    <div class="cart-item">
                        <div class="cart-item-header">
                            <div>
                                <div style="font-weight: bold;">${item.name}</div>
                                ${item.sku ? `<div style="font-size: 12px; color: #999;">SKU: ${item.sku}</div>` : ''}
                                ${hasDiscount ? `<span class="badge bg-danger">${item.discount_percent}% تخفیف</span>` : ''}
                            </div>
                            <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <label>تعداد:</label>
                            <input type="number" 
                                   class="form-control form-control-sm d-inline-block" 
                                   style="width: 80px;"
                                   value="${item.quantity}" 
                                   min="1" 
                                   max="${item.stock}"
                                   onchange="updateQuantity(${index}, this.value)">
                            <div class="mt-1">
                                ${hasDiscount ? `<span style="text-decoration: line-through; color: #999;">${formatPrice(item.original_price)}</span> ` : ''}
                                <strong>${formatPrice(item.price)} تومان</strong>
                                <div style="font-size: 14px; color: #667eea;">جمع: ${formatPrice(item.total)} تومان</div>
                            </div>
                        </div>
                    </div>
                `);
            });
            
            updateSummary();
        }
        
        // تغییر تعداد
        function updateQuantity(index, newQty) {
            newQty = parseInt(newQty) || 1;
            if (newQty < 1) newQty = 1;
            if (newQty > cart[index].stock) newQty = cart[index].stock;
            
            cart[index].quantity = newQty;
            calculateTieredPrice(cart[index], true);
        }
        
        // حذف
        function removeFromCart(index) {
            Swal.fire({
                title: 'حذف محصول',
                text: 'آیا مطمئن هستید؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'بله',
                cancelButtonText: 'خیر'
            }).then(result => {
                if (result.isConfirmed) {
                    cart.splice(index, 1);
                    updateCartUI();
                }
            });
        }
        
        // بروزرسانی خلاصه با حمل و نقل
        function updateSummary() {
            let subtotal = 0, totalDiscount = 0, totalWeight = 0;
            
            cart.forEach(item => {
                subtotal += item.total;
                if (item.discount_amount) totalDiscount += item.discount_amount * item.quantity;
                totalWeight += (parseFloat(item.weight) || 0) * item.quantity;
            });
            
            // محاسبه حمل و نقل
            let shippingCost = 0, shippingText = '';
            
            if (subtotal >= SHIPPING_CONFIG.FREE_SHIPPING_THRESHOLD) {
                shippingText = '<span style="color: #10b981;">رایگان 🎉</span>';
            } else {
                shippingCost = SHIPPING_CONFIG.BASE_COST + (totalWeight * SHIPPING_CONFIG.PER_KG_COST);
                shippingText = formatPrice(Math.round(shippingCost)) + ' تومان';
            }
            
            const total = subtotal + shippingCost;
            
            $('#summary-items').text(cart.length);
            $('#summary-subtotal').text(formatPrice(subtotal + totalDiscount) + ' تومان');
            $('#summary-discount').text(formatPrice(totalDiscount) + ' تومان');
            $('#summary-shipping').html(shippingText);
            $('#summary-total').text(formatPrice(Math.round(total)) + ' تومان');
        }
        
        // ذخیره
        function saveOrder() {
            if (cart.length === 0) {
                showError('سبد خرید خالی است');
                return;
            }
            
            // محاسبه حمل و نقل
            let subtotal = 0, totalWeight = 0;
            cart.forEach(item => {
                subtotal += item.total;
                totalWeight += (parseFloat(item.weight) || 0) * item.quantity;
            });
            
            let shippingCost = 0;
            if (subtotal < SHIPPING_CONFIG.FREE_SHIPPING_THRESHOLD) {
                shippingCost = SHIPPING_CONFIG.BASE_COST + (totalWeight * SHIPPING_CONFIG.PER_KG_COST);
            }
            
            const orderData = {
                order_id: MN_CONFIG.orderId,
                customer: {
                    first_name: $('#first_name').val(),
                    last_name: $('#last_name').val(),
                    email: $('#email').val(),
                    address: $('#address').val(),
                    city: $('#city').val(),
                    state: $('#state').val(),
                    postcode: $('#postcode').val()
                },
                items: cart.map(item => ({
                    product_id: item.id,
                    product_name: item.name,
                    product_sku: item.sku,
                    quantity: item.quantity,
                    price: item.price,
                    weight: item.weight,
                    total: item.total
                })),
                shipping: {
                    cost: Math.round(shippingCost),
                    weight: totalWeight,
                    is_free: shippingCost === 0
                },
                order_notes: $('#order-notes').val()
            };
            
            $('#loadingOverlay').addClass('active');
            
            $.ajax({
                url: MN_CONFIG.apiUrl + 'update-order.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(orderData),
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'موفق!',
                            text: 'سفارش بروزرسانی شد',
                            timer: 1500
                        }).then(() => {
                            window.location.href = 'order-details.php?id=' + MN_CONFIG.orderId;
                        });
                    } else {
                        showError(response.message);
                    }
                },
                error: () => showError('خطا در ذخیره'),
                complete: () => $('#loadingOverlay').removeClass('active')
            });
        }
        
        function formatPrice(price) {
            return parseFloat(price).toLocaleString('fa-IR');
        }
        
        function showError(msg) {
            Swal.fire('خطا', msg, 'error');
        }
        
        $(document).ready(() => loadOrderData());
    </script>
</body>
</html>