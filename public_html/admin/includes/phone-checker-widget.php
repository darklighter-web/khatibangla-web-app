<?php
/**
 * Phone Check Widget v12 — Own DB counts + API cross-merchant data
 * Include: <?php include __DIR__ . '/../includes/phone-checker-widget.php'; ?>
 */
$siteUrl = defined('SITE_URL') ? SITE_URL : '';
?>
<style>
#pcWidget{display:none;margin-bottom:16px;animation:pcSlide .3s ease}
@keyframes pcSlide{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.pc-grid{display:flex;gap:8px;flex-wrap:wrap}
.pc-card{flex:1;min-width:130px;max-width:220px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;display:flex;flex-direction:column}
.pc-card-h{padding:8px 10px 4px;font-weight:700;font-size:12px;color:#374151;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between}
.pc-badge{font-size:9px;padding:1px 6px;border-radius:8px;font-weight:700;color:#fff}
.pc-badge.g{background:#22c55e}.pc-badge.y{background:#eab308}.pc-badge.r{background:#ef4444}.pc-badge.b{background:#3b82f6}.pc-badge.x{background:#9ca3af}
.pc-card-b{padding:6px 10px 8px;flex:1;font-size:12px}
.pc-num{font-size:22px;font-weight:800;line-height:1.2}
.pc-num.g{color:#16a34a}.pc-num.y{color:#ca8a04}.pc-num.r{color:#dc2626}.pc-num.b{color:#2563eb}.pc-num.x{color:#9ca3af}
.pc-label{color:#6b7280;font-size:10px}
.pc-stat{color:#374151;font-size:11px;margin-top:2px}
.pc-xm{color:#9ca3af;font-size:10px;font-style:italic;margin-top:3px}
.pc-bar{height:4px;background:#e5e7eb;margin-top:auto}
.pc-bar-f{height:100%;transition:width .5s ease}
.pc-bar-f.g{background:#22c55e}.pc-bar-f.y{background:#eab308}.pc-bar-f.r{background:#ef4444}.pc-bar-f.b{background:#3b82f6}
.pc-fill{display:inline-block;margin-top:4px;padding:3px 14px;background:#22c55e;color:#fff;border:none;border-radius:5px;font-size:10px;font-weight:600;cursor:pointer;width:100%;text-align:center}
.pc-fill:hover{background:#16a34a}
.pc-loading{text-align:center;padding:14px;color:#9ca3af;font-size:13px}
.pc-summary{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px;padding:8px 12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;align-items:center}
.pc-summary-stat{text-align:center}
.pc-summary-num{font-size:20px;font-weight:800}
.pc-summary-label{font-size:10px;color:#6b7280}
.pc-summary-rate{font-size:28px;font-weight:900;margin-right:8px}
</style>

<div id="pcWidget"><div id="pcContent"></div></div>

<script>
(function(){
    const FAPI = '<?= $siteUrl ?>/api/fraud-checker.php';
    let lastChecked = '', debounceTimer = null;

    function findPhoneInput() {
        const sels = ['input[name="customer_phone"]','input[name="phone"]','input[name="mobile"]','input[name="recipient_phone"]','input[type="tel"]','input[placeholder*="phone" i]','input[placeholder*="mobile" i]','input[placeholder*="নম্বর"]'];
        for (const s of sels) { const el = document.querySelector(s); if (el) return el; }
        return null;
    }

    function rc(rate) { return rate >= 70 ? 'g' : rate >= 40 ? 'y' : 'r'; }
    function ratingBadge(cr) {
        const m = {excellent_customer:['Excellent','g'],good_customer:['Good','g'],moderate_customer:['Moderate','y'],risky_customer:['Risky','r'],new_customer:['New','b']};
        const [l,c] = m[cr] || [cr,'x'];
        return `<span class="pc-badge ${c}">⭐ ${l}</span>`;
    }

    function courierCard(title, data) {
        const t = parseInt(data.total||0);
        const s = parseInt(data.success||0);
        const ca = parseInt(data.cancel||0);
        const rate = t > 0 ? Math.round((s/t)*100) : 0;
        const c = t > 0 ? rc(rate) : 'b';
        const cr = data.customer_rating;

        // Cross-merchant note
        let xm = '';
        if (data.cross_merchant_total > 0) {
            xm = `<div class="pc-xm">All merchants: ${data.cross_merchant_total} orders</div>`;
        }
        if (data.api_note) {
            xm = `<div class="pc-xm">⚠ ${data.api_note.substring(0,40)}</div>`;
        }

        let badge = cr ? ratingBadge(cr) : '';

        return `<div class="pc-card">
            <div class="pc-card-h">${title} ${badge}</div>
            <div class="pc-card-b">
                ${t > 0 ? `
                    <div class="pc-stat">✅ Delivered: <b>${s}</b></div>
                    <div class="pc-stat">❌ Cancelled: <b>${ca}</b></div>
                    <div class="pc-stat">📦 Total: <b>${t}</b></div>
                ` : `<div class="pc-num b" style="font-size:14px">${cr ? 'Rating only' : 'No data'}</div>`}
                ${xm}
            </div>
            <div class="pc-bar"><div class="pc-bar-f ${c}" style="width:${t>0?rate:0}%"></div></div>
        </div>`;
    }

    function checkPhone(phone) {
        phone = phone.replace(/\D/g, '');
        if (phone.length < 11) { document.getElementById('pcWidget').style.display = 'none'; lastChecked = ''; return; }
        if (phone.startsWith('88')) phone = phone.substring(2);
        if (phone.length === 10 && phone[0] !== '0') phone = '0' + phone;
        if (phone.length !== 11 || !phone.match(/^01[3-9]/)) return;
        if (phone === lastChecked) return;
        lastChecked = phone;

        const w = document.getElementById('pcWidget');
        const ct = document.getElementById('pcContent');
        w.style.display = 'block';
        ct.innerHTML = '<div class="pc-loading">⏳ Checking ' + phone + '...</div>';

        fetch(FAPI + '?phone=' + encodeURIComponent(phone))
        .then(r => r.json())
        .then(j => {
            if (!j.success) { ct.innerHTML = '<div style="color:#dc2626;padding:8px">❌ ' + (j.error||'Failed') + '</div>'; return; }

            const co = j.combined || {};
            const l = j.local || {};
            const rate = parseInt(co.rate||0);
            const riskColors = {low:'g',medium:'y',high:'r',new:'b',blocked:'r'};
            const riskC = riskColors[co.risk] || 'b';

            // Summary bar
            let summary = `<div class="pc-summary">
                <div class="pc-summary-rate ${riskC}">${rate}%</div>
                <div class="pc-summary-stat"><div class="pc-summary-num" style="color:#374151">${co.total||0}</div><div class="pc-summary-label">API Total</div></div>
                <div class="pc-summary-stat"><div class="pc-summary-num" style="color:#16a34a">${co.success||0}</div><div class="pc-summary-label">Delivered ✅</div></div>
                <div class="pc-summary-stat"><div class="pc-summary-num" style="color:#dc2626">${co.cancel||0}</div><div class="pc-summary-label">Cancelled ❌</div></div>
                <div class="pc-summary-stat"><div class="pc-summary-num" style="color:#6366f1">৳${parseFloat(l.total_spent||0).toLocaleString()}</div><div class="pc-summary-label">Total Spent</div></div>
                <div><span class="pc-badge ${riskC}" style="font-size:11px;padding:3px 10px">${co.risk_label||co.risk||'—'}</span></div>
            </div>`;

            // Per-courier cards
            let localCard = `<div class="pc-card" style="border-color:#bbf7d0">
                <div class="pc-card-h">Our Record</div>
                <div class="pc-card-b">
                    ${parseInt(l.total||0) > 0 ? `
                        <div class="pc-stat">✅ Delivered: <b>${l.delivered||0}</b></div>
                        <div class="pc-stat">❌ Cancelled: <b>${l.cancelled||0}</b></div>
                        <div class="pc-stat">🔄 Returned: <b>${l.returned||0}</b></div>
                        <div class="pc-stat">📦 Total: <b>${l.total}</b></div>
                    ` : '<div class="pc-num b" style="font-size:14px">New</div>'}
                    <button class="pc-fill" onclick="pcFill(this)">Fill Address</button>
                </div>
                <div class="pc-bar"><div class="pc-bar-f ${parseInt(l.total||0)>0?rc(Math.round((parseInt(l.delivered||0)/parseInt(l.total))*100)):'b'}" style="width:${parseInt(l.total||0)>0?Math.round((parseInt(l.delivered||0)/parseInt(l.total))*100):0}%"></div></div>
            </div>`;

            ct.innerHTML = summary + `<div class="pc-grid">
                ${courierCard('Pathao', j.pathao||{})}
                ${courierCard('Steadfast', j.steadfast||{})}
                ${courierCard('RedX', j.redx||{})}
                ${localCard}
            </div>`;

            // Store local data for fill
            window._pcLocalData = l;
        })
        .catch(e => { ct.innerHTML = '<div style="color:#dc2626;padding:8px">❌ ' + e.message + '</div>'; });
    }

    window.pcFill = function(btn) {
        const d = window._pcLocalData;
        if (!d || !d.areas || d.areas.length === 0) { btn.textContent = 'No data'; setTimeout(()=>btn.textContent='Fill Address',1500); return; }
        // Try to fill address fields
        const fields = {customer_name:'name',customer_address:'address',customer_district:'district',customer_city:'city'};
        let filled = 0;
        for (const [name] of Object.entries(fields)) {
            const input = document.querySelector(`[name="${name}"]`);
            if (input && d[name]) { input.value = d[name]; input.dispatchEvent(new Event('input',{bubbles:true})); input.dispatchEvent(new Event('change',{bubbles:true})); filled++; }
        }
        btn.textContent = filled > 0 ? '✓ Filled' : 'No fields';
        btn.style.background = '#16a34a';
        setTimeout(()=>{btn.textContent='Fill Address';btn.style.background='#22c55e'},1500);
    };

    function attach() {
        const input = findPhoneInput();
        if (!input) { setTimeout(attach, 1000); return; }
        if (input.dataset.pcAttached) return;
        input.dataset.pcAttached = '1';
        const section = input.closest('form') || input.closest('.card') || input.closest('section') || input.parentNode.parentNode;
        if (section && section.parentNode) section.parentNode.insertBefore(document.getElementById('pcWidget'), section);
        input.addEventListener('input', function() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => checkPhone(this.value), 400); });
        input.addEventListener('change', function() { checkPhone(this.value); });
        if (input.value.replace(/\D/g,'').length >= 11) checkPhone(input.value);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach);
    else attach();
})();
</script>
