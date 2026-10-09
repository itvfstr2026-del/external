<?php
/**
 * Administrator Document & Reports Download Center
 */
$pageTitle = "Document Downloads";
require_once __DIR__ . '/includes/header.php';
require_auth('admin');

$message = "";
$messageType = "info";

// Filters
$filters = [
    'department'    => $_GET['department'] ?? '',
    'programme'     => $_GET['programme'] ?? '',
    'academic_year' => $_GET['academic_year'] ?? '',
    'semester'      => $_GET['semester'] ?? '',
    'status'        => $_GET['status'] ?? (isset($_GET['filtered']) ? '' : ST_COMPLETE),
    'search'        => $_GET['search'] ?? '',
];

$courses = services_list_courses(array_filter($filters));
$filterOpts = services_filter_options();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-download" style="color: var(--vignan-blue);"></i> Download Filled Forms &amp; Reports</h1>
        <p class="page-subtitle">Export official Word documents, PDF forms, and institutional summary spreadsheets</p>
    </div>
    <div class="header-actions">
        <a href="<?= BASE_URL ?>/export.php?type=status" class="btn btn-secondary">
            <i class="fa-solid fa-file-excel" style="color: #10B981;"></i> Status Summary (CSV)
        </a>
        <a href="<?= BASE_URL ?>/export.php?type=ratings" class="btn btn-secondary">
            <i class="fa-solid fa-chart-line" style="color: #F59E0B;"></i> Ratings Analysis (CSV)
        </a>
    </div>
</div>

<!-- Bulk ZIP Download Form Card -->
<form method="POST" action="<?= BASE_URL ?>/download_doc.php" id="bulkDownloadForm">
    <input type="hidden" name="action" value="bulk_zip">

    <div class="card" style="background: var(--bg-alt); border: 2px solid var(--vignan-blue-light);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 class="card-title" style="font-size: 1.15rem;">
                    <i class="fa-solid fa-box-archive" style="color: var(--vignan-blue);"></i> Bulk Package (ZIP Archive)
                </h2>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.2rem;">
                    Documents in the ZIP are automatically grouped into Academic Year &gt; Semester &gt; Department folders.
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <label class="custom-checkbox" style="margin-bottom:0;">
                        <input type="checkbox" name="formats[]" value="docx" checked> Word (.docx)
                    </label>
                </div>
                <button type="submit" class="btn btn-primary bulk-action-btn" disabled>
                    <i class="fa-solid fa-file-zipper"></i> Download ZIP for Selected (<span id="selected-count-display">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card">
        <div class="filter-bar" style="margin-bottom: 1.25rem;">
            <input type="hidden" name="filtered" value="1">
            <div class="filter-item">
                <label>Academic Year</label>
                <select name="filter_academic_year" class="form-control" onchange="window.location.href='<?= BASE_URL ?>/downloads.php?academic_year=' + this.value">
                    <option value="">All</option>
                    <?php foreach ($filterOpts['academic_year'] as $ay): ?>
                        <option value="<?= htmlspecialchars($ay) ?>" <?= $filters['academic_year'] === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Semester</label>
                <select name="filter_semester" class="form-control" onchange="window.location.href='<?= BASE_URL ?>/downloads.php?semester=' + this.value">
                    <option value="">All</option>
                    <?php foreach ($filterOpts['semester'] as $sem): ?>
                        <option value="<?= htmlspecialchars($sem) ?>" <?= $filters['semester'] === $sem ? 'selected' : '' ?>><?= htmlspecialchars($sem) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Department</label>
                <select name="filter_department" class="form-control" onchange="window.location.href='<?= BASE_URL ?>/downloads.php?department=' + this.value">
                    <option value="">All</option>
                    <?php foreach ($filterOpts['department'] as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department'] === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Status</label>
                <select name="filter_status" class="form-control" onchange="window.location.href='<?= BASE_URL ?>/downloads.php?filtered=1&status=' + this.value">
                    <option value="">All</option>
                    <?php foreach (ALL_STATUSES as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

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
                        <th>External Expert</th>
                        <th style="text-align: right;">Download Form</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                No courses found for download.
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
                                <td><?= htmlspecialchars($c['expert_name'] ?: '—') ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= BASE_URL ?>/view_form.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" target="_blank" title="View / Print Document">
                                        <i class="fa-solid fa-print"></i> Print/PDF
                                    </a>
                                    <a href="<?= BASE_URL ?>/download_doc.php?id=<?= $c['id'] ?>&fmt=docx" class="btn btn-primary btn-sm" title="Download Official Word Document">
                                        <i class="fa-solid fa-file-word"></i> Word (.docx)
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
