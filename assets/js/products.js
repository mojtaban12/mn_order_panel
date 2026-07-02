(function () {

    var currentPage     = 1;
    var pendingDeleteId = null;

    // ════════════════════════════════════════
    // بارگذاری
    // ════════════════════════════════════════
    function loadProducts(page) {
        page = page || 1;
        currentPage = page;
        loading(true);

        var params = new URLSearchParams({
            page:         page,
            per_page:     20,
            search:       document.getElementById('f-search').value.trim(),
            status:       document.getElementById('f-status').value,
            stock_status: document.getElementById('f-stock').value,
        });

        fetch(AJAX + 'get-products.php?' + params)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    renderTable(res.products);
                    renderPagination(res.pagination);
                    renderStats(res.stats);
                } else {
                    showTableMsg('❌ ' + (res.message || 'خطا در بارگذاری'));
                }
            })
            .catch(function () { showTableMsg('❌ خطا در ارتباط با سرور'); })
            .finally(function () { loading(false); });
    }

    // ════════════════════════════════════════
    // رندر جدول
    // ════════════════════════════════════════
    function renderTable(products) {
        var tbody = document.getElementById('products-tbody');

        if (!products || products.length === 0) {
            showTableMsg('هیچ محصولی یافت نشد 📭');
            return;
        }

        var rows = products.map(function (p) {
            // تصویر
            var imgHtml;
            if (p.primary_image) {
                var img = document.createElement('img');
                img.src       = p.primary_image;
                img.className = 'product-thumb';
                img.onerror   = function () { this.style.display = 'none'; };
                imgHtml = img.outerHTML;
            } else {
                imgHtml = '<div class="product-thumb-placeholder">📦</div>';
            }

            // قیمت
            var price = parseFloat(p.regular_price).toLocaleString('fa-IR') + ' ' + CUR;
            if (p.sale_price && parseFloat(p.sale_price) > 0) {
                price = '<span style="text-decoration:line-through;color:#9ca3af;font-size:11px;">'
                      + price + '</span><br>'
                      + parseFloat(p.sale_price).toLocaleString('fa-IR') + ' ' + CUR;
            }

            // موجودی
            var stockText = p.stock_quantity !== null
                ? '<span style="font-weight:600;">' + parseInt(p.stock_quantity).toLocaleString('fa-IR') + '</span>'
                  + '<span style="font-size:11px;color:#9ca3af;"> / '
                  + (p.real_stock_quantity !== null ? parseInt(p.real_stock_quantity).toLocaleString('fa-IR') : '—')
                  + '</span>'
                : '—';

            // badge وضعیت
            var statusBadges = {
                active:   '<span class="badge badge-active">✅ فعال</span>',
                inactive: '<span class="badge badge-inactive">⏸ غیرفعال</span>',
                draft:    '<span class="badge badge-draft">📝 پیش‌نویس</span>',
            };
            var statusBadge = statusBadges[p.status] || esc(p.status);

            // badge انبار
            var stockBadges = {
                instock:     '<span class="badge badge-instock">موجود</span>',
                outofstock:  '<span class="badge badge-outofstock">ناموجود</span>',
                onbackorder: '<span class="badge badge-onbackorder">پیش‌فروش</span>',
            };
            var stockBadge = stockBadges[p.stock_status] || esc(p.stock_status);

            // تعداد تصاویر
            var imgCountBadge = parseInt(p.image_count) > 0
                ? '<span class="badge badge-instock">' + p.image_count + ' 🖼️</span>'
                : '<span style="color:#d1d5db;font-size:12px;">—</span>';

            // sync badge
            var syncBadge = p.is_synced == 1
                ? '<span class="badge badge-synced">✔ synced</span>'
                : '';

            // تاریخ
            var date = p.created_at
                ? new Date(p.created_at).toLocaleDateString('fa-IR')
                : '—';

            // WP sku
            var skuHtml   = p.sku ? esc(p.sku) : '—';
            var titleHtml = '<div class="product-title" title="' + esc(p.title) + '">' + esc(p.title) + '</div>';
            var wpHtml    = p.wp_product_id
                ? '<div class="product-sku">WP: #' + p.wp_product_id + '</div>'
                : '';

            return '<tr>'
                + '<td><input type="checkbox" class="row-chk" value="' + p.id + '"></td>'
                + '<td><div class="product-info">' + imgHtml + '<div>' + titleHtml + wpHtml + '</div></div></td>'
                + '<td><span style="font-size:12px;color:#6b7280;">' + skuHtml + '</span></td>'
                + '<td>' + price + '</td>'
                + '<td>' + stockText + '</td>'
                + '<td>' + statusBadge + ' ' + syncBadge + '</td>'
                + '<td>' + stockBadge + '</td>'
                + '<td>' + imgCountBadge + '</td>'
                + '<td style="font-size:12px;color:#6b7280;">' + date + '</td>'
                + '<td><div class="action-btns">'
                + '<button class="act act-edit"   onclick="editProduct(' + p.id + ')" title="ویرایش">✏️</button>'
                + '<button class="act act-sync"   onclick="syncProduct(' + p.id + ')" title="سینک با وردپرس">🔄</button>'
                + '<button class="act act-delete" onclick="askDelete('  + p.id + ')" title="حذف">🗑️</button>'
                + '</div></td>'
                + '</tr>';
        });

        tbody.innerHTML = rows.join('');
    }

    // ════════════════════════════════════════
    // صفحه‌بندی
    // ════════════════════════════════════════
    function renderPagination(pag) {
        document.getElementById('pag-info').textContent = pag.total > 0
            ? 'نمایش ' + pag.from + ' تا ' + pag.to + ' از ' + pag.total + ' محصول'
            : 'محصولی یافت نشد';

        document.getElementById('tbl-summary').textContent = pag.total > 0
            ? 'مجموع: ' + pag.total + ' محصول' : '';

        var container = document.getElementById('pag-btns');
        container.innerHTML = '';
        if (pag.total_pages <= 1) return;

        function mkBtn(label, page, active, disabled) {
            var b = document.createElement('button');
            b.className   = 'pag-btn' + (active ? ' active' : '');
            b.disabled    = !!disabled;
            b.textContent = label;
            if (!active && !disabled) {
                b.onclick = function () { loadProducts(page); };
            }
            return b;
        }

        container.appendChild(mkBtn('«', pag.current_page - 1, false, pag.current_page === 1));

        for (var i = 1; i <= pag.total_pages; i++) {
            if (i === 1 || i === pag.total_pages ||
                (i >= pag.current_page - 2 && i <= pag.current_page + 2)) {
                container.appendChild(mkBtn(i, i, i === pag.current_page));
            } else if (i === pag.current_page - 3 || i === pag.current_page + 3) {
                var dots = document.createElement('button');
                dots.className   = 'pag-btn';
                dots.disabled    = true;
                dots.textContent = '…';
                container.appendChild(dots);
            }
        }

        container.appendChild(mkBtn('»', pag.current_page + 1, false, pag.current_page === pag.total_pages));
    }

    // ════════════════════════════════════════
    // آمار
    // ════════════════════════════════════════
    function renderStats(s) {
        document.getElementById('st-total').textContent      = s.total      || 0;
        document.getElementById('st-active').textContent     = s.active     || 0;
        document.getElementById('st-instock').textContent    = s.instock    || 0;
        document.getElementById('st-outofstock').textContent = s.outofstock || 0;
    }

    // ════════════════════════════════════════
    // عملیات
    // ════════════════════════════════════════
    window.editProduct = function (id) {
        window.location.href = 'create-product.php?id=' + id;
    };

    // ── سینک یک محصول ────────────────────────
    window.syncProduct = function (id) {
        var btn = document.querySelector('[onclick="syncProduct(' + id + ')"]');
        if (btn) { btn.textContent = '⏳'; btn.disabled = true; }

        fetch(AJAX + 'sync-product.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_id: id }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            showToast(res.success ? 'success' : 'error',
                res.message + (res.wp_product_id ? ' (WP#' + res.wp_product_id + ')' : ''));
            if (res.success) loadProducts(currentPage);
            else if (btn) { btn.textContent = '🔄'; btn.disabled = false; }
        })
        .catch(function () {
            showToast('error', 'خطا در ارتباط با سرور');
            if (btn) { btn.textContent = '🔄'; btn.disabled = false; }
        });
    };

    // ── سینک انتخاب‌شده‌ها ───────────────────
    window.syncSelected = function () {
        var ids = [];
        document.querySelectorAll('.row-chk:checked').forEach(function (c) { ids.push(c.value); });

        if (ids.length === 0) {
            showToast('error', 'ابتدا محصولاتی را انتخاب کنید');
            return;
        }

        loading(true);
        fetch(AJAX + 'sync-product.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_ids: ids }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            showToast(res.success ? 'success' : 'error', res.message);
            loadProducts(currentPage);
        })
        .catch(function () { showToast('error', 'خطا در ارتباط با سرور'); })
        .finally(function () { loading(false); });
    };

    window.askDelete = function (id) {
        pendingDeleteId = id;
        document.getElementById('confirm-bg').classList.add('show');
    };

    document.getElementById('confirm-cancel').onclick = function () {
        document.getElementById('confirm-bg').classList.remove('show');
        pendingDeleteId = null;
    };

    document.getElementById('confirm-ok').onclick = function () {
        if (!pendingDeleteId) return;
        document.getElementById('confirm-bg').classList.remove('show');
        deleteProduct(pendingDeleteId);
        pendingDeleteId = null;
    };

    function deleteProduct(id) {
        loading(true);
        fetch(AJAX + 'delete-product.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_id: id }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                loadProducts(currentPage);
            } else {
                showToast('error', res.message || 'خطا در حذف محصول');
                loading(false);
            }
        })
        .catch(function () {
            showToast('error', 'خطا در ارتباط با سرور');
            loading(false);
        });
    }

    // ════════════════════════════════════════
    // Select all
    // ════════════════════════════════════════
    document.getElementById('chk-all').addEventListener('change', function () {
        var checked = this.checked;
        document.querySelectorAll('.row-chk').forEach(function (c) { c.checked = checked; });
    });

    // ════════════════════════════════════════
    // فیلترها
    // ════════════════════════════════════════
    window.resetFilters = function () {
        document.getElementById('f-search').value = '';
        document.getElementById('f-status').value = '';
        document.getElementById('f-stock').value  = '';
        loadProducts(1);
    };

    var searchTimer;
    document.getElementById('f-search').addEventListener('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadProducts(1); }, 450);
    });
    document.getElementById('f-status').addEventListener('change', function () { loadProducts(1); });
    document.getElementById('f-stock').addEventListener('change',  function () { loadProducts(1); });

    // ════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════
    function loading(on) {
        document.getElementById('loading-overlay').classList.toggle('active', on);
    }

    function showTableMsg(msg) {
        document.getElementById('products-tbody').innerHTML =
            '<tr><td colspan="10" class="table-empty"><span class="empty-icon">📭</span>' + msg + '</td></tr>';
    }

    function showToast(type, msg) {
        var existing = document.getElementById('mn-toast');
        if (existing) existing.remove();

        var t = document.createElement('div');
        t.id = 'mn-toast';
        t.style.cssText = [
            'position:fixed', 'bottom:24px', 'left:50%', 'transform:translateX(-50%)',
            'background:' + (type === 'success' ? '#16a34a' : '#dc2626'),
            'color:#fff', 'padding:12px 24px', 'border-radius:8px',
            'font-size:14px', 'z-index:99999', 'box-shadow:0 4px 16px rgba(0,0,0,.2)',
            'max-width:90vw', 'text-align:center', 'direction:rtl',
        ].join(';');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { if (t.parentNode) t.remove(); }, 4000);
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // init
    loadProducts(1);

})();