(function () {
    var curPage        = 1;
    var selectedIds    = new Set();
    var stopRequested  = false;
    var running        = false;

    // ════════════════════════════════════════
    // آمار
    // ════════════════════════════════════════
    function loadStats() {
        fetch(AJAX + 'sync-products-batch.php?action=stats')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                document.getElementById('st-total').textContent   = res.total.toLocaleString('fa-IR');
                document.getElementById('st-pending').textContent = res.pending.toLocaleString('fa-IR');
                document.getElementById('st-done').textContent    = res.done_today.toLocaleString('fa-IR');
            });
    }

    // ════════════════════════════════════════
    // لیست محصولات
    // ════════════════════════════════════════
    function loadList(page) {
        page = page || 1;
        curPage = page;
        var params = new URLSearchParams({
            action: 'list', page: page, per_page: 30,
            search: document.getElementById('f-search').value.trim(),
        });
        fetch(AJAX + 'sync-products-batch.php?' + params)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) { renderTable(res.items); renderPag(res.pagination); }
            });
    }

    function renderTable(items) {
        var tbody = document.getElementById('tbody');
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#9ca3af;">محصولی یافت نشد</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(function (p) {
            var syncedToday = p.synced_at && p.synced_at.slice(0, 10) === new Date().toISOString().slice(0, 10);
            var badge = (p.is_synced == 1 && p.wp_product_id)
                ? '<span class="badge badge-synced">✔ سینک شده</span>'
                : '<span class="badge badge-pending">در انتظار</span>';
            var checked = selectedIds.has(p.id) ? 'checked' : '';
            return '<tr>' +
                '<td><input type="checkbox" class="row-chk" data-id="' + p.id + '" ' + checked + '></td>' +
                '<td>' + esc(p.title) + '</td>' +
                '<td style="font-size:12px;color:#6b7280;">' + esc(p.sku) + '</td>' +
                '<td>' + (p.wp_product_id ? '#' + p.wp_product_id : '—') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td style="font-size:11px;color:#9ca3af;">' + (p.synced_at ? new Date(p.synced_at).toLocaleString('fa-IR') : '—') + '</td>' +
                '</tr>';
        }).join('');

        tbody.querySelectorAll('.row-chk').forEach(function (chk) {
            chk.addEventListener('change', function () {
                var id = parseInt(this.dataset.id);
                if (this.checked) selectedIds.add(id); else selectedIds.delete(id);
                updateSelCount();
            });
        });
    }

    function renderPag(pag) {
        document.getElementById('pag-info').textContent =
            'صفحه ' + pag.current_page + ' از ' + pag.total_pages + ' — مجموع: ' + pag.total.toLocaleString('fa-IR');

        var box = document.getElementById('pag-btns');
        box.innerHTML = '';
        if (pag.total_pages <= 1) return;

        function mk(label, page, active, disabled) {
            var b = document.createElement('button');
            b.className = 'pag-btn' + (active ? ' active' : '');
            b.disabled = !!disabled;
            b.textContent = label;
            if (!active && !disabled) b.onclick = function () { loadList(page); };
            return b;
        }
        box.appendChild(mk('«', pag.current_page - 1, false, pag.current_page === 1));
        for (var i = 1; i <= pag.total_pages; i++) {
            if (i === 1 || i === pag.total_pages || Math.abs(i - pag.current_page) <= 2) {
                box.appendChild(mk(i, i, i === pag.current_page));
            }
        }
        box.appendChild(mk('»', pag.current_page + 1, false, pag.current_page === pag.total_pages));
    }

    document.getElementById('chk-all').addEventListener('change', function () {
        var checked = this.checked;
        document.querySelectorAll('.row-chk').forEach(function (c) {
            c.checked = checked;
            var id = parseInt(c.dataset.id);
            if (checked) selectedIds.add(id); else selectedIds.delete(id);
        });
        updateSelCount();
    });

    function updateSelCount() {
        document.getElementById('sel-count').textContent = selectedIds.size;
        document.getElementById('btn-sync-selected').disabled = selectedIds.size === 0;
    }

    var searchTimer;
    document.getElementById('f-search').addEventListener('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadList(1); }, 400);
    });

    // ════════════════════════════════════════
    // موتور سینک (batch به batch، resumable)
    // ════════════════════════════════════════
    var progressArea = document.getElementById('progress-area');
    var progressBar   = document.getElementById('progress-bar');
    var progressText  = document.getElementById('progress-text');
    var logBox        = document.getElementById('sync-log');
    var btnAll         = document.getElementById('btn-sync-all');
    var btnSelected    = document.getElementById('btn-sync-selected');
    var btnStop        = document.getElementById('btn-stop');

    document.getElementById('btn-toggle-log').addEventListener('click', function () {
        logBox.style.display = logBox.style.display === 'block' ? 'none' : 'block';
    });

    btnStop.addEventListener('click', function () {
        stopRequested = true;
        this.disabled = true;
        this.textContent = '⏳ در حال توقف...';
    });

    btnAll.addEventListener('click', function () { startSync('all', null); });
    btnSelected.addEventListener('click', function () { startSync('selected', Array.from(selectedIds)); });

    function startSync(mode, idsList) {
        if (running) return;
        running = true;
        stopRequested = false;

        btnAll.disabled = true;
        btnSelected.disabled = true;
        btnStop.style.display = 'inline-flex';
        btnStop.disabled = false;
        btnStop.textContent = '⏹ توقف';
        progressArea.style.display = 'block';
        logBox.innerHTML = '';

        var totalDone = 0;
        var totalOk   = 0;
        var totalFail = 0;

        // برای mode=all کل عدد اولیه رو از stats می‌گیریم تا progress bar درست باشه
        var initialTotalPromise = (mode === 'all')
            ? fetch(AJAX + 'sync-products-batch.php?action=stats').then(function (r) { return r.json(); }).then(function (r) { return r.pending; })
            : Promise.resolve(idsList.length);

        initialTotalPromise.then(function (grandTotal) {
            var remainingIds = mode === 'selected' ? idsList.slice() : null;

            function nextBatch() {
                if (stopRequested) { finish(); return; }

                var body = { mode: mode, batch_size: 10 };
                if (mode === 'selected') {
                    body.product_ids = remainingIds.splice(0, 10);
                    if (body.product_ids.length === 0) { finish(); return; }
                }

                fetch(AJAX + 'sync-products-batch.php?action=process', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.success) { logLine('❌ خطا: ' + res.message, false); finish(); return; }

                        res.results.forEach(function (r) {
                            totalDone++;
                            if (r.success) totalOk++; else totalFail++;
                            logLine((r.success ? '✓ ' : '✗ ') + esc(r.title || ('#' + r.id)) + (r.message ? ' — ' + esc(r.message) : ''), r.success);
                        });

                        var effectiveTotal = mode === 'all'
                            ? (res.remaining !== null ? totalDone + res.remaining : grandTotal)
                            : grandTotal;

                        updateProgress(totalDone, effectiveTotal, totalOk, totalFail);

                        if (res.done || (mode === 'selected' && remainingIds.length === 0)) {
                            finish();
                        } else if (!stopRequested) {
                            setTimeout(nextBatch, 120); // فاصله کوچک بین batchها
                        } else {
                            finish();
                        }
                    })
                    .catch(function () {
                        logLine('❌ خطا در ارتباط با سرور — تلاش مجدد در ۳ ثانیه...', false);
                        setTimeout(nextBatch, 3000);
                    });
            }

            updateProgress(0, grandTotal, 0, 0);
            nextBatch();
        });

        function finish() {
            running = false;
            btnAll.disabled = false;
            btnSelected.disabled = selectedIds.size === 0;
            btnStop.style.display = 'none';
            loadStats();
            loadList(curPage);
            progressText.textContent += stopRequested ? ' — متوقف شد (با کلیک روی «سینک» ادامه پیدا می‌کند)' : ' — تمام شد ✅';
        }
    }

    function updateProgress(done, total, ok, fail) {
        var pct = total > 0 ? Math.min(100, Math.round(done / total * 100)) : 0;
        progressBar.style.width = pct + '%';
        progressText.textContent = done.toLocaleString('fa-IR') + ' از ' + total.toLocaleString('fa-IR') +
            ' (' + pct + '%) — موفق: ' + ok + ' | خطا: ' + fail;
    }

    function logLine(text, ok) {
        var row = document.createElement('div');
        row.className = 'sync-log-row ' + (ok ? 'ok' : 'fail');
        row.textContent = text;
        logBox.appendChild(row);
        logBox.scrollTop = logBox.scrollHeight;
    }

    function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // init
    loadStats();
    loadList(1);

})();