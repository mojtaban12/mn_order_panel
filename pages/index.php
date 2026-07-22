<?php
/**
 * MN Order Panel - صفحه اصلی
 * پنل ثبت سفارش
 */

require_once __DIR__ . '/../includes/auth-check.php';
require_once '../config/settings.php';

$current_user = get_panel_user();
$panel_title = MN_Settings::get('panel_title', 'پنل ثبت سفارش');
$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');

$page_title = 'ثبت سفارش جدید - پنل ثبت سفارش';
$extra_css = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

<style>
.select2-container .select2-selection--single{
    height: 40px;
}
</style>


';
ob_start();

?>
    <!-- بخش جستجوی محصول -->
    <section class="search-section">
        <h2 class="section-title">
            <span class="icon">🔍</span>
            جستجوی محصول
        </h2>
        <div class="search-box">
            <select id="product-search" class="product-search-input" style="width: 100%;">
                <option value="">جستجوی محصول با نام، کد یا SKU...</option>
            </select>
        </div>
    </section>

    <!-- بخش سبد خرید -->
    <section class="cart-section">
        <div class="section-header">
            <h2 class="section-title">
                <span class="icon">🛍️</span>
                سبد خرید
                <span class="cart-count" id="cart-count">0</span>
            </h2>
            <button type="button" class="btn btn-clear" id="clear-cart" style="display: none;">
                <span class="icon">🗑️</span>
                خالی کردن
            </button>
        </div>
        
        <div id="cart-empty" class="cart-empty">
            <div class="empty-icon">🛒</div>
            <p>سبد خرید خالی است</p>
            <small>محصولات مورد نظر را جستجو کرده و اضافه کنید</small>
        </div>

        <div id="cart-items" class="cart-items" style="display: none;">
            <!-- آیتم‌ها با JavaScript اضافه می‌شن -->
        </div>

        <div id="cart-total" class="cart-total" style="display: none;">
            <!-- محتوا با JavaScript پر میشه -->
        </div>
    </section>

    <!-- بخش اطلاعات مشتری -->
    <section class="customer-section">
                <h2 class="section-title">
                    <span class="icon">👤</span>
                    اطلاعات مشتری
                </h2>
                
                <form id="order-form" class="order-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">نام <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">نام خانوادگی <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">شماره تماس <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" placeholder="09123456789" required>
                        </div>
                        <div class="form-group">
                            <label for="email">ایمیل</label>
                            <input type="email" id="email" name="email" placeholder="example@mail.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">آدرس</label>
                        <textarea id="address" name="address" rows="2" placeholder="آدرس کامل مشتری..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="state">استان</label>
                            <select id="state" name="state" class="form-control">
                                <option value="">در حال بارگذاری...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city">شهر</label>
                            <select id="city" name="city" class="form-control" disabled>
                                <option value="">ابتدا استان را انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="postcode">کد پستی</label>
                            <input type="text" id="postcode" name="postcode">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="order_notes">توضیحات سفارش</label>
                        <textarea id="order_notes" name="order_notes" rows="3" placeholder="توضیحات اضافی در مورد سفارش..."></textarea>
                    </div>

                    <!-- ── بخش حمل و نقل ───────────────────────────────── -->
                    <div class="form-group" id="shipping-section">
                        <label>
                            <span class="icon">🚚</span>
                            روش حمل و نقل
                            <span class="required">*</span>
                        </label>

                        <!-- حالت بارگذاری -->
                        <div id="shipping-loading" style="padding: 10px; color: #888; font-size: 14px;">
                            ⏳ در حال دریافت روش‌های حمل...
                        </div>

                        <!-- خطا -->
                        <div id="shipping-error" style="display:none; padding: 10px; color: #e74c3c; font-size: 14px; background: #fff5f5; border-radius: 6px; border: 1px solid #fed7d7;">
                            ⚠️ خطا در دریافت روش‌های حمل.
                            <button type="button" id="retry-shipping" style="margin-right: 8px; background: none; border: none; color: #667eea; cursor: pointer; text-decoration: underline;">تلاش مجدد</button>
                        </div>

                        <!-- لیست گزینه‌ها (با JS پر می‌شه) -->
                        <div id="shipping-options" style="display:none; display: flex; flex-direction: column; gap: 8px; margin-top: 6px;">
                            <!-- کارت‌های حمل اینجا رندر می‌شن -->
                        </div>

                        <!-- حمل دستی — اگه هیچ گزینه‌ای از WP نیومد -->
                        <div id="shipping-manual" style="display:none; margin-top: 8px;">
                            <input type="number" id="shipping-manual-cost" min="0" step="1000"
                                   placeholder="هزینه حمل را وارد کنید (تومان)"
                                   style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 14px;">
                        </div>
                    </div>
                    <!-- ── پایان بخش حمل ────────────────────────────────── -->

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-submit" id="submit-order" disabled>
                            <span class="icon">✅</span>
                            ثبت سفارش
                        </button>
                    </div>
                </form>
            </section>
 <?php
$content = ob_get_clean();

$extra_js = '

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../assets/js/state-city-selector.js"></script>

    <script>
        // تنظیمات سراسری
        window.MN_CONFIG = {
            currencySymbol: "'.$currency_symbol.'",
            ajaxUrl: "../ajax/"
        };
        
        let stateCity;
        document.addEventListener("DOMContentLoaded", function() {
            stateCity = new StateCitySelector("state", "city");
        });
    </script>
    <script src="../assets/js/main.js"></script>
';
require_once __DIR__ . '/layout.php';
?>
 