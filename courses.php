<?php
/**
 * Administrator Courses Management
 */
$pageTitle = "Courses Management";
require_once __DIR__ . '/includes/header.php';
require_auth('admin');

$message = "";
$messageType = "info";
$importResults = null;

// Handle Course Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_single' || $action === 'edit_single') {
        try {
            $data = [
                'course_id'     => !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null,
                'department'    => $_POST['department'] ?? '',
                'programme'     => $_POST['programme'] ?? '',
                'regulation'    => $_POST['regulation'] ?? '',
                'academic_year' => $_POST['academic_year'] ?? '',
                'semester'      => $_POST['semester'] ?? '',
                'course_code'   => $_POST['course_code'] ?? '',
                'course_name'   => $_POST['course_name'] ?? '',
                'setter_name'   => $_POST['setter_name'] ?? '',
                'setter_email'  => $_POST['setter_email'] ?? '',
                'setter_phone'  => $_POST['setter_phone'] ?? '',
            ];
            [$cid, $how] = services_save_course($data);
            $message = "Course successfully " . ($how === 'created' ? 'added' : 'updated') . ".";
            $messageType = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'delete') {
        $cid = (int)($_POST['course_id'] ?? 0);
        if ($cid > 0) {
            try {
                services_delete_course($cid);
                $message = "Course deleted successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error deleting course: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === 'upload_file') {
        if (!empty($_FILES['courses_file']['tmp_name'])) {
            try {
                $importResults = services_import_courses_from_csv($_FILES['courses_file']['tmp_name']);
                $message = "Import complete: {$importResults['created']} created, {$importResults['updated']} updated, " . count($importResults['errors']) . " row(s) skipped.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Upload error: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Please choose a valid CSV or Excel file.";
            $messageType = "error";
        }
    }
}

// Active Tab
$tab = $_GET['tab'] ?? ($importResults ? 'upload' : 'browse');

// Filter parameters
$filters = [
    'department'    => $_GET['department'] ?? '',
    'programme'     => $_GET['programme'] ?? '',
    'academic_year' => $_GET['academic_year'] ?? '',
    'semester'      => $_GET['semester'] ?? '',
    'status'        => $_GET['status'] ?? '',
    'search'        => $_GET['search'] ?? '',
];

$courses = services_list_courses(array_filter($filters));
$filterOpts = services_filter_options();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-book-bookmark" style="color: var(--vignan-blue);"></i> Courses Management</h1>
        <p class="page-subtitle">Add, edit, bulk import and manage question paper scrutiny courses</p>
    </div>
    <div class="header-actions">
        <a href="<?= BASE_URL ?>/export.php?type=template" class="btn btn-secondary">
            <i class="fa-solid fa-download"></i> Download Upload Template
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Navigation Tabs -->
<div class="role-tabs" style="max-width: 600px; margin-bottom: 1.75rem;">
    <a href="<?= BASE_URL ?>/courses.php?tab=browse" class="role-tab-btn <?= $tab === 'browse' ? 'active' : '' ?>">
        <i class="fa-solid fa-table-list"></i> Browse &amp; Edit (<?= count($courses) ?>)
    </a>
    <a href="<?= BASE_URL ?>/courses.php?tab=upload" class="role-tab-btn <?= $tab === 'upload' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-import"></i> Bulk Upload (Excel/CSV)
    </a>
    <a href="<?= BASE_URL ?>/courses.php?tab=add" class="role-tab-btn <?= $tab === 'add' ? 'active' : '' ?>">
        <i class="fa-solid fa-plus-circle"></i> Add Single Course
    </a>
</div>

<?php if ($tab === 'browse'): ?>
    <!-- BROWSE COURSES TAB -->
    <div class="card">
        <!-- Filter Bar -->
        <form method="GET" action="<?= BASE_URL ?>/courses.php" class="filter-bar">
            <input type="hidden" name="tab" value="browse">
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
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Year &bull; Sem</th>
                        <th>Dept &bull; Prog</th>
                        <th>Course Code &amp; Title</th>
                        <th>Paper Setter</th>
                        <th>External Expert</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                No courses found matching criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?= get_status_badge($c['status']) ?></td>
                                <td><?= htmlspecialchars($c['academic_year']) ?> &bull; Sem <?= htmlspecialchars($c['semester']) ?></td>
                                <td><?= htmlspecialchars($c['department']) ?> (<?= htmlspecialchars($c['programme']) ?>)</td>
                                <td>
                                    <span class="code-col"><?= htmlspecialchars($c['course_code']) ?></span><br>
                                    <strong><?= htmlspecialchars($c['course_name']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c['setter_name']) ?><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($c['setter_email']) ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($c['expert_name'])): ?>
                                        <?= htmlspecialchars($c['expert_name']) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick='editCourse(<?= json_encode($c) ?>)'>
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <form method="POST" action="<?= BASE_URL ?>/courses.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this course and all associated feedback?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--vignan-red);">
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

<?php elseif ($tab === 'upload'): ?>
    <!-- BULK UPLOAD TAB -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-cloud-arrow-up" style="color: var(--vignan-blue);"></i> Upload Courses Sheet (Excel / CSV)</h2>
            <a href="<?= BASE_URL ?>/export.php?type=template" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-download"></i> Download CSV Template
            </a>
        </div>

        <p style="margin-bottom: 1.5rem; color: var(--text-muted);">
            Upload a spreadsheet with course details and paper setters. External experts can either be included in the file or assigned later using the <strong>Allocate Experts</strong> interface.
        </p>

        <form method="POST" action="<?= BASE_URL ?>/courses.php?tab=upload" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_file">
            <div class="form-group" style="border: 2px dashed var(--border-color); padding: 2.5rem 1.5rem; text-align: center; border-radius: var(--radius-md); background: var(--bg-alt);">
                <i class="fa-solid fa-file-excel" style="font-size: 3rem; color: #10B981; margin-bottom: 1rem;"></i>
                <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem;">Select CSV file to import</div>
                <input type="file" name="courses_file" accept=".csv, .xlsx, .xls" required style="margin-bottom: 1rem;">
                <div class="form-help" style="justify-content: center;">
                    <span>Supported formats: CSV (Comma-separated values)</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-file-import"></i> Process &amp; Import Courses
            </button>
        </form>

        <?php if ($importResults && !empty($importResults['errors'])): ?>
            <div style="margin-top: 2rem;">
                <h3 style="color: var(--vignan-red); margin-bottom: 0.75rem;"><i class="fa-solid fa-triangle-exclamation"></i> Skipped Rows</h3>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Row #</th>
                                <th>Issue / Error Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importResults['errors'] as $err): ?>
                                <tr>
                                    <td><strong>Row <?= $err['row'] ?></strong></td>
                                    <td style="color: var(--vignan-red);"><?= htmlspecialchars($err['error']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'add'): ?>
    <!-- ADD SINGLE COURSE TAB -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-plus-circle" style="color: var(--vignan-blue);"></i> Add New Course</h2>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/courses.php?tab=browse">
            <input type="hidden" name="action" value="add_single">

            <h3 style="font-size: 1.05rem; margin-bottom: 1rem; color: var(--vignan-blue);">1. Course Academic Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <input type="text" name="department" class="form-control" placeholder="e.g. CSE, ECE, Mech" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Programme *</label>
                    <input type="text" name="programme" class="form-control" placeholder="e.g. B.Tech, M.Tech" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Regulation</label>
                    <input type="text" name="regulation" class="form-control" placeholder="e.g. R22">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2025-26" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Semester *</label>
                    <input type="text" name="semester" class="form-control" placeholder="e.g. III, IV, V" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Course Code *</label>
                    <input type="text" name="course_code" class="form-control" placeholder="e.g. CS301" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Course Title / Name *</label>
                <input type="text" name="course_name" class="form-control" placeholder="e.g. Data Structures and Algorithms" required>
            </div>

            <h3 style="font-size: 1.05rem; margin: 1.5rem 0 1rem; color: var(--vignan-shield);">2. Internal Paper Setter / Course Faculty</h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Paper Setter Name *</label>
                    <input type="text" name="setter_name" class="form-control" placeholder="e.g. Dr. A. Kumar" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Setter E-mail (Login ID) *</label>
                    <input type="email" name="setter_email" class="form-control" placeholder="setter@vignan.ac.in" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Setter Contact No.</label>
                    <input type="text" name="setter_phone" class="form-control" placeholder="e.g. 9876543210">
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-save"></i> Save Course
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Edit Course Modal -->
<div class="modal-overlay" id="editCourseModal">
    <div class="modal-container">
        <form method="POST" action="<?= BASE_URL ?>/courses.php?tab=browse">
            <input type="hidden" name="action" value="edit_single">
            <input type="hidden" name="course_id" id="edit_cid">

            <div class="modal-header">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--vignan-blue);">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Course Details
                </h3>
                <button type="button" class="btn-close" onclick="closeModal('editCourseModal')">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <input type="text" name="department" id="edit_dept" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Programme *</label>
                        <input type="text" name="programme" id="edit_prog" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Regulation</label>
                        <input type="text" name="regulation" id="edit_reg" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Academic Year *</label>
                        <input type="text" name="academic_year" id="edit_ay" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester *</label>
                        <input type="text" name="semester" id="edit_sem" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Code *</label>
                        <input type="text" name="course_code" id="edit_code" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Course Name *</label>
                    <input type="text" name="course_name" id="edit_name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Paper Setter Name *</label>
                        <input type="text" name="setter_name" id="edit_sn" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Setter E-mail *</label>
                        <input type="email" name="setter_email" id="edit_se" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Setter Phone</label>
                        <input type="text" name="setter_phone" id="edit_sp" class="form-control">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCourseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCourse(c) {
    document.getElementById('edit_cid').value = c.id;
    document.getElementById('edit_dept').value = c.department;
    document.getElementById('edit_prog').value = c.programme;
    document.getElementById('edit_reg').value = c.regulation;
    document.getElementById('edit_ay').value = c.academic_year;
    document.getElementById('edit_sem').value = c.semester;
    document.getElementById('edit_code').value = c.course_code;
    document.getElementById('edit_name').value = c.course_name;
    document.getElementById('edit_sn').value = c.setter_name;
    document.getElementById('edit_se').value = c.setter_email;
    document.getElementById('edit_sp').value = c.setter_phone || '';
    openModal('editCourseModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
