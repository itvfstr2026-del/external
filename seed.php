<?php
/**
 * Database Demo Seeder
 * Populates sample courses, paper setters, and external experts for instant testing.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/services.php';

$pdo = DB::getConnection();

// Sample Demo Courses
$demoCourses = [
    [
        'department'    => 'CSE',
        'programme'     => 'B.Tech',
        'regulation'    => 'R22',
        'academic_year' => '2025-26',
        'semester'      => 'III',
        'course_code'   => 'CS301',
        'course_name'   => 'Data Structures & Algorithms',
        'setter_name'   => 'Dr. K. Srinivas Rao',
        'setter_email'  => 'srinivas.cse@vignan.ac.in',
        'setter_phone'  => '9848012345',
        'expert_name'   => 'Prof. A. V. Ramanathan',
        'expert_email'  => 'ramanathan.expert@iitm.ac.in',
        'expert_phone'  => '9876543210',
        'expert_designation_institution' => 'Professor, Department of CSE, IIT Madras'
    ],
    [
        'department'    => 'CSE',
        'programme'     => 'B.Tech',
        'regulation'    => 'R22',
        'academic_year' => '2025-26',
        'semester'      => 'III',
        'course_code'   => 'CS302',
        'course_name'   => 'Database Management Systems',
        'setter_name'   => 'Dr. P. Madhavi Latha',
        'setter_email'  => 'madhavi.cse@vignan.ac.in',
        'setter_phone'  => '9848054321',
        'expert_name'   => 'Prof. A. V. Ramanathan',
        'expert_email'  => 'ramanathan.expert@iitm.ac.in',
        'expert_phone'  => '9876543210',
        'expert_designation_institution' => 'Professor, Department of CSE, IIT Madras'
    ],
    [
        'department'    => 'ECE',
        'programme'     => 'B.Tech',
        'regulation'    => 'R22',
        'academic_year' => '2025-26',
        'semester'      => 'IV',
        'course_code'   => 'EC401',
        'course_name'   => 'Digital Signal Processing',
        'setter_name'   => 'Dr. M. Venkateswara Rao',
        'setter_email'  => 'venkatesh.ece@vignan.ac.in',
        'setter_phone'  => '9848099887',
        'expert_name'   => 'Prof. T. S. Narayanan',
        'expert_email'  => 'narayanan.ece@nitw.ac.in',
        'expert_phone'  => '9440112233',
        'expert_designation_institution' => 'Senior Professor, NIT Warangal'
    ],
    [
        'department'    => 'IT',
        'programme'     => 'B.Tech',
        'regulation'    => 'R22',
        'academic_year' => '2025-26',
        'semester'      => 'V',
        'course_code'   => 'IT501',
        'course_name'   => 'Cloud Computing Architecture',
        'setter_name'   => 'Dr. G. Ramesh Kumar',
        'setter_email'  => 'ramesh.it@vignan.ac.in',
        'setter_phone'  => '9848033445',
        'expert_name'   => '',
        'expert_email'  => '',
        'expert_phone'  => '',
        'expert_designation_institution' => ''
    ]
];

$results = [];
foreach ($demoCourses as $c) {
    try {
        [$cid, $how] = services_save_course($c, $pdo);
        $results[] = "Course {$c['course_code']} ({$c['course_name']}) - $how";
    } catch (Exception $e) {
        $results[] = "Error saving {$c['course_code']}: " . $e->getMessage();
    }
}

$experts = services_list_people('expert');
$setters = services_list_people('setter');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Data Initialized | <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/vignan-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body style="background: var(--bg-page); padding: 2rem 1rem;">
<div style="max-width: 900px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-circle-check" style="color: var(--status-green);"></i> Demo Sample Courses &amp; Accounts Loaded!</h2>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Return to Login</a>
        </div>

        <div class="alert alert-success">
            <strong>Courses Populated:</strong><br>
            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                <?php foreach ($results as $res): ?>
                    <li><?= htmlspecialchars($res) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <h3 style="margin: 1.5rem 0 0.75rem; color: var(--dark-primary);"><i class="fa-solid fa-user-graduate" style="color: var(--vignan-blue);"></i> External Expert Login Credentials</h3>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email ID (Login)</th>
                        <th>Access Code</th>
                        <th>Designation &amp; Institution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($experts as $ex): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ex['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($ex['email']) ?></code></td>
                            <td><span style="background: var(--vignan-blue-light); color: var(--vignan-blue-dark); font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 4px; font-family: monospace; font-size: 1.05rem;"><?= htmlspecialchars($ex['access_code']) ?></span></td>
                            <td><?= htmlspecialchars($ex['designation_institution']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="margin: 1.5rem 0 0.75rem; color: var(--dark-primary);"><i class="fa-solid fa-chalkboard-user" style="color: var(--vignan-shield);"></i> Paper Setter Login Credentials</h3>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Email ID (Login)</th>
                        <th>Access Code</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($setters as $st): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                            <td><?= htmlspecialchars($st['department']) ?></td>
                            <td><code><?= htmlspecialchars($st['email']) ?></code></td>
                            <td><span style="background: var(--bg-alt); color: var(--dark-primary); font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 4px; font-family: monospace; font-size: 1.05rem;"><?= htmlspecialchars($st['access_code']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="margin: 1.5rem 0 0.75rem; color: var(--dark-primary);"><i class="fa-solid fa-shield-halved" style="color: var(--vignan-red);"></i> Administrator Account</h3>
        <div style="background: var(--bg-alt); padding: 1rem 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <strong>Role:</strong> Administrator &bull; <em>(Use your configured master admin password)</em>
        </div>

        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary btn-lg"><i class="fa-solid fa-arrow-right-to-bracket"></i> Proceed to Login</a>
        </div>
    </div>
</div>
</body>
</html>
