<?php /* سایدبار مشترک — خلاصه + دکمه ثبت */ ?>
<div>
    <div class="form-card">
        <div class="form-card-title"><span>🧮</span> خلاصه فاکتور</div>
        <div class="summary-box">
            <div class="s-row"><span>مبلغ کل:</span><span class="s-total">—</span></div>
            <div class="s-row"><span>تخفیف:</span><span class="s-discount" style="color:#dc2626;">—</span></div>
            <div class="s-row"><span>مالیات:</span><span class="s-tax">—</span></div>
            <div class="s-row"><span>حمل:</span><span class="s-ship">—</span></div>
            <div class="s-row total"><span>مبلغ نهایی:</span><span class="s-final">—</span></div>
        </div>
        <div style="margin-top:14px;padding:12px;background:#fffbeb;border-radius:7px;font-size:12px;color:#92400e;border:1px solid #fde68a;">
            📊 پس از ثبت، <strong>موجودی واقعی</strong> افزایش و <strong>قیمت خرید</strong> آپدیت می‌شود.
        </div>
        <button type="submit" class="btn-submit" id="btn-submit">
            <span>💾</span><span id="btn-text">ثبت فاکتور</span>
        </button>
        <div style="margin-top:10px;text-align:center;">
            <a href="invoices.php" style="font-size:12px;color:#6b7280;text-decoration:none;">← بازگشت به لیست</a>
        </div>
    </div>
</div>