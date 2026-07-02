<?php
/**
 * MN Order Panel - Panel Categories Management
 * مدیریت دسته‌بندی‌های داخلی پنل
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$page_title = 'دسته‌بندی‌های داخلی - پنل ثبت سفارش';

$extra_css = '
<style>
.cats-layout { display:grid; grid-template-columns:360px 1fr; gap:20px; align-items:start; }
@media(max-width:800px){ .cats-layout{ grid-template-columns:1fr; } }

/* ── Form card ── */
.cat-form-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:22px 24px;
    position:sticky; top:20px;
}
.cat-form-card h2 { font-size:14px;font-weight:700;color:#374151;margin:0 0 18px;padding-bottom:10px;border-bottom:1px solid #f3f4f6; }
.form-group { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
.form-group label { font-size:13px;font-weight:600;color:#374151; }
.form-control {
    width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;
    font-size:14px;font-family:inherit;color:#111827;background:#fff;box-sizing:border-box;
    transition:border-color .15s,box-shadow .15s;
}
.form-control:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.color-row { display:flex;gap:8px;align-items:center; }
.color-row input[type=color] { width:40px;height:38px;border:1px solid #d1d5db;border-radius:7px;padding:2px;cursor:pointer; }
.btn-submit-cat {
    width:100%;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:8px;
    font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;transition:background .15s;
    display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-submit-cat:hover { background:#1d4ed8; }
.btn-cancel-edit {
    width:100%;padding:9px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;
    font-size:13px;font-family:inherit;cursor:pointer;margin-top:8px;display:none;
}

/* ── Table card ── */
.cats-table-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden; }
.cats-table-header {
    padding:14px 20px;border-bottom:1px solid #f3f4f6;
    display:flex;align-items:center;justify-content:space-between;gap:12px;
}
.cats-table-header h2 { font-size:14px;font-weight:700;color:#374151;margin:0; }
.search-input { padding:7px 12px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;font-family:inherit;width:200px; }

table { width:100%;border-collapse:collapse;font-size:13px; }
thead th { padding:10px 14px;text-align:right;font-weight:600;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb; }
tbody tr { border-bottom:1px solid #f3f4f6;transition:background .1s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#f9fafb; }
td { padding:10px 14px;vertical-align:middle; }

.cat-color-dot { width:14px;height:14px;border-radius:50%;display:inline-block;border:1px solid rgba(0,0,0,.1); }
.depth-indent { color:#d1d5db; }
.badge-count { background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600; }
.act { width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:14px;transition:opacity .15s; }
.act:hover { opacity:.75; }
.act-edit   { background:#fef9c3;color:#a16207; }
.act-delete { background:#fee2e2;color:#dc2626; }

.table-empty { text-align:center;padding:48px;color:#9ca3af; }
.cat-alert { display:none;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px; }
.cat-alert.success { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.cat-alert.error   { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }

/* confirm dialog */
.mn-confirm-bg { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center; }
.mn-confirm-bg.show { display:flex; }
.mn-confirm-box { background:#fff;border-radius:12px;padding:28px 32px;max-width:360px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2); }
.mn-confirm-box .icon { font-size:38px;margin-bottom:10px; }
.mn-confirm-box h3 { font-size:15px;font-weight:700;margin:0 0 8px; }
.mn-confirm-box p  { font-size:13px;color:#6b7280;margin:0 0 18px; }
.mn-confirm-btns { display:flex;gap:10px;justify-content:center; }
.mn-confirm-btns button { padding:9px 22px;border-radius:7px;border:none;font-size:13px;font-family:inherit;cursor:pointer;font-weight:600; }
.mn-confirm-ok     { background:#dc2626;color:#fff; }
.mn-confirm-cancel { background:#f3f4f6;color:#374151; }
</style>
';

ob_start();
?>

<!-- confirm dialog -->
<div class="mn-confirm-bg" id="confirm-bg">
    <div class="mn-confirm-box">
        <div class="icon">🗑️</div>
        <h3>حذف دسته‌بندی</h3>
        <p>آیا مطمئن هستید؟</p>
        <div class="mn-confirm-btns">
            <button class="mn-confirm-ok"     id="confirm-ok">بله، حذف کن</button>
            <button class="mn-confirm-cancel" id="confirm-cancel">انصراف</button>
        </div>
    </div>
</div>

<div class="cats-layout">

    <!-- ── فرم ── -->
    <div>
        <div class="cat-form-card">
            <h2 id="form-title">➕ دسته‌بندی جدید</h2>

            <div class="cat-alert" id="cat-alert"></div>

            <input type="hidden" id="edit-id" value="">

            <div class="form-group">
                <label for="cat-name">نام دسته <span style="color:#ef4444">*</span></label>
                <input type="text" id="cat-name" class="form-control" placeholder="مثال: کتاب‌های درسی">
            </div>

            <div class="form-group">
                <label for="cat-parent">دسته مادر</label>
                <select id="cat-parent" class="form-control">
                    <option value="">— بدون والد (دسته مادر) —</option>
                </select>
            </div>

            <div class="form-group">
                <label for="cat-desc">توضیحات</label>
                <textarea id="cat-desc" class="form-control" rows="2" placeholder="توضیح کوتاه..."></textarea>
            </div>

            <div class="form-group">
                <label>رنگ نمایشی</label>
                <div class="color-row">
                    <input type="color" id="cat-color" value="#3b82f6">
                    <input type="text"  id="cat-color-hex" class="form-control" value="#3b82f6" placeholder="#3b82f6" style="flex:1;">
                </div>
            </div>

            <button type="button" class="btn-submit-cat" id="btn-save">
                <span>💾</span> <span id="btn-save-text">ایجاد دسته‌بندی</span>
            </button>
            <button type="button" class="btn-cancel-edit" id="btn-cancel">انصراف از ویرایش</button>
        </div>
    </div>

    <!-- ── جدول ── -->
    <div>
        <div class="cats-table-card">
            <div class="cats-table-header">
                <h2>📁 دسته‌بندی‌ها</h2>
                <input type="text" class="search-input" id="search-input" placeholder="جستجو...">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>والد</th>
                        <th>رنگ</th>
                        <th>محصولات</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="cats-tbody">
                    <tr><td colspan="5" class="table-empty">⏳ در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();

$extra_js = '
<script>
var CATS_URL = "../ajax/panel-categories.php";
var pendingDeleteId = null;

// ════════════════════════════════════════
// بارگذاری لیست
// ════════════════════════════════════════
function loadCategories(search) {
    search = search || "";
    fetch(CATS_URL + "?action=list&search=" + encodeURIComponent(search))
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (!res.success) { showAlert("error", res.message); return; }
            renderTable(res.categories);
            loadParentDropdown(res.categories);
        })
        .catch(function(){ showAlert("error", "خطا در ارتباط با سرور"); });
}

function renderTable(cats) {
    var tbody = document.getElementById("cats-tbody");
    if (!cats || !cats.length) {
        tbody.innerHTML = "<tr><td colspan=\"5\" class=\"table-empty\">📭 دسته‌بندی یافت نشد</td></tr>";
        return;
    }
    tbody.innerHTML = cats.map(function(c) {
        var dot   = c.color
            ? "<span class=\"cat-color-dot\" style=\"background:" + esc(c.color) + "\"></span>"
            : "<span class=\"cat-color-dot\" style=\"background:#e5e7eb\"></span>";
        var count = "<span class=\"badge-count\">" + (c.product_count||0) + " محصول</span>";
        var pname = c.parent_name
            ? "<span style=\"font-size:11px;color:#6b7280;\">" + esc(c.parent_name) + "</span>"
            : "<span style=\"color:#d1d5db;\">—</span>";
        return "<tr>" +
            "<td><strong>" + esc(c.name) + "</strong></td>" +
            "<td>" + pname + "</td>" +
            "<td>" + dot + "</td>" +
            "<td>" + count + "</td>" +
            "<td><div style=\"display:flex;gap:6px;\">" +
                "<button class=\"act act-edit\"   onclick=\"editCat(" + c.id + ")\"  title=\"ویرایش\">✏️</button>" +
                "<button class=\"act act-delete\" onclick=\"askDelete(" + c.id + ")\" title=\"حذف\">🗑️</button>" +
            "</div></td>" +
        "</tr>";
    }).join("");
}

function loadParentDropdown(cats) {
    var sel     = document.getElementById("cat-parent");
    var current = sel.value;
    var editId  = document.getElementById("edit-id").value;

    sel.innerHTML = "<option value=\"\">— بدون والد (دسته مادر) —</option>";
    cats.forEach(function(c) {
        if (c.id == editId) return; // نمیشه خودش والد خودش باشه
        var opt = document.createElement("option");
        opt.value       = c.id;
        opt.textContent = c.name;
        if (c.id == current) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ════════════════════════════════════════
// ذخیره
// ════════════════════════════════════════
document.getElementById("btn-save").addEventListener("click", function() {
    var editId = document.getElementById("edit-id").value;
    var name   = document.getElementById("cat-name").value.trim();
    if (!name) { showAlert("error", "نام دسته الزامی است"); return; }

    var payload = {
        action:      editId ? "update" : "create",
        id:          editId || undefined,
        name:        name,
        parent_id:   document.getElementById("cat-parent").value || null,
        description: document.getElementById("cat-desc").value.trim(),
        color:       document.getElementById("cat-color-hex").value.trim(),
    };

    fetch(CATS_URL + (editId ? "?action=update&id=" + editId : "?action=create"), {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(payload),
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.success) {
            showAlert("success", res.message);
            resetForm();
            loadCategories();
        } else {
            showAlert("error", res.message);
        }
    })
    .catch(function(){ showAlert("error", "خطا در ارتباط با سرور"); });
});

// ════════════════════════════════════════
// ویرایش
// ════════════════════════════════════════
window.editCat = function(id) {
    fetch(CATS_URL + "?action=list")
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (!res.success) return;
            var c = res.categories.find(function(x){ return x.id == id; });
            if (!c) return;

            document.getElementById("edit-id").value          = c.id;
            document.getElementById("cat-name").value         = c.name;
            document.getElementById("cat-parent").value       = c.parent_id || "";
            document.getElementById("cat-desc").value         = c.description || "";
            document.getElementById("cat-color").value        = c.color || "#3b82f6";
            document.getElementById("cat-color-hex").value    = c.color || "#3b82f6";

            document.getElementById("form-title").textContent  = "✏️ ویرایش دسته‌بندی";
            document.getElementById("btn-save-text").textContent = "ذخیره تغییرات";
            document.getElementById("btn-cancel").style.display  = "block";

            loadParentDropdown(res.categories);
            document.getElementById("cat-name").focus();
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
};

document.getElementById("btn-cancel").addEventListener("click", resetForm);

function resetForm() {
    document.getElementById("edit-id").value            = "";
    document.getElementById("cat-name").value           = "";
    document.getElementById("cat-parent").value         = "";
    document.getElementById("cat-desc").value           = "";
    document.getElementById("cat-color").value          = "#3b82f6";
    document.getElementById("cat-color-hex").value      = "#3b82f6";
    document.getElementById("form-title").textContent   = "➕ دسته‌بندی جدید";
    document.getElementById("btn-save-text").textContent= "ایجاد دسته‌بندی";
    document.getElementById("btn-cancel").style.display = "none";
}

// ════════════════════════════════════════
// حذف
// ════════════════════════════════════════
window.askDelete = function(id) {
    pendingDeleteId = id;
    document.getElementById("confirm-bg").classList.add("show");
};

document.getElementById("confirm-cancel").onclick = function() {
    document.getElementById("confirm-bg").classList.remove("show");
    pendingDeleteId = null;
};

document.getElementById("confirm-ok").onclick = function() {
    if (!pendingDeleteId) return;
    document.getElementById("confirm-bg").classList.remove("show");

    fetch(CATS_URL + "?action=delete&id=" + pendingDeleteId, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete", id: pendingDeleteId }),
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.success) { loadCategories(); }
        else showAlert("error", res.message);
    })
    .catch(function(){ showAlert("error", "خطا در ارتباط با سرور"); });

    pendingDeleteId = null;
};

// ════════════════════════════════════════
// Color sync
// ════════════════════════════════════════
document.getElementById("cat-color").addEventListener("input", function() {
    document.getElementById("cat-color-hex").value = this.value;
});
document.getElementById("cat-color-hex").addEventListener("input", function() {
    if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
        document.getElementById("cat-color").value = this.value;
    }
});

// ════════════════════════════════════════
// جستجو
// ════════════════════════════════════════
var searchTimer;
document.getElementById("search-input").addEventListener("input", function() {
    clearTimeout(searchTimer);
    var q = this.value;
    searchTimer = setTimeout(function(){ loadCategories(q); }, 350);
});

// ════════════════════════════════════════
// Helpers
// ════════════════════════════════════════
function showAlert(type, msg) {
    var el = document.getElementById("cat-alert");
    el.className  = "cat-alert " + type;
    el.textContent = msg;
    el.style.display = "block";
    if (type === "success") setTimeout(function(){ el.style.display="none"; }, 4000);
}
function esc(str) {
    return String(str||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

// init
loadCategories();
</script>
';

require_once __DIR__ . '/layout.php';
?>