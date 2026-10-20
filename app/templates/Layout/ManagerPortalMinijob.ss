<div class="manager-portal">
    <header class="portal-header">
        <h1 class="portal-header__title">$Title — $Month</h1>
        <nav class="portal-nav">
            <a href="/manager/" class="portal-nav__item">Übersicht</a>
            <a href="/manager/workers" class="portal-nav__item">Mitarbeiter</a>
            <a href="/manager/revirs" class="portal-nav__item">Reviere</a>
            <a href="/manager/swaps" class="portal-nav__item">Tauschanfragen</a>
            <a href="/manager/minijob" class="portal-nav__item portal-nav__item--active">Minijob</a>
        </nav>
    </header>

    <% if $AtRisk %>
    <div class="table-responsive">
        <table class="data-table">
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
                <tr class="<% if $Critical %>row--critical<% end_if %>">
                    <td>$Worker.Name</td>
                    <td>$Hours h</td>
                    <td>{$Earnings} €</td>
                    <td>{$Threshold} €</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-bar__fill <% if $Critical %>progress-bar__fill--critical<% else %>progress-bar__fill--warning<% end_if %>"
                                 style="width: <% if $Percent.RAW > 100 %>100<% else %>$Percent<% end_if %>%"></div>
                        </div>
                        <span class="progress-bar__label">$Percent%</span>
                    </td>
                    <td>
                        <% if $Critical %>
                        <span class="badge badge--danger">⛔ Kritisch</span>
                        <% else %>
                        <span class="badge badge--warning">⚠ Warnung</span>
                        <% end_if %>
                    </td>
                </tr>
                <% end_loop %>
            </tbody>
        </table>
    </div>
    <% else %>
    <p class="empty-state">✅ Keine Mitarbeiter nahe der Minijob-Grenze.</p>
    <% end_if %>
</div>
