<div class="portal-card">
    <div class="portal-card__header">
        <h2 class="portal-card__title">Mitarbeiterübersicht</h2>
        <span class="badge bg-light text-muted" style="font-weight:500">Heute, $TodayDate</span>
    </div>
    <% if $Workers %>
    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Heutiger Einsatz</th>
                    <th>Check-In</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <% loop $Workers %>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="worker-avatar">$Worker.FirstName.FirstChar</div>
                            <div>
                                <div style="font-weight:500;font-size:.875rem">$Worker.Name</div>
                                <div style="font-size:.75rem;color:var(--clr-muted)">$Worker.Email</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <% if $TodayAssignment %>
                        <span style="font-size:.85rem">$TodayAssignment.Revir.Name</span><br>
                        <span style="font-size:.75rem;color:var(--clr-muted)">$TodayAssignment.Revir.CleaningObject.Name</span>
                        <% else %>
                        <span style="color:var(--clr-muted);font-size:.82rem">Kein Einsatz</span>
                        <% end_if %>
                    </td>
                    <td>
                        <% if $HasCheckedIn %>
                        <span class="status-pill status-pill--confirmed">Eingestempelt</span>
                        <% else_if $TodayAssignment %>
                        <span class="status-pill status-pill--pending">Ausstehend</span>
                        <% else %>
                        <span style="color:var(--clr-muted);font-size:.82rem">–</span>
                        <% end_if %>
                    </td>
                    <td>
                        <% if $TodayAssignment %>
                        <span class="status-pill status-pill--{$TodayAssignment.Status.LowerCase}">$TodayAssignment.Status</span>
                        <% else %>
                        <span class="status-pill status-pill--cancelled">Frei</span>
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
            <div class="empty-state__icon">👥</div>
            <div class="empty-state__title">Keine Mitarbeiter</div>
            <div class="empty-state__text">Noch keine Mitarbeiter in der Gruppe "workers" vorhanden.</div>
        </div>
    </div>
    <% end_if %>
</div>
