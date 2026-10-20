<div class="manager-portal">
    <header class="portal-header">
        <h1 class="portal-header__title">$Title</h1>
        <nav class="portal-nav">
            <a href="/manager/" class="portal-nav__item portal-nav__item--active">Übersicht</a>
            <a href="/manager/workers" class="portal-nav__item">Mitarbeiter</a>
            <a href="/manager/revirs" class="portal-nav__item">Reviere</a>
            <a href="/manager/swaps" class="portal-nav__item">
                Tauschanfragen
                <% if $PendingSwaps %>
                    <span class="badge badge--danger">$PendingSwaps</span>
                <% end_if %>
            </a>
            <a href="/manager/minijob" class="portal-nav__item">Minijob</a>
        </nav>
    </header>

    <!-- KPI Cards -->
    <section class="kpi-row">
        <div class="kpi-card">
            <span class="kpi-card__value">$TodayCount</span>
            <span class="kpi-card__label">Einsätze heute</span>
        </div>
        <div class="kpi-card kpi-card--success">
            <span class="kpi-card__value">$CheckInCount</span>
            <span class="kpi-card__label">Check-Ins</span>
        </div>
        <div class="kpi-card <% if $PendingSwaps %>kpi-card--warning<% end_if %>">
            <span class="kpi-card__value">$PendingSwaps</span>
            <span class="kpi-card__label">Offene Tausche</span>
        </div>
    </section>

    <!-- Today's assignments -->
    <section class="portal-section">
        <h2 class="portal-section__title">Heutige Einsätze</h2>
        <% if $Assignments %>
        <div class="table-responsive">
            <table class="data-table">
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
                        <td>$Worker.Name</td>
                        <td>$Revir.Name</td>
                        <td>$Revir.CleaningObject.Name</td>
                        <td><span class="status-badge status-badge--$Status.LowerCase">$Status</span></td>
                    </tr>
                    <% end_loop %>
                </tbody>
            </table>
        </div>
        <% else %>
        <p class="empty-state">Keine Einsätze für heute geplant.</p>
        <% end_if %>
    </section>

    <!-- Recent check-ins -->
    <% if $CheckIns %>
    <section class="portal-section">
        <h2 class="portal-section__title">Heutige Check-Ins</h2>
        <div class="table-responsive">
            <table class="data-table">
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
                        <td><%t CleaningSaaS.TimeFormat '{scanned}' scanned=$ScannedAt %></td>
                        <td>$Worker.Name</td>
                        <td>$Revir.Name</td>
                        <td>
                            <% if $Latitude %>
                            <a href="https://maps.google.com/?q={$Latitude},{$Longitude}" target="_blank" rel="noopener" class="link link--map">📍</a>
                            <% else %>
                            <span class="text-muted">–</span>
                            <% end_if %>
                        </td>
                    </tr>
                    <% end_loop %>
                </tbody>
            </table>
        </div>
    </section>
    <% end_if %>

    <!-- Pending swaps -->
    <% if $RecentSwaps %>
    <section class="portal-section">
        <h2 class="portal-section__title">Offene Tauschanfragen</h2>
        <div class="swap-list">
            <% loop $RecentSwaps %>
            <div class="swap-card">
                <div class="swap-card__info">
                    <strong>$Requester.Name</strong>
                    &rarr; <strong>$TargetWorker.Name</strong>
                    &mdash; $Assignment.Revir.Name ($Assignment.AssignedDate)
                </div>
                <div class="swap-card__actions">
                    <button
                        class="btn btn--success btn--sm js-swap-approve"
                        data-swap-id="$ID"
                        data-action="approve"
                    >✓ Genehmigen</button>
                    <button
                        class="btn btn--danger btn--sm js-swap-approve"
                        data-swap-id="$ID"
                        data-action="reject"
                    >✕ Ablehnen</button>
                </div>
            </div>
            <% end_loop %>
        </div>
    </section>
    <% end_if %>
</div>

<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll('.js-swap-approve').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const swapId = btn.dataset.swapId;
            const action = btn.dataset.action;

            btn.disabled = true;

            const resp = await fetch('/manager/approveSwap', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrf,
                },
                body: new URLSearchParams({ swap_id: swapId, action }),
            });

            const data = await resp.json();
            if (data.success) {
                btn.closest('.swap-card').remove();
            } else {
                alert(data.error ?? 'Fehler');
                btn.disabled = false;
            }
        });
    });
})();
</script>
