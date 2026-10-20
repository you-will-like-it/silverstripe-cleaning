<%-- ── Profile header ───────────────────────────────────────── --%>
<div class="d-flex align-items-start gap-4 mb-5 flex-wrap">
    <div style="width:80px;height:80px;border-radius:50%;background:var(--clr-primary);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;flex-shrink:0">
        $CurrentMember.FirstName.FirstChar
    </div>
    <div>
        <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 4px">$CurrentMember.Name</h1>
        <div style="color:var(--clr-muted);font-size:.875rem;margin-bottom:8px">$CurrentMember.Email</div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="status-pill status-pill--confirmed">Aktiv</span>
            <% if $WorkerGroup %>
            <span class="badge bg-light" style="font-size:.72rem;font-weight:500;color:var(--clr-muted)">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:2px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                $WorkerGroup
            </span>
            <% end_if %>
        </div>
    </div>
</div>

<%-- ── Info + Password cards ───────────────────────────────── --%>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="portal-card">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Persönliche Daten</h2>
            </div>
            <div class="portal-card__body" style="padding:20px">
                <form id="profileForm" style="display:flex;flex-direction:column;gap:14px">
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">Vorname</label>
                        <input type="text" name="FirstName" class="form-control form-control-sm" value="$CurrentMember.FirstName">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">Nachname</label>
                        <input type="text" name="Surname" class="form-control form-control-sm" value="$CurrentMember.Surname">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">E-Mail</label>
                        <input type="email" name="Email" class="form-control form-control-sm" value="$CurrentMember.Email">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">Telefon</label>
                        <input type="text" name="Phone" class="form-control form-control-sm" value="$CurrentMember.Phone">
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn-portal-primary" style="min-width:120px">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="portal-card">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Passwort ändern</h2>
            </div>
            <div class="portal-card__body" style="padding:20px">
                <form id="passwordForm" style="display:flex;flex-direction:column;gap:14px">
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">Aktuelles Passwort</label>
                        <input type="password" name="OldPassword" class="form-control form-control-sm" autocomplete="current-password">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">Neues Passwort</label>
                        <input type="password" name="NewPassword" class="form-control form-control-sm" autocomplete="new-password" id="pwNew">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:var(--clr-muted)">Wiederholen</label>
                        <input type="password" name="ConfirmPassword" class="form-control form-control-sm" autocomplete="new-password" id="pwConfirm">
                        <div id="pwMismatch" style="display:none;font-size:.78rem;color:var(--clr-danger);margin-top:4px">Passwörter stimmen nicht überein.</div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn-portal-primary" style="min-width:120px">Ändern</button>
                    </div>
                </form>
            </div>
        </div>

        <%-- ── iCal subscription ── --%>
        <div class="portal-card mt-4">
            <div class="portal-card__header">
                <h2 class="portal-card__title">Kalender-Abo</h2>
            </div>
            <div class="portal-card__body" style="padding:16px">
                <p style="font-size:.85rem;color:var(--clr-muted);margin-bottom:12px">
                    Abonnieren Sie Ihren Dienstplan in Google Kalender, Outlook o. ä.
                </p>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" value="$ICalURL" readonly id="icalURL"
                           style="font-size:.75rem;font-family:monospace">
                    <button class="btn btn-sm btn-outline-secondary" id="btnCopyICal" style="flex-shrink:0">Kopieren</button>
                </div>
            </div>
        </div>
    </div>
</div>

<%-- ── Monthly time overview ───────────────────────────────── --%>
<div class="portal-card mt-4">
    <div class="portal-card__header">
        <h2 class="portal-card__title">Zeitübersicht</h2>
        <span class="badge bg-light text-muted" style="font-weight:500">$CurrentMonth</span>
    </div>
    <% if $TimeEntries %>
    <div class="table-responsive">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Revier</th>
                    <th>Eingestempelt</th>
                    <th>Ausgestempelt</th>
                    <th>Dauer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <% loop $TimeEntries %>
                <tr>
                    <td style="font-size:.85rem;font-weight:500">$StartTime.Date</td>
                    <td style="font-size:.85rem">$Assignment.Revir.Name</td>
                    <td style="font-variant-numeric:tabular-nums;font-size:.85rem">$StartTime.Time</td>
                    <td style="font-variant-numeric:tabular-nums;font-size:.85rem">
                        <% if $EndTime %>$EndTime.Time<% else %><span style="color:var(--clr-muted)">aktiv</span><% end_if %>
                    </td>
                    <td style="font-variant-numeric:tabular-nums;font-size:.85rem">
                        <% if $DurationMinutes %>
                        $DurationMinutes Min.
                        <% else %>–<% end_if %>
                    </td>
                    <td><span class="status-pill status-pill--{$Status.LowerCase}">$Status</span></td>
                </tr>
                <% end_loop %>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc">
                    <td colspan="4" style="font-size:.85rem;font-weight:600;padding:10px 16px">Gesamt</td>
                    <td style="font-variant-numeric:tabular-nums;font-size:.85rem;font-weight:700;padding:10px 16px">$TotalHours h</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <% else %>
    <div class="portal-card__body">
        <div class="empty-state">
            <div class="empty-state__icon">⏱️</div>
            <div class="empty-state__title">Noch keine Einträge</div>
            <div class="empty-state__text">Diesen Monat wurden keine Zeiten erfasst.</div>
        </div>
    </div>
    <% end_if %>
</div>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // Save profile
    document.getElementById('profileForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('[type="submit"]');
        btn.disabled = true;
        const body = new URLSearchParams(new FormData(this));
        const resp = await fetch('/portal/profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
            body,
        });
        const data = await resp.json();
        if (data.success) window.portalToast('Gespeichert ✓', 'success');
        else window.portalToast(data.error ?? 'Fehler', 'danger');
        btn.disabled = false;
    });

    // Change password
    document.getElementById('passwordForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const pw1 = document.getElementById('pwNew')?.value;
        const pw2 = document.getElementById('pwConfirm')?.value;
        if (pw1 !== pw2) {
            document.getElementById('pwMismatch').style.display = 'block';
            return;
        }
        document.getElementById('pwMismatch').style.display = 'none';
        const btn = this.querySelector('[type="submit"]');
        btn.disabled = true;
        const body = new URLSearchParams(new FormData(this));
        const resp = await fetch('/portal/profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
            body,
        });
        const data = await resp.json();
        if (data.success) { window.portalToast('Passwort geändert ✓', 'success'); this.reset(); }
        else window.portalToast(data.error ?? 'Fehler', 'danger');
        btn.disabled = false;
    });

    // Copy iCal URL
    document.getElementById('btnCopyICal')?.addEventListener('click', function () {
        const url = document.getElementById('icalURL')?.value;
        if (url) navigator.clipboard.writeText(url).then(() => window.portalToast('URL kopiert ✓', 'success'));
    });
})();
</script>
