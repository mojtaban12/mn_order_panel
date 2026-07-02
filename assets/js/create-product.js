(function () {

    // imagesList و AJAX_URL و CURRENCY از صفحه PHP تزریق می‌شن
    if (typeof imagesList === 'undefined') window.imagesList = [];
    if (typeof panelCategoryId === 'undefined') window.panelCategoryId = 0;

    const UPLOAD_URL = '../ajax/upload-image.php';

    // ════════════════════════════════════════
    // Panel Category Search
    // ════════════════════════════════════════
    var pcatInput    = document.getElementById('pcat-search');
    var pcatDropdown = document.getElementById('pcat-dropdown');
    var pcatSelected = document.getElementById('pcat-selected');
    var pcatDot      = document.getElementById('pcat-dot');
    var pcatNameDisp = document.getElementById('pcat-name-display');
    var pcatClear    = document.getElementById('pcat-clear');
    var pcatHidden   = document.getElementById('panel_category_id');
    var pcatRequired = document.getElementById('pcat-required');
    var pcatTimer    = null;
    var pcatAllCats  = []; // کش همه دسته‌ها

    // بارگذاری همه دسته‌ها یکبار
    function loadAllPCats(callback) {
        if (pcatAllCats.length) { if (callback) callback(pcatAllCats); return; }
        fetch(typeof PANEL_CATS_URL !== 'undefined' ? PANEL_CATS_URL : '../ajax/panel-categories.php')
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (res.success) {
                    pcatAllCats = res.categories || [];
                    if (callback) callback(pcatAllCats);
                    // حالت edit: اگر panelCategoryId داشتیم نشون بده
                    if (panelCategoryId) {
                        var found = pcatAllCats.find(function(c){ return c.id == panelCategoryId; });
                        if (found) setPCat(found);
                    }
                }
            })
            .catch(function(){});
    }

    function filterPCats(q) {
        if (!q) return pcatAllCats;
        q = q.toLowerCase();
        return pcatAllCats.filter(function(c){
            return c.name.toLowerCase().indexOf(q) !== -1
                || (c.parent_name && c.parent_name.toLowerCase().indexOf(q) !== -1);
        });
    }

    function renderPCatDropdown(cats) {
        if (!cats.length) {
            pcatDropdown.innerHTML = '<div class="pcat-dd-empty">دسته‌ای یافت نشد</div>';
        } else {
            pcatDropdown.innerHTML = cats.map(function(c) {
                var dot = '<span class="pcat-color-dot" style="background:' + escHtml(c.color || '#e5e7eb') + '"></span>';
                var parent = c.parent_name
                    ? '<span class="pcat-parent">' + escHtml(c.parent_name) + '</span>'
                    : '';
                return '<div class="pcat-dd-item" data-id="' + c.id + '" data-name="' + escHtml(c.name) + '" data-color="' + escHtml(c.color||'#e5e7eb') + '">'
                    + dot
                    + '<span>' + escHtml(c.name) + '</span>'
                    + parent
                    + '</div>';
            }).join('');

            pcatDropdown.querySelectorAll('.pcat-dd-item').forEach(function(el) {
                el.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    setPCat({ id: this.dataset.id, name: this.dataset.name, color: this.dataset.color });
                });
            });
        }
        pcatDropdown.classList.add('open');
    }

    function setPCat(cat) {
        pcatHidden.value       = cat.id;
        pcatInput.value        = cat.name;
        pcatNameDisp.textContent = cat.name;
        pcatDot.style.background = cat.color || '#e5e7eb';
        pcatSelected.classList.add('show');
        pcatDropdown.classList.remove('open');
        pcatRequired.style.display = 'none';
    }

    function clearPCat() {
        pcatHidden.value = '';
        pcatInput.value  = '';
        pcatSelected.classList.remove('show');
    }

    if (pcatInput) {
        // focus → نشون بده همه
        pcatInput.addEventListener('focus', function() {
            loadAllPCats(function(cats) {
                renderPCatDropdown(filterPCats(pcatInput.value.trim()));
            });
        });

        pcatInput.addEventListener('input', function() {
            clearTimeout(pcatTimer);
            pcatHidden.value = '';
            pcatSelected.classList.remove('show');
            var q = this.value.trim();
            pcatTimer = setTimeout(function() {
                loadAllPCats(function(){ renderPCatDropdown(filterPCats(q)); });
            }, 200);
        });

        pcatInput.addEventListener('blur', function() {
            setTimeout(function(){ pcatDropdown.classList.remove('open'); }, 200);
        });

        pcatClear.addEventListener('click', clearPCat);

        // دکمه افزودن دسته inline
        var btnAddCat = document.getElementById('btn-add-cat-inline');
        if (btnAddCat) {
            btnAddCat.addEventListener('click', function () {
                MNCategoryPicker.open(function (id, name, color) {
                    // کش رو reset کن تا دفعه بعد دوباره لود بشه
                    pcatAllCats = [];
                    // انتخاب دسته جدید
                    setPCat({ id: id, name: name, color: color || '#3b82f6' });
                });
            });
        }

        loadAllPCats();
    }

    // ────────────────────────────────────────
    var wpSearchTimer   = null;
    var wpSelectedWpId  = null;   // WP product id انتخاب‌شده

    var wpInput    = document.getElementById('wp-search-input');
    var wpDropdown = document.getElementById('wp-search-dropdown');
    var wpBar      = document.getElementById('wp-selected-bar');
    var wpClearBtn = document.getElementById('wp-clear-btn');
    var wpXBtn     = document.getElementById('wp-search-clear-btn');

    if (wpInput) {
        wpInput.addEventListener('input', function () {
            var q = this.value.trim();
            wpXBtn.style.display = q ? 'block' : 'none';
            clearTimeout(wpSearchTimer);
            if (q.length < 2) { closeWpDropdown(); return; }
            wpSearchTimer = setTimeout(function () { doWpSearch(q, 1); }, 350);
        });

        wpInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeWpDropdown();
        });

        document.addEventListener('click', function (e) {
            if (!document.getElementById('wp-search-card').contains(e.target)) {
                closeWpDropdown();
            }
        });
    }

    if (wpXBtn) {
        wpXBtn.addEventListener('click', function () {
            wpInput.value = '';
            this.style.display = 'none';
            closeWpDropdown();
        });
    }

    if (wpClearBtn) {
        wpClearBtn.addEventListener('click', clearWpSelection);
    }

    function doWpSearch(q, page) {
        wpDropdown.innerHTML = '<div class="wp-msg">⏳ در حال جستجو...</div>';
        wpDropdown.classList.add('open');

        var url = (typeof WP_SEARCH_URL !== 'undefined' ? WP_SEARCH_URL : '../ajax/search-wp-products.php')
                  + '?q=' + encodeURIComponent(q) + '&page=' + page;

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.items || res.items.length === 0) {
                    wpDropdown.innerHTML = '<div class="wp-msg">نتیجه‌ای یافت نشد</div>';
                    return;
                }
                renderWpDropdown(res.items, q, page, res.pagination);
            })
            .catch(function () {
                wpDropdown.innerHTML = '<div class="wp-msg" style="color:#dc2626;">خطا در جستجو</div>';
            });
    }

    function renderWpDropdown(items, q, page, pag) {
        var html = '';
        items.forEach(function (item) {
            var thumb = item.image_url
                ? '<img class="wp-thumb" src="' + escHtml(item.image_url) + '" onerror="this.style.display=\'none\'">'
                : '<div class="wp-thumb-ph">📦</div>';
            var price = item.regular_price ? parseFloat(item.regular_price).toLocaleString('fa-IR') + ' ' + (typeof CUR !== 'undefined' ? CUR : '') : '—';
            var sku   = item.sku ? 'SKU: ' + escHtml(item.sku) : 'WP #' + item.wp_id;

            html += '<div class="wp-search-item" data-idx="' + encodeURIComponent(JSON.stringify(item)) + '">'
                  + thumb
                  + '<div class="wp-item-info"><div class="wp-item-name">' + escHtml(item.title) + '</div>'
                  + '<div class="wp-item-sku">' + sku + '</div></div>'
                  + '<div class="wp-item-price">' + price + '</div>'
                  + '</div>';
        });

        if (pag && pag.more) {
            html += '<div class="wp-msg" style="cursor:pointer;color:#2563eb;" id="wp-load-more">نمایش بیشتر ▼</div>';
        }

        wpDropdown.innerHTML = html;

        // کلیک روی آیتم
        wpDropdown.querySelectorAll('.wp-search-item').forEach(function (el) {
            el.addEventListener('click', function () {
                var item = JSON.parse(decodeURIComponent(this.dataset.idx));
                selectWpProduct(item);
            });
        });

        // load more
        var moreBtn = document.getElementById('wp-load-more');
        if (moreBtn) {
            moreBtn.addEventListener('click', function () {
                doWpSearch(q, page + 1);
            });
        }
    }

    function selectWpProduct(item) {
        wpSelectedWpId = item.wp_id;
        closeWpDropdown();

        // نمایش badge
        document.getElementById('wp-selected-name').textContent = item.title;
        document.getElementById('wp-selected-id').textContent   = item.wp_id;
        wpBar.classList.add('show');
        wpInput.value = item.title;
        wpXBtn.style.display = 'none';

        // ── پر کردن فرم ──────────────────────
        setVal('title',          item.title);
        setVal('sku',            item.sku || '');   // از WP — readonly
        setVal('wp_product_id',  item.wp_id);       // از WP — readonly
        setVal('regular_price',  item.regular_price || '');
        setVal('sale_price',     item.sale_price || '');
        setVal('stock_quantity', item.stock_quantity !== null ? item.stock_quantity : '');
        setVal('real_stock_quantity', item.stock_quantity !== null ? item.stock_quantity : '');
        setVal('weight',         item.weight || '');
        setVal('length',         item.length || '');
        setVal('width',          item.width  || '');
        setVal('height',         item.height || '');

        // manage_stock
        var msEl = document.getElementById('manage_stock');
        if (msEl) { msEl.checked = !!item.manage_stock; toggleStockFields(); }

        // stock_status
        var ssEl = document.getElementById('stock_status');
        if (ssEl) ssEl.value = item.stock_status || 'instock';

        // discount_percent
        if (item.regular_price && item.sale_price && parseFloat(item.sale_price) > 0) {
            var d = (1 - item.sale_price / item.regular_price) * 100;
            setVal('discount_percent', d.toFixed(2));
        }

        // status → active
        var statusActive = document.querySelector('[name=status][value=active]');
        if (statusActive) { statusActive.checked = true; statusActive.dispatchEvent(new Event('change')); }

        // ── پر کردن ویژگی‌ها ─────────────────
        var attrList = document.getElementById('attributes-list');
        if (attrList && item.attributes && item.attributes.length > 0) {
            attrList.innerHTML = '';
            item.attributes.forEach(function(attr) {
                var row = makeAttributeItem(
                    attr.label,              // nameVal
                    attr.label,              // labelVal
                    attr.term_name,          // valueVal
                    attr.taxonomy,           // taxonomyVal    (pa_color)
                    attr.term_id,            // termIdVal
                    attr.attribute_id || '', // attributeIdVal
                    attr.attribute_name || ''// attributeNameVal (color)
                );
                attrList.appendChild(row);
            });
        }

        updateSummary();

        // ── دانلود تصاویر ────────────────────
        if (item.thumbnail_id || item.image_url) {
            downloadWpImages(item.wp_id);
        }
    }

    function downloadWpImages(wpId) {
        var url = typeof WP_IMAGES_URL !== 'undefined' ? WP_IMAGES_URL : '../ajax/fetch-wp-images.php';

        // placeholder در grid
        var placeholder = document.createElement('div');
        placeholder.className = 'image-thumb uploading';
        placeholder.id        = 'wp-img-loading';
        placeholder.innerHTML = '<div class="broken-placeholder" style="display:flex;font-size:18px;flex-direction:column;gap:4px;">⏳<span style="font-size:10px;">دانلود...</span></div>';
        var grid = document.getElementById('images-grid');
        var empty = document.getElementById('images-empty');
        if (empty) empty.style.display = 'none';
        if (grid)  { grid.style.display = 'grid'; grid.appendChild(placeholder); }

        fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ wp_product_id: wpId, product_id: 0 }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var loader = document.getElementById('wp-img-loading');
            if (loader) loader.remove();

            if (res.success && res.images && res.images.length > 0) {
                imagesList = [];
                res.images.forEach(function (img) {
                    imagesList.push({ url: img.url, alt: '', is_primary: !!img.is_primary });
                });
                renderImages();
            }
        })
        .catch(function () {
            var loader = document.getElementById('wp-img-loading');
            if (loader) loader.remove();
            renderImages();
        });
    }

    function clearWpSelection() {
        wpSelectedWpId = null;
        wpBar.classList.remove('show');
        wpInput.value = '';
        wpXBtn.style.display = 'none';
        setVal('wp_product_id', '');
    }

    function closeWpDropdown() {
        wpDropdown.classList.remove('open');
        wpDropdown.innerHTML = '';
    }

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el) el.value = val;
    }

    // ── readonly برای SKU و wp_product_id ────
    // این دو فیلد فقط از WP می‌آن یا auto generate می‌شن
    function makeReadonly(id, hint) {
        var el = document.getElementById(id);
        if (!el) return;
        el.readOnly    = true;
        el.style.background = '#f3f4f6';
        el.style.color      = '#6b7280';
        el.style.cursor     = 'default';
        if (hint) {
            var small = document.createElement('div');
            small.style.cssText = 'font-size:11px;color:#9ca3af;margin-top:3px;';
            small.textContent   = hint;
            if (el.parentNode && !el.parentNode.querySelector('.readonly-hint')) {
                small.className = 'readonly-hint';
                el.parentNode.appendChild(small);
            }
        }
    }

    // wp_product_id همیشه readonly
    makeReadonly('wp_product_id', 'از وردپرس — غیرقابل تغییر');

    // SKU: اگر edit است → readonly (مقدار از server می‌آد)
    //       اگر جدید است → readonly + placeholder auto
    var isEditMode = !!document.querySelector('[name=edit_id]');
    if (isEditMode) {
        makeReadonly('sku', 'غیرقابل تغییر پس از ثبت');
    } else {
        makeReadonly('sku', 'خودکار تولید می‌شود (مثال: P_30000)');
        // placeholder نشون بده
        var skuEl = document.getElementById('sku');
        if (skuEl && !skuEl.value) skuEl.placeholder = 'P_30000 — خودکار';
    }

    // ────────────────────────────────────────
    const imagesGrid  = document.getElementById('images-grid');
    const imagesEmpty = document.getElementById('images-empty');
    const imagesCount = document.getElementById('images-count');

    // ════════════════════════════════════════
    // render
    // ════════════════════════════════════════
    function renderImages() {
        imagesGrid.innerHTML = '';
        if (imagesList.length === 0) {
            imagesGrid.style.display = 'none';
            imagesEmpty.style.display = 'block';
            imagesCount.textContent = '(۰ تصویر)';
            return;
        }
        imagesEmpty.style.display = 'none';
        imagesGrid.style.display  = 'grid';
        imagesCount.textContent   = '(' + imagesList.length + ' تصویر)';

        imagesList.forEach((img, idx) => {
            const div = document.createElement('div');
            div.className = 'image-thumb' + (img.is_primary ? ' is-primary' : '');
            div.innerHTML =
                '<img src="' + escHtml(img.url) + '" alt="' + escHtml(img.alt || '') + '" onerror="this.classList.add(\'broken\')">' +
                '<div class="broken-placeholder">⚠️<br>خطا در بارگذاری</div>' +
                '<div class="image-thumb-actions">' +
                    (!img.is_primary
                        ? '<button type="button" class="thumb-btn primary-btn" data-action="primary" data-idx="' + idx + '">اصلی</button>'
                        : '<span></span>'
                    ) +
                    '<button type="button" class="thumb-btn" data-action="remove" data-idx="' + idx + '">×</button>' +
                '</div>' +
                (img.is_primary ? '<span class="primary-badge">اصلی</span>' : '');

            div.querySelectorAll('[data-action]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var i = parseInt(this.dataset.idx);
                    if (this.dataset.action === 'remove') {
                        imagesList.splice(i, 1);
                        if (imagesList.length > 0 && !imagesList.some(function(x){ return x.is_primary; }))
                            imagesList[0].is_primary = true;
                    } else if (this.dataset.action === 'primary') {
                        imagesList.forEach(function(x){ x.is_primary = false; });
                        imagesList[i].is_primary = true;
                    }
                    renderImages();
                });
            });
            imagesGrid.appendChild(div);
        });
    }

    function addImage(url, alt) {
        url = (url || '').trim();
        if (!url) return false;
        if (imagesList.some(function(x){ return x.url === url; })) return false;
        imagesList.push({ url: url, alt: alt || '', is_primary: imagesList.length === 0 });
        renderImages();
        return true;
    }

    function addUploadingThumb() {
        var div = document.createElement('div');
        div.className = 'image-thumb uploading';
        div.innerHTML =
            '<div class="broken-placeholder" style="display:flex;font-size:22px;">⏳</div>' +
            '<div class="upload-progress"><div class="upload-progress-bar" style="width:0%"></div></div>';
        imagesEmpty.style.display = 'none';
        imagesGrid.style.display  = 'grid';
        imagesGrid.appendChild(div);
        return div;
    }

    // ════════════════════════════════════════
    // تب‌ها
    // ════════════════════════════════════════
    document.querySelectorAll('.img-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.img-tab').forEach(function(t){ t.classList.remove('active'); });
            this.classList.add('active');
            var target = this.dataset.tab;
            document.getElementById('tab-upload').style.display = target === 'upload' ? '' : 'none';
            document.getElementById('tab-url').style.display    = target === 'url'    ? '' : 'none';
        });
    });

    // ════════════════════════════════════════
    // آپلود
    // ════════════════════════════════════════
    function uploadFile(file) {
        return new Promise(function(resolve) {
            var thumb = addUploadingThumb();
            var bar   = thumb.querySelector('.upload-progress-bar');
            var xhr   = new XMLHttpRequest();
            var form  = new FormData();
            form.append('image', file);

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable)
                    bar.style.width = Math.round(e.loaded / e.total * 100) + '%';
            });

            xhr.addEventListener('load', function() {
                thumb.remove();
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        addImage(res.url, file.name.replace(/\.[^.]+$/, ''));
                    } else {
                        showAlert('error', 'خطا در آپلود «' + file.name + '»: ' + res.message);
                    }
                } catch(ex) {
                    showAlert('error', 'پاسخ نامعتبر از سرور');
                }
                resolve();
            });

            xhr.addEventListener('error', function() {
                thumb.remove();
                showAlert('error', 'خطا در ارتباط با سرور هنگام آپلود');
                resolve();
            });

            xhr.open('POST', UPLOAD_URL);
            xhr.send(form);
        });
    }

    async function handleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            if (!file.type.startsWith('image/')) {
                showAlert('error', '«' + file.name + '» یک تصویر نیست'); continue;
            }
            if (file.size > 5 * 1024 * 1024) {
                showAlert('error', '«' + file.name + '» بیشتر از ۵ مگابایت است'); continue;
            }
            await uploadFile(file);
        }
    }

    var uploadZone = document.getElementById('upload-zone');
    var fileInput  = document.getElementById('file-input');

    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
        this.value = '';
    });

    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault(); uploadZone.classList.add('dragover');
    });
    uploadZone.addEventListener('dragleave', function() {
        uploadZone.classList.remove('dragover');
    });
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    // ════════════════════════════════════════
    // URL
    // ════════════════════════════════════════
    document.getElementById('btn-add-url').addEventListener('click', function() {
        var input = document.getElementById('new-image-url');
        var url   = input.value.trim();
        if (!url) { input.focus(); flashBorder(input, '#ef4444'); return; }
        if (!addImage(url, '')) { flashBorder(input, '#f59e0b'); return; }
        input.value = '';
        input.focus();
    });

    document.getElementById('new-image-url').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btn-add-url').click(); }
    });

    function flashBorder(el, color) {
        el.style.borderColor = color;
        setTimeout(function(){ el.style.borderColor = ''; }, 1500);
    }

    renderImages();

    // ════════════════════════════════════════
    // Status radios
    // ════════════════════════════════════════
    document.querySelectorAll('.status-radio input').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.status-radio').forEach(function(el){
                el.classList.remove('selected-active','selected-inactive','selected-draft');
            });
            if (this.value === 'active')   this.closest('.status-radio').classList.add('selected-active');
            if (this.value === 'inactive') this.closest('.status-radio').classList.add('selected-inactive');
            if (this.value === 'draft')    this.closest('.status-radio').classList.add('selected-draft');
        });
    });

    // ════════════════════════════════════════
    // manage_stock
    // ════════════════════════════════════════
    var manageStockCb = document.getElementById('manage_stock');
    var stockFields   = document.getElementById('stock-fields');
    function toggleStockFields() {
        stockFields.style.display = manageStockCb.checked ? 'block' : 'none';
    }
    manageStockCb.addEventListener('change', toggleStockFields);
    toggleStockFields();

    // ════════════════════════════════════════
    // Price summary
    // ════════════════════════════════════════
    var priceSummary   = document.getElementById('price-summary');
    var summaryRegular = document.getElementById('summary-regular');
    var summarySale    = document.getElementById('summary-sale');
    var summaryProfit  = document.getElementById('summary-profit');

    function updateSummary() {
        var regular  = parseFloat(document.getElementById('regular_price').value)  || 0;
        var purchase = parseFloat(document.getElementById('purchase_price').value) || 0;
        var sale     = parseFloat(document.getElementById('sale_price').value)     || 0;
        if (regular > 0) {
            priceSummary.style.display = 'block';
            summaryRegular.textContent = regular.toLocaleString('fa-IR')  + ' ' + CURRENCY;
            summarySale.textContent    = sale > 0 ? sale.toLocaleString('fa-IR') + ' ' + CURRENCY : '—';
            var effective = sale > 0 ? sale : regular;
            summaryProfit.textContent  = purchase > 0 ? (effective - purchase).toLocaleString('fa-IR') + ' ' + CURRENCY : '—';
        } else {
            priceSummary.style.display = 'none';
        }
    }

    ['regular_price','purchase_price','sale_price'].forEach(function(id){
        document.getElementById(id).addEventListener('input', updateSummary);
    });
    updateSummary();

    document.getElementById('discount_percent').addEventListener('input', function() {
        var regular = parseFloat(document.getElementById('regular_price').value) || 0;
        var pct     = parseFloat(this.value) || 0;
        if (regular > 0 && pct > 0) {
            document.getElementById('sale_price').value = Math.round(regular * (1 - pct / 100));
            updateSummary();
        }
    });
    document.getElementById('sale_price').addEventListener('input', function() {
        var regular = parseFloat(document.getElementById('regular_price').value) || 0;
        var sale    = parseFloat(this.value) || 0;
        if (regular > 0 && sale > 0)
            document.getElementById('discount_percent').value = ((1 - sale / regular) * 100).toFixed(2);
    });

    // ════════════════════════════════════════
    // Dynamic lists
    // ════════════════════════════════════════
    function makeCategoryItem(idVal, nameVal) {
        var div = document.createElement('div');
        div.className = 'dynamic-item cat-item';
        div.innerHTML =
            '<input type="text" class="form-control cat-id" placeholder="ID (اختیاری)" style="width:90px;flex-shrink:0" value="' + escHtml(idVal||'') + '">' +
            '<input type="text" class="form-control cat-name" placeholder="نام دسته‌بندی..." value="' + escHtml(nameVal||'') + '">' +
            '<button type="button" class="btn-remove-item" title="حذف">×</button>';
        div.querySelector('.btn-remove-item').addEventListener('click', function(){ div.remove(); });
        return div;
    }

    /**
     * ساخت ردیف ویژگی
     * nameVal       = برچسب فارسی (رنگ)
     * labelVal      = (استفاده نمیشه — برای سازگاری)
     * valueVal      = مقدار (قرمز)
     * taxonomyVal   = pa_color
     * termIdVal     = term_id در WC
     * attributeIdVal = attribute_id در wc_product_attributes
     */
    function makeAttributeItem(nameVal, labelVal, valueVal, taxonomyVal, termIdVal, attributeIdVal, attributeNameVal) {
        var div = document.createElement('div');
        div.className = 'dynamic-item attr-item';

        var attrSearch = typeof WP_ATTR_SEARCH_URL !== 'undefined'
            ? WP_ATTR_SEARCH_URL : '../ajax/search-wp-attributes.php';

        // attribute_name = slug بدون pa_ (مثل color)
        var attrName = attributeNameVal || (taxonomyVal ? taxonomyVal.replace('pa_', '') : '');

        div.innerHTML =
            '<input type="hidden" class="attr-taxonomy"      value="' + escHtml(taxonomyVal    ||'') + '">' +
            '<input type="hidden" class="attr-term-id"       value="' + escHtml(termIdVal      ||'') + '">' +
            '<input type="hidden" class="attr-attribute-id"  value="' + escHtml(attributeIdVal ||'') + '">' +
            '<input type="hidden" class="attr-attribute-name" value="' + escHtml(attrName      ||'') + '">' +
            '<div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;">' +
              '<div style="position:relative;">' +
                '<input type="text" class="form-control attr-name" placeholder="نام ویژگی (مثل: رنگ)" value="' + escHtml(nameVal||'') + '">' +
                '<div class="attr-name-dropdown attr-dd"></div>' +
              '</div>' +
              '<div style="position:relative;">' +
                '<input type="text" class="form-control attr-value" placeholder="مقدار (مثل: قرمز)..." value="' + escHtml(valueVal||'') + '">' +
                '<div class="attr-value-dropdown attr-dd"></div>' +
              '</div>' +
            '</div>' +
            '<button type="button" class="btn-remove-item" title="حذف">×</button>';

        div.querySelector('.btn-remove-item').addEventListener('click', function(){ div.remove(); });

        var nameInput    = div.querySelector('.attr-name');
        var valueInput   = div.querySelector('.attr-value');
        var nameDd       = div.querySelector('.attr-name-dropdown');
        var valueDd      = div.querySelector('.attr-value-dropdown');
        var hidTaxonomy  = div.querySelector('.attr-taxonomy');
        var hidTermId    = div.querySelector('.attr-term-id');
        var hidAttrId    = div.querySelector('.attr-attribute-id');
        var hidAttrName  = div.querySelector('.attr-attribute-name');

        function openDd(dd, html) {
            dd.innerHTML  = html;
            dd.style.cssText = 'display:block;position:absolute;top:100%;right:0;left:0;background:#fff;' +
                'border:1px solid #bfdbfe;border-radius:0 0 7px 7px;' +
                'box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:1000;max-height:200px;overflow-y:auto;';
        }
        function closeDd(dd) { dd.style.display = 'none'; dd.innerHTML = ''; }

        // ── سرچ نام attribute با label فارسی ──
        var nameTimer    = null;
        var cachedTerms  = []; // terms آخرین attribute انتخاب‌شده

        nameInput.addEventListener('input', function() {
            // ریست هر بار که کاربر تایپ می‌کنه
            hidTaxonomy.value = '';
            hidAttrId.value   = '';
            hidAttrName.value = '';
            hidTermId.value   = '';
            cachedTerms       = [];
            var q = this.value.trim();
            clearTimeout(nameTimer);
            closeDd(nameDd);
            closeDd(valueDd);
            if (!q) return;

            nameTimer = setTimeout(function() {
                fetch(attrSearch + '?mode=attribute&q=' + encodeURIComponent(q))
                    .then(function(r){ return r.json(); })
                    .then(function(res) {
                        var rows = [];
                        if (res.success && res.attributes && res.attributes.length) {
                            rows = res.attributes.map(function(a) {
                                // terms رو serialize کن توی data attribute
                                var termsJson = encodeURIComponent(JSON.stringify(a.terms || []));
                                return '<div class="attr-dd-item" ' +
                                    'data-taxonomy="' + escHtml(a.taxonomy) + '" ' +
                                    'data-label="'   + escHtml(a.label)    + '" ' +
                                    'data-id="'      + a.id                + '" ' +
                                    'data-terms="'   + termsJson           + '" ' +
                                    'style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">' +
                                    '<div>' +
                                      '<div style="font-weight:600;font-size:13px;">' + escHtml(a.label) + '</div>' +
                                      '<div style="font-size:11px;color:#9ca3af;">' + escHtml(a.taxonomy) + '</div>' +
                                    '</div>' +
                                    (a.terms && a.terms.length
                                        ? '<span style="font-size:10px;background:#dbeafe;color:#1e40af;padding:2px 7px;border-radius:10px;">' + a.terms.length + ' مقدار</span>'
                                        : '<span style="font-size:10px;color:#d1d5db;">بدون مقدار</span>') +
                                    '</div>';
                            });
                        }
                        // گزینه «جدید»
                        rows.push(
                            '<div class="attr-dd-item attr-dd-new" ' +
                            'style="padding:9px 14px;cursor:pointer;color:#7c3aed;font-size:12px;border-top:1px solid #e5e7eb;">' +
                            '✨ ایجاد ویژگی «' + escHtml(nameInput.value.trim()) + '» در وردپرس' +
                            '</div>'
                        );

                        openDd(nameDd, rows.join(''));

                        // کلیک روی attribute موجود
                        nameDd.querySelectorAll('.attr-dd-item[data-taxonomy]').forEach(function(el) {
                            el.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                var terms = JSON.parse(decodeURIComponent(this.dataset.terms || '[]'));
                                nameInput.value   = this.dataset.label;
                                hidTaxonomy.value = this.dataset.taxonomy;
                                hidAttrId.value   = this.dataset.id;
                                hidAttrName.value = this.dataset.taxonomy.replace('pa_', '');
                                hidTermId.value   = '';
                                valueInput.value  = '';
                                cachedTerms       = terms;
                                closeDd(nameDd);
                                // باز کردن dropdown مقدار با terms موجود
                                if (terms.length) {
                                    showTermsDropdown(terms, '');
                                }
                                valueInput.focus();
                            });
                        });

                        // کلیک روی «ایجاد جدید»
                        var newItem = nameDd.querySelector('.attr-dd-new');
                        if (newItem) {
                            newItem.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                closeDd(nameDd);
                                // taxonomy خالی → موقع ذخیره در WC ساخته میشه
                                valueInput.focus();
                            });
                        }
                    })
                    .catch(function(){ closeDd(nameDd); });
            }, 300);
        });
        nameInput.addEventListener('blur', function(){ setTimeout(function(){ closeDd(nameDd); }, 200); });

        // ── نمایش terms در dropdown value ──────
        function showTermsDropdown(terms, filterQ) {
            var filtered = filterQ
                ? terms.filter(function(t){ return t.name.indexOf(filterQ) !== -1; })
                : terms;

            var rows = filtered.map(function(t) {
                return '<div class="attr-dd-item" ' +
                    'data-term-id="' + t.term_id + '" ' +
                    'data-name="' + escHtml(t.name) + '" ' +
                    'style="padding:8px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;">' +
                    escHtml(t.name) + '</div>';
            });

            var currentVal = valueInput.value.trim();
            rows.push(
                '<div class="attr-dd-item attr-dd-new" ' +
                'style="padding:8px 14px;cursor:pointer;color:#2563eb;border-top:1px solid #e5e7eb;font-size:12px;">' +
                '+ افزودن «' + escHtml(currentVal || '...') + '» به عنوان مقدار جدید' +
                '</div>'
            );

            openDd(valueDd, rows.join(''));
            bindTermDdEvents();
        }

        function bindTermDdEvents() {
            valueDd.querySelectorAll('.attr-dd-item[data-term-id]').forEach(function(el) {
                el.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    valueInput.value = this.dataset.name;
                    hidTermId.value  = this.dataset.termId;
                    closeDd(valueDd);
                });
            });
            var newBtn = valueDd.querySelector('.attr-dd-new');
            if (newBtn) {
                newBtn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    hidTermId.value = ''; // term_id خالی → در WC ساخته میشه
                    closeDd(valueDd);
                });
            }
        }

        // ── کاربر در فیلد value تایپ می‌کنه ───
        var valueTimer = null;
        valueInput.addEventListener('input', function() {
            hidTermId.value = '';
            var q        = this.value.trim();
            var taxonomy = hidTaxonomy.value;
            clearTimeout(valueTimer);
            closeDd(valueDd);

            // اگر terms کش شده داریم → فیلتر کن
            if (cachedTerms.length) {
                showTermsDropdown(cachedTerms, q);
                return;
            }

            if (!q) return;

            // درخواست جدید برای term هایی که کش نشدن
            valueTimer = setTimeout(function() {
                var url = attrSearch + '?mode=terms&q=' + encodeURIComponent(q)
                        + (taxonomy ? '&taxonomy=' + encodeURIComponent(taxonomy) : '');

                fetch(url)
                    .then(function(r){ return r.json(); })
                    .then(function(res) {
                        var terms = (res.success && res.terms) ? res.terms : [];
                        showTermsDropdown(terms, q);
                    })
                    .catch(function(){ closeDd(valueDd); });
            }, 300);
        });

        // وقتی روی value کلیک میکنه و terms کش داریم
        valueInput.addEventListener('focus', function() {
            if (cachedTerms.length && !this.value.trim()) {
                showTermsDropdown(cachedTerms, '');
            }
        });

        valueInput.addEventListener('blur', function(){ setTimeout(function(){ closeDd(valueDd); }, 200); });

        return div;
    }

    function loadAttrTerms(taxonomy, attrId, valueInput, dropdown) {
        if (dropdown) { dropdown.style.display = 'none'; }
        if (valueInput) valueInput.focus();
    }


    document.querySelectorAll('.btn-remove-item').forEach(function(btn){
        btn.addEventListener('click', function(){ btn.closest('.dynamic-item').remove(); });
    });
    document.getElementById('add-category').addEventListener('click', function(){
        document.getElementById('categories-list').appendChild(makeCategoryItem());
    });
    document.getElementById('add-attribute').addEventListener('click', function(){
        document.getElementById('attributes-list').appendChild(makeAttributeItem());
    });

    // ════════════════════════════════════════
    // Alert
    // ════════════════════════════════════════
    function showAlert(type, msg) {
        var el = document.getElementById('product-alert');
        el.className = type;
        el.textContent = msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (type === 'success') setTimeout(function(){ el.style.display = 'none'; }, 5000);
    }

    // ════════════════════════════════════════
    // Collect data
    // ════════════════════════════════════════
    function collectData() {
        var get  = function(id){ var el = document.getElementById(id); return el ? el.value.trim() : ''; };
        var getF = function(id){ var v = get(id); return v === '' ? null : parseFloat(v); };
        var getI = function(id){ var v = get(id); return v === '' ? null : parseInt(v, 10); };

        var data = {
            title:               get('title'),
            // sku و wp_product_id در server قفل هستند — فقط برای ثبت جدید از WP می‌آیند
            sku:                 isEditMode ? undefined : (get('sku') || undefined),
            wp_product_id:       isEditMode ? undefined : (get('wp_product_id') || null),
            regular_price:       getF('regular_price'),
            purchase_price:      getF('purchase_price'),
            sale_price:          getF('sale_price'),
            discount_percent:    getF('discount_percent'),
            stock_quantity:      getI('stock_quantity'),
            real_stock_quantity: getI('real_stock_quantity'),
            stock_status:        get('stock_status'),
            manage_stock:        document.getElementById('manage_stock').checked ? 1 : 0,
            weight:              getF('weight'),
            length:              getF('length'),
            width:               getF('width'),
            height:              getF('height'),
            status:              (document.querySelector('[name=status]:checked') || {}).value || 'active',
            categories:          [],
            attributes:          [],
            images:              imagesList.map(function(img){ return { url: img.url, alt: img.alt||'', is_primary: img.is_primary ? 1 : 0 }; }),
        };

        var editId = document.querySelector('[name=edit_id]');
        if (editId) data.edit_id = parseInt(editId.value, 10);

        // دسته داخلی پنل
        var pcatEl = document.getElementById('panel_category_id');
        data.panel_category_id = (pcatEl && pcatEl.value) ? parseInt(pcatEl.value, 10) : null;

        document.querySelectorAll('.cat-item').forEach(function(el){
            var name = el.querySelector('.cat-name').value.trim();
            if (name) data.categories.push({ id: el.querySelector('.cat-id').value.trim() || null, name: name });
        });
        document.querySelectorAll('.attr-item').forEach(function(el){
            var name  = el.querySelector('.attr-name').value.trim();
            var value = el.querySelector('.attr-value').value.trim();
            if (name && value) data.attributes.push({
                name:           name,
                label:          name,
                value:          value,
                taxonomy:       el.querySelector('.attr-taxonomy')      ? el.querySelector('.attr-taxonomy').value      : '',
                attribute_name: el.querySelector('.attr-attribute-name')? el.querySelector('.attr-attribute-name').value: '',
                term_id:        el.querySelector('.attr-term-id')       ? el.querySelector('.attr-term-id').value       : '',
                attribute_id:   el.querySelector('.attr-attribute-id')  ? el.querySelector('.attr-attribute-id').value  : '',
            });
        });

        return data;
    }

    // ════════════════════════════════════════
    // Submit
    // ════════════════════════════════════════
    document.getElementById('product-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        var btn    = document.getElementById('btn-submit');
        var text   = document.getElementById('btn-text');
        var isEdit = !!document.querySelector('[name=edit_id]');

        if (imagesGrid.querySelector('.uploading')) {
            showAlert('error', 'لطفاً صبر کنید تا آپلود تصاویر تمام شود');
            return;
        }

        // چک دسته داخلی — اجباری
        var pcatEl = document.getElementById('panel_category_id');
        if (!pcatEl || !pcatEl.value) {
            var req = document.getElementById('pcat-required');
            if (req) req.style.display = 'block';
            if (pcatInput) pcatInput.focus();
            showAlert('error', 'انتخاب دسته‌بندی داخلی اجباری است');
            return;
        }

        btn.disabled     = true;
        text.textContent = 'در حال ذخیره...';

        try {
            var response = await fetch(AJAX_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json; charset=utf-8' },
                body:    JSON.stringify(collectData()),
            });
            var result = await response.json();

            if (result.success) {
                showAlert('success', result.message);
                if (result.mode === 'create') {
                    // اگر از WP import شده → تصاویر رو با product_id واقعی ذخیره کن
                    if (wpSelectedWpId && result.product && result.product.id) {
                        var imgUrl = typeof WP_IMAGES_URL !== 'undefined' ? WP_IMAGES_URL : '../ajax/fetch-wp-images.php';
                        fetch(imgUrl, {
                            method:  'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body:    JSON.stringify({ wp_product_id: wpSelectedWpId, product_id: result.product.id }),
                        }).catch(function(){});
                    }
                    e.target.reset();
                    document.getElementById('categories-list').innerHTML = '';
                    document.getElementById('attributes-list').innerHTML = '';
                    imagesList = [];
                    renderImages();
                    updateSummary();
                    priceSummary.style.display = 'none';
                    clearPCat();
                    clearWpSelection();
                    document.querySelectorAll('.status-radio').forEach(function(el){
                        el.classList.remove('selected-active','selected-inactive','selected-draft');
                    });
                    document.querySelector('.status-radio').classList.add('selected-active');
                }
            } else {
                showAlert('error', result.message || 'خطایی رخ داد');
            }
        } catch(err) {
            showAlert('error', 'خطا در ارتباط با سرور');
            console.error(err);
        } finally {
            btn.disabled     = false;
            text.textContent = isEdit ? 'ذخیره تغییرات' : 'ثبت محصول';
        }
    });

    // ── helpers ──────────────────────────────
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

})();