<?php
/**
 * Paper Setter - Assigned Courses & ATR Dashboard
 */
$pageTitle = "My Assigned Courses";
require_once __DIR__ . '/includes/header.php';
require_auth('setter');

$user = current_user();
$courses = services_list_courses(['setter_id' => $user['id']]);

$readyCount = 0;
foreach ($courses as $c) {
    if ($c['status'] === ST_AWAIT_SETTER) {
        $readyCount++;
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-file-lines" style="color: var(--vignan-blue);"></i> Action Taken Report (ATR) Portal</h1>
        <p class="page-subtitle">
            Welcome, <strong><?= htmlspecialchars($user['name']) ?></strong> &bull; <?= count($courses) ?> course(s) &bull; 
            <span style="color: var(--status-orange); font-weight: 700;"><?= $readyCount ?> ready for Action Taken Report</span>
        </p>
    </div>
</div>

<?php if (empty($courses)): ?>
    <div class="card" style="text-align: center; padding: 4rem 2rem;">
        <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h2 style="font-size: 1.35rem; color: var(--dark-primary); margin-bottom: 0.5rem;">No Courses Assigned</h2>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">
            You have not been assigned as a paper setter for any active courses yet.
        </p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
        <?php foreach ($courses as $c): ?>
            <?php 
                $fbSubmitted = ($c['feedback_status'] ?? '') === 'submitted';
                $atrSubmitted = ($c['atr_status'] ?? '') === 'submitted';
            ?>
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
                        <div><strong>Term:</strong> Year <?= htmlspecialchars($c['academic_year']) ?> &bull; Semester <?= htmlspecialchars($c['semester']) ?></div>
                        <div><strong>External Scrutinizer:</strong> <?= htmlspecialchars($c['expert_name'] ?: 'Not assigned yet') ?></div>
                    </div>
                </div>

                <div style="padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                    <?php if ($atrSubmitted): ?>
                        <span style="color: var(--status-green); font-size: 0.85rem; font-weight: 700;">
                            <i class="fa-solid fa-check-double"></i> ATR Completed
                        </span>
                        <a href="<?= BASE_URL ?>/setter_atr.php?course_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-eye"></i> View ATR
                        </a>
                    <?php elseif ($fbSubmitted): ?>
                        <span style="color: var(--status-orange); font-size: 0.85rem; font-weight: 700;">
                            <i class="fa-solid fa-hourglass-start"></i> Ready for ATR
                        </span>
                        <a href="<?= BASE_URL ?>/setter_atr.php?course_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-pen-to-square"></i> Fill Action Taken Report
                        </a>
                    <?php else: ?>
                        <span style="color: var(--status-gray); font-size: 0.85rem; font-weight: 600;">
                            <i class="fa-solid fa-clock"></i> Awaiting Expert Feedback
                        </span>
                        <button type="button" class="btn btn-outline btn-sm" disabled title="The external expert must submit their scrutiny feedback first.">
                            Pending Expert
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
