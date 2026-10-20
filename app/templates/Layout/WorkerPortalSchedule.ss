<%-- ── Week navigator ──────────────────────────────────────── --%>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h2 style="font-size:1.1rem;font-weight:600;margin:0">Mein Dienstplan</h2>
    <div class="d-flex gap-2">
        <a href="/portal/schedule?week=$PrevWeek" class="btn btn-sm btn-outline-secondary">← Vorherige</a>
        <span class="btn btn-sm btn-light" style="font-weight:500;pointer-events:none">$WeekLabel</span>
        <a href="/portal/schedule?week=$NextWeek" class="btn btn-sm btn-outline-secondary">Nächste →</a>
    </div>
    <a href="/portal/schedule?ical=1" class="btn btn-sm btn-outline-primary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        iCal-Export
    </a>
</div>

<%-- ── 7-day grid ──────────────────────────────────────────── --%>
<% if $WeekDays %>
<div class="week-grid">
    <% loop $WeekDays %>
    <div class="week-day <% if $IsToday %>week-day--today<% end_if %>">
        <div class="week-day__header">
            <span class="week-day__name">$DayName</span>
            <span class="week-day__date">$DayDate</span>
        </div>
        <div class="week-day__body">
            <% if $Assignments %>
                <% loop $Assignments %>
                <div class="week-shift">
                    <div class="week-shift__dot status-dot--<% if $Status == 'Confirmed' %>confirmed<% else_if $Status == 'Absent' %>absent<% else %>pending<% end_if %>"></div>
                    <div>
                        <div class="week-shift__revir">$Revir.Name</div>
                        <div class="week-shift__object">$Revir.CleaningObject.Name</div>
                    </div>
                </div>
                <% end_loop %>
            <% else %>
                <div class="week-day__free">Frei</div>
            <% end_if %>
        </div>
    </div>
    <% end_loop %>
</div>
<% else %>
<div class="portal-card">
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">📅</div>
            <div class="empty-state__title">Keine Einträge</div>
            <div class="empty-state__text">Für diese Woche sind keine Einsätze geplant.</div>
        </div>
    </div>
</div>
<% end_if %>

<%-- ── Full list ────────────────────────────────────────────── --%>
<div class="portal-card mt-4">
    <div class="portal-card__header">
        <h2 class="portal-card__title">Alle Einsätze dieser Woche</h2>
        <span class="badge bg-light text-muted">$UpcomingAssignments.Count Einsätze</span>
    </div>
    <% if $UpcomingAssignments %>
    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Tag</th>
                    <th>Revier</th>
                    <th>Objekt</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <% loop $UpcomingAssignments %>
                <tr>
                    <td style="font-weight:500;font-size:.85rem">$AssignedDate</td>
                    <td>$Revir.Name</td>
                    <td style="font-size:.85rem;color:var(--clr-muted)">$Revir.CleaningObject.Name</td>
                    <td><span class="status-pill status-pill--{$Status.LowerCase}">$Status</span></td>
                    <td>
                        <% if $CanRequestSwap %>
                        <a href="/portal/swaps?request=$ID" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">
                            Tausch anfragen
                        </a>
                        <% end_if %>
                    </td>
                </tr>
                <% end_loop %>
            </tbody>
        </table>
    </div>
    <% else %>
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">😴</div>
            <div class="empty-state__title">Leere Woche</div>
            <div class="empty-state__text">Keine geplanten Einsätze diese Woche.</div>
        </div>
    </div>
    <% end_if %>
</div>

<style>
.week-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-bottom: 24px;
}
@media (max-width: 900px) { .week-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 540px) { .week-grid { grid-template-columns: repeat(2, 1fr); } }

.week-day {
    background: var(--clr-card-bg);
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-card);
    overflow: hidden;
    min-height: 110px;
}
.week-day--today {
    border-color: var(--clr-primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,.08);
}
.week-day__header {
    background: #f8fafc;
    padding: 8px 10px 6px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.week-day--today .week-day__header { background: var(--clr-primary-soft); }
.week-day__name  { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--clr-muted); }
.week-day--today .week-day__name { color: var(--clr-primary); }
.week-day__date  { font-size:.8rem; font-weight:600; color: var(--clr-heading); }
.week-day__body  { padding: 8px; }
.week-day__free  { font-size:.75rem; color:var(--clr-muted); text-align:center; padding:12px 0; }

.week-shift       { display:flex; gap:6px; align-items:flex-start; margin-bottom:6px; }
.week-shift__dot  { width:7px; height:7px; border-radius:50%; flex-shrink:0; margin-top:4px; }
.week-shift__revir  { font-size:.78rem; font-weight:600; color:var(--clr-heading); }
.week-shift__object { font-size:.72rem; color:var(--clr-muted); }

.status-dot--confirmed { background:var(--clr-success); }
.status-dot--absent    { background:var(--clr-danger); }
.status-dot--pending   { background:var(--clr-warning); }
</style>
