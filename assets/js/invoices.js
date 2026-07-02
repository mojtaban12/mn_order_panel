(function () {

    var curPage      = 1;
    var pendingDelId = null;

    function loadInvoices(page) {
        page    = page || 1;
        curPage = page;
        loading(true);

        var params = new URLSearchParams({
            action:         'list',
            page:           page,
            per_page:       20,
            search:         document.getElementById('f-search').value.trim(),
            payment_status: document.getElementById('f-status').value,
            date_from:      document.getElementById('f-date-from').value,
            date_to:        document.getElementById('f-date-to').value,
        });

        fetch(AJAX + 'invoices.php?' + params)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    renderTable(res.invoices);
                    renderPag(res.pagination);
                    document.getElementById('tbl-summary').textContent = 'مجموع: ' + res.pagination.total;
                }
            })
            .catch(function () { tableMsg('❌ خطا در بارگذاری'); })
            .finally(function () { loading(false); });
    }

    function loadStats() {
        fetch(AJAX + 'invoices.php?action=stats')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var s = res.stats;
                document.getElementById('st-total').textContent  = (s.total||0).toLocaleString('fa-IR');
                document.getElementById('st-paid').textContent   = parseFloat(s.paid_amount||0).toLocaleString('fa-IR') + ' ' + CUR;
                document.getElementById('st-unpaid').textContent = parseFloat(s.unpaid_amount||0).toLocaleString('fa-IR') + ' ' + CUR;
                document.getElementById('st-qty').textContent    = (s.total_qty||0).toLocaleString('fa-IR');
            });
    }

    function renderTable(invoices) {
        var tbody = document.getElementById('inv-tbody');
        if (!invoices || !invoices.length) {
            tableMsg('📭 فاکتوری یافت نشد');
            return;
        }
        tbody.innerHTML = invoices.map(function (inv) {
            var statusMap = { paid: 'badge-paid', unpaid: 'badge-unpaid', partial: 'badge-partial' };
            var statusLabel = { paid: 'پرداخت شده', unpaid: 'پرداخت نشده', partial: 'نیم‌پرداخت' };
            var badge = '<span class="badge ' + (statusMap[inv.payment_status]||'') + '">' + (statusLabel[inv.payment_status]||inv.payment_status) + '</span>';
            var date  = inv.invoice_date ? new Date(inv.invoice_date + 'T00:00:00').toLocaleDateString('fa-IR') : '—';

            return '<tr>' +
                '<td><strong style="color:#7c3aed;">' + esc(inv.invoice_number || '#' + inv.id) + '</strong></td>' +
                '<td style="font-size:12px;color:#6b7280;">' + date + '</td>' +
                '<td><div style="font-weight:600;">' + esc(inv.product_title||'—') + '</div><div style="font-size:11px;color:#9ca3af;">' + esc(inv.product_sku||'') + '</div></td>' +
                '<td>' + esc(inv.supplier_name) + '</td>' +
                '<td><strong>' + inv.quantity + '</strong></td>' +
                '<td>' + parseFloat(inv.unit_price).toLocaleString('fa-IR') + ' ' + CUR + '</td>' +
                '<td><strong>' + parseFloat(inv.final_amount).toLocaleString('fa-IR') + ' ' + CUR + '</strong></td>' +
                '<td>' + badge + '</td>' +
                '<td><div style="display:flex;gap:6px;">' +
                    '<a href="create-invoice.php?product_id=' + inv.product_id + '" class="act act-view" title="فاکتور جدید برای این محصول">+</a>' +
                    '<button class="act act-delete" onclick="askDel(' + inv.id + ')" title="حذف">🗑️</button>' +
                '</div></td>' +
            '</tr>';
        }).join('');
    }

    function renderPag(pag) {
        document.getElementById('pag-info').textContent = pag.total > 0
            ? 'نمایش ' + pag.from + ' تا ' + pag.to + ' از ' + pag.total : 'نتیجه‌ای یافت نشد';

        var btns = document.getElementById('pag-btns');
        btns.innerHTML = '';
        if (pag.total_pages <= 1) return;

        function mkBtn(lbl, pg, active, disabled) {
            var b = document.createElement('button');
            b.className = 'pag-btn' + (active ? ' active' : '');
            b.disabled  = !!disabled;
            b.textContent = lbl;
            if (!active && !disabled) b.onclick = function () { loadInvoices(pg); };
            return b;
        }
        btns.appendChild(mkBtn('«', pag.current_page - 1, false, pag.current_page === 1));
        for (var i = 1; i <= pag.total_pages; i++) {
            if (i === 1 || i === pag.total_pages || (i >= pag.current_page - 2 && i <= pag.current_page + 2))
                btns.appendChild(mkBtn(i, i, i === pag.current_page));
            else if (i === pag.current_page - 3 || i === pag.current_page + 3) {
                var d = document.createElement('button'); d.className = 'pag-btn'; d.disabled = true; d.textContent = '…'; btns.appendChild(d);
            }
        }
        btns.appendChild(mkBtn('»', pag.current_page + 1, false, pag.current_page === pag.total_pages));
    }

    window.askDel = function (id) { pendingDelId = id; document.getElementById('confirm-bg').classList.add('show'); };
    document.getElementById('confirm-cancel').onclick = function () { document.getElementById('confirm-bg').classList.remove('show'); pendingDelId = null; };
    document.getElementById('confirm-ok').onclick = function () {
        if (!pendingDelId) return;
        document.getElementById('confirm-bg').classList.remove('show');
        loading(true);
        fetch(AJAX + 'invoices.php?action=delete', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: pendingDelId }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (res.success) { loadInvoices(curPage); loadStats(); } else alert(res.message); })
        .catch(function () { alert('خطا در ارتباط با سرور'); })
        .finally(function () { loading(false); });
        pendingDelId = null;
    };

    var st;
    document.getElementById('f-search').addEventListener('keyup', function () { clearTimeout(st); st = setTimeout(function () { loadInvoices(1); }, 400); });
    document.getElementById('f-status').addEventListener('change', function () { loadInvoices(1); });
    document.getElementById('f-date-from').addEventListener('change', function () { loadInvoices(1); });
    document.getElementById('f-date-to').addEventListener('change', function () { loadInvoices(1); });

    function loading(on) { document.getElementById('loading-overlay').classList.toggle('active', on); }
    function tableMsg(msg) { document.getElementById('inv-tbody').innerHTML = '<tr><td colspan="9" style="text-align:center;padding:48px;color:#9ca3af;">' + esc(msg) + '</td></tr>'; }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    loadInvoices(1);
    loadStats();

})();