<!DOCTYPE html>
<html lang="$ContentLocale">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="$SecurityID">
    <title><% if $MetaTitle %>$MetaTitle<% else %>$Title<% end_if %> — Reinigung SaaS</title>

    <%-- Bootstrap 5.3 --%>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <%-- Portal design system --%>
    <link rel="stylesheet" href="$resourceURL('youwilllikeit/silverstripe-cleaning:client/css/portal.css')">

    $HeadTags
</head>
<body>

<div class="portal-shell">

    <%-- ── Sidebar ──────────────────────────────────────────── --%>
    <aside class="portal-sidebar" id="portalSidebar">

        <a href="/" class="sidebar-brand">
            <% if $SiteConfig.Logo %>
            <img src="$SiteConfig.Logo.ScaleWidth(64).URL" alt="$SiteConfig.Title">
            <% else %>
            <div style="width:32px;height:32px;background:var(--clr-primary);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            </div>
            <% end_if %>
            <div>
                <div class="sidebar-brand__name">$SiteConfig.Title</div>
                <div class="sidebar-brand__role">
                    <% if $currentMember.inGroup('super-admin') %>Super Admin
                    <% else_if $currentMember.inGroup('company-admin') %>Company Admin
                    <% else_if $currentMember.inGroup('area-manager') %>Area Manager
                    <% else_if $currentMember.inGroup('workers') %>Mitarbeiter
                    <% else_if $currentMember.inGroup('client') %>Auftraggeber
                    <% else %>Portal
                    <% end_if %>
                </div>
            </div>
        </a>

        <nav class="sidebar-nav">

            <%-- Manager nav --%>
            <% if $currentMember.inGroup('area-manager') || $currentMember.inGroup('company-admin') || $currentMember.inGroup('super-admin') %>
            <div class="sidebar-nav__section">Manager</div>

            <a href="/manager/" class="sidebar-nav__item <% if $URLSegment == 'manager' && !$Action %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Übersicht
            </a>

            <a href="/manager/workers" class="sidebar-nav__item <% if $Action == 'workers' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Mitarbeiter
            </a>

            <a href="/manager/revirs" class="sidebar-nav__item <% if $Action == 'revirs' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Reviere
            </a>

            <a href="/manager/swaps" class="sidebar-nav__item <% if $Action == 'swaps' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Tauschanfragen
                <% if $PendingSwaps %><span class="sidebar-nav__badge">$PendingSwaps</span><% end_if %>
            </a>

            <a href="/manager/minijob" class="sidebar-nav__item <% if $Action == 'minijob' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Minijob-Monitor
            </a>
            <% end_if %>

            <%-- Worker nav --%>
            <% if $currentMember.inGroup('workers') || $currentMember.inGroup('area-manager') || $currentMember.inGroup('company-admin') || $currentMember.inGroup('super-admin') %>
            <div class="sidebar-nav__section" style="margin-top:8px">Mitarbeiter</div>

            <a href="/portal/" class="sidebar-nav__item <% if $URLSegment == 'portal' && !$Action %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Mein Portal
            </a>

            <a href="/portal/schedule" class="sidebar-nav__item <% if $Action == 'schedule' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Dienstplan
            </a>

            <a href="/portal/swaps" class="sidebar-nav__item <% if $Action == 'swaps' && $URLSegment == 'portal' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Tauschanfragen
            </a>

            <a href="/portal/profile" class="sidebar-nav__item <% if $Action == 'profile' %>active<% end_if %>">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Mein Profil
            </a>
            <% end_if %>

            <%-- Admin link --%>
            <% if $currentMember.inGroup('super-admin') || $currentMember.inGroup('company-admin') %>
            <div class="sidebar-nav__section" style="margin-top:8px">System</div>
            <a href="/admin/" class="sidebar-nav__item">
                <svg class="sidebar-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 19.07a10 10 0 0 1 0-14.14"/></svg>
                CMS Admin
            </a>
            <% end_if %>

        </nav>

        <div class="sidebar-footer">
            <% if $currentMember %>
            <div class="sidebar-footer__user">
                <div class="sidebar-footer__avatar">
                    $currentMember.FirstName.FirstChar.UCFirst
                </div>
                <div>
                    <div class="sidebar-footer__name">$currentMember.Name</div>
                    <a href="/Security/logout?SecurityID=$SecurityID" class="sidebar-footer__logout">Abmelden</a>
                </div>
            </div>
            <% end_if %>
        </div>

    </aside>

    <%-- ── Main ──────────────────────────────────────────────── --%>
    <main class="portal-main">

        <div class="portal-topbar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menü">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <span class="portal-topbar__title">$Title</span>
            <span class="portal-topbar__date">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span id="topbarDate"></span>
            </span>
        </div>

        <div class="portal-content">
            $Layout
        </div>

    </main>

</div>

<%-- Toast container --%>
<div class="portal-toast" id="portalToast"></div>

<%-- Sidebar overlay (mobile) --%>
<div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99" onclick="closeSidebar()"></div>

<%-- Bootstrap JS --%>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<%-- Chart.js --%>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
(function () {
    'use strict';

    // Topbar date
    const d = new Date();
    const opts = { weekday:'short', day:'2-digit', month:'short', year:'numeric' };
    const el = document.getElementById('topbarDate');
    if (el) el.textContent = d.toLocaleDateString('de-DE', opts);

    // Sidebar toggle
    const sidebar  = document.getElementById('portalSidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');

    window.closeSidebar = function () {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
    };

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('open');
            overlay.style.display = isOpen ? 'block' : 'none';
        });
    }

    // Toast helper — global
    window.portalToast = function (msg, type) {
        const toast = document.getElementById('portalToast');
        if (!toast) return;
        toast.textContent = msg;
        toast.className = 'portal-toast portal-toast--' + (type || 'success') + ' show';
        setTimeout(() => toast.classList.remove('show'), 3500);
    };

    // Chart.js defaults
    if (window.Chart) {
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.font.size   = 12;
        Chart.defaults.color       = '#64748b';
        Chart.defaults.plugins.legend.position = 'bottom';
    }
})();
</script>

$Scripts
</body>
</html>
