<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR-Bestätigung – $SiteConfig.Title</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css"
          integrity="sha512-b2QcjmzCzfpFDoMdUQpFOLJTiUFOGNQv6/N00EFC7GkTcX23Nn7AINmchsQQyHNOMO8iVFGI7cFU7dLFZQ0A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --clr-primary: #2563eb;
            --clr-success: #16a34a;
            --clr-danger:  #dc2626;
            --clr-warning: #d97706;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .qr-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.04);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }
        .qr-card__top {
            padding: 32px 32px 24px;
            text-align: center;
        }
        .qr-icon {
            width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .qr-icon--success { background: #dcfce7; }
        .qr-icon--error   { background: #fee2e2; }
        .qr-icon--already { background: #fef9c3; }
        .qr-card__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 6px; color: #0f172a; }
        .qr-card__sub   { font-size: .88rem; color: #64748b; }
        .qr-card__body  { padding: 0 24px 24px; }
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: .87rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row__label { color: #64748b; font-weight: 500; }
        .info-row__value { font-weight: 600; color: #0f172a; text-align: right; }
        .qr-card__footer {
            padding: 0 24px 28px;
            display: flex;
            gap: 10px;
        }
        .btn-full {
            display: block; width: 100%; padding: 11px;
            border-radius: 10px; border: none; cursor: pointer;
            font-weight: 600; font-size: .88rem; text-decoration: none;
            text-align: center; transition: opacity .15s;
        }
        .btn-full:hover { opacity: .88; }
        .btn-primary-full { background: var(--clr-primary); color: #fff; }
        .btn-outline-full  { background: #fff; color: #475569; border: 1.5px solid #e2e8f0; }
    </style>
</head>
<body>

<div class="qr-card">
    <div class="qr-card__top">

        <% if $Success %>

        <div class="qr-icon qr-icon--success">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--clr-success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="qr-card__title">Eingestempelt ✓</div>
        <div class="qr-card__sub">Check-In erfolgreich registriert</div>

        <% else_if $AlreadyDone %>

        <div class="qr-icon qr-icon--already">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="qr-card__title">Bereits eingestempelt</div>
        <div class="qr-card__sub">Für dieses Revier wurde heute bereits eingecheckt</div>

        <% else %>

        <div class="qr-icon qr-icon--error">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--clr-danger)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="qr-card__title">Ungültiger QR-Code</div>
        <div class="qr-card__sub">$ErrorMessage</div>

        <% end_if %>

    </div>

    <% if $RevirName %>
    <div class="qr-card__body">
        <% if $RevirName %>
        <div class="info-row">
            <span class="info-row__label">Revier</span>
            <span class="info-row__value">$RevirName</span>
        </div>
        <% end_if %>
        <% if $ObjectName %>
        <div class="info-row">
            <span class="info-row__label">Objekt</span>
            <span class="info-row__value">$ObjectName</span>
        </div>
        <% end_if %>
        <% if $WorkerName %>
        <div class="info-row">
            <span class="info-row__label">Mitarbeiter</span>
            <span class="info-row__value">$WorkerName</span>
        </div>
        <% end_if %>
        <% if $ScannedAt %>
        <div class="info-row">
            <span class="info-row__label">Uhrzeit</span>
            <span class="info-row__value">$ScannedAt</span>
        </div>
        <% end_if %>
        <% if $Latitude %>
        <div class="info-row">
            <span class="info-row__label">Standort</span>
            <span class="info-row__value">
                <a href="https://maps.google.com/?q=$Latitude,$Longitude" target="_blank" rel="noopener"
                   style="color:var(--clr-primary)">Karte öffnen 📍</a>
            </span>
        </div>
        <% end_if %>
    </div>
    <% end_if %>

    <div class="qr-card__footer">
        <a href="/portal" class="btn-full btn-primary-full">Zum Portal</a>
        <% if $Success %>
        <a href="/portal/clockIn" class="btn-full btn-outline-full">Einstempeln</a>
        <% end_if %>
    </div>
</div>

</body>
</html>
