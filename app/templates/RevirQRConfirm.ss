<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Check-In — $Revir.Name</title>
    <style>
        :root {
            --color-primary: #2563eb;
            --color-success: #16a34a;
            --color-bg: #f8fafc;
            --color-card: #ffffff;
            --color-text: #1e293b;
            --color-muted: #64748b;
            --radius: 1rem;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            background: var(--color-card);
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            text-align: center;
        }
        .card__icon { font-size: 3rem; margin-bottom: 1rem; }
        .card__title { font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
        .card__subtitle { color: var(--color-muted); margin-bottom: 1.5rem; }
        .card__meta {
            background: var(--color-bg);
            border-radius: .5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .card__meta dt { font-size: .75rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: .05em; }
        .card__meta dd { font-weight: 600; margin-bottom: .5rem; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .875rem 2rem;
            border: none;
            border-radius: .5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: opacity .15s;
        }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn--primary { background: var(--color-primary); color: #fff; }
        .btn--success { background: var(--color-success); color: #fff; }
        .status { margin-top: 1rem; padding: .75rem; border-radius: .5rem; font-size: .9rem; }
        .status--success { background: #dcfce7; color: #166534; }
        .status--error   { background: #fee2e2; color: #991b1b; }
        .gps-hint { font-size: .8rem; color: var(--color-muted); margin-top: .75rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card__icon">📍</div>
        <h1 class="card__title">QR Check-In</h1>
        <p class="card__subtitle">Bestätigen Sie Ihren Anwesenheitsnachweis</p>

        <dl class="card__meta">
            <dt>Revier</dt>
            <dd>$Revir.Name</dd>
            <dt>Objekt</dt>
            <dd>$CleaningObject.Name</dd>
            <dt>Mitarbeiter</dt>
            <dd>$Worker.Name</dd>
        </dl>

        <button id="js-checkin-btn" class="btn btn--primary" type="button">
            ✓ Jetzt einchecken
        </button>

        <p class="gps-hint" id="js-gps-hint">📡 GPS-Position wird ermittelt…</p>

        <div id="js-status" style="display:none" class="status"></div>
    </div>

    <script>
    (function () {
        'use strict';

        const btn    = document.getElementById('js-checkin-btn');
        const status = document.getElementById('js-status');
        const hint   = document.getElementById('js-gps-hint');
        const token  = '$Token';
        const csrf   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        let latitude  = null;
        let longitude = null;

        // Try to get GPS position
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    latitude  = pos.coords.latitude.toFixed(6);
                    longitude = pos.coords.longitude.toFixed(6);
                    hint.textContent = '📍 GPS-Position gefunden (' + latitude + ', ' + longitude + ')';
                },
                () => {
                    hint.textContent = '📡 GPS nicht verfügbar — Check-In ohne Standort möglich';
                },
                { timeout: 8000 }
            );
        } else {
            hint.textContent = '📡 GPS nicht unterstützt';
        }

        btn.addEventListener('click', async function () {
            btn.disabled = true;
            btn.textContent = '⏳ Wird gesendet…';

            const body = new URLSearchParams({
                token,
                SecurityID: csrf,
            });
            if (latitude  !== null) body.append('latitude',  latitude);
            if (longitude !== null) body.append('longitude', longitude);

            try {
                const resp = await fetch('/revir-qr/confirm', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrf,
                    },
                    body: body.toString(),
                });

                const data = await resp.json();
                status.style.display = 'block';

                if (data.success) {
                    status.className = 'status status--success';
                    status.textContent = '✅ Eingecheckt bei ' + data.revir_name
                        + ' um ' + new Date(data.scanned_at).toLocaleTimeString('de-DE');
                    btn.className   = 'btn btn--success';
                    btn.textContent = '✓ Eingecheckt';
                } else {
                    status.className = 'status status--error';
                    status.textContent = '❌ Fehler: ' + (data.error ?? 'Unbekannter Fehler');
                    btn.disabled    = false;
                    btn.textContent = '✓ Erneut versuchen';
                }
            } catch (e) {
                status.style.display = 'block';
                status.className     = 'status status--error';
                status.textContent   = '❌ Netzwerkfehler — bitte erneut versuchen';
                btn.disabled    = false;
                btn.textContent = '✓ Erneut versuchen';
            }
        });
    })();
    </script>
</body>
</html>
