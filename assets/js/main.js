/**
 * MN Order Panel - Main JavaScript با محاسبه حمل و نقل
 */

(function($) {
    'use strict';

    let cart = [];
    let currentUserRoles = [];
    let currentWpUserId = null;
    
    // تنظیمات حمل و نقل
    const SHIPPING_CONFIG = {
        BASE_COST: 99000,           // هزینه ثابت
        PER_KG_COST: 18000,         // هزینه به ازای هر کیلو
        FREE_SHIPPING_THRESHOLD: 1000000  // بالای 1 میلیون رایگان
    };

    $('#product-search').select2({
        ajax: {
            url: MN_CONFIG.ajaxUrl + 'product-search.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    page: params.page || 1
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                
                if (!data.success) {
                    return { results: [] };
                }
                
                return {
                    results: data.items,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: '🔍 جستجوی محصول با نام، کد یا SKU...',
        dir: 'rtl',
        language: {
            inputTooShort: function() {
                return 'حداقل 2 کاراکتر وارد کنید';
            },
            searching: function() {
                return 'در حال جستجو...';
            },
            noResults: function() {
                return 'محصولی یافت نشد';
            },
            loadingMore: function() {
                return 'بارگذاری بیشتر...';
            }
        },
        templateResult: formatProduct,
        templateSelection: formatProductSelection
    });

    function formatProduct(product) {
        if (product.loading) {
            return product.text;
        }

        let stockClass = product.in_stock ? 'stock-in' : 'stock-out';
        
        return $(`
            <div class="product-result" style="display: flex; gap: 10px; padding: 5px;">
                <img src="https://puonak.com/wp-content/uploads/${product.image}" style="width: 50px; height: 50px; border-radius: 5px; object-fit: cover;">
                <div style="flex: 1;">
                    <div style="font-weight: bold;">${product.text}</div>
                    <div style="font-size: 12px; color: #999;">SKU: ${product.sku}</div>
                    <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                        <span style="color: #667eea; font-weight: bold;">${product.price_formatted} ${MN_CONFIG.currencySymbol}</span>
                        <span class="${stockClass}" style="font-size: 12px;">${product.stock_text}</span>
                    </div>
                </div>
            </div>
        `);
    }

    function formatProductSelection(product) {
        return product.text;
    }

    $('#product-search').on('select2:select', function(e) {
        const product = e.params.data;
        
        if (!product.in_stock) {
            Swal.fire({
                icon: 'warning',
                title: 'محصول ناموجود',
                text: 'این محصول موجود نیست',
                confirmButtonText: 'متوجه شدم'
            });
            return;
        }

        addToCart(product);
        $(this).val(null).trigger('change');
    });

    function addToCart(product) {
        const existingIndex = cart.findIndex(item => item.id === product.id);
        
        if (existingIndex !== -1) {
            cart[existingIndex].quantity++;
            calculateTieredPrice(cart[existingIndex], true);
        } else {
            const newItem = {
                id: product.id,
                name: product.text,
                sku: product.sku,
                price: product.price,
                original_price: product.price,
                price_formatted: product.price_formatted,
                quantity: 1,
                weight: parseFloat(product.weight) || 0,
                image: "https://puonak.com/wp-content/uploads/" + product.image
            };
            
            cart.push(newItem);
            calculateTieredPrice(newItem, true);
        }
        
        updateCartUI();
        
        Swal.fire({
            icon: 'success',
            title: 'اضافه شد',
            text: `${product.text} به سبد خرید اضافه شد`,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    // محاسبه حمل و نقل
    function calculateShipping() {
        const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
        
        // رایگان اگه بالای 1 میلیون باشه
        if (subtotal >= SHIPPING_CONFIG.FREE_SHIPPING_THRESHOLD) {
            return {
                cost: 0,
                is_free: true,
                weight: 0
            };
        }
        
        // محاسبه وزن کل
        const totalWeight = cart.reduce((sum, item) => {
            const itemWeightGrams = parseFloat(item.weight) || 0;
            const itemWeightKg = itemWeightGrams / 1000; // تبدیل به کیلو
            return sum + (itemWeightKg * item.quantity);
        }, 0);
        
        // محاسبه هزینه
        const shippingCost = SHIPPING_CONFIG.BASE_COST + (totalWeight * SHIPPING_CONFIG.PER_KG_COST);
        
        return {
            cost: Math.round(shippingCost),
            is_free: false,
            weight: totalWeight
        };
    }

    function updateCartUI() {
        const $cartEmpty = $('#cart-empty');
        const $cartItems = $('#cart-items');
        const $cartTotal = $('#cart-total');
        const $clearBtn = $('#clear-cart');
        const $submitBtn = $('#submit-order');
        const $cartCount = $('#cart-count');

        if (cart.length === 0) {
            $cartEmpty.show();
            $cartItems.hide();
            $cartTotal.hide();
            $clearBtn.hide();
            $submitBtn.prop('disabled', true);
            $cartCount.text('0');
        } else {
            $cartEmpty.hide();
            $cartItems.show();
            $cartTotal.show();
            $clearBtn.show();
            $submitBtn.prop('disabled', false);
            $cartCount.text(cart.length);

            renderCartItems();
            updateTotal();
        }
    }

    function renderCartItems() {
        const $cartItems = $('#cart-items');
        $cartItems.empty();

        cart.forEach(item => {
            const itemTotal = item.quantity * item.price;
            const itemTotalFormatted = numberFormat(itemTotal);
            
            let discountBadge = '';
            if (item.discount_percent && item.discount_percent > 0) {
                discountBadge = `<span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 5px;">${item.discount_percent}% تخفیف</span>`;
            }
            
            let priceDisplay = `${item.price_formatted || numberFormat(item.price)} ${MN_CONFIG.currencySymbol}`;
            if (item.original_price && item.price < item.original_price) {
                priceDisplay = `
                    <span style="text-decoration: line-through; color: #999; font-size: 12px;">${numberFormat(item.original_price)}</span>
                    <span style="color: #e74c3c; font-weight: bold;">${numberFormat(item.price)} ${MN_CONFIG.currencySymbol}</span>
                `;
            }

            const $item = $(`
                <div class="cart-item" data-id="${item.id}">
                    <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${item.name} ${discountBadge}</div>
                        <div class="cart-item-sku">SKU: ${item.sku}</div>
                        <div class="cart-item-price">${priceDisplay}</div>
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-controls">
                            <button type="button" class="btn-qty btn-decrease">−</button>
                            <input type="number" class="qty-input" value="${item.quantity}" min="1" />
                            <button type="button" class="btn-qty btn-increase">+</button>
                        </div>
                        <button type="button" class="btn-remove">🗑️</button>
                    </div>
                    <div class="cart-item-total">
                        ${itemTotalFormatted} ${MN_CONFIG.currencySymbol}
                    </div>
                </div>
            `);

            $cartItems.append($item);
        });
    }

    function updateTotal() {
        const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
        const shipping = calculateShipping();
        const total = subtotal + shipping.cost;
        
        let shippingHtml = '';
        if (shipping.is_free) {
            shippingHtml = '<span style="color: #10b981;">رایگان 🎉</span>';
        } else {
            shippingHtml = `${numberFormat(shipping.cost)} ${MN_CONFIG.currencySymbol}`;
        }
        
        $('#cart-total').html(`
            <div class="total-row">
                <span>جمع محصولات:</span>
                <span>${numberFormat(subtotal)} ${MN_CONFIG.currencySymbol}</span>
            </div>
            <div class="total-row">
                <span>حمل و نقل:</span>
                <span>${shippingHtml}</span>
            </div>
            <div class="total-row" style="border-top: 2px solid #e2e8f0; padding-top: 10px; margin-top: 10px;">
                <span style="font-weight: bold;">جمع کل:</span>
                <strong id="total-amount" style="color: #667eea;">${numberFormat(total)}</strong>
                <span>${MN_CONFIG.currencySymbol}</span>
            </div>
        `);
    }

    function calculateTieredPrice(item, forceWithRole) {
        forceWithRole = forceWithRole !== false;
        const requestData = {
            product_id: item.id,
            quantity: item.quantity,
            base_price: item.original_price || item.price,
            wp_user_id: forceWithRole ? currentWpUserId : null,
            force_calculation: !forceWithRole
        };
        
        $.ajax({
            url: MN_CONFIG.ajaxUrl + 'calculate-tiered-price.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(requestData),
            success: function(response) {
                if (response.success && response.pricing) {
                    const pricing = response.pricing;
                    
                    if (!item.original_price) {
                        item.original_price = item.price;
                    }
                    
                    item.price = pricing.final_price;
                    item.discount_percent = pricing.discount_percent;
                    item.discount_amount = pricing.discount_amount;
                    item.rule_applied = pricing.rule_applied;
                }
                updateCartUI();
            },
            error: function() {
                updateCartUI();
            }
        });
    }

    $(document).on('blur', '.qty-input', function() {
        const productId = $(this).closest('.cart-item').data('id');
        const item = cart.find(i => i.id === productId);
        if (item) {
            item.quantity = parseInt($(this).val()) || 1;
            calculateTieredPrice(item, true);
        }
    });
    
    $(document).on('click', '.btn-increase', function() {
        const productId = $(this).closest('.cart-item').data('id');
        const item = cart.find(i => i.id === productId);
        if (item) {
            item.quantity++;
            calculateTieredPrice(item, true);
        }
    });

    $(document).on('click', '.btn-decrease', function() {
        const productId = $(this).closest('.cart-item').data('id');
        const item = cart.find(i => i.id === productId);
        if (item && item.quantity > 1) {
            item.quantity--;
            calculateTieredPrice(item, true);
        }
    });

    $(document).on('click', '.btn-remove', function() {
        const productId = $(this).closest('.cart-item').data('id');
        
        Swal.fire({
            title: 'حذف محصول؟',
            text: 'آیا مطمئن هستید؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'بله، حذف شود',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#e74c3c'
        }).then((result) => {
            if (result.isConfirmed) {
                cart = cart.filter(i => i.id !== productId);
                updateCartUI();
                
                Swal.fire({
                    icon: 'success',
                    title: 'حذف شد',
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    });

    $('#clear-cart').on('click', function() {
        Swal.fire({
            title: 'خالی کردن سبد؟',
            text: 'همه محصولات حذف خواهند شد',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'بله، خالی شود',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#e74c3c'
        }).then((result) => {
            if (result.isConfirmed) {
                cart = [];
                updateCartUI();
                
                Swal.fire({
                    icon: 'success',
                    title: 'سبد خالی شد',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    });

    $('#order-form').on('submit', function(e) {
        e.preventDefault();

        if (cart.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'سبد خالی است',
                text: 'لطفا محصولی به سبد اضافه کنید'
            });
            return;
        }

        const selectedState = stateCity ? stateCity.getSelectedState() : null;
        const selectedCity = stateCity ? stateCity.getSelectedCity() : null;
        const shipping = calculateShipping();

        const formData = {
            customer: {
                first_name: $('#first_name').val().trim(),
                last_name: $('#last_name').val().trim(),
                phone: $('#phone').val().trim(),
                email: $('#email').val().trim(),
                address: $('#address').val().trim(),
                city: selectedCity ? selectedCity.name : $('#city').val().trim(),
                state: selectedState ? selectedState.name : $('#state').val().trim(),
                postcode: $('#postcode').val().trim()
            },
            items: cart.map(item => ({
                product_id: item.id,
                product_name: item.name,
                product_sku: item.sku,
                quantity: item.quantity,
                price: item.price,
                weight: item.weight || 0
            })),
            shipping: {
                cost: shipping.cost,
                weight: shipping.weight,
                is_free: shipping.is_free
            },
            order_notes: $('#order_notes').val().trim()
        };

        const $submitBtn = $('#submit-order');
        $submitBtn.addClass('loading').prop('disabled', true);

        $.ajax({
            url: MN_CONFIG.ajaxUrl + 'create-order.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'سفارش ثبت شد',
                        html: `
                            <p>${response.message}</p>
                            <p><strong>شماره سفارش:</strong> ${response.order.id}</p>
                            <p><strong>مشتری:</strong> ${response.order.customer_name}</p>
                            <p><strong>مبلغ:</strong> ${response.order.total_formatted} ${MN_CONFIG.currencySymbol}</p>
                        `,
                        confirmButtonText: 'سفارش جدید'
                    }).then(() => {
                        resetForm();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: response.message || 'خطا در ثبت سفارش'
                    });
                }
            },
            error: function(xhr) {
                let errorMsg = 'خطا در ارتباط با سرور';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch (e) {}
                
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: errorMsg
                });
            },
            complete: function() {
                $submitBtn.removeClass('loading').prop('disabled', false);
            }
        });
    });

    function resetForm() {
        $('#order-form')[0].reset();
        cart = [];
        currentWpUserId = null;
        currentUserRoles = null;
        if (stateCity) {
            stateCity.clearCities();
        }
        updateCartUI();
    }

    function numberFormat(number) {
        return new Intl.NumberFormat('fa-IR').format(number);
    }

    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (!$('#submit-order').prop('disabled')) {
                $('#order-form').submit();
            }
        }
    });

    setTimeout(function() {
        $('#product-search').select2('open');
    }, 500);

    let phoneCheckTimeout;
    
    $('#phone').on('input', function() {
        clearTimeout(phoneCheckTimeout);
        
        const phone = $(this).val().trim();
        
        if (phone.length < 10) {
            return;
        }
        
        phoneCheckTimeout = setTimeout(function() {
            checkCustomerByPhone(phone);
        }, 800);
    });
    
    function checkCustomerByPhone(phone) {
        $.ajax({
            url: MN_CONFIG.ajaxUrl + 'check-customer.php',
            type: 'GET',
            data: { phone: phone },
            success: function(response) {
                if (response.success && response.customer) {
                    const customer = response.customer;
                    
                    if (customer.exists) {
                        if (customer.first_name) $('#first_name').val(customer.first_name);
                        if (customer.last_name) $('#last_name').val(customer.last_name);
                        if (customer.email) $('#email').val(customer.email);
                        if (customer.address) $('#address').val(customer.address);
                        if (customer.postcode) $('#postcode').val(customer.postcode);
                        
                        if (customer.state && stateCity) {
                            const stateName = customer.state;
                            const cityName = customer.city;
                            
                            const stateOption = $('#state option').filter(function() {
                                return $(this).text() === stateName;
                            });
                            
                            if (stateOption.length > 0) {
                                const stateId = stateOption.val();
                                stateCity.setStateValue(stateId);
                                
                                if (cityName) {
                                    setTimeout(() => {
                                        const cityOption = $('#city option').filter(function() {
                                            return $(this).text() === cityName;
                                        });
                                        if (cityOption.length > 0) {
                                            stateCity.setCityValue(cityOption.val());
                                        }
                                    }, 1000);
                                }
                            }
                        }
                        
                        if (customer.wp_user_id) {
                            currentWpUserId = customer.wp_user_id;
                            currentUserRoles = customer.user_roles || [];
                            recalculateCartPrices();
                        }
                        
                        let message = '✓ مشتری شناسایی شد';
                        if (customer.total_orders) {
                            message += ` (${customer.total_orders} سفارش قبلی)`;
                        }
                        if (customer.user_roles && customer.user_roles.length > 0) {
                            message += ` - نقش: ${customer.user_roles.join(', ')}`;
                        }
                        
                        Swal.fire({
                            icon: 'success',
                            title: message,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                }
            },
            error: function() {
            }
        });
    }
    
    function recalculateCartPrices() {
        cart.forEach(item => {
            calculateTieredPrice(item, true);
        });
    }

})(jQuery);