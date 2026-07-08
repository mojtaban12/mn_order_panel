(function () {

    var rawData = [];
    var BATCH   = 100;

    var colFields = [
        { key: 'excel_id',       label: 'شناسه محصول (سایت)', required: true  },
        { key: 'name',           label: 'نام کالا',            required: true  },
        { key: 'quantity',       label: 'تعداد',                required: true  },
        { key: 'purchase_price', label: 'قیمت خرید',           required: false },
        { key: 'regular_price',  label: 'قیمت فروش',           required: false },
    ];

    // ── File select ──────────────────────────
    var dropZone  = document.getElementById('ex-drop-zone');
    var fileInput = document.getElementById('ex-file-input');

    dropZone.addEventListener('dragover',  function(e){ e.preventDefault(); dropZone.classList.add('drag'); });
    dropZone.addEventListener('dragleave', function(){ dropZone.classList.remove('drag'); });
    dropZone.addEventListener('drop', function(e){
        e.preventDefault(); dropZone.classList.remove('drag');
        if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', function(){
        if (this.files[0]) handleFile(this.files[0]);
    });

    function handleFile(file) {
        if (file.size > 20 * 1024 * 1024) { showAlert('error', 'حجم فایل بیش از 20MB'); return; }
        document.getElementById('ex-file-name').textContent = '📄 ' + file.name;
        document.getElementById('ex-file-name').style.display = 'block';
        showAlert('info', '⏳ در حال خواندن فایل...');

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var wb  = XLSX.read(e.target.result, { type: 'array' });
                var ws  = wb.Sheets[wb.SheetNames[0]];
                rawData = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                buildColMap();
                document.getElementById('ex-col-map-card').style.display = 'block';
                showAlert('success', '✓ فایل لود شد — ' + (rawData.length - 1) + ' ردیف');
            } catch(err) {
                showAlert('error', 'خطا در خواندن فایل: ' + err.message);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function buildColMap() {
        var hRow = parseInt(document.getElementById('ex-inp-header-row').value || 1) - 1;
        var hdrs = rawData[hRow] || [];
        var auto = autoDetect(hdrs);

        var opts = '<option value="-1">— نادیده —</option>';
        hdrs.forEach(function(h, i){
            opts += '<option value="' + i + '">' + esc(String(h || 'ستون ' + (i+1))) + '</option>';
        });

        var html = '<div class="col-map-title">📋 تطبیق ستون‌ها</div>';
        colFields.forEach(function(f){
            var sel = auto[f.key] !== undefined ? auto[f.key] : -1;
            var o   = opts.replace('value="' + sel + '"', 'value="' + sel + '" selected');
            html += '<div class="col-map-row">' +
                '<label>' + f.label + (f.required ? ' <span style="color:#ef4444">*</span>' : '') + '</label>' +
                '<select id="ex-map-' + f.key + '">' + o + '</select></div>';
        });
        document.getElementById('ex-col-map-wrap').innerHTML = html;
    }

    function autoDetect(hdrs) {
        var m = {}, p = {
            excel_id:       /شناسه|id|کد\s*محصول/i,
            name:           /نام|كالا|کالا|عنوان|product|title/i,
            quantity:       /تعداد|qty|quantity|موجودي|موجودی/i,
            purchase_price: /خرید|خريد|purchase|cost/i,
            regular_price:  /فروش|price/i,
        };
        hdrs.forEach(function(h, i){
            var s = String(h || '');
            Object.keys(p).forEach(function(k){ if (!m.hasOwnProperty(k) && p[k].test(s)) m[k] = i; });
        });
        return m;
    }

    function getMapping() {
        var m = {};
        colFields.forEach(function(f){
            var el = document.getElementById('ex-map-' + f.key);
            m[f.key] = el ? parseInt(el.value) : -1;
        });
        return m;
    }

    function parseRows() {
        var hRow    = parseInt(document.getElementById('ex-inp-header-row').value || 1) - 1;
        var mapping = getMapping();
        var rows    = [];

        rawData.slice(hRow + 1).forEach(function(row){
            var excelId = String(row[mapping.excel_id] || '').trim();
            var name    = String(row[mapping.name]      || '').trim();
            var qty     = parseNum(row[mapping.quantity]);
            var buyP    = mapping.purchase_price >= 0 ? parseNum(row[mapping.purchase_price]) : 0;
            var sellP   = mapping.regular_price  >= 0 ? parseNum(row[mapping.regular_price])  : 0;

            if (!excelId || !name || qty <= 0) return;

            rows.push({
                excel_id:       excelId,
                name:           name,
                quantity:       qty,
                purchase_price: buyP  > 0 ? buyP  : null,
                regular_price:  sellP > 0 ? sellP : null,
            });
        });
        return rows;
    }

    document.getElementById('ex-btn-import').addEventListener('click', startImport);

    function startImport() {
        var catId   = document.getElementById('ex-sel-cat').value || null;
        var mapping = getMapping();

        if (mapping.excel_id === -1) { showAlert('error', 'ستون «شناسه محصول» را انتخاب کنید'); return; }
        if (mapping.name     === -1) { showAlert('error', 'ستون «نام کالا» را انتخاب کنید');     return; }
        if (mapping.quantity === -1) { showAlert('error', 'ستون «تعداد» را انتخاب کنید');         return; }
        if (!rawData.length)         { showAlert('error', 'ابتدا فایل را انتخاب کنید');           return; }

        var allRows = parseRows();
        if (!allRows.length) { showAlert('error', 'ردیف قابل ثبتی یافت نشد'); return; }

        document.getElementById('ex-btn-import').disabled = true;
        document.getElementById('ex-import-section').style.display = 'block';
        document.getElementById('ex-st-total').textContent = allRows.length;
        document.getElementById('ex-st-done').textContent  = 0;
        document.getElementById('ex-st-error').textContent = 0;

        sendBatches(allRows, catId);
    }

    function sendBatches(allRows, catId) {
        var total = allRows.length, done = 0, errors = 0, offset = 0;

        function next() {
            if (offset >= total) {
                var msg = '✅ ' + done + ' ردیف از ' + total + ' پردازش شد';
                if (errors) msg += ' | ' + errors + ' خطا';
                showAlert(errors ? 'info' : 'success', msg);
                document.getElementById('ex-btn-import').disabled = false;
                refreshSyncCount();
                return;
            }

            var batch = allRows.slice(offset, offset + BATCH);
            offset += BATCH;

            fetch(AJAX + 'import-existing-batch.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ category_id: catId, rows: batch }),
            })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.success) {
                        done   += (res.created || 0) + (res.updated || 0);
                        errors += res.errors || 0;
                    } else {
                        errors += batch.length;
                    }
                    document.getElementById('ex-st-done').textContent  = done;
                    document.getElementById('ex-st-error').textContent = errors;
                    updateProgress(offset, total);
                    setTimeout(next, 50);
                })
                .catch(function(){
                    errors += batch.length;
                    document.getElementById('ex-st-error').textContent = errors;
                    updateProgress(offset, total);
                    setTimeout(next, 50);
                });
        }
        next();
    }

    function updateProgress(done, total) {
        var pct = total > 0 ? Math.round(Math.min(done, total) / total * 100) : 0;
        document.getElementById('ex-progress-bar').style.width = pct + '%';
        document.getElementById('ex-prog-text').textContent =
            Math.min(done, total) + ' از ' + total + ' (' + pct + '%)';
    }

    document.getElementById('ex-btn-reset').addEventListener('click', function(){
        rawData = [];
        document.getElementById('ex-col-map-card').style.display   = 'none';
        document.getElementById('ex-import-section').style.display = 'none';
        document.getElementById('ex-file-name').style.display      = 'none';
        document.getElementById('ex-btn-import').disabled           = false;
        document.getElementById('ex-alert-box').innerHTML           = '';
        fileInput.value = '';
    });

    // ════════════════════════════════════════
    // سینک جمعی با وردپرس
    // ════════════════════════════════════════
    var SYNC_BATCH = 10;

    function refreshSyncCount() {
        fetch(AJAX + 'sync-existing-products.php?action=pending&limit=1')
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) document.getElementById('sync-remaining').textContent = res.remaining;
            });
    }

    document.getElementById('btn-bulk-sync').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.textContent = '⏳ در حال همگام‌سازی...';
        var totalDone = 0, totalNotfound = 0;

        function step() {
            fetch(AJAX + 'sync-existing-products.php?action=pending&limit=' + SYNC_BATCH)
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res.success || !res.items.length) {
                        finish();
                        return;
                    }
                    var ids = res.items.map(function(i){ return i.id; });

                    fetch(AJAX + 'sync-existing-products.php?action=pull', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({ product_ids: ids }),
                    })
                        .then(function(r){ return r.json(); })
                        .then(function(pullRes){
                            if (pullRes.success) {
                                totalDone     += pullRes.done     || 0;
                                totalNotfound += pullRes.notfound || 0;
                                document.getElementById('sync-done').textContent     = totalDone;
                                document.getElementById('sync-notfound').textContent = totalNotfound;
                                document.getElementById('sync-remaining').textContent = res.remaining - ids.length;
                            }
                            setTimeout(step, 150);
                        })
                        .catch(finish);
                })
                .catch(finish);
        }

        function finish() {
            btn.disabled = false;
            btn.textContent = '🔄 شروع همگام‌سازی جمعی';
            refreshSyncCount();
        }

        step();
    });

    // ── helpers ──────────────────────────────
    function parseNum(v) {
        if (v === '' || v === null || v === undefined) return 0;
        return parseFloat(String(v).replace(/[,،\s]/g, '')) || 0;
    }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function showAlert(type, msg) {
        var el = document.getElementById('ex-alert-box');
        if (el) el.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
    }

    // init
    refreshSyncCount();

})();