(function () {

    // ════════════════════════════════════════
    // Tab switching
    // ════════════════════════════════════════
    window.switchTab = function (mode, btn) {
        document.getElementById('mode').value = mode;
        document.querySelectorAll('.inv-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.inv-tab-pane').forEach(function (p) { p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('pane-' + mode).classList.add('active');
        initDatepickers();
    };

    // ════════════════════════════════════════
    // Shamsi Datepicker — روی همه .inv-date-display
    // ════════════════════════════════════════
    function initDatepickers() {
        document.querySelectorAll('.inv-date-display:not(.dpk-init)').forEach(function (el) {
            el.classList.add('dpk-init');

            var hiddenEl = el.closest('.form-group').querySelector('.inv-date-val');

            // مقدار اولیه شمسی
            var today = new persianDate();
            el.value = today.year() + '/' + pad(today.month()) + '/' + pad(today.date());

            $(el).persianDatepicker({
                format:        'YYYY/MM/DD',
                initialValue:  true,
                autoClose:     true,
                calendarType:  'persian',
                calendar:      { persian: { locale: 'fa' } },
                onSelect: function (unix) {
                    var pd = new persianDate(unix);
                    var gd = pd.toCalendar('gregorian');
                    hiddenEl.value = gd.year() + '-' + pad(gd.month()) + '-' + pad(gd.date());
                }
            });
        });
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    // ════════════════════════════════════════
    // Panel categories dropdown
    // ════════════════════════════════════════
    function loadPCats() {
        fetch(AJAX + 'panel-categories.php?action=list')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var sel = document.getElementById('pcat-new');
                if (!sel) return;
                res.categories.forEach(function (c) {
                    var o = document.createElement('option');
                    o.value       = c.id;
                    o.textContent = (c.parent_name ? c.parent_name + ' / ' : '') + c.name;
                    sel.appendChild(o);
                });
            });
    }

    // ════════════════════════════════════════
    // Product search (تب موجود)
    // ════════════════════════════════════════
    var prodInput  = document.getElementById('prod-search');
    var prodDd     = document.getElementById('prod-dd');
    var prodBadge  = document.getElementById('prod-badge');
    var prodHidden = document.getElementById('product_id');
    var prodTimer  = null;

    if (prodInput) {
        prodInput.addEventListener('input', function () {
            var q = this.value.trim();
            clearTimeout(prodTimer);
            closeProdDd();
            prodHidden.value = '';
            prodBadge.classList.remove('show');
            if (q.length < 2) return;

            prodTimer = setTimeout(function () {
                fetch(AJAX + 'get-products.php?search=' + encodeURIComponent(q) + '&per_page=10&page=1')
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.success || !res.products || !res.products.length) {
                            prodDd.innerHTML = '<div style="padding:12px 14px;color:#9ca3af;font-size:13px;">محصولی یافت نشد</div>';
                            prodDd.classList.add('open');
                            return;
                        }
                        prodDd.innerHTML = res.products.map(function (p) {
                            return '<div class="prod-dd-item" data-id="' + p.id + '" data-title="' + esc(p.title) + '" data-stock="' + (p.real_stock_quantity || 0) + '" data-price="' + (p.purchase_price || 0) + '">' +
                                '<div><div class="prod-dd-name">' + esc(p.title) + '</div><div class="prod-dd-sku">' + esc(p.sku || '') + '</div></div>' +
                                '<span class="prod-dd-stock">انبار: ' + (p.real_stock_quantity || 0) + '</span>' +
                            '</div>';
                        }).join('');
                        prodDd.classList.add('open');

                        prodDd.querySelectorAll('.prod-dd-item').forEach(function (el) {
                            el.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                prodHidden.value = this.dataset.id;
                                prodInput.value  = this.dataset.title;
                                document.getElementById('prod-badge-name').textContent  = this.dataset.title;
                                document.getElementById('prod-badge-stock').textContent = 'انبار: ' + this.dataset.stock;
                                prodBadge.classList.add('show');
                                closeProdDd();
                                // پیش‌پر قیمت
                                var existingPane = document.getElementById('pane-existing');
                                var priceEl = existingPane.querySelector('[name=unit_price]');
                                if (priceEl && !priceEl.value && parseFloat(this.dataset.price) > 0) {
                                    priceEl.value = this.dataset.price;
                                    calcSummary();
                                }
                            });
                        });
                    })
                    .catch(function () { closeProdDd(); });
            }, 300);
        });

        prodInput.addEventListener('blur', function () { setTimeout(closeProdDd, 200); });

        document.getElementById('prod-clear').addEventListener('click', function () {
            prodInput.value = '';
            prodHidden.value = '';
            prodBadge.classList.remove('show');
        });
    }

    function closeProdDd() { if (prodDd) { prodDd.classList.remove('open'); } }

    // ════════════════════════════════════════
    // خلاصه مالی — برای هر دو تب
    // ════════════════════════════════════════
    function calcSummary() {
        var pane = document.querySelector('.inv-tab-pane.active');
        if (!pane) return;

        var qty      = parseFloat((pane.querySelector('[name=quantity]')     || {}).value) || 0;
        var price    = parseFloat((pane.querySelector('[name=unit_price]')   || {}).value) || 0;
        var discount = parseFloat((pane.querySelector('[name=discount]')     || {}).value) || 0;
        var tax      = parseFloat((pane.querySelector('[name=tax]')          || {}).value) || 0;
        var ship     = parseFloat((pane.querySelector('[name=shipping_cost]')|| {}).value) || 0;

        var total = qty * price;
        var final = total - discount + tax + ship;

        pane.querySelectorAll('.s-total')   .forEach(function(e){ e.textContent = total.toLocaleString('fa-IR')    + ' ' + CUR; });
        pane.querySelectorAll('.s-discount').forEach(function(e){ e.textContent = discount > 0 ? '- ' + discount.toLocaleString('fa-IR') + ' ' + CUR : '—'; });
        pane.querySelectorAll('.s-tax')     .forEach(function(e){ e.textContent = tax  > 0 ? tax.toLocaleString('fa-IR')  + ' ' + CUR : '—'; });
        pane.querySelectorAll('.s-ship')    .forEach(function(e){ e.textContent = ship > 0 ? ship.toLocaleString('fa-IR') + ' ' + CUR : '—'; });
        pane.querySelectorAll('.s-final')   .forEach(function(e){ e.textContent = final.toLocaleString('fa-IR')   + ' ' + CUR; });

        // paid_amount اگر paid
        var ps = pane.querySelector('.inv-pay-status');
        if (ps && ps.value === 'paid') {
            var pa = pane.querySelector('.inv-paid');
            if (pa) pa.value = final;
        }
    }

    // bind calc events
    document.querySelectorAll('.inv-qty,.inv-price,.inv-discount,.inv-tax,.inv-ship').forEach(function (el) {
        el.addEventListener('input', calcSummary);
    });

    // paid row toggle
    document.querySelectorAll('.inv-pay-status').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var row = this.closest('.form-card').querySelector('.inv-paid-row');
            if (row) row.style.display = this.value === 'partial' ? 'block' : 'none';
            if (this.value === 'paid') {
                var pa = this.closest('.form-card').querySelector('.inv-paid');
                var sf = this.closest('.inv-tab-pane').querySelector('.s-final');
                if (pa && sf) pa.value = parseFloat(sf.textContent.replace(/[^0-9.]/g, '')) || 0;
            }
            calcSummary();
        });
    });

    // ════════════════════════════════════════
    // Submit
    // ════════════════════════════════════════
    document.getElementById('inv-form').addEventListener('submit', function (e) {
        e.preventDefault();

        var mode = document.getElementById('mode').value;
        var pane = document.querySelector('.inv-tab-pane.active');

        // validation
        if (mode === 'existing' && !document.getElementById('product_id').value) {
            showAlert('error', 'محصول را از لیست انتخاب کنید');
            if (prodInput) prodInput.focus();
            return;
        }
        if (mode === 'new') {
            var titleEl = pane.querySelector('[name=product_title]');
            if (!titleEl || !titleEl.value.trim()) {
                showAlert('error', 'نام محصول الزامی است');
                titleEl && titleEl.focus();
                return;
            }
        }

        var btn  = document.getElementById('btn-submit');
        var text = document.getElementById('btn-text');
        btn.disabled = true;
        text.textContent = 'در حال ثبت...';

        // جمع‌آوری داده از تب فعال
        var data = { mode: mode };
        pane.querySelectorAll('[name]').forEach(function (el) {
            if (el.name) data[el.name] = el.value;
        });
        if (mode === 'existing') data.product_id = document.getElementById('product_id').value;

        fetch(AJAX + 'invoices.php?action=create', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(data),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                // ── پیام موفقیت + لینک محصول ──
                var productUrl = 'create-product.php?id=' + res.product_id;
                var linkHtml   = res.product_id
                    ? '<div style="margin-top:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;display:flex;align-items:center;justify-content:space-between;gap:12px;">' +
                        '<span style="font-size:13px;color:#374151;">برای تکمیل محصول (تصویر، ویژگی و...):</span>' +
                        '<a href="' + productUrl + '" ' +
                           'style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;' +
                                  'background:#16a34a;color:#fff;border-radius:7px;text-decoration:none;' +
                                  'font-size:13px;font-weight:700;white-space:nowrap;">' +
                           '✏️ ویرایش محصول' +
                        '</a>' +
                      '</div>'
                    : '';

                showAlert('success', '✅ ' + res.message + linkHtml);

                // reset تب فعال
                pane.querySelectorAll('input:not([type=hidden]):not(.dpk-init),textarea,select').forEach(function (el) {
                    if (el.type === 'number') el.value = el.defaultValue || 0;
                    else el.value = el.defaultValue || '';
                });
                pane.querySelectorAll('.inv-date-display').forEach(function (el) { el.value = ''; });
                if (prodInput) { prodInput.value = ''; }
                if (prodBadge) { prodBadge.classList.remove('show'); }
                if (prodHidden) prodHidden.value = '';
                calcSummary();
            } else {
                showAlert('error', res.message);
            }
        })
        .catch(function () { showAlert('error', 'خطا در ارتباط با سرور'); })
        .finally(function () { btn.disabled = false; text.textContent = 'ثبت فاکتور'; });
    });

    // ════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════
    function showAlert(type, html) {
        var el = document.getElementById('inv-alert');
        el.className     = 'inv-alert ' + type;
        el.innerHTML     = html;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        // success: فقط اگر لینک محصول نداشت auto-hide کن
        if (type === 'success' && html.indexOf('ویرایش محصول') === -1) {
            setTimeout(function () { el.style.display = 'none'; }, 6000);
        }
    }
    function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── init ─────────────────────────────────
    $(function () { initDatepickers(); });
    loadPCats();
    calcSummary();

})();