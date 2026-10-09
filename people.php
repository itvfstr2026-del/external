<?php
/**
 * Administrator External Experts & Paper Setters Management
 */
$pageTitle = "Faculty & External Experts";
require_once __DIR__ . '/includes/header.php';
require_auth('admin');

$message = "";
$messageType = "info";
$newCodeGenerated = null;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expert') {
        try {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $desig = $_POST['designation_institution'] ?? '';

            services_upsert_person('expert', $name, $email, $phone, $desig);
            $message = "External expert successfully added.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'reset_code') {
        $pid = (int)($_POST['person_id'] ?? 0);
        if ($pid > 0) {
            try {
                $code = services_reset_access_code($pid);
                $p = services_get_person($pid);
                $message = "New access code for {$p['name']}: <strong>{$code}</strong>";
                $messageType = "success";
                $newCodeGenerated = ['name' => $p['name'], 'code' => $code];
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === 'delete') {
        $pid = (int)($_POST['person_id'] ?? 0);
        if ($pid > 0) {
            try {
                services_delete_person($pid);
                $message = "Person deleted successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

$tab = $_GET['tab'] ?? 'expert';
$people = services_list_people($tab);
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-users" style="color: var(--vignan-blue);"></i> Experts &amp; Paper Setters</h1>
        <p class="page-subtitle">Manage portal user accounts, login credentials, and digital access codes</p>
    </div>
    <div class="header-actions">
        <?php if ($tab === 'expert'): ?>
            <button type="button" class="btn btn-primary" onclick="openModal('addExpertModal')">
                <i class="fa-solid fa-user-plus"></i> Add External Expert
            </button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/export.php?type=credentials" class="btn btn-secondary">
            <i class="fa-solid fa-file-csv" style="color: #10B981;"></i> Download All Credentials (CSV)
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fa-solid fa-circle-info"></i> <?= $message ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="role-tabs" style="max-width: 450px; margin-bottom: 1.75rem;">
    <a href="<?= BASE_URL ?>/people.php?tab=expert" class="role-tab-btn <?= $tab === 'expert' ? 'active' : '' ?>">
        <i class="fa-solid fa-user-graduate"></i> External Experts (<?= count(services_list_people('expert')) ?>)
    </a>
    <a href="<?= BASE_URL ?>/people.php?tab=setter" class="role-tab-btn <?= $tab === 'setter' ? 'active' : '' ?>">
        <i class="fa-solid fa-chalkboard-user"></i> Paper Setters (<?= count(services_list_people('setter')) ?>)
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email (Login ID)</th>
                    <th>Access Code</th>
                    <th><?= $tab === 'expert' ? 'Designation & Institution' : 'Department' ?></th>
                    <th>Contact</th>
                    <th>Courses</th>
                    <th>Signature</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($people)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No <?= $tab === 'expert' ? 'external experts' : 'paper setters' ?> registered yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($people as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($p['email']) ?></code></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-family: monospace; font-weight: 800; font-size: 1rem; background: var(--bg-alt); padding: 0.2rem 0.55rem; border-radius: var(--radius-sm); color: var(--dark-primary);">
                                        <?= htmlspecialchars($p['access_code']) ?>
                                    </span>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText('<?= $p['access_code'] ?>'); showToast('Access code copied!', 'info');" title="Copy Code">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($tab === 'expert' ? ($p['designation_institution'] ?: '—') : ($p['department'] ?: '—')) ?></td>
                            <td><?= htmlspecialchars($p['phone'] ?: '—') ?></td>
                            <td>
                                <span class="status-badge" style="background: var(--bg-alt); color: var(--text-main);">
                                    <?= $p['course_count'] ?> course(s)
                                </span>
                            </td>
                            <td>
                                <?php if ($p['has_signature']): ?>
                                    <span style="color: var(--status-green); font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Uploaded</span>
                                <?php else: ?>
                                    <span style="color: var(--text-light); font-size: 0.85rem;"><i class="fa-regular fa-circle"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <form method="POST" action="<?= BASE_URL ?>/people.php?tab=<?= $tab ?>" style="display: inline-block;">
                                    <input type="hidden" name="action" value="reset_code">
                                    <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" title="Generate New Access Code" onclick="return confirm('Generate a new access code for <?= htmlspecialchars($p['name']) ?>?');">
                                        <i class="fa-solid fa-key"></i> Reset Code
                                    </button>
                                </form>
                                <form method="POST" action="<?= BASE_URL ?>/people.php?tab=<?= $tab ?>" style="display: inline-block;" onsubmit="return confirm('Delete <?= htmlspecialchars($p['name']) ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--vignan-red);" title="Delete" <?= $p['course_count'] > 0 ? 'disabled' : '' ?>>
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Expert Modal -->
<div class="modal-overlay" id="addExpertModal">
    <div class="modal-container">
        <form method="POST" action="<?= BASE_URL ?>/people.php?tab=expert">
            <input type="hidden" name="action" value="add_expert">

            <div class="modal-header">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--vignan-blue);">
                    <i class="fa-solid fa-user-plus"></i> Add External Scrutinizer
                </h3>
                <button type="button" class="btn-close" onclick="closeModal('addExpertModal')">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Prof. A. V. Ramanathan" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail ID (Login) *</label>
                    <input type="email" name="email" class="form-control" placeholder="expert@university.edu" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation &amp; Institution</label>
                    <input type="text" name="designation_institution" class="form-control" placeholder="e.g. Professor, Department of CSE, IIT Madras">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. 9876543210">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addExpertModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Add Expert</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
