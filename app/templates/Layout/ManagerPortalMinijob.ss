<%-- ── Info banner ──────────────────────────────────────────── --%>
<div class="alert d-flex align-items-start gap-3 mb-4" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div style="font-size:.85rem;color:#1e40af">
        Angezeigt werden Mitarbeiter, die <strong>75 % oder mehr</strong> ihrer Minijob-Grenze erreicht haben.
        Rot = kritisch (≥ 90 %).
    </div>
</div>

<%-- ── Chart + Table ───────────────────────────────────────── --%>
<% if $AtRisk %>
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="portal-card h-100">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Auslastung</h2>
                <span class="badge bg-light text-muted" style="font-weight:500">$Month</span>
            </div>
            <div class="portal-card__body d-flex align-items-center justify-content-center" style="min-height:220px">
                <canvas id="minijobChart" width="260" height="260"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Mitarbeiter nahe der Minijob-Grenze</h2>
            </div>
            <div class="table-responsive">
                <table class="portal-table" id="minijobTable">
                    <thead>
                        <tr>
                            <th>Mitarbeiter</th>
                            <th>Stunden</th>
                            <th>Verdienst</th>
                            <th>Grenze</th>
                            <th>Auslastung</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <% loop $AtRisk %>
                        <tr data-pct="$Percent" data-name="$Worker.FirstName $Worker.Surname" data-critical="<% if $Critical %>1<% else %>0<% end_if %>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="worker-avatar <% if $Critical %>bg-danger-subtle<% else %>bg-warning-subtle<% end_if %>"
                                         style="color:<% if $Critical %>var(--clr-danger)<% else %>var(--clr-warning)<% end_if %>">
                                        $Worker.FirstName.FirstChar
                                    </div>
                                    $Worker.Name
                                </div>
                            </td>
                            <td style="font-variant-numeric:tabular-nums">$Hours h</td>
                            <td style="font-variant-numeric:tabular-nums">$Earnings €</td>
                            <td style="font-variant-numeric:tabular-nums;color:var(--clr-muted)">$Threshold €</td>
                            <td style="min-width:160px">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress-slim flex-grow-1">
                                        <div class="progress-slim__fill <% if $Critical %>progress-slim__fill--critical<% end_if %>"
                                             style="width:<% if $Percent.RAW > 100 %>100<% else %>$Percent<% end_if %>%"></div>
                                    </div>
                                    <span style="font-size:.78rem;font-weight:600;color:<% if $Critical %>var(--clr-danger)<% else %>var(--clr-warning)<% end_if %>">
                                        $Percent%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <% if $Critical %>
                                <span class="badge bg-danger-subtle text-danger">⛔ Kritisch</span>
                                <% else %>
                                <span class="badge bg-warning-subtle text-warning">⚠ Warnung</span>
                                <% end_if %>
                            </td>
                        </tr>
                        <% end_loop %>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<% else %>
<div class="portal-card">
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">✅</div>
            <div class="empty-state__title">Alles im grünen Bereich</div>
            <div class="empty-state__text">Kein Mitarbeiter liegt über 75 % der Minijob-Grenze.</div>
        </div>
    </div>
</div>
<% end_if %>

<script>
(function () {
    if (!window.Chart) return;

    const rows  = document.querySelectorAll('#minijobTable tbody tr');
    const names = [], pcts = [], colors = [];

    rows.forEach(function (tr) {
        names.push(tr.dataset.name);
        pcts.push(parseFloat(tr.dataset.pct) || 0);
        colors.push(tr.dataset.critical === '1' ? '#dc2626' : '#d97706');
    });

    const ctx = document.getElementById('minijobChart');
    if (!ctx || names.length === 0) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: names,
            datasets: [{
                label: 'Auslastung %',
                data: pcts,
                backgroundColor: colors.map(c => c + '22'),
                borderColor: colors,
                borderWidth: 2,
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: {
                    min: 0, max: 100,
                    ticks: { callback: v => v + '%' },
                    grid: { color: '#f1f5f9' },
                },
                y: { grid: { display: false } },
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.x + '%' } },
            },
        },
    });
})();
</script>
