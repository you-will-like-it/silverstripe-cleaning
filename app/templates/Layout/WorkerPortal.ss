<%-- ── KPI row ──────────────────────────────────────────────── --%>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr))">

    <div class="kpi-card">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <span class="kpi-card__value">$TodayAssignments.Count</span>
        <span class="kpi-card__label">Einsätze heute</span>
    </div>

    <div class="kpi-card <% if $ActiveTimeEntry %>kpi-card--success<% end_if %>">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <span class="kpi-card__value">
            <% if $ActiveTimeEntry %>Aktiv<% else %>–<% end_if %>
        </span>
        <span class="kpi-card__label">Zeiterfassung</span>
    </div>

    <div class="kpi-card <% if $PendingSwaps %>kpi-card--warning<% end_if %>">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </div>
        <span class="kpi-card__value">$PendingSwaps</span>
        <span class="kpi-card__label">Tauschanfragen</span>
    </div>

</div>

<%-- ── Active clock-in widget ─────────────────────────────── --%>
<% if $ActiveTimeEntry %>
<div class="clock-widget">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--clr-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <div>
        <div class="clock-widget__time" id="clockDisplay">00:00:00</div>
        <div class="clock-widget__label">Einstempelzeit: $ActiveTimeEntry.StartTime</div>
    </div>
    <div class="clock-widget__actions">
        <button class="btn-portal-danger" id="btnClockOut" data-entry="$ActiveTimeEntry.ID">Ausstempeln</button>
    </div>
</div>
<% end_if %>

<%-- ── Today's assignments ─────────────────────────────────── --%>
<div class="portal-card mb-4">
    <div class="portal-card__header">
        <h2 class="portal-card__title">Meine heutigen Einsätze</h2>
        <a href="/portal/schedule" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">Dienstplan →</a>
    </div>
    <% if $TodayAssignments %>
    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Revier</th>
                    <th>Objekt</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <% loop $TodayAssignments %>
                <tr>
                    <td>
                        <div style="font-weight:500">$Revir.Name</div>
                        <div style="font-size:.75rem;color:var(--clr-muted)">$Revir.CleaningObject.getFullAddress</div>
                    </td>
                    <td style="font-size:.85rem">$Revir.CleaningObject.Name</td>
                    <td><span class="status-pill status-pill--{$Status.LowerCase}">$Status</span></td>
                    <td>
                        <div class="d-flex gap-2">
                            <button class="btn-portal-primary btn-sm js-clock-in" data-assignment="$ID"
                                    style="font-size:.75rem;padding:5px 10px">
                                ⏱ Einstempeln
                            </button>
                            <% if $Revir.QRToken %>
                            <a href="/portal/checkin/$Revir.QRToken"
                               class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">
                                📱 QR
                            </a>
                            <% end_if %>
                        </div>
                    </td>
                </tr>
                <% end_loop %>
            </tbody>
        </table>
    </div>
    <% else %>
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">☕</div>
            <div class="empty-state__title">Heute kein Einsatz</div>
            <div class="empty-state__text">Sie haben für heute keinen geplanten Einsatz.</div>
        </div>
    </div>
    <% end_if %>
</div>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // Live clock
    const startTimeStr = '$ActiveTimeEntry.StartTime';
    const clockEl = document.getElementById('clockDisplay');
    if (clockEl && startTimeStr) {
        const startTs = new Date(startTimeStr).getTime();
        setInterval(function () {
            const diff = Math.floor((Date.now() - startTs) / 1000);
            const h = String(Math.floor(diff / 3600)).padStart(2, '0');
            const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const s = String(diff % 60).padStart(2, '0');
            clockEl.textContent = h + ':' + m + ':' + s;
        }, 1000);
    }

    // Clock out
    const btnOut = document.getElementById('btnClockOut');
    if (btnOut) {
        btnOut.addEventListener('click', async function () {
            btnOut.disabled = true;
            const resp = await fetch('/portal/clockOut', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
                body: new URLSearchParams({ entry_id: btnOut.dataset.entry }),
            });
            const data = await resp.json();
            if (data.success) {
                window.portalToast('Ausgestempelt ✓', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                window.portalToast(data.error ?? 'Fehler', 'danger');
                btnOut.disabled = false;
            }
        });
    }

    // Clock in
    document.querySelectorAll('.js-clock-in').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            btn.disabled = true;
            const resp = await fetch('/portal/clockIn', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
                body: new URLSearchParams({ assignment_id: btn.dataset.assignment }),
            });
            const data = await resp.json();
            if (data.success) {
                window.portalToast('Eingestempelt ✓', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                window.portalToast(data.error ?? 'Fehler', 'danger');
                btn.disabled = false;
            }
        });
    });
})();
</script>
