<?php
/**
 * Administrator Analytics Dashboard
 */
$pageTitle = "Administrator Dashboard";
require_once __DIR__ . '/includes/header.php';
require_auth('admin');

// Handle Form Unlock/Re-open action
$message = "";
$messageType = "info";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reopen') {
    $cid = (int)($_POST['course_id'] ?? 0);
    if ($cid > 0) {
        try {
            services_reopen_feedback($cid);
            $message = "Form successfully unlocked and re-opened for editing.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch filters
$filters = [
    'department'    => $_GET['department'] ?? '',
    'programme'     => $_GET['programme'] ?? '',
    'academic_year' => $_GET['academic_year'] ?? '',
    'semester'      => $_GET['semester'] ?? '',
    'status'        => $_GET['status'] ?? '',
    'search'        => $_GET['search'] ?? '',
];

$allCourses = services_list_courses();
$filteredCourses = services_list_courses(array_filter($filters));
$filterOpts = services_filter_options();

// Metric Counts
$totalCount = count($allCourses);
$countUnallocated = 0;
$countAwaitExpert = 0;
$countAwaitSetter = 0;
$countComplete = 0;

$deptStats = [];

foreach ($allCourses as $c) {
    $st = $c['status'];
    if ($st === ST_UNALLOCATED) $countUnallocated++;
    elseif ($st === ST_AWAIT_EXPERT) $countAwaitExpert++;
    elseif ($st === ST_AWAIT_SETTER) $countAwaitSetter++;
    elseif ($st === ST_COMPLETE) $countComplete++;

    $dept = $c['department'] ?: 'Unassigned';
    if (!isset($deptStats[$dept])) {
        $deptStats[$dept] = [
            ST_UNALLOCATED => 0,
            ST_AWAIT_EXPERT => 0,
            ST_AWAIT_SETTER => 0,
            ST_COMPLETE => 0
        ];
    }
    $deptStats[$dept][$st]++;
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-chart-pie" style="color: var(--vignan-blue);"></i> Dashboard Overview</h1>
        <p class="page-subtitle">Summative Question Paper Scrutiny &amp; Action Taken Report Status</p>
    </div>
    <div class="header-actions">
        <a href="<?= BASE_URL ?>/export.php?type=status" class="btn btn-secondary">
            <i class="fa-solid fa-file-excel" style="color: #10B981;"></i> Export Status (CSV)
        </a>
        <a href="<?= BASE_URL ?>/export.php?type=ratings" class="btn btn-secondary">
            <i class="fa-solid fa-star" style="color: #F59E0B;"></i> Export Ratings (CSV)
        </a>
        <button type="button" class="btn btn-outline" onclick="openModal('reopenModal')">
            <i class="fa-solid fa-lock-open" style="color: var(--vignan-red);"></i> Re-open Submitted Form
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Metrics Grid -->
<div class="metrics-grid">
    <div class="metric-card metric-all">
        <div class="metric-icon icon-blue">
            <i class="fa-solid fa-book-open"></i>
        </div>
        <div class="metric-data">
            <div class="metric-num"><?= $totalCount ?></div>
            <div class="metric-label">Total Courses</div>
        </div>
    </div>

    <div class="metric-card metric-unallocated">
        <div class="metric-icon icon-gray">
            <i class="fa-solid fa-user-xmark"></i>
        </div>
        <div class="metric-data">
            <div class="metric-num"><?= $countUnallocated ?></div>
            <div class="metric-label">Not Allocated</div>
        </div>
    </div>

    <div class="metric-card metric-expert">
        <div class="metric-icon icon-yellow">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div class="metric-data">
            <div class="metric-num"><?= $countAwaitExpert ?></div>
            <div class="metric-label">Awaiting Expert</div>
        </div>
    </div>

    <div class="metric-card metric-setter">
        <div class="metric-icon icon-orange">
            <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div class="metric-data">
            <div class="metric-num"><?= $countAwaitSetter ?></div>
            <div class="metric-label">Awaiting Setter ATR</div>
        </div>
    </div>

    <div class="metric-card metric-complete">
        <div class="metric-icon icon-green">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="metric-data">
            <div class="metric-num"><?= $countComplete ?></div>
            <div class="metric-label">Completed</div>
        </div>
    </div>
</div>

<!-- Progress Chart by Department -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-chart-column" style="color: var(--vignan-blue);"></i> Progress by Department</h2>
    </div>
    <div style="height: 280px; width: 100%;">
        <canvas id="deptProgressChart"></canvas>
    </div>
</div>

<!-- Courses Filter & Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-list-check"></i> Courses Overview</h2>
        <span class="brand-tag"><?= count($filteredCourses) ?> matching course(s)</span>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?= BASE_URL ?>/dashboard.php" class="filter-bar">
        <div class="filter-item">
            <label>Academic Year</label>
            <select name="academic_year" class="form-control" onchange="this.form.submit()">
                <option value="">All Years</option>
                <?php foreach ($filterOpts['academic_year'] as $ay): ?>
                    <option value="<?= htmlspecialchars($ay) ?>" <?= $filters['academic_year'] === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Semester</label>
            <select name="semester" class="form-control" onchange="this.form.submit()">
                <option value="">All Semesters</option>
                <?php foreach ($filterOpts['semester'] as $sem): ?>
                    <option value="<?= htmlspecialchars($sem) ?>" <?= $filters['semester'] === $sem ? 'selected' : '' ?>><?= htmlspecialchars($sem) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Department</label>
            <select name="department" class="form-control" onchange="this.form.submit()">
                <option value="">All Departments</option>
                <?php foreach ($filterOpts['department'] as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department'] === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Status</label>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (ALL_STATUSES as $st): ?>
                    <option value="<?= htmlspecialchars($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Code / Title / Faculty" value="<?= htmlspecialchars($filters['search']) ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-filter"></i> Filter</button>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Year / Sem</th>
                    <th>Dept</th>
                    <th>Course Code &amp; Name</th>
                    <th>Paper Setter</th>
                    <th>External Expert</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filteredCourses)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            <i class="fa-solid fa-inbox" style="font-size: 2.2rem; margin-bottom: 0.5rem; display: block;"></i>
                            No courses match the current filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filteredCourses as $c): ?>
                        <tr>
                            <td><?= get_status_badge($c['status']) ?></td>
                            <td><?= htmlspecialchars($c['academic_year']) ?> &bull; Sem <?= htmlspecialchars($c['semester']) ?></td>
                            <td><span style="font-weight: 700;"><?= htmlspecialchars($c['department']) ?></span></td>
                            <td>
                                <span class="code-col"><?= htmlspecialchars($c['course_code']) ?></span><br>
                                <span style="font-weight: 600;"><?= htmlspecialchars($c['course_name']) ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($c['setter_name']) ?><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($c['setter_email']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($c['expert_name'])): ?>
                                    <?= htmlspecialchars($c['expert_name']) ?><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($c['expert_email']) ?></small>
                                <?php else: ?>
                                    <span style="color: var(--text-light); font-style: italic;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="<?= BASE_URL ?>/view_form.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="View Form Report" target="_blank">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                                <a href="<?= BASE_URL ?>/download_doc.php?id=<?= $c['id'] ?>&fmt=docx" class="btn btn-secondary btn-sm" title="Download Word Document">
                                    <i class="fa-solid fa-file-word" style="color: #2B579A;"></i> DOCX
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Re-open Modal -->
<div class="modal-overlay" id="reopenModal">
    <div class="modal-container">
        <form method="POST" action="<?= BASE_URL ?>/dashboard.php">
            <input type="hidden" name="action" value="reopen">
            <div class="modal-header">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--vignan-red);">
                    <i class="fa-solid fa-lock-open"></i> Unlock Submitted Form
                </h3>
                <button type="button" class="btn-close" onclick="closeModal('reopenModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1rem; color: var(--text-muted);">
                    Unlocking a course allows the External Expert and Paper Setter to make corrections and re-submit their reports.
                </p>
                <div class="form-group">
                    <label class="form-label">Select Submitted Course to Re-open</label>
                    <select name="course_id" class="form-control" required>
                        <option value="">-- Choose Course --</option>
                        <?php 
                        $submittedCourses = array_filter($allCourses, fn($c) => ($c['feedback_status'] ?? '') === 'submitted');
                        foreach ($submittedCourses as $sc): 
                        ?>
                            <option value="<?= $sc['id'] ?>">
                                <?= htmlspecialchars($sc['academic_year']) ?> | Sem <?= htmlspecialchars($sc['semester']) ?> | <?= htmlspecialchars($sc['department']) ?> | <?= htmlspecialchars($sc['course_code']) ?> – <?= htmlspecialchars($sc['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reopenModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-unlock"></i> Confirm Re-open</button>
            </div>
        </form>
    </div>
</div>

<!-- Chart Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('deptProgressChart');
    if (!ctx) return;

    const deptLabels = <?= json_encode(array_keys($deptStats)) ?>;
    const dataUnallocated = <?= json_encode(array_map(fn($d) => $d[ST_UNALLOCATED], array_values($deptStats))) ?>;
    const dataAwaitExpert = <?= json_encode(array_map(fn($d) => $d[ST_AWAIT_EXPERT], array_values($deptStats))) ?>;
    const dataAwaitSetter = <?= json_encode(array_map(fn($d) => $d[ST_AWAIT_SETTER], array_values($deptStats))) ?>;
    const dataComplete    = <?= json_encode(array_map(fn($d) => $d[ST_COMPLETE], array_values($deptStats))) ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: deptLabels,
            datasets: [
                {
                    label: 'Completed',
                    data: dataComplete,
                    backgroundColor: '#10B981',
                    borderRadius: 4
                },
                {
                    label: 'Awaiting Setter ATR',
                    data: dataAwaitSetter,
                    backgroundColor: '#F59E0B',
                    borderRadius: 4
                },
                {
                    label: 'Awaiting Expert Feedback',
                    data: dataAwaitExpert,
                    backgroundColor: '#EAB308',
                    borderRadius: 4
                },
                {
                    label: 'Not Allocated',
                    data: dataUnallocated,
                    backgroundColor: '#94A3B8',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', weight: '600' } } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
