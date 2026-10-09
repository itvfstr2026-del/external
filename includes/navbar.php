<?php
/**
 * Navigation Bar Component with Vignan University Logo and Role Links
 */
$user = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<header class="top-header no-print">
    <div class="header-container">
        <!-- University Logo & Brand -->
        <a href="<?= BASE_URL ?>/<?= $user['role'] === 'admin' ? 'dashboard.php' : ($user['role'] === 'expert' ? 'expert_courses.php' : 'setter_courses.php') ?>" class="brand-wrapper">
            <img src="<?= BASE_URL ?>/assets/img/vignan-logo.svg" alt="Vignan University" class="brand-logo-img">
            <div class="brand-info">
                <span class="brand-title">QP Feedback Portal</span>
                <span class="brand-tag"><?= INSTITUTION_NAME ?></span>
            </div>
        </a>

        <!-- Role Based Navigation Menu -->
        <nav>
            <ul class="nav-menu">
                <?php if ($user['role'] === 'admin'): ?>
                    <li class="nav-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
                    </li>
                    <li class="nav-item <?= $currentScript === 'courses.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/courses.php"><i class="fa-solid fa-book-bookmark"></i> Courses</a>
                    </li>
                    <li class="nav-item <?= $currentScript === 'allocate.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/allocate.php"><i class="fa-solid fa-link"></i> Allocate Experts</a>
                    </li>
                    <li class="nav-item <?= $currentScript === 'people.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/people.php"><i class="fa-solid fa-users"></i> Experts & Setters</a>
                    </li>
                    <li class="nav-item <?= $currentScript === 'downloads.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/downloads.php"><i class="fa-solid fa-download"></i> Downloads</a>
                    </li>
                <?php elseif ($user['role'] === 'expert'): ?>
                    <li class="nav-item <?= in_array($currentScript, ['expert_courses.php', 'expert_feedback.php']) ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/expert_courses.php"><i class="fa-solid fa-list-check"></i> My Allocated Courses</a>
                    </li>
                <?php elseif ($user['role'] === 'setter'): ?>
                    <li class="nav-item <?= in_array($currentScript, ['setter_courses.php', 'setter_atr.php']) ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/setter_courses.php"><i class="fa-solid fa-file-lines"></i> Action Taken Reports</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- User Information & Logout -->
        <div style="display: flex; align-items: center; gap: 0.9rem;">
            <div class="user-profile-badge">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-role-label role-<?= htmlspecialchars($user['role']) ?>">
                        <?= $user['role'] === 'admin' ? 'Administrator' : ($user['role'] === 'expert' ? 'External Expert' : 'Paper Setter') ?>
                    </span>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Log Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</header>
