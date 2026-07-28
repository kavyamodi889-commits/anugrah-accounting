<?php
/**
 * includes/admin-sidebar.php
 * Shared admin sidebar navigation. Include on every admin page.
 *
 * Usage:
 *   require_once 'includes/admin-sidebar.php';
 *   renderAdminSidebar('dashboard'); // pass the active page key
 */

/**
 * Renders the full admin sidebar navigation.
 *
 * @param string $activePage Key of the currently active page
 */
function renderAdminSidebar($activePage = '') {
    $nav = [
        'dashboard'      => ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard',       'href' => 'admin_dashboard.php'],
        'users'          => ['icon' => 'fa-users',           'label' => 'Users',            'href' => 'admin_users.php'],
        'accounting'     => ['icon' => 'fa-calculator',      'label' => 'Accounting',       'href' => 'admin_accounting.php'],
        'income_tax'     => ['icon' => 'fa-file-invoice',    'label' => 'Income Tax',       'href' => 'admin_income_tax.php'],
        'gst_reg'        => ['icon' => 'fa-registered',      'label' => 'GST Registration', 'href' => 'admin_gst_reg.php'],
        'gst_returns'    => ['icon' => 'fa-file-alt',        'label' => 'GST Returns',      'href' => 'admin_gst_returns.php'],
        'fssai'          => ['icon' => 'fa-utensils',        'label' => 'FSSAI Licence',    'href' => 'admin_fssai.php'],
        'msme'           => ['icon' => 'fa-industry',        'label' => 'MSME Registration','href' => 'admin_msme.php'],
        'cma'            => ['icon' => 'fa-chart-line',      'label' => 'CMA Data',         'href' => 'admin_cma.php'],
        'tax_planning'   => ['icon' => 'fa-coins',           'label' => 'Tax Planning',     'href' => 'admin_tax_planning.php'],
        'documents'      => ['icon' => 'fa-folder-open',     'label' => 'Documents',        'href' => 'admin_documents.php'],
        'messages'       => ['icon' => 'fa-envelope',        'label' => 'Messages',         'href' => 'admin_messages.php'],
        'feedback'       => ['icon' => 'fa-star',            'label' => 'Feedback',         'href' => 'admin_feedback.php'],
        'notifications'  => ['icon' => 'fa-bell',            'label' => 'Notifications',    'href' => 'admin_notifications.php'],
        'settings'       => ['icon' => 'fa-cog',             'label' => 'Settings',         'href' => 'admin_settings.php'],
    ];

    $adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') : 'Admin';
    $adminRole = isset($_SESSION['admin_role']) ? htmlspecialchars($_SESSION['admin_role'], ENT_QUOTES, 'UTF-8') : '';
    ?>
    <div class="sidebar" id="sidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="sidebar-brand-text">
                <span class="brand-name">Anugrah</span>
                <span class="brand-sub">Accounting</span>
            </div>
        </div>

        <!-- Admin info -->
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name"><?= $adminName ?></span>
                <span class="sidebar-user-role"><?= $adminRole ?></span>
            </div>
        </div>

        <hr class="sidebar-divider">

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="sidebar-nav-link <?= ($activePage === $key) ? 'active' : '' ?>"
                   title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>

            <hr class="sidebar-divider">

            <a href="admin_logout.php" class="sidebar-nav-link sidebar-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    <?php
}
?>
