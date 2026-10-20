<div class="manager-portal">
    <header class="portal-header">
        <h1 class="portal-header__title">$Title</h1>
        <nav class="portal-nav">
            <a href="/manager/" class="portal-nav__item">Übersicht</a>
            <a href="/manager/workers" class="portal-nav__item">Mitarbeiter</a>
            <a href="/manager/revirs" class="portal-nav__item portal-nav__item--active">Reviere</a>
            <a href="/manager/swaps" class="portal-nav__item">Tauschanfragen</a>
            <a href="/manager/minijob" class="portal-nav__item">Minijob</a>
        </nav>
    </header>

    <div class="revir-grid">
        <% loop $Revirs %>
        <div class="revir-card $StatusClass">
            <div class="revir-card__name">$Revir.Name</div>
            <div class="revir-card__object">$Revir.CleaningObject.Name</div>
            <% if $Worker %>
            <div class="revir-card__worker">
                <span class="revir-card__worker-icon">👤</span>
                $Worker.Name
            </div>
            <% else %>
            <div class="revir-card__worker revir-card__worker--unassigned">Nicht besetzt</div>
            <% end_if %>

            <% if $CheckedIn %>
            <div class="revir-card__checkin">
                ✅ Check-In: {$LastCheckIn.ScannedAt}
                <% if $LastCheckIn.Latitude %>
                <a href="https://maps.google.com/?q={$LastCheckIn.Latitude},{$LastCheckIn.Longitude}"
                   target="_blank" rel="noopener" class="link link--map">📍</a>
                <% end_if %>
            </div>
            <% else_if $Assignment %>
            <div class="revir-card__checkin revir-card__checkin--missing">
                ⏳ Kein Check-In
            </div>
            <% end_if %>
        </div>
        <% end_loop %>
    </div>
</div>
