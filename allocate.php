<?php
/**
 * Administrator Allocate Courses to External Experts
 */
$pageTitle = "Allocate External Experts";
require_once __DIR__ . '/includes/header.php';
require_auth('admin');

$message = "";
$messageType = "info";

// Handle Allocation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selectedIds = $_POST['course_ids'] ?? [];

    if (empty($selectedIds)) {
        $message = "Please select at least one course using the checkboxes.";
        $messageType = "error";
    } else {
        if ($action === 'allocate') {
            $expertType = $_POST['expert_type'] ?? 'existing';
            $expertId = null;

            try {
                if ($expertType === 'new') {
                    $newName = $_POST['new_expert_name'] ?? '';
                    $newEmail = $_POST['new_expert_email'] ?? '';
                    $newPhone = $_POST['new_expert_phone'] ?? '';
                    $newDesig = $_POST['new_expert_desig'] ?? '';

                    [$expertId, ] = services_upsert_person('expert', $newName, $newEmail, $newPhone, $newDesig);
                } else {
                    $expertId = (int)($_POST['expert_id'] ?? 0);
                    if ($expertId <= 0) {
                        throw new ValidationException(["Please choose an external expert."]);
                    }
                }

                $rep = services_allocate_expert($selectedIds, $expertId);
                $message = "Successfully allocated {$rep['allocated']} course(s) to external expert.";
                if (!empty($rep['skipped_already_submitted'])) {
                    $message .= " (" . count($rep['skipped_already_submitted']) . " course(s) skipped as feedback is already submitted).";
                }
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Allocation error: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($action === 'unallocate') {
            try {
                $rep = services_allocate_expert($selectedIds, null);
                $message = "Successfully unallocated {$rep['allocated']} course(s).";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// Filters (Default to Unallocated for convenience)
$filters = [
    'department'    => $_GET['department'] ?? '',
    'programme'     => $_GET['programme'] ?? '',
    'academic_year' => $_GET['academic_year'] ?? '',
    'semester'      => $_GET['semester'] ?? '',
    'status'        => $_GET['status'] ?? (isset($_GET['filtered']) ? '' : ST_UNALLOCATED),
    'search'        => $_GET['search'] ?? '',
];

$courses = services_list_courses(array_filter($filters));
$filterOpts = services_filter_options();
$experts = services_list_people('expert');
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-link" style="color: var(--vignan-blue);"></i> Allocate Courses to External Experts</h1>
        <p class="page-subtitle">Select one or more courses and assign external subject scrutinizers</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Filter Bar -->
<form method="GET" action="<?= BASE_URL ?>/allocate.php" class="filter-bar">
    <input type="hidden" name="filtered" value="1">
    <div class="filter-item">
        <label>Academic Year</label>
        <select name="academic_year" class="form-control" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($filterOpts['academic_year'] as $ay): ?>
                <option value="<?= htmlspecialchars($ay) ?>" <?= $filters['academic_year'] === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item">
        <label>Semester</label>
        <select name="semester" class="form-control" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($filterOpts['semester'] as $sem): ?>
                <option value="<?= htmlspecialchars($sem) ?>" <?= $filters['semester'] === $sem ? 'selected' : '' ?>><?= htmlspecialchars($sem) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item">
        <label>Department</label>
        <select name="department" class="form-control" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($filterOpts['department'] as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department'] === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item">
        <label>Status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach (ALL_STATUSES as $st): ?>
                <option value="<?= htmlspecialchars($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item">
        <label>Search</label>
        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filters['search']) ?>">
    </div>
    <div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-filter"></i> Filter</button>
    </div>
</form>

<form method="POST" action="<?= BASE_URL ?>/allocate.php" id="allocationForm">
    <!-- Allocation Assignment Bar Card -->
    <div class="card" style="background: var(--bg-alt); border: 2px solid var(--vignan-blue-light);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-weight: 800; font-size: 1.1rem; color: var(--dark-primary);" id="selected-count-display">0 selected</span>
                <span style="color: var(--text-muted); font-size: 0.85rem;">(Select rows in table below)</span>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button type="submit" name="action" value="allocate" class="btn btn-primary bulk-action-btn" disabled>
                    <i class="fa-solid fa-user-check"></i> Allocate Selected
                </button>
                <button type="submit" name="action" value="unallocate" class="btn btn-outline bulk-action-btn" disabled onclick="return confirm('Remove external expert allocation for selected courses?');">
                    <i class="fa-solid fa-user-slash"></i> Remove Allocation
                </button>
            </div>
        </div>

        <!-- Expert Selection Toggle -->
        <div class="form-row">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Assign to External Expert</label>
                <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                    <label class="custom-checkbox" style="margin-bottom:0;">
                        <input type="radio" name="expert_type" value="existing" checked onchange="toggleExpertInputs()"> Existing Expert
                    </label>
                    <label class="custom-checkbox" style="margin-bottom:0;">
                        <input type="radio" name="expert_type" value="new" onchange="toggleExpertInputs()"> + Add New Expert Inline
                    </label>
                </div>

                <div id="existingExpertBox">
                    <select name="expert_id" class="form-control">
                        <option value="">-- Select Registered Expert --</option>
                        <?php foreach ($experts as $ex): ?>
                            <option value="<?= $ex['id'] ?>">
                                <?= htmlspecialchars($ex['name']) ?> (<?= htmlspecialchars($ex['email']) ?>) &bull; <?= $ex['course_count'] ?> assigned
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Inline New Expert Form Box -->
        <div id="newExpertBox" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Expert Name *</label>
                    <input type="text" name="new_expert_name" class="form-control" placeholder="e.g. Prof. A. V. Ramanathan">
                </div>
                <div class="form-group">
                    <label class="form-label">Expert E-mail (Login) *</label>
                    <input type="email" name="new_expert_email" class="form-control" placeholder="expert@university.edu">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation &amp; Institution</label>
                    <input type="text" name="new_expert_desig" class="form-control" placeholder="e.g. Professor, IIT Madras">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact No.</label>
                    <input type="text" name="new_expert_phone" class="form-control" placeholder="9876543210">
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" class="select-all-checkbox" title="Select All">
                        </th>
                        <th>Status</th>
                        <th>Academic Year &bull; Sem</th>
                        <th>Dept &bull; Code</th>
                        <th>Course Name</th>
                        <th>Paper Setter</th>
                        <th>Assigned Expert</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                No courses matching current filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="course_ids[]" value="<?= $c['id'] ?>" class="row-checkbox">
                                </td>
                                <td><?= get_status_badge($c['status']) ?></td>
                                <td><?= htmlspecialchars($c['academic_year']) ?> &bull; Sem <?= htmlspecialchars($c['semester']) ?></td>
                                <td>
                                    <span style="font-weight: 700;"><?= htmlspecialchars($c['department']) ?></span> &bull; 
                                    <span class="code-col"><?= htmlspecialchars($c['course_code']) ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($c['course_name']) ?></strong></td>
                                <td><?= htmlspecialchars($c['setter_name']) ?></td>
                                <td>
                                    <?php if (!empty($c['expert_name'])): ?>
                                        <span style="font-weight: 600; color: var(--vignan-blue);"><?= htmlspecialchars($c['expert_name']) ?></span><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($c['expert_email']) ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-style: italic;">Not allocated</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
function toggleExpertInputs() {
    const isNew = document.querySelector('input[name="expert_type"]:checked').value === 'new';
    document.getElementById('existingExpertBox').style.display = isNew ? 'none' : 'block';
    document.getElementById('newExpertBox').style.display = isNew ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
