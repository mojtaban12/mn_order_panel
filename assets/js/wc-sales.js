(function () {

    var curPage = 1;

    // ════════════════════════════════════════
    // بارگذاری
    // ════════════════════════════════════════
    function loadSales(page) {
        page    = page || 1;
        curPage = page;
        loading(true);

        var params = new URLSearchParams({
            page:      page,
            per_page:  20,
            search:    document.getElementById('f-search').value.trim(),
            status:    document.getElementById('f-status').value,
            date_from: document.getElementById('f-date-from').value,
            date_to:   document.getElementById('f-date-to').value,
        });

        fetch(AJAX + 'get-wc-sales.php?' + params)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    renderTable(res.sales);
                    renderPag(res.pagination);
                    renderStats(res.stats);
                    document.getElementById('tbl-summary').textContent = 'مجموع: ' + res.pagination.total;
                }
            })
            .catch(function () { showMsg('خطا در بارگذاری'); })
            .finally(function () { loading(false); });
    }

    // ════════════════════════════════════════
    // رندر جدول
    // ════════════════════════════════════════
    function renderTable(sales) {
        var tbody = document.getElementById('sales-tbody');
        if (!sales || !sales.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:48px;color:#9ca3af;">📭 فاکتوری یافت نشد</td></tr>';
            return;
        }
        tbody.innerHTML = sales.map(function (s) {
            var statusMap = { completed: 'badge-completed', processing: 'badge-processing', 'on-hold': 'badge-on-hold' };
            var badge = '<span class="badge ' + (statusMap[s.order_status] || 'badge-other') + '">' + esc(s.order_status || '—') + '</span>';
            var stock = s.stock_updated == 1
                ? '<span class="stock-badge stock-dec">' + s.stock_before + ' → ' + s.stock_after + '</span>'
                : '<span class="stock-badge stock-no">—</span>';
            var date = s.order_date ? new Date(s.order_date).toLocaleDateString('fa-IR') : '—';

            return '<tr>' +
                '<td><strong style="color:#7c3aed;">#' + s.wc_order_id + '</strong></td>' +
                '<td style="font-size:12px;color:#6b7280;">' + date + '</td>' +
                '<td>' +
                    '<div style="font-weight:600;">' + esc(s.customer_name || '—') + '</div>' +
                    (s.customer_phone ? '<div style="font-size:11px;color:#9ca3af;">' + esc(s.customer_phone) + '</div>' : '') +
                '</td>' +
                '<td><div class="product-link">' + esc(s.product_title) + '</div></td>' +
                '<td><strong>' + s.quantity + '</strong></td>' +
                '<td>' + parseFloat(s.unit_price).toLocaleString('fa-IR') + ' ' + CUR + '</td>' +
                '<td><strong>' + parseFloat(s.total_price).toLocaleString('fa-IR') + ' ' + CUR + '</strong></td>' +
                '<td>' + badge + '</td>' +
                '<td>' + stock + '</td>' +
            '</tr>';
        }).join('');
    }

    // ════════════════════════════════════════
    // آمار
    // ════════════════════════════════════════
    function renderStats(s) {
        if (!s) return;
        document.getElementById('st-total').textContent         = (s.total || 0).toLocaleString('fa-IR');
        document.getElementById('st-revenue').textContent       = parseFloat(s.total_revenue || 0).toLocaleString('fa-IR');
        document.getElementById('st-qty').textContent           = (s.total_qty || 0).toLocaleString('fa-IR');
        document.getElementById('st-stock-updated').textContent = (s.stock_updated || 0).toLocaleString('fa-IR');
    }

    // ════════════════════════════════════════
    // صفحه‌بندی
    // ════════════════════════════════════════
    function renderPag(pag) {
        document.getElementById('pag-info').textContent = pag.total > 0
            ? 'نمایش ' + pag.from + ' تا ' + pag.to + ' از ' + pag.total
            : 'نتیجه‌ای یافت نشد';

        var btns = document.getElementById('pag-btns');
        btns.innerHTML = '';
        if (pag.total_pages <= 1) return;

        function mkBtn(label, page, active, disabled) {
            var b = document.createElement('button');
            b.className   = 'pag-btn' + (active ? ' active' : '');
            b.disabled    = !!disabled;
            b.textContent = label;
            if (!active && !disabled) b.onclick = function () { loadSales(page); };
            return b;
        }

        btns.appendChild(mkBtn('«', pag.current_page - 1, false, pag.current_page === 1));
        for (var i = 1; i <= pag.total_pages; i++) {
            if (i === 1 || i === pag.total_pages || (i >= pag.current_page - 2 && i <= pag.current_page + 2)) {
                btns.appendChild(mkBtn(i, i, i === pag.current_page));
            } else if (i === pag.current_page - 3 || i === pag.current_page + 3) {
                var d = document.createElement('button');
                d.className = 'pag-btn'; d.disabled = true; d.textContent = '…';
                btns.appendChild(d);
            }
        }
        btns.appendChild(mkBtn('»', pag.current_page + 1, false, pag.current_page === pag.total_pages));
    }

    // ════════════════════════════════════════
    // Sync
    // ════════════════════════════════════════
    window.runSync = function () {
        var btn    = document.getElementById('btn-sync');
        var status = document.getElementById('sync-status');
        btn.disabled       = true;
        status.className   = 'sync-status running';
        status.textContent = '⏳ در حال بررسی سفارشات...';

        fetch(AJAX + 'sync-wc-sales.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ batch_size: 20 }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                status.className   = 'sync-status done';
                status.textContent = '✓ ' + res.message;
                loadSales(1);
            } else {
                status.className   = 'sync-status error';
                status.textContent = '✗ ' + res.message;
            }
        })
        .catch(function () {
            status.className   = 'sync-status error';
            status.textContent = '✗ خطا در ارتباط با سرور';
        })
        .finally(function () { btn.disabled = false; });
    };

    // ════════════════════════════════════════
    // فیلترها
    // ════════════════════════════════════════
    var st;
    document.getElementById('f-search').addEventListener('keyup', function () {
        clearTimeout(st);
        st = setTimeout(function () { loadSales(1); }, 400);
    });
    document.getElementById('f-status').addEventListener('change',    function () { loadSales(1); });
    document.getElementById('f-date-from').addEventListener('change', function () { loadSales(1); });
    document.getElementById('f-date-to').addEventListener('change',   function () { loadSales(1); });

    // ════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════
    function loading(on) {
        document.getElementById('loading-overlay').classList.toggle('active', on);
    }
    function showMsg(msg) {
        var tbody = document.getElementById('sales-tbody');
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:32px;color:#dc2626;">⚠️ ' + esc(msg) + '</td></tr>';
    }
    function esc(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // init
    loadSales(1);

})();