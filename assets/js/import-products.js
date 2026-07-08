(function () {

    var rawData  = [];
    var BATCH    = 100; // هر بار ۱۰۰ ردیف

    var colFields = [
        { key: 'name',           label: 'نام کالا',    required: true  },
        { key: 'quantity',       label: 'تعداد',        required: true  },
        { key: 'purchase_price', label: 'قیمت خرید',   required: true  },
        { key: 'regular_price',  label: 'قیمت فروش',   required: false },
        { key: 'sale_rate',      label: 'نرخ فروش (%)', required: false },
    ];

    // ── Shamsi datepicker ─────────────────────
    $(function () {
        var today = new persianDate();
        $('#inp-date-display').val(
            today.year() + '/' + pad(today.month()) + '/' + pad(today.date())
        );
        $('#inp-date-display').persianDatepicker({
            format: 'YYYY/MM/DD', initialValue: true, autoClose: true,
            calendarType: 'persian', calendar: { persian: { locale: 'fa' } },
            onSelect: function (unix) {
                var gd = new persianDate(unix).toCalendar('gregorian');
                $('#inp-date-val').val(gd.year() + '-' + pad(gd.month()) + '-' + pad(gd.date()));
            }
        });
    });

    window.switchImportTab = function (tab, btn) {
        document.querySelectorAll('.import-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.import-pane').forEach(function (p) { p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('pane-' + tab).classList.add('active');
    };

    // ── File ─────────────────────────────────
    var dropZone  = document.getElementById('drop-zone');
    var fileInput = document.getElementById('file-input');

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
        document.getElementById('file-name').textContent = '📄 ' + file.name;
        document.getElementById('file-name').style.display = 'block';
        showAlert('info', '⏳ در حال خواندن فایل...');

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var wb  = XLSX.read(e.target.result, { type: 'array' });
                var ws  = wb.Sheets[wb.SheetNames[0]];
                rawData = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                buildColMap();
                document.getElementById('col-map-card').style.display = 'block';
                showAlert('success', '✓ فایل لود شد — ' + (rawData.length - 1) + ' ردیف');
            } catch(err) {
                showAlert('error', 'خطا در خواندن فایل: ' + err.message);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // ── Column mapping ────────────────────────
    function buildColMap() {
        var hRow = parseInt(document.getElementById('inp-header-row').value || 1) - 1;
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
                '<select id="map-' + f.key + '">' + o + '</select></div>';
        });
        document.getElementById('col-map-wrap').innerHTML = html;
    }

    function autoDetect(hdrs) {
        var m = {}, p = {
            name:           /نام|كالا|کالا|عنوان|product|title/i,
            quantity:       /تعداد|qty|quantity|موجودي|موجودی/i,
            purchase_price: /خرید|خريد|purchase|cost/i,
            regular_price:  /فروش|price/i,
            sale_rate:      /نرخ|rate|درصد/i,
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
            var el = document.getElementById('map-' + f.key);
            m[f.key] = el ? parseInt(el.value) : -1;
        });
        return m;
    }

    // ── Parse rows (بدون رندر DOM) ───────────
    function parseRows() {
        var hRow    = parseInt(document.getElementById('inp-header-row').value || 1) - 1;
        var mapping = getMapping();
        var rows    = [];

        rawData.slice(hRow + 1).forEach(function(row){
            var name  = String(row[mapping.name] || '').trim();
            var qty   = parseNum(row[mapping.quantity]);
            var buyP  = parseNum(row[mapping.purchase_price]);
            var sellP = mapping.regular_price >= 0 ? parseNum(row[mapping.regular_price]) : 0;
            var rate  = mapping.sale_rate     >= 0 ? parseNum(row[mapping.sale_rate])     : 0;
            if (!name || qty <= 0 || buyP <= 0) return;

            var saleP = 0, disc = 0;
            if (rate > 0 && rate < 100 && sellP > 0) {
                saleP = Math.round(sellP * rate / 100);
                disc  = Math.round(100 - rate);
            }
            rows.push({
                name:             name,
                quantity:         qty,
                purchase_price:   buyP,
                regular_price:    sellP > 0 ? sellP : null,
                sale_price:       saleP > 0 ? saleP : null,
                discount_percent: disc  > 0 ? disc  : null,
            });
        });
        return rows;
    }

    // ── Import ────────────────────────────────
    document.getElementById('btn-import').addEventListener('click', startImport);

    function startImport() {
        var catId    = document.getElementById('sel-cat').value;
        var supplier = document.getElementById('inp-supplier').value.trim();
        var date     = document.getElementById('inp-date-val').value;
        var mapping  = getMapping();

        if (!catId)              { showAlert('error', 'دسته‌بندی را انتخاب کنید');    return; }
        if (!supplier)           { showAlert('error', 'نام فروشنده را وارد کنید');    return; }
        if (!date)               { showAlert('error', 'تاریخ فاکتور را وارد کنید');  return; }
        if (mapping.name === -1) { showAlert('error', 'ستون «نام کالا» را انتخاب کنید'); return; }
        if (mapping.quantity === -1)        { showAlert('error', 'ستون «تعداد» را انتخاب کنید'); return; }
        if (mapping.purchase_price === -1)  { showAlert('error', 'ستون «قیمت خرید» را انتخاب کنید'); return; }
        if (!rawData.length)     { showAlert('error', 'ابتدا فایل را انتخاب کنید');   return; }

        // parse بدون DOM
        var allRows = parseRows();
        if (!allRows.length) { showAlert('error', 'ردیف قابل ثبتی یافت نشد'); return; }

        // نمایش progress
        document.getElementById('btn-import').disabled     = true;
        document.getElementById('import-section').style.display = 'block';
        document.getElementById('st-total').textContent   = allRows.length;
        document.getElementById('st-done').textContent    = 0;
        document.getElementById('st-error').textContent   = 0;

        // ارسال batch ای
        sendBatches(allRows, catId, supplier, date);
    }

    function sendBatches(allRows, catId, supplier, date) {
        var total  = allRows.length;
        var done   = 0;
        var errors = 0;
        var offset = 0;

        function next() {
            if (offset >= total) {
                // تمام
                var msg = '✅ ' + done + ' محصول از ' + total + ' ثبت شد';
                if (errors) msg += ' | ' + errors + ' خطا';
                showAlert(errors ? 'info' : 'success', msg);
                document.getElementById('btn-import').disabled = false;
                return;
            }

            var batch = allRows.slice(offset, offset + BATCH);
            offset += BATCH;

            fetch(AJAX + 'import-invoice-batch.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    category_id:   catId,
                    supplier_name: supplier,
                    invoice_date:  date,
                    rows:          batch,
                }),
            })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.success) {
                        done   += res.done   || 0;
                        errors += res.errors || 0;
                    } else {
                        errors += batch.length;
                    }
                    document.getElementById('st-done').textContent  = done;
                    document.getElementById('st-error').textContent = errors;
                    updateProgress(offset, total);
                    // batch بعدی
                    setTimeout(next, 50);
                })
                .catch(function(err){
                    errors += batch.length;
                    document.getElementById('st-error').textContent = errors;
                    updateProgress(offset, total);
                    setTimeout(next, 50);
                });
        }

        next();
    }

    function updateProgress(done, total) {
        var pct = total > 0 ? Math.round(Math.min(done, total) / total * 100) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('prog-text').textContent   =
            Math.min(done, total) + ' از ' + total + ' (' + pct + '%)';
    }

    // reset
    document.getElementById('btn-reset').addEventListener('click', function(){
        rawData = [];
        document.getElementById('col-map-card').style.display    = 'none';
        document.getElementById('import-section').style.display  = 'none';
        document.getElementById('file-name').style.display       = 'none';
        document.getElementById('btn-import').disabled           = false;
        document.getElementById('alert-box').innerHTML           = '';
        fileInput.value = '';
    });

    // helpers
    function parseNum(v) {
        if (v === '' || v === null || v === undefined) return 0;
        return parseFloat(String(v).replace(/[,،\s]/g, '')) || 0;
    }
    function pad(n) { return String(n).padStart(2, '0'); }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function showAlert(type, msg) {
        var el = document.getElementById('alert-box');
        if (el) el.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
    }

})();