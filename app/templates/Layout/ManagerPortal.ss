<%-- ── KPI row ──────────────────────────────────────────────── --%>
<div class="kpi-grid">

    <div class="kpi-card">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <span class="kpi-card__value">$TodayCount</span>
        <span class="kpi-card__label">Einsätze heute</span>
    </div>

    <div class="kpi-card kpi-card--success">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span class="kpi-card__value">$CheckInCount</span>
        <span class="kpi-card__label">Check-Ins</span>
    </div>

    <div class="kpi-card <% if $PendingSwaps %>kpi-card--warning<% end_if %>">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </div>
        <span class="kpi-card__value">$PendingSwaps</span>
        <span class="kpi-card__label">Tauschanfragen</span>
    </div>

    <div class="kpi-card kpi-card--info">
        <div class="kpi-card__accent"></div>
        <div class="kpi-card__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <span class="kpi-card__value" id="coveragePct">–</span>
        <span class="kpi-card__label">Abdeckung heute</span>
    </div>

</div>

<%-- ── Main grid: chart + today's assignments ─────────────── --%>
<div class="row g-4 mb-4">

    <%-- Chart --%>
    <div class="col-lg-5">
        <div class="portal-card h-100">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Einsatz-Status</h2>
                <span class="badge bg-light text-muted" style="font-weight:500">Heute</span>
            </div>
            <div class="portal-card__body d-flex align-items-center justify-content-center" style="min-height:200px">
                <div class="chart-container" style="width:200px;height:200px">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <%-- Today's assignments --%>
    <div class="col-lg-7">
        <div class="portal-card h-100">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Heutige Einsätze</h2>
                <a href="/manager/revirs" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">Alle Reviere →</a>
            </div>
            <% if $Assignments %>
            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Mitarbeiter</th>
                            <th>Revier</th>
                            <th>Objekt</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <% loop $Assignments %>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="worker-avatar">$Worker.FirstName.FirstChar</div>
                                    <span>$Worker.Name</span>
                                </div>
                            </td>
                            <td>$Revir.Name</td>
                            <td class="text-muted" style="font-size:.82rem">$Revir.CleaningObject.Name</td>
                            <td><span class="status-pill status-pill--{$Status.LowerCase}">$Status</span></td>
                        </tr>
                        <% end_loop %>
                    </tbody>
                </table>
            </div>
            <% else %>
            <div class="portal-card__body">
                <div class="empty-state">
                    <div class="empty-state__icon">📋</div>
                    <div class="empty-state__title">Keine Einsätze</div>
                    <div class="empty-state__text">Für heute sind keine Einsätze geplant.</div>
                </div>
            </div>
            <% end_if %>
        </div>
    </div>

</div>

<%-- ── Check-ins + Swaps side by side ─────────────────────── --%>
<div class="row g-4">

    <%-- Check-ins --%>
    <div class="col-lg-7">
        <div class="portal-card">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Heutige Check-Ins</h2>
                <span class="badge bg-success-subtle text-success" style="font-weight:600">$CheckInCount eingestempelt</span>
            </div>
            <% if $CheckIns %>
            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Uhrzeit</th>
                            <th>Mitarbeiter</th>
                            <th>Revier</th>
                            <th>GPS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <% loop $CheckIns %>
                        <tr>
                            <td style="font-variant-numeric:tabular-nums;font-size:.82rem;color:var(--clr-muted)">
                                $ScannedAt
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="worker-avatar" style="width:28px;height:28px;font-size:.65rem">$Worker.FirstName.FirstChar</div>
                                    $Worker.Name
                                </div>
                            </td>
                            <td>$Revir.Name</td>
                            <td>
                                <% if $Latitude %>
                                <a href="https://maps.google.com/?q=$Latitude,$Longitude" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-secondary py-0" style="font-size:.7rem">📍 Map</a>
                                <% else %>
                                <span class="text-muted">–</span>
                                <% end_if %>
                            </td>
                        </tr>
                        <% end_loop %>
                    </tbody>
                </table>
            </div>
            <% else %>
            <div class="portal-card__body">
                <div class="empty-state" style="padding:32px 24px">
                    <div class="empty-state__icon">🔍</div>
                    <div class="empty-state__text">Noch keine Check-Ins heute.</div>
                </div>
            </div>
            <% end_if %>
        </div>
    </div>

    <%-- Pending swaps --%>
    <div class="col-lg-5">
        <div class="portal-card">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Tauschanfragen</h2>
                <% if $PendingSwaps %>
                <span class="badge bg-warning-subtle text-warning-emphasis" style="font-weight:600">$PendingSwaps offen</span>
                <% end_if %>
            </div>
            <div class="portal-card__body" style="padding:16px">
                <% if $RecentSwaps %>
                <div class="swap-list">
                    <% loop $RecentSwaps %>
                    <div class="swap-card" id="swap-$ID">
                        <div>
                            <div style="font-size:.85rem;font-weight:500;color:var(--clr-heading)">
                                <span>$RequestedBy.FirstName</span>
                                <span class="swap-card__arrow">⇄</span>
                                <span>$RequestedTo.FirstName</span>
                            </div>
                            <div class="swap-card__meta">
                                $OriginalShift.Revir.Name · $OriginalShift.AssignedDate
                            </div>
                        </div>
                        <div class="swap-card__actions">
                            <button class="btn-portal-success js-swap-btn" data-id="$ID" data-action="approve">✓</button>
                            <button class="btn-portal-danger js-swap-btn"  data-id="$ID" data-action="reject">✕</button>
                        </div>
                    </div>
                    <% end_loop %>
                </div>
                <% else %>
                <div class="empty-state" style="padding:32px 24px">
                    <div class="empty-state__icon">✅</div>
                    <div class="empty-state__text">Keine offenen Anfragen.</div>
                </div>
                <% end_if %>
            </div>
            <% if $RecentSwaps %>
            <div class="portal-card__footer">
                <a href="/manager/swaps" style="font-size:.8rem;color:var(--clr-primary);text-decoration:none">Alle Anfragen anzeigen →</a>
            </div>
            <% end_if %>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';

    // ── Coverage KPI ──────────────────────────────────────────
    const total     = parseInt('$TodayCount', 10)    || 0;
    const checkIns  = parseInt('$CheckInCount', 10)  || 0;
    const pct       = total > 0 ? Math.round(checkIns / total * 100) : 0;
    const kpiEl     = document.getElementById('coveragePct');
    if (kpiEl) kpiEl.textContent = pct + '%';

    // ── Doughnut chart ────────────────────────────────────────
    const ctx = document.getElementById('statusChart');
    if (ctx && window.Chart) {
        // Count statuses from rows
        const rows   = document.querySelectorAll('.portal-table tbody tr');
        const counts = { Pending: 0, Confirmed: 0, Completed: 0, Cancelled: 0 };
        rows.forEach(function (row) {
            const pill = row.querySelector('.status-pill');
            if (!pill) return;
            const txt = pill.textContent.trim();
            if (counts.hasOwnProperty(txt)) counts[txt]++;
        });

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Ausstehend', 'Bestätigt', 'Abgeschlossen', 'Abgesagt'],
                datasets: [{
                    data: [counts.Pending, counts.Confirmed, counts.Completed, counts.Cancelled],
                    backgroundColor: ['#fef9c3','#dcfce7','#dbeafe','#f1f5f9'],
                    borderColor:     ['#d97706','#16a34a','#2563eb','#94a3b8'],
                    borderWidth: 1.5,
                    hoverOffset: 6,
                }],
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } },
                },
            },
        });
    }

    // ── Swap approve/reject ───────────────────────────────────
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll('.js-swap-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id     = btn.dataset.id;
            const action = btn.dataset.action;
            btn.disabled = true;

            try {
                const resp = await fetch('/manager/approveSwap', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrf,
                    },
                    body: new URLSearchParams({ swap_id: id, action }),
                });
                const data = await resp.json();
                if (data.success) {
                    document.getElementById('swap-' + id)?.remove();
                    window.portalToast(
                        action === 'approve' ? 'Tausch genehmigt ✓' : 'Tausch abgelehnt',
                        action === 'approve' ? 'success' : 'danger'
                    );
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
