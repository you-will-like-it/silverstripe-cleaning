<%-- ── Header + Request button ─────────────────────────────── --%>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h2 style="font-size:1.1rem;font-weight:600;margin:0">Meine Tauschanfragen</h2>
    <button class="btn-portal-primary" id="btnOpenSwapModal">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tausch anfragen
    </button>
</div>

<%-- ── My requests ─────────────────────────────────────────── --%>
<% if $MySwaps %>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:32px">
    <% loop $MySwaps %>
    <div class="swap-card">
        <div style="flex:1">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span class="status-pill status-pill--{$Status.LowerCase}">$Status</span>
                <span style="font-size:.78rem;color:var(--clr-muted)">$Created</span>
            </div>
            <div style="font-size:.9rem;font-weight:500;margin-bottom:4px">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;opacity:.5"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                $OriginalShift.Revir.Name · $OriginalShift.AssignedDate
                <span style="margin:0 8px;color:var(--clr-muted)">→</span>
                <% if $SwapShift %>
                $SwapShift.Revir.Name · $SwapShift.AssignedDate
                <% else %>
                <span style="color:var(--clr-muted);font-style:italic">Offen (kein Zieltermin)</span>
                <% end_if %>
            </div>
            <% if $RequestedTo %>
            <div style="font-size:.8rem;color:var(--clr-muted)">
                Angefragt an: <strong>$RequestedTo.Name</strong>
            </div>
            <% end_if %>
            <% if $Note %>
            <div style="font-size:.8rem;color:var(--clr-muted);margin-top:4px;font-style:italic">"$Note"</div>
            <% end_if %>
        </div>
        <% if $Status == 'Pending' %>
        <div class="swap-card__actions">
            <button class="btn btn-sm btn-outline-secondary js-cancel-swap" data-id="$ID" style="font-size:.75rem">
                Zurückziehen
            </button>
        </div>
        <% end_if %>
    </div>
    <% end_loop %>
</div>
<% else %>
<div class="portal-card mb-4">
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">🔄</div>
            <div class="empty-state__title">Keine Anfragen</div>
            <div class="empty-state__text">Sie haben noch keine Tauschanfragen gestellt.</div>
        </div>
    </div>
</div>
<% end_if %>

<%-- ── Incoming requests (others → me) ────────────────────── --%>
<% if $IncomingSwaps %>
<div class="portal-card mb-4">
    <div class="portal-card__header">
        <h2 class="portal-card__title">An mich gerichtete Anfragen</h2>
        <span class="badge bg-warning-subtle text-warning-emphasis" style="font-weight:600">$IncomingSwaps.Count neu</span>
    </div>
    <div class="portal-card__body" style="padding:16px">
        <% loop $IncomingSwaps %>
        <div class="swap-card" id="incoming-$ID">
            <div style="flex:1">
                <div style="font-size:.88rem;font-weight:500;margin-bottom:4px">
                    <div class="worker-avatar d-inline-flex" style="width:22px;height:22px;font-size:.58rem;vertical-align:middle;margin-right:4px">$RequestedBy.FirstName.FirstChar</div>
                    <strong>$RequestedBy.FirstName</strong> möchte tauschen:
                </div>
                <div style="font-size:.85rem;color:var(--clr-muted)">
                    Ihr Einsatz: <strong style="color:var(--clr-heading)">$OriginalShift.Revir.Name · $OriginalShift.AssignedDate</strong>
                    <% if $SwapShift %>
                    → gegen: <strong style="color:var(--clr-heading)">$SwapShift.Revir.Name · $SwapShift.AssignedDate</strong>
                    <% end_if %>
                </div>
                <% if $Note %>
                <div style="font-size:.78rem;color:var(--clr-muted);margin-top:4px;font-style:italic">"$Note"</div>
                <% end_if %>
            </div>
            <div class="swap-card__actions">
                <button class="btn-portal-success js-swap-respond" data-id="$ID" data-action="accept" style="font-size:.75rem">✓ Annehmen</button>
                <button class="btn-portal-danger  js-swap-respond" data-id="$ID" data-action="decline" style="font-size:.75rem">✕ Ablehnen</button>
            </div>
        </div>
        <% end_loop %>
    </div>
</div>
<% end_if %>

<%-- ── New swap request modal ──────────────────────────────── --%>
<div id="swapModal" style="display:none;position:fixed;inset:0;z-index:1050;background:rgba(15,23,42,.45);backdrop-filter:blur(4px)">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;padding:28px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.18)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="margin:0;font-size:1.05rem;font-weight:700">Tauschanfrage stellen</h3>
            <button id="btnCloseSwapModal" style="background:none;border:none;cursor:pointer;color:var(--clr-muted);font-size:1.3rem;line-height:1">✕</button>
        </div>
        <form id="swapForm" style="display:flex;flex-direction:column;gap:14px">
            <div>
                <label style="font-size:.82rem;font-weight:600;color:var(--clr-muted);display:block;margin-bottom:6px">Mein Einsatz (zum Tauschen)</label>
                <select name="original_shift_id" class="form-select form-select-sm" required>
                    <option value="">– Einsatz wählen –</option>
                    <% loop $UpcomingAssignments %>
                    <option value="$ID">$AssignedDate – $Revir.Name</option>
                    <% end_loop %>
                </select>
            </div>
            <div>
                <label style="font-size:.82rem;font-weight:600;color:var(--clr-muted);display:block;margin-bottom:6px">Gewünschter Tausch (optional)</label>
                <select name="swap_shift_id" class="form-select form-select-sm">
                    <option value="">– Kein Zieltermin –</option>
                    <% loop $UpcomingAssignments %>
                    <option value="$ID">$AssignedDate – $Revir.Name</option>
                    <% end_loop %>
                </select>
            </div>
            <div>
                <label style="font-size:.82rem;font-weight:600;color:var(--clr-muted);display:block;margin-bottom:6px">Notiz (optional)</label>
                <textarea name="note" class="form-control form-control-sm" rows="2" style="resize:none" placeholder="Begründung oder Hinweis…"></textarea>
            </div>
            <div class="d-flex gap-3 justify-content-end mt-2">
                <button type="button" id="btnCancelSwapModal" class="btn btn-sm btn-outline-secondary">Abbrechen</button>
                <button type="submit" class="btn-portal-primary">Absenden</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const modal = document.getElementById('swapModal');

    // Modal open/close
    document.getElementById('btnOpenSwapModal')?.addEventListener('click', () => modal.style.display = 'block');
    ['btnCloseSwapModal','btnCancelSwapModal'].forEach(id =>
        document.getElementById(id)?.addEventListener('click', () => modal.style.display = 'none'));
    modal?.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

    // Submit swap request
    document.getElementById('swapForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('[type="submit"]');
        btn.disabled = true;
        const fd = new FormData(this);
        const body = new URLSearchParams(fd);
        const resp = await fetch('/portal/requestSwap', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
            body,
        });
        const data = await resp.json();
        if (data.success) {
            window.portalToast('Anfrage gesendet ✓', 'success');
            modal.style.display = 'none';
            setTimeout(() => location.reload(), 1200);
        } else {
            window.portalToast(data.error ?? 'Fehler', 'danger');
            btn.disabled = false;
        }
    });

    // Cancel my request
    document.querySelectorAll('.js-cancel-swap').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('Anfrage zurückziehen?')) return;
            btn.disabled = true;
            const resp = await fetch('/portal/requestSwap', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
                body: new URLSearchParams({ swap_id: btn.dataset.id, action: 'cancel' }),
            });
            const data = await resp.json();
            if (data.success) { window.portalToast('Zurückgezogen', 'success'); setTimeout(() => location.reload(), 1200); }
            else { window.portalToast(data.error ?? 'Fehler', 'danger'); btn.disabled = false; }
        });
    });

    // Respond to incoming
    document.querySelectorAll('.js-swap-respond').forEach(btn => {
        btn.addEventListener('click', async function () {
            btn.disabled = true;
            const resp = await fetch('/portal/requestSwap', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
                body: new URLSearchParams({ swap_id: btn.dataset.id, action: btn.dataset.action }),
            });
            const data = await resp.json();
            if (data.success) {
                document.getElementById('incoming-' + btn.dataset.id)?.remove();
                window.portalToast(btn.dataset.action === 'accept' ? 'Angenommen ✓' : 'Abgelehnt', btn.dataset.action === 'accept' ? 'success' : 'danger');
            } else {
                window.portalToast(data.error ?? 'Fehler', 'danger');
                btn.disabled = false;
            }
        });
    });
})();
</script>
