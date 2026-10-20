<%-- ── QR / token check-in result ───────────────────────────── --%>
<div class="d-flex justify-content-center align-items-center" style="min-height:60vh">
    <div style="max-width:440px;width:100%;text-align:center">

        <% if $CheckInSuccess %>

        <%-- ── SUCCESS ── --%>
        <div style="margin-bottom:24px">
            <div style="width:80px;height:80px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="var(--clr-success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h1 style="font-size:1.4rem;font-weight:700;color:var(--clr-heading);margin-bottom:8px">Eingestempelt!</h1>
            <p style="color:var(--clr-muted);font-size:.92rem;margin-bottom:20px">
                Check-In erfolgreich registriert.
            </p>
        </div>

        <div class="portal-card" style="text-align:left;margin-bottom:20px">
            <div class="portal-card__body" style="padding:16px">
                <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:.875rem">
                    <dt style="color:var(--clr-muted);font-weight:500">Revier</dt>
                    <dd style="margin:0;font-weight:600">$CheckIn.Revir.Name</dd>

                    <dt style="color:var(--clr-muted);font-weight:500">Objekt</dt>
                    <dd style="margin:0">$CheckIn.Revir.CleaningObject.Name</dd>

                    <dt style="color:var(--clr-muted);font-weight:500">Uhrzeit</dt>
                    <dd style="margin:0;font-variant-numeric:tabular-nums">$CheckIn.ScannedAt</dd>

                    <% if $CheckIn.Latitude %>
                    <dt style="color:var(--clr-muted);font-weight:500">Standort</dt>
                    <dd style="margin:0">
                        <a href="https://maps.google.com/?q=$CheckIn.Latitude,$CheckIn.Longitude"
                           target="_blank" rel="noopener" style="color:var(--clr-primary);font-size:.82rem">
                            📍 Karte öffnen
                        </a>
                    </dd>
                    <% end_if %>
                </dl>
            </div>
        </div>

        <a href="/portal" class="btn-portal-primary" style="display:inline-block">Zum Portal</a>

        <% else_if $AlreadyCheckedIn %>

        <%-- ── ALREADY CHECKED IN ── --%>
        <div style="margin-bottom:24px">
            <div style="width:80px;height:80px;border-radius:50%;background:#fef9c3;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h1 style="font-size:1.4rem;font-weight:700;color:var(--clr-heading);margin-bottom:8px">Bereits eingestempelt</h1>
            <p style="color:var(--clr-muted);font-size:.92rem;margin-bottom:20px">
                Für diesen Einsatz wurde bereits ein Check-In erfasst.
            </p>
        </div>

        <div class="portal-card" style="text-align:left;margin-bottom:20px">
            <div class="portal-card__body" style="padding:16px">
                <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:.875rem">
                    <dt style="color:var(--clr-muted);font-weight:500">Revier</dt>
                    <dd style="margin:0;font-weight:600">$ExistingCheckIn.Revir.Name</dd>
                    <dt style="color:var(--clr-muted);font-weight:500">Eingestempelt um</dt>
                    <dd style="margin:0;font-variant-numeric:tabular-nums">$ExistingCheckIn.ScannedAt</dd>
                </dl>
            </div>
        </div>

        <a href="/portal" class="btn-portal-primary" style="display:inline-block">Zum Portal</a>

        <% else %>

        <%-- ── ERROR / INVALID TOKEN ── --%>
        <div style="margin-bottom:24px">
            <div style="width:80px;height:80px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="var(--clr-danger)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <h1 style="font-size:1.4rem;font-weight:700;color:var(--clr-heading);margin-bottom:8px">Ungültiger QR-Code</h1>
            <p style="color:var(--clr-muted);font-size:.92rem;margin-bottom:20px">
                Dieser QR-Code ist abgelaufen oder ungültig.
                <% if $ErrorMessage %><br><span style="color:var(--clr-danger)">$ErrorMessage</span><% end_if %>
            </p>
        </div>
        <a href="/portal" class="btn-portal-primary" style="display:inline-block">Zurück zum Portal</a>

        <% end_if %>

    </div>
</div>
