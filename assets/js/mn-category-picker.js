/**
 * MN Category Picker
 * یک کامپوننت reusable برای انتخاب دسته‌بندی با امکان افزودن inline
 *
 * استفاده:
 *   MNCategoryPicker.init('#sel-cat');
 *   MNCategoryPicker.init('#pcat-new', { onSelect: function(id, name){} });
 *
 * یا auto-init روی همه select هایی که data-cat-picker دارند:
 *   <select data-cat-picker id="sel-cat"></select>
 */

(function (window) {

    // ── modal HTML (یک بار در body ─────────────
    var MODAL_ID = 'mn-cat-picker-modal';

    function ensureModal() {
        if (document.getElementById(MODAL_ID)) return;

        var el = document.createElement('div');
        el.innerHTML =
            '<div id="' + MODAL_ID + '" style="' +
                'display:none;position:fixed;inset:0;z-index:99999;' +
                'background:rgba(0,0,0,.45);align-items:center;justify-content:center;">' +
              '<div style="background:#fff;border-radius:12px;padding:24px 28px;' +
                           'width:360px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;' +
                             'margin-bottom:18px;">' +
                  '<h3 style="font-size:15px;font-weight:700;margin:0;">➕ دسته‌بندی جدید</h3>' +
                  '<button id="mn-cat-modal-close" style="background:none;border:none;font-size:22px;' +
                           'cursor:pointer;color:#9ca3af;line-height:1;">×</button>' +
                '</div>' +

                '<div style="margin-bottom:14px;">' +
                  '<label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">' +
                    'نام دسته <span style="color:#ef4444">*</span></label>' +
                  '<input id="mn-cat-name" type="text" placeholder="مثال: لوازم‌التحریر"' +
                         'style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;' +
                                'font-size:14px;font-family:inherit;box-sizing:border-box;">' +
                '</div>' +

                '<div style="margin-bottom:14px;">' +
                  '<label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">' +
                    'دسته مادر</label>' +
                  '<select id="mn-cat-parent"' +
                          'style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;' +
                                 'font-size:13px;font-family:inherit;">' +
                    '<option value="">— بدون والد —</option>' +
                  '</select>' +
                '</div>' +

                '<div style="margin-bottom:18px;">' +
                  '<label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">' +
                    'رنگ</label>' +
                  '<div style="display:flex;gap:8px;align-items:center;">' +
                    '<input id="mn-cat-color" type="color" value="#3b82f6"' +
                           'style="width:40px;height:36px;border:1px solid #d1d5db;border-radius:6px;' +
                                  'padding:2px;cursor:pointer;">' +
                    '<input id="mn-cat-color-hex" type="text" value="#3b82f6" maxlength="7"' +
                           'style="flex:1;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;' +
                                  'font-size:13px;font-family:inherit;">' +
                  '</div>' +
                '</div>' +

                '<div id="mn-cat-alert" style="display:none;padding:8px 12px;border-radius:6px;' +
                                              'font-size:12px;margin-bottom:12px;"></div>' +

                '<div style="display:flex;gap:8px;">' +
                  '<button id="mn-cat-save"' +
                          'style="flex:1;padding:10px;background:#16a34a;color:#fff;border:none;' +
                                 'border-radius:7px;font-size:13px;font-family:inherit;cursor:pointer;' +
                                 'font-weight:600;">💾 ذخیره</button>' +
                  '<button id="mn-cat-cancel"' +
                          'style="padding:10px 16px;background:#f3f4f6;color:#374151;border:none;' +
                                 'border-radius:7px;font-size:13px;font-family:inherit;cursor:pointer;">انصراف</button>' +
                '</div>' +
              '</div>' +
            '</div>';

        document.body.appendChild(el.firstChild);
        bindModalEvents();
    }

    // ── callback بعد از ذخیره ─────────────────
    var _onCreated = null; // function(id, name, color)

    function bindModalEvents() {
        var modal   = document.getElementById(MODAL_ID);
        var nameEl  = document.getElementById('mn-cat-name');
        var colorEl = document.getElementById('mn-cat-color');
        var hexEl   = document.getElementById('mn-cat-color-hex');
        var alertEl = document.getElementById('mn-cat-alert');

        // بستن
        ['mn-cat-modal-close', 'mn-cat-cancel'].forEach(function (id) {
            document.getElementById(id).addEventListener('click', closeModal);
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        // sync color ↔ hex
        colorEl.addEventListener('input', function () { hexEl.value = this.value; });
        hexEl.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value)) colorEl.value = this.value;
        });

        // Enter در نام
        nameEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') document.getElementById('mn-cat-save').click();
        });

        // ذخیره
        document.getElementById('mn-cat-save').addEventListener('click', function () {
            var name   = nameEl.value.trim();
            var parent = document.getElementById('mn-cat-parent').value;
            var color  = colorEl.value;

            if (!name) {
                showModalAlert('error', 'نام دسته الزامی است');
                nameEl.focus();
                return;
            }

            var btn = this;
            btn.disabled = true;
            btn.textContent = '⏳';
            clearModalAlert();

            var CATS_URL = (typeof AJAX !== 'undefined' ? AJAX : '../ajax/') + 'panel-categories.php';

            fetch(CATS_URL + '?action=create', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    action:    'create',
                    name:      name,
                    parent_id: parent || null,
                    color:     color,
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && res.category) {
                    showModalAlert('success', '✓ دسته «' + name + '» ایجاد شد');
                    if (_onCreated) _onCreated(res.category.id, res.category.name, color);
                    setTimeout(closeModal, 600);
                } else {
                    showModalAlert('error', res.message || 'خطا در ایجاد دسته');
                    btn.disabled = false;
                    btn.textContent = '💾 ذخیره';
                }
            })
            .catch(function (err) {
                showModalAlert('error', 'خطا: ' + err.message);
                btn.disabled = false;
                btn.textContent = '💾 ذخیره';
            });
        });
    }

    function openModal(onCreated) {
        ensureModal(); // ← اطمینان از وجود modal در DOM

        _onCreated = onCreated || null;

        var modal  = document.getElementById(MODAL_ID);
        var nameEl = document.getElementById('mn-cat-name');

        // reset
        nameEl.value = '';
        document.getElementById('mn-cat-parent').value  = '';
        document.getElementById('mn-cat-color').value   = '#3b82f6';
        document.getElementById('mn-cat-color-hex').value = '#3b82f6';
        document.getElementById('mn-cat-save').disabled = false;
        document.getElementById('mn-cat-save').textContent = '💾 ذخیره';
        clearModalAlert();

        // لود والدها
        loadParentOptions();

        modal.style.display = 'flex';
        setTimeout(function () { nameEl.focus(); }, 100);
    }

    function closeModal() {
        document.getElementById(MODAL_ID).style.display = 'none';
        _onCreated = null;
    }

    function loadParentOptions() {
        var sel     = document.getElementById('mn-cat-parent');
        var CATS_URL = (typeof AJAX !== 'undefined' ? AJAX : '../ajax/') + 'panel-categories.php';

        fetch(CATS_URL + '?action=list')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                sel.innerHTML = '<option value="">— بدون والد —</option>';
                (res.categories || []).forEach(function (c) {
                    var o = document.createElement('option');
                    o.value       = c.id;
                    o.textContent = (c.parent_name ? c.parent_name + ' / ' : '') + c.name;
                    sel.appendChild(o);
                });
            })
            .catch(function () {});
    }

    function showModalAlert(type, msg) {
        var el = document.getElementById('mn-cat-alert');
        el.style.display = 'block';
        el.style.background = type === 'success' ? '#f0fdf4' : '#fef2f2';
        el.style.color      = type === 'success' ? '#16a34a' : '#dc2626';
        el.style.border     = '1px solid ' + (type === 'success' ? '#bbf7d0' : '#fecaca');
        el.textContent      = msg;
    }
    function clearModalAlert() {
        var el = document.getElementById('mn-cat-alert');
        el.style.display = 'none';
        el.textContent   = '';
    }

    // ════════════════════════════════════════
    // Public API
    // ════════════════════════════════════════

    /**
     * init روی یک select
     * @param {string|Element} selector
     * @param {object} opts  { onSelect: fn(id,name) }
     */
    function init(selector, opts) {
        opts = opts || {};
        var selEl = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;
        if (!selEl) return;
        if (selEl._mnCatInit) return; // جلوگیری از دوبار init
        selEl._mnCatInit = true;

        ensureModal();

        // ساخت wrapper
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;gap:6px;align-items:center;';

        selEl.parentNode.insertBefore(wrap, selEl);
        wrap.appendChild(selEl);
        selEl.style.flex = '1';

        // دکمه + جدید
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.title     = 'افزودن دسته جدید';
        btn.innerHTML = '＋';
        btn.style.cssText =
            'width:36px;height:36px;flex-shrink:0;border:1px solid #d1d5db;' +
            'border-radius:7px;background:#f9fafb;color:#374151;font-size:18px;' +
            'cursor:pointer;display:flex;align-items:center;justify-content:center;' +
            'transition:all .15s;line-height:1;';
        btn.addEventListener('mouseenter', function () {
            this.style.background   = '#16a34a';
            this.style.color        = '#fff';
            this.style.borderColor  = '#16a34a';
        });
        btn.addEventListener('mouseleave', function () {
            this.style.background   = '#f9fafb';
            this.style.color        = '#374151';
            this.style.borderColor  = '#d1d5db';
        });

        btn.addEventListener('click', function () {
            openModal(function (id, name, color) {
                // اضافه کردن option جدید به select
                var opt = document.createElement('option');
                opt.value    = id;
                opt.textContent = name;
                opt.selected = true;
                selEl.appendChild(opt);

                // trigger change
                selEl.dispatchEvent(new Event('change'));

                if (opts.onSelect) opts.onSelect(id, name);
            });
        });

        wrap.appendChild(btn);

        // لود initial options اگر خالیه (فقط placeholder دارد)
        if (selEl.options.length <= 1) {
            loadSelectOptions(selEl);
        }
    }

    function loadSelectOptions(selEl) {
        var CATS_URL = (typeof AJAX !== 'undefined' ? AJAX : '../ajax/') + 'panel-categories.php';
        var current  = selEl.value;

        fetch(CATS_URL + '?action=list')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                // حفظ placeholder
                var ph = selEl.options[0];
                selEl.innerHTML = '';
                if (ph) selEl.appendChild(ph);
                (res.categories || []).forEach(function (c) {
                    var o = document.createElement('option');
                    o.value       = c.id;
                    o.textContent = (c.parent_name ? c.parent_name + ' / ' : '') + c.name;
                    if (c.id == current) o.selected = true;
                    selEl.appendChild(o);
                });
            })
            .catch(function () {});
    }

    // auto-init روی data-cat-picker
    function autoInit() {
        document.querySelectorAll('[data-cat-picker]').forEach(function (el) {
            init(el);
        });
    }

    // ── expose ────────────────────────────────
    window.MNCategoryPicker = {
        init:     init,
        autoInit: autoInit,
        open:     openModal,
    };

    // اجرا بعد از DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

})(window);