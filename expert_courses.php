<?php
/**
 * External Expert - Allocated Courses Dashboard
 */
$pageTitle = "My Allocated Courses";
require_once __DIR__ . '/includes/header.php';
require_auth('expert');

$user = current_user();
$courses = services_list_courses(['expert_id' => $user['id']]);

$pendingCount = 0;
foreach ($courses as $c) {
    if ($c['status'] === ST_AWAIT_EXPERT) {
        $pendingCount++;
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-list-check" style="color: var(--vignan-blue);"></i> Allocated Question Papers for Review</h1>
        <p class="page-subtitle">
            Welcome, <strong><?= htmlspecialchars($user['name']) ?></strong> &bull; <?= count($courses) ?> course(s) assigned &bull; 
            <span style="color: var(--status-orange); font-weight: 700;"><?= $pendingCount ?> awaiting your scrutiny</span>
        </p>
    </div>
</div>

<?php if (empty($courses)): ?>
    <div class="card" style="text-align: center; padding: 4rem 2rem;">
        <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h2 style="font-size: 1.35rem; color: var(--dark-primary); margin-bottom: 0.5rem;">No Courses Allocated Yet</h2>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">
            The Examination Cell has not assigned any question papers to your account yet. You will see them listed here once allocated.
        </p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
        <?php foreach ($courses as $c): ?>
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
                        <span class="code-col" style="font-size: 1.15rem;"><?= htmlspecialchars($c['course_code']) ?></span>
                        <?= get_status_badge($c['status']) ?>
                    </div>

                    <h2 style="font-size: 1.2rem; margin-bottom: 0.75rem; color: var(--dark-primary);">
                        <?= htmlspecialchars($c['course_name']) ?>
                    </h2>

                    <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.6;">
                        <div><strong>Department:</strong> <?= htmlspecialchars($c['department']) ?> (<?= htmlspecialchars($c['programme']) ?> <?= htmlspecialchars($c['regulation']) ?>)</div>
                        <div><strong>Academic Term:</strong> Year <?= htmlspecialchars($c['academic_year']) ?> &bull; Semester <?= htmlspecialchars($c['semester']) ?></div>
                        <div><strong>Paper Setter:</strong> <?= htmlspecialchars($c['setter_name']) ?> (<?= htmlspecialchars($c['setter_email']) ?>)</div>
                    </div>
                </div>

                <div style="padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                    <?php if (($c['feedback_status'] ?? '') === 'submitted'): ?>
                        <span style="color: var(--status-green); font-size: 0.85rem; font-weight: 700;">
                            <i class="fa-solid fa-check-double"></i> Feedback Submitted
                        </span>
                        <a href="<?= BASE_URL ?>/expert_feedback.php?course_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-eye"></i> View Feedback
                        </a>
                    <?php else: ?>
                        <span style="color: var(--status-yellow); font-size: 0.85rem; font-weight: 700;">
                            <i class="fa-solid fa-clock"></i> Scrutiny Pending
                        </span>
                        <a href="<?= BASE_URL ?>/expert_feedback.php?course_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-pen-nib"></i> Fill Scrutiny Feedback
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
