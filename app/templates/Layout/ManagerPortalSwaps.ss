<div class="portal-card">
    <div class="portal-card__header">
        <h2 class="portal-card__title">Offene Tauschanfragen</h2>
        <% if $Swaps %>
        <span class="badge bg-warning-subtle text-warning-emphasis" style="font-weight:600">$Swaps.Count offen</span>
        <% end_if %>
    </div>
    <% if $Swaps %>
    <div class="portal-card__body" style="padding:16px">
        <div class="swap-list" id="swapList">
            <% loop $Swaps %>
            <div class="swap-card" id="swap-$ID">
                <div style="flex:1">
                    <div style="font-size:.9rem;font-weight:500;color:var(--clr-heading);margin-bottom:4px">
                        <div class="worker-avatar d-inline-flex" style="width:24px;height:24px;font-size:.6rem;vertical-align:middle;margin-right:4px">$RequestedBy.FirstName.FirstChar</div>
                        $RequestedBy.Name
                        <span class="swap-card__arrow" style="margin:0 6px">⇄</span>
                        <% if $RequestedTo %>
                        <div class="worker-avatar d-inline-flex" style="width:24px;height:24px;font-size:.6rem;vertical-align:middle;margin-right:4px">$RequestedTo.FirstName.FirstChar</div>
                        $RequestedTo.Name
                        <% else %>
                        <span style="color:var(--clr-muted);font-style:italic">Offen</span>
                        <% end_if %>
                    </div>
                    <div class="swap-card__meta">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        $OriginalShift.Revir.Name · $OriginalShift.AssignedDate
                        <% if $SwapShift %>
                        &rarr; $SwapShift.Revir.Name · $SwapShift.AssignedDate
                        <% end_if %>
                    </div>
                </div>
                <div class="swap-card__actions">
                    <button class="btn-portal-success js-swap-btn" data-id="$ID" data-action="approve">✓ Genehmigen</button>
                    <button class="btn-portal-danger js-swap-btn"  data-id="$ID" data-action="reject">✕ Ablehnen</button>
                </div>
            </div>
            <% end_loop %>
        </div>
    </div>
    <% else %>
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">✅</div>
            <div class="empty-state__title">Keine offenen Anfragen</div>
            <div class="empty-state__text">Alle Tauschanfragen sind bearbeitet.</div>
        </div>
    </div>
    <% end_if %>
</div>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll('.js-swap-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = btn.dataset.id, action = btn.dataset.action;
            btn.disabled = true;
            try {
                const resp = await fetch('/manager/approveSwap', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
                    body: new URLSearchParams({ swap_id: id, action }),
                });
                const data = await resp.json();
                if (data.success) {
                    document.getElementById('swap-' + id)?.remove();
                    window.portalToast(action === 'approve' ? 'Genehmigt ✓' : 'Abgelehnt', action === 'approve' ? 'success' : 'danger');
                    if (!document.querySelector('.swap-card')) {
                        document.getElementById('swapList')?.closest('.portal-card__body')
                            ?.insertAdjacentHTML('beforeend', '<div class="empty-state" style="padding:32px 24px"><div class="empty-state__icon">✅</div><div class="empty-state__text">Alle erledigt.</div></div>');
                    }
                } else {
                    window.portalToast(data.error ?? 'Fehler', 'danger');
                    btn.disabled = false;
                }
            } catch (e) {
                window.portalToast('Netzwerkfehler', 'danger');
                btn.disabled = false;
            }
        });
    });
})();
</script>
