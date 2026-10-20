<%-- ── Legend ────────────────────────────────────────────────── --%>
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <span style="font-size:.82rem;color:var(--clr-muted)">Status:</span>
    <span class="d-flex align-items-center gap-1" style="font-size:.8rem">
        <span style="width:10px;height:10px;border-radius:50%;background:var(--clr-success);display:inline-block"></span> Eingestempelt
    </span>
    <span class="d-flex align-items-center gap-1" style="font-size:.8rem">
        <span style="width:10px;height:10px;border-radius:50%;background:var(--clr-warning);display:inline-block"></span> Zugewiesen
    </span>
    <span class="d-flex align-items-center gap-1" style="font-size:.8rem">
        <span style="width:10px;height:10px;border-radius:50%;background:var(--clr-danger);display:inline-block"></span> Abwesend
    </span>
    <span class="d-flex align-items-center gap-1" style="font-size:.8rem">
        <span style="width:10px;height:10px;border-radius:50%;background:#cbd5e1;display:inline-block"></span> Nicht besetzt
    </span>
</div>

<%-- ── Revir cards grid ────────────────────────────────────── --%>
<% if $Revirs %>
<div class="revir-grid">
    <% loop $Revirs %>
    <div class="revir-card $StatusClass">
        <div class="revir-card__status-dot"></div>

        <div class="revir-card__name">$Revir.Name</div>
        <div class="revir-card__object">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:3px;opacity:.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            $Revir.CleaningObject.Name
        </div>

        <% if $Worker %>
        <div class="revir-card__worker">
            <div class="revir-card__worker-avatar">$Worker.FirstName.FirstChar</div>
            $Worker.Name
        </div>
        <% else %>
        <div class="revir-card__worker" style="color:var(--clr-muted);font-style:italic">Nicht besetzt</div>
        <% end_if %>

        <% if $CheckedIn %>
        <div class="revir-card__checkin revir-card__checkin--ok">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Check-In: $LastCheckIn.ScannedAt
            <% if $LastCheckIn.Latitude %>
            <a href="https://maps.google.com/?q=$LastCheckIn.Latitude,$LastCheckIn.Longitude"
               target="_blank" rel="noopener" style="color:var(--clr-primary);margin-left:4px">📍</a>
            <% end_if %>
        </div>
        <% else_if $Assignment %>
        <div class="revir-card__checkin revir-card__checkin--miss">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Kein Check-In
        </div>
        <% else %>
        <div class="revir-card__checkin revir-card__checkin--none">Kein Einsatz heute</div>
        <% end_if %>
    </div>
    <% end_loop %>
</div>
<% else %>
<div class="portal-card">
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">🗺️</div>
            <div class="empty-state__title">Keine Reviere</div>
            <div class="empty-state__text">Noch keine Reviere angelegt.</div>
        </div>
    </div>
</div>
<% end_if %>
