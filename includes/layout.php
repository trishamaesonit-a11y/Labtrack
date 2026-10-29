<?php
require_once __DIR__ . '/connection.php';

function icon($n, $size = 18) {
    static $i = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
        'tag' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'repeat' => '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'dollar' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'mail' => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'menu' => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'flask' => '<path d="M9 3h6M10 3v6L4.5 19a2 2 0 0 0 1.8 3h11.4a2 2 0 0 0 1.8-3L14 9V3"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
    ];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($i[$n] ?? '') . '</svg>';
}

function head($title, $userId = null) {
    $userAttribute = $userId === null ? '' : ' data-user-id="' . e($userId) . '"';
    echo '<!DOCTYPE html><html lang="en"' . $userAttribute . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' · LabTrack</title>'
       . '<script>try{document.documentElement.dataset.theme=localStorage.getItem("lt-theme")||"dark"}catch(e){document.documentElement.dataset.theme="dark"}</script>'
       . '<link rel="stylesheet" href="' . asset_url('assets/css/style.css') . '"></head>';
}
function asset_url($path) {
    $file = __DIR__ . '/../' . ltrim($path, '/\\');
    $version = is_file($file) ? (string)filemtime($file) : '1';
    return url($path) . '?v=' . rawurlencode($version);
}
function theme_toggle() {
    return '<button type="button" class="theme-toggle" aria-label="Toggle light/dark mode">' . icon('moon', 16) . '<span class="knob"></span>' . icon('sun', 16) . '</button>';
}
function accent_picker() {
    return '<div class="accent-picker"><label for="accent-color-input">Accent color</label><input id="accent-color-input" type="color" class="accent-input" aria-label="Choose accent color" value="#22c55e"><button type="button" class="accent-reset" aria-label="Reset accent color" title="Reset accent color">Reset</button></div>';
}
function background_picker() {
    return '<div class="color-preference"><label for="background-color-input">Background</label><input id="background-color-input" type="color" class="color-preference-input" data-preference="background" aria-label="Choose background color" value="#04120b"><button type="button" class="color-preference-reset" data-reset-preference="background" aria-label="Reset background color" title="Reset background color">Reset</button></div>';
}
function glass_picker() {
    return '<div class="color-preference"><label for="glass-color-input">Glass</label><input id="glass-color-input" type="color" class="color-preference-input" data-preference="glass" aria-label="Choose glass color" value="#ffffff"><button type="button" class="color-preference-reset" data-reset-preference="glass" aria-label="Reset glass color" title="Reset glass color">Reset</button></div>';
}
function appearance_controls() {
    return '<div class="appearance-controls">' . accent_picker() . background_picker() . glass_picker() . '</div>';
}
function flash_html() {
    $o = '';
    foreach (flash() as [$t, $m]) $o .= '<div class="flash ' . $t . '">' . e($m) . '</div>';
    return $o;
}

function page_start($title, $area, $active) {
    $u = me();
    head($title, $u['user_id']);
    if ($area === 'admin') {
        $pending = (int)val("SELECT COUNT(*) FROM borrow_records WHERE status='Requested'");
        $unread  = (int)val("SELECT COUNT(*) FROM messages m JOIN users s ON s.user_id=m.sender_id WHERE m.group_id IS NULL AND m.is_read=0 AND (m.recipient_id=? OR (m.recipient_id IS NULL AND s.role<>'admin'))", [$u['user_id']]);
        $nav = [
            'dashboard'   => ['Dashboard', 'grid', 'admin/dashboard.php', 0],
            'equipment'   => ['Equipment', 'box', 'admin/equipment.php', 0],
            'categories'  => ['Categories', 'tag', 'admin/categories.php', 0],
            'borrows'     => ['Borrow Records', 'repeat', 'admin/borrows.php', $pending],
            'maintenance' => ['Maintenance', 'tool', 'admin/maintenance.php', 0],
            'fines'       => ['Fines & Penalties', 'dollar', 'admin/fines.php', 0],
            'messages'    => ['Messages', 'mail', 'admin/messages.php', $unread],
            'users'       => ['Users', 'users', 'admin/users.php', 0],
        ];
        echo '<body class="admin"><div class="shell"><aside class="side glass"><a class="brand" href="' . url('admin/dashboard.php') . '"><span class="logo">' . icon('flask', 20) . '</span><span>LAB<b>TRACK</b><small>ADMIN CONSOLE</small></span></a><nav>';
        foreach ($nav as $k => [$label, $ic, $href, $n]) {
            echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . url($href) . '">' . icon($ic) . '<span>' . e($label) . '</span>' . ($n ? '<em class="pill">' . $n . '</em>' : '') . '</a>';
        }
        echo '</nav><div class="side-foot"><a class="' . ($active === 'profile' ? 'on' : '') . '" href="' . url('admin/profile.php') . '">' . icon('settings') . '<span>Settings</span></a><a href="' . url('logout.php') . '">' . icon('logout') . '<span>Log out</span></a>' . theme_toggle() . appearance_controls() . '</div></aside>';
        echo '<main class="main"><header class="top"><button class="icon-btn menu-btn" type="button" aria-label="Menu">' . icon('menu') . '</button><h1>' . e($title) . '</h1><div class="top-tools">'
           . '<form class="search glass-s" action="' . url('admin/equipment.php') . '" method="get">' . icon('search', 16) . '<input name="q" placeholder="Search equipment…"></form>'
           . '<a class="icon-btn glass-s" href="' . url('admin/borrows.php?status=Requested') . '" title="Pending requests">' . icon('bell') . ($pending ? '<em class="dot">' . $pending . '</em>' : '') . '</a>'
           . '<a class="chip glass-s" href="' . url('admin/profile.php') . '">' . user_avatar($u) . '<span class="nm">' . e($u['full_name']) . '</span></a></div></header>';
    } else {
        $unread = (int)val("SELECT COUNT(*) FROM messages m LEFT JOIN users s ON s.user_id=m.sender_id WHERE m.group_id IS NULL AND m.is_read=0 AND (m.recipient_id=? OR (m.recipient_id IS NULL AND s.role='admin'))", [$u['user_id']]);
        $nav = [
            'dashboard' => ['Dashboard', 'home', 'user/dashboard.php', 0],
            'equipment' => ['Equipment', 'box', 'user/equipment.php', 0],
            'borrows'   => ['My Borrows', 'repeat', 'user/borrows.php', 0],
            'fines'     => ['My Fines', 'dollar', 'user/fines.php', 0],
            'messages'  => ['Messages', 'mail', 'user/messages.php', $unread],
        ];
        echo '<body class="user"><div class="uapp">'
           . '<header class="utop glass"><button class="icon-btn menu-btn" type="button" aria-label="Open menu" aria-expanded="false">' . icon('menu') . '</button>'
           . '<a class="brand" href="' . url('user/dashboard.php') . '"><span class="logo">' . icon('flask', 20) . '</span><span>LAB<b>TRACK</b></span></a>'
           . '<div class="utools">' . theme_toggle() . appearance_controls()
           . '<a class="chip glass-s" href="' . url('user/profile.php') . '" title="My profile">' . user_avatar($u) . '<span class="nm">' . e($u['full_name']) . '<small>' . e(ucfirst($u['role'])) . '</small></span></a>'
           . '<a class="icon-btn glass-s" href="' . url('logout.php') . '" title="Log out" aria-label="Log out">' . icon('logout') . '</a></div></header>'
           . '<div class="ubody"><aside class="uside glass" id="uside"><nav aria-label="Main">';
        foreach ($nav as $k => [$label, $ic, $href, $n]) {
            echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . url($href) . '">' . icon($ic, 18) . '<span>' . e($label) . '</span>' . ($n ? '<em class="pill">' . $n . '</em>' : '') . '</a>';
        }
        echo '</nav></aside><div class="nav-backdrop"></div>'
           . '<main class="ucontent"><h1 class="ptitle">' . e($title) . '</h1>';
    }
    echo flash_html();
}
function page_end() {
    echo '</main></div></div><div id="tip" class="tip"></div><script src="' . asset_url('assets/js/app.js') . '"></script></body></html>';
}
