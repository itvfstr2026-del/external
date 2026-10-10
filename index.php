<?php
/**
 * Vignan Question Paper Feedback Portal - Login Screen
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = user_role();
    if ($role === 'admin') {
        header("Location: " . BASE_URL . "/dashboard.php");
    } elseif ($role === 'expert') {
        header("Location: " . BASE_URL . "/expert_courses.php");
    } else {
        header("Location: " . BASE_URL . "/setter_courses.php");
    }
    exit;
}

$error = "";
$role = $_POST['role'] ?? 'expert';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'admin') {
        $password = trim($_POST['password'] ?? '');
        if (login_admin($password)) {
            header("Location: " . BASE_URL . "/dashboard.php");
            exit;
        } else {
            $error = "Invalid administrator password.";
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $access_code = trim($_POST['access_code'] ?? '');
        if (login_person($role, $email, $access_code)) {
            if ($role === 'expert') {
                header("Location: " . BASE_URL . "/expert_courses.php");
            } else {
                header("Location: " . BASE_URL . "/setter_courses.php");
            }
            exit;
        } else {
            $error = "E-mail / Access code not recognised. Please verify credentials or contact the Examination Cell.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?> - <?= INSTITUTION_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/vignan-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-brand">
        <img src="<?= BASE_URL ?>/assets/img/vignan-logo.svg" alt="Vignan's University Logo" class="login-logo">
        <h1 class="login-portal-title">Question Paper Feedback Portal</h1>
        <p class="login-portal-sub">External Scrutinizer Feedback &bull; Action Taken Report</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- Role Selection Tabs -->
    <div class="role-tabs" id="roleTabs">
        <button type="button" class="role-tab-btn <?= $role === 'expert' ? 'active' : '' ?>" data-role="expert">
            <i class="fa-solid fa-user-graduate"></i> External Expert
        </button>
        <button type="button" class="role-tab-btn <?= $role === 'setter' ? 'active' : '' ?>" data-role="setter">
            <i class="fa-solid fa-chalkboard-user"></i> Paper Setter
        </button>
        <button type="button" class="role-tab-btn <?= $role === 'admin' ? 'active' : '' ?>" data-role="admin">
            <i class="fa-solid fa-shield-halved"></i> Administrator
        </button>
    </div>

    <!-- Login Form -->
    <form method="POST" action="<?= BASE_URL ?>/index.php" id="loginForm">
        <input type="hidden" name="role" id="selectedRole" value="<?= htmlspecialchars($role) ?>">

        <!-- Expert & Setter Fields -->
        <div id="facultyFields" style="<?= $role === 'admin' ? 'display:none;' : '' ?>">
            <div class="form-group">
                <label class="form-label" for="emailInput">Registered E-mail ID</label>
                <input type="email" name="email" id="emailInput" class="form-control" placeholder="name@domain.edu" <?= $role !== 'admin' ? 'required' : '' ?>>
            </div>
            <div class="form-group">
                <label class="form-label" for="codeInput">Access Code</label>
                <input type="password" name="access_code" id="codeInput" class="form-control" placeholder="8-character access code" maxlength="12" <?= $role !== 'admin' ? 'required' : '' ?>>
                <div class="form-help">
                    <span>Issued by Controller of Examinations</span>
                </div>
            </div>
        </div>

        <!-- Administrator Fields -->
        <div id="adminFields" style="<?= $role !== 'admin' ? 'display:none;' : '' ?>">
            <div class="form-group">
                <label class="form-label" for="adminPassword">Administrator Password</label>
                <input type="password" name="password" id="adminPassword" class="form-control" placeholder="Enter administrator password" <?= $role === 'admin' ? 'required' : '' ?>>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Portal
        </button>
    </form>
</div>

<script>
document.querySelectorAll('.role-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.role-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const role = btn.getAttribute('data-role');
        document.getElementById('selectedRole').value = role;

        const facultyFields = document.getElementById('facultyFields');
        const adminFields = document.getElementById('adminFields');
        const emailInput = document.getElementById('emailInput');
        const codeInput = document.getElementById('codeInput');
        const adminPassword = document.getElementById('adminPassword');

        if (role === 'admin') {
            facultyFields.style.display = 'none';
            adminFields.style.display = 'block';
            emailInput.removeAttribute('required');
            codeInput.removeAttribute('required');
            adminPassword.setAttribute('required', 'required');
        } else {
            facultyFields.style.display = 'block';
            adminFields.style.display = 'none';
            emailInput.setAttribute('required', 'required');
            codeInput.setAttribute('required', 'required');
            adminPassword.removeAttribute('required');
        }
    });
});
</script>

</body>
</html>
