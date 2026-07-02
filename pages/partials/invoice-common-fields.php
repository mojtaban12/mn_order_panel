<?php /* فیلدهای مشترک فاکتور — در هر دو تب استفاده می‌شه */ ?>

<!-- اطلاعات فاکتور -->
<div class="form-card">
    <div class="form-card-title"><span>🧾</span> اطلاعات فاکتور</div>
    <div class="form-row">
        <div class="form-group">
            <label>شماره فاکتور</label>
            <input type="text" name="invoice_number" class="form-control" placeholder="اختیاری">
        </div>
        <div class="form-group">
            <label>تاریخ فاکتور <span class="req">*</span></label>
            <input type="hidden" name="invoice_date" class="inv-date-val" value="<?php echo date('Y-m-d'); ?>">
            <input type="text" class="form-control inv-date-display" placeholder="انتخاب تاریخ شمسی" readonly style="cursor:pointer;">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>نام فروشنده <span class="req">*</span></label>
            <input type="text" name="supplier_name" class="form-control" placeholder="نام تأمین‌کننده" required>
        </div>
        <div class="form-group">
            <label>تلفن فروشنده</label>
            <input type="tel" name="supplier_phone" class="form-control" placeholder="اختیاری">
        </div>
    </div>
</div>

<!-- جزئیات خرید -->
<div class="form-card">
    <div class="form-card-title"><span>🔢</span> جزئیات خرید</div>

    <!-- ردیف اول: تعداد + قیمت خرید + قیمت فروش -->
    <div class="form-row-3">
        <div class="form-group">
            <label>تعداد <span class="req">*</span></label>
            <input type="number" name="quantity" class="inv-qty form-control" min="1" value="1" required>
        </div>
        <div class="form-group">
            <label>قیمت خرید <span class="req">*</span></label>
            <div class="input-sfx">
                <input type="number" name="unit_price" class="inv-price form-control" min="0" placeholder="0" required>
                <span class="sfx"><?php echo $currency_symbol ?? 'تومان'; ?></span>
            </div>
        </div>
        <div class="form-group">
            <label>قیمت فروش
                <span style="font-size:10px;font-weight:400;color:#9ca3af;">اختیاری</span>
            </label>
            <div class="input-sfx">
                <input type="number" name="regular_price" class="form-control" min="0" placeholder="بدون تغییر">
                <span class="sfx"><?php echo $currency_symbol ?? 'تومان'; ?></span>
            </div>
        </div>
    </div>

    <!-- ردیف دوم: تخفیف + مالیات + حمل -->
    <div class="form-row-3">
        <div class="form-group">
            <label>تخفیف</label>
            <div class="input-sfx">
                <input type="number" name="discount" class="inv-discount form-control" min="0" value="0">
                <span class="sfx"><?php echo $currency_symbol ?? 'تومان'; ?></span>
            </div>
        </div>
        <div class="form-group">
            <label>مالیات</label>
            <div class="input-sfx">
                <input type="number" name="tax" class="inv-tax form-control" min="0" value="0">
                <span class="sfx"><?php echo $currency_symbol ?? 'تومان'; ?></span>
            </div>
        </div>
        <div class="form-group">
            <label>هزینه حمل</label>
            <div class="input-sfx">
                <input type="number" name="shipping_cost" class="inv-ship form-control" min="0" value="0">
                <span class="sfx"><?php echo $currency_symbol ?? 'تومان'; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- پرداخت -->
<div class="form-card">
    <div class="form-card-title"><span>💳</span> پرداخت</div>
    <div class="form-row">
        <div class="form-group">
            <label>روش پرداخت</label>
            <select name="payment_method" class="form-control">
                <option value="cash">نقد</option>
                <option value="card">کارت</option>
                <option value="cheque">چک</option>
                <option value="credit">اعتباری</option>
                <option value="other">سایر</option>
            </select>
        </div>
        <div class="form-group">
            <label>وضعیت پرداخت</label>
            <select name="payment_status" class="inv-pay-status form-control">
                <option value="paid">پرداخت شده</option>
                <option value="unpaid">پرداخت نشده</option>
                <option value="partial">نیم‌پرداخت</option>
            </select>
        </div>
    </div>
    <div class="form-group inv-paid-row" style="display:none;">
        <label>مبلغ پرداخت شده</label>
        <div class="input-sfx">
            <input type="number" name="paid_amount" class="inv-paid form-control" min="0" value="0">
            <span class="sfx"><?php echo $currency_symbol ?? 'تومان'; ?></span>
        </div>
    </div>
    <div class="form-group">
        <label>یادداشت</label>
        <textarea name="notes" class="form-control" rows="2" placeholder="توضیحات اضافی..."></textarea>
    </div>
</div>