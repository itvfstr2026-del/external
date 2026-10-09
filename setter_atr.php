<?php
/**
 * Paper Setter - Action Taken Report (ATR) Form
 */
$pageTitle = "Action Taken Report";
require_once __DIR__ . '/includes/header.php';
require_auth('setter');

$user = current_user();
$courseId = (int)($_GET['course_id'] ?? 0);

if ($courseId <= 0) {
    header("Location: " . BASE_URL . "/setter_courses.php");
    exit;
}

try {
    $course = services_get_course($courseId);
    if ((int)$course['setter_id'] !== (int)$user['id']) {
        throw new PermissionDeniedException("You are not the paper setter for this course.");
    }
} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

$fb = services_get_feedback($courseId);
if (!$fb || $fb['status'] !== 'submitted') {
    ?>
    <div class="card" style="text-align: center; padding: 4rem 2rem;">
        <i class="fa-solid fa-hourglass-half" style="font-size: 3rem; color: var(--status-yellow); margin-bottom: 1rem;"></i>
        <h2 style="font-size: 1.35rem; color: var(--dark-primary); margin-bottom: 0.5rem;">Expert Feedback Not Submitted Yet</h2>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 1.5rem;">
            The external expert (<strong><?= htmlspecialchars($course['expert_name'] ?: 'External Scrutinizer') ?></strong>) has not submitted their scrutiny feedback for this course yet. You will be able to fill the Action Taken Report once the expert submits their feedback.
        </p>
        <a href="<?= BASE_URL ?>/setter_courses.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Return to Courses</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$person = services_get_person($user['id'], true);
$atr = services_get_action_report($courseId);
$isSubmitted = $atr && ($atr['status'] === 'submitted');

$message = "";
$messageType = "info";

// Handle Form Submission
// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isSubmitted) {
    $action = $_POST['submit_action'] ?? 'draft';
    $isFinalSubmit = ($action === 'submit');

    $summary      = $_POST['summary'] ?? '';
    $declarations = $_POST['declarations'] ?? [];
    $retained     = $_POST['retained'] ?? [];
    $fdate        = $_POST['form_date'] ?? date('Y-m-d');
    $contact      = $_POST['contact'] ?? '';

    // Convert checkboxes to boolean array
    $declArr = [];
    for ($i = 0; $i < count(DECLARATIONS); $i++) {
        $declArr[$i] = isset($declarations[$i]) && $declarations[$i] === '1';
    }

    $retArr = [];
    for ($i = 0; $i < count(RETAINED_DOCS); $i++) {
        $retArr[$i] = isset($retained[$i]) && $retained[$i] === '1';
    }

    try {
        // 1. Process Digital Signature if drawn on canvas or uploaded as file
        $removeBg = isset($_POST['remove_bg']);
        $rawSig = "";
        if (!empty($_POST['sig_canvas_data']) && str_starts_with($_POST['sig_canvas_data'], 'data:image')) {
            $parts = explode(',', $_POST['sig_canvas_data']);
            $rawSig = base64_decode($parts[1]);
        } elseif (!empty($_FILES['sig_file']['tmp_name'])) {
            $rawSig = file_get_contents($_FILES['sig_file']['tmp_name']);
        }

        if (!empty($rawSig)) {
            services_set_signature($user['id'], $rawSig, 'signature', $removeBg);
            $person = services_get_person($user['id'], true);
        }

        // 2. Save Action Taken Report
        $atr = services_save_action_report(
            $courseId,
            $user['id'],
            $summary,
            $declArr,
            $retArr,
            $fdate,
            $contact,
            $isFinalSubmit
        );

        $isSubmitted = ($atr['status'] === 'submitted');
        if ($isFinalSubmit) {
            $message = "Action Taken Report and digital signature successfully submitted and finalized!";
            $messageType = "success";
        } else {
            $message = "Draft and signature saved successfully.";
            $messageType = "success";
        }
    } catch (ValidationException $e) {
        $message = implode("<br>", array_map('htmlspecialchars', $e->errors));
        $messageType = "error";
    } catch (Exception $e) {
        $message = htmlspecialchars($e->getMessage());
        $messageType = "error";
    }
}

// Current values
$curSummary = $atr['summary'] ?? '';
$curDecl    = $atr['declarations'] ?? [false, false, false, false];
$curRet     = $atr['retained'] ?? [false, false, false];
$curDate    = $atr['form_date'] ?? date('Y-m-d');
$curContact = $atr['contact'] ?? $person['phone'];
?>

<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/setter_courses.php" class="btn btn-outline btn-sm" style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-arrow-left"></i> Back to My Courses
        </a>
        <h1 class="page-title"><i class="fa-solid fa-file-shield" style="color: var(--vignan-blue);"></i> Action Taken Report (ATR)</h1>
        <p class="page-subtitle">Summative Question Paper Scrutiny &bull; To be completed by Paper Setter</p>
    </div>
    <div class="header-actions">
        <?php if ($isSubmitted): ?>
            <a href="<?= BASE_URL ?>/view_form.php?id=<?= $courseId ?>" class="btn btn-primary" target="_blank">
                <i class="fa-solid fa-eye"></i> View Final Report
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fa-solid fa-circle-info"></i>
        <div><?= $message ?></div>
    </div>
<?php endif; ?>

<!-- Course Information Card -->
<div class="card" style="background: linear-gradient(to right, #FFFFFF, var(--vignan-blue-light)); border-left: 5px solid var(--vignan-blue);">
    <div class="card-header" style="border-bottom-color: rgba(0, 112, 235, 0.15);">
        <h2 class="card-title" style="color: var(--vignan-blue-dark);">
            <i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($course['course_code']) ?> &ndash; <?= htmlspecialchars($course['course_name']) ?>
        </h2>
        <?= get_status_badge($course['status']) ?>
    </div>
    <div class="form-row" style="font-size: 0.92rem; color: var(--text-main);">
        <div><strong>Department:</strong> <?= htmlspecialchars($course['department']) ?> (<?= htmlspecialchars($course['programme']) ?> <?= htmlspecialchars($course['regulation']) ?>)</div>
        <div><strong>Academic Year / Sem:</strong> <?= htmlspecialchars($course['academic_year']) ?> / Semester <?= htmlspecialchars($course['semester']) ?></div>
        <div><strong>External Scrutinizer:</strong> <?= htmlspecialchars($course['expert_name']) ?> (<?= htmlspecialchars($course['expert_email']) ?>)</div>
    </div>
</div>

<!-- Collapsible External Expert Feedback Review -->
<div class="card">
    <div class="card-header" style="cursor: pointer;" onclick="document.getElementById('expertReviewBody').classList.toggle('hidden')">
        <h2 class="card-title" style="font-size: 1.15rem; color: var(--vignan-blue);">
            <i class="fa-solid fa-comments"></i> External Expert Scrutiny Observations (Read-Only)
        </h2>
        <span style="font-size: 0.85rem; color: var(--vignan-blue); font-weight: 700;">Click to Toggle</span>
    </div>

    <div id="expertReviewBody">
        <div style="margin-bottom: 1.25rem;">
            <strong>Overall Expert Recommendation:</strong> 
            <span style="background: var(--vignan-blue-light); color: var(--vignan-blue-dark); font-weight: 800; padding: 0.25rem 0.65rem; border-radius: 4px;">
                <?= htmlspecialchars($fb['recommendation'] ?: '—') ?>
            </span>
        </div>

        <?php if (!empty($fb['overall_remarks'])): ?>
            <div style="background: var(--bg-alt); padding: 1rem 1.25rem; border-radius: var(--radius-sm); border-left: 4px solid var(--vignan-blue); margin-bottom: 1.5rem;">
                <strong>Overall Remarks &amp; Suggestions by Expert:</strong><br>
                <p style="margin-top: 0.35rem; white-space: pre-wrap; color: var(--text-main);"><?= htmlspecialchars($fb['overall_remarks']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Criteria Ratings Table -->
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Scrutiny Criterion</th>
                        <th style="width: 180px;">Rating Given</th>
                        <th>Expert Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (CRITERIA as $idx => $crit): ?>
                        <?php 
                            $cNum = $idx + 1;
                            $score = (int)($fb['ratings'][$cNum] ?? ($fb['ratings'][(string)$cNum] ?? 0));
                            $remark = $fb['remarks'][$cNum] ?? ($fb['remarks'][(string)$cNum] ?? '');
                        ?>
                        <tr>
                            <td><strong><?= $cNum ?></strong></td>
                            <td><?= htmlspecialchars($crit['title']) ?></td>
                            <td>
                                <?php if ($score > 0): ?>
                                    <span style="font-weight: 800; color: var(--vignan-blue);"><?= $score ?></span> &ndash; <?= RATING_LABELS[$score] ?? '' ?>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($remark) ? htmlspecialchars($remark) : '<span style="color: var(--text-light); font-style: italic;">No specific remarks</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ATR Form -->
<form method="POST" action="<?= BASE_URL ?>/setter_atr.php?course_id=<?= $courseId ?>" enctype="multipart/form-data">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-user-pen" style="color: var(--vignan-blue);"></i> Paper Setter Details</h2>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Name of Paper Setter</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($person['name']) ?>" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Department</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($course['department']) ?>" readonly disabled>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($curContact) ?>" placeholder="e.g. 9848012345" <?= $isSubmitted ? 'readonly disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label class="form-label">E-mail ID</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($person['email']) ?>" readonly disabled>
            </div>
        </div>
    </div>

    <!-- 4 Declarations -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-clipboard-check" style="color: var(--vignan-blue);"></i> Declaration by Paper Setter / Subject Expert</h2>
        </div>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Please read and tick all four declarations before submitting the final Action Taken Report:
        </p>

        <?php foreach (DECLARATIONS as $idx => $declText): ?>
            <label class="custom-checkbox">
                <input type="checkbox" name="declarations[<?= $idx ?>]" value="1" <?= !empty($curDecl[$idx]) ? 'checked' : '' ?> <?= $isSubmitted ? 'disabled' : '' ?> required>
                <span><strong><?= $idx + 1 ?>.</strong> <?= htmlspecialchars($declText) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <!-- Summary of Major Changes Incorporated -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-pen-fancy" style="color: var(--vignan-blue);"></i> Summary of Major Changes Incorporated</h2>
        </div>

        <div class="form-group">
            <label class="form-label">Detailed Summary of Corrections / Improvements Made in Question Paper *</label>
            <textarea name="summary" class="form-control" style="min-height: 180px;" placeholder="Document the specific modifications incorporated into the question paper based on the external expert's observations..." data-max-chars="<?= SUMMARY_MAX_CHARS ?>" data-counter-id="summary_counter" <?= $isSubmitted ? 'readonly disabled' : '' ?> required><?= htmlspecialchars($curSummary) ?></textarea>
            <div class="form-help">
                <span>Must fit official Word template text box (maximum <?= SUMMARY_MAX_CHARS ?> characters)</span>
                <span class="char-counter" id="summary_counter">0 / <?= SUMMARY_MAX_CHARS ?></span>
            </div>
        </div>

        <div class="form-row" style="margin-top: 1.5rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Date of ATR Submission *</label>
                <input type="date" name="form_date" class="form-control" value="<?= htmlspecialchars($curDate) ?>" <?= $isSubmitted ? 'readonly disabled' : '' ?> required>
            </div>
        </div>
    </div>

    <!-- Documents to be Retained -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-folder-tree" style="color: var(--vignan-blue);"></i> Documents to be Retained</h2>
        </div>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Confirm archival and retention of the following mandatory examination documents:
        </p>

        <?php foreach (RETAINED_DOCS as $idx => $docTitle): ?>
            <label class="custom-checkbox">
                <input type="checkbox" name="retained[<?= $idx ?>]" value="1" <?= !empty($curRet[$idx]) ? 'checked' : '' ?> <?= $isSubmitted ? 'disabled' : '' ?>>
                <span><?= htmlspecialchars($docTitle) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <!-- Digital Signature Endorsement Card at the End of Form -->
    <div class="card" style="border: 2px solid var(--vignan-blue-light); background: #FFFFFF;">
        <div class="card-header" style="background: linear-gradient(135deg, rgba(0, 112, 235, 0.06), rgba(0, 112, 235, 0.01));">
            <h2 class="card-title" style="color: var(--vignan-blue-dark);">
                <i class="fa-solid fa-signature" style="color: var(--vignan-blue);"></i> Paper Setter Endorsement: Digital Signature
            </h2>
            <?php if (!empty($person['signature'])): ?>
                <span class="status-badge status-complete"><i class="fa-solid fa-check"></i> Digital Signature Available</span>
            <?php else: ?>
                <span class="status-badge status-setter"><i class="fa-solid fa-asterisk"></i> Signature Required Before Submit</span>
            <?php endif; ?>
        </div>

        <input type="hidden" name="sig_canvas_data" id="sig-canvas-data">

        <?php if ($isSubmitted): ?>
            <!-- Submitted Read-Only Display -->
            <div style="margin-top: 1rem;">
                <label class="form-label"><strong>Submitted Paper Setter Signature</strong></label>
                <?php if (!empty($atr['signature'])): ?>
                    <div class="signature-preview-box" style="max-width: 320px;">
                        <img src="data:image/png;base64,<?= base64_encode($atr['signature']) ?>" alt="Signature" class="signature-preview-img">
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted); font-style: italic;">No signature recorded.</p>
                <?php endif; ?>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                    <i class="fa-solid fa-circle-info"></i> Paper Setter signature is officially recorded. HoD and School Dean sign on the printed physical copy.
                </p>
            </div>
        <?php else: ?>
            <!-- Interactive Signature Section Before Submitting -->
            <div style="margin-top: 1rem;">
                <?php if (!empty($person['signature'])): ?>
                    <div style="margin-bottom: 1.25rem;">
                        <span style="font-size: 0.88rem; color: var(--text-muted);">Current Active Signature:</span>
                        <div class="signature-preview-box" style="margin-top: 0.35rem; min-height: 90px; max-width: 350px;">
                            <img src="data:image/png;base64,<?= base64_encode($person['signature']) ?>" alt="Saved Signature" class="signature-preview-img">
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('setter-sig-box').classList.toggle('hidden');">
                            <i class="fa-solid fa-arrows-rotate"></i> Change / Re-draw Signature
                        </button>
                    </div>
                <?php endif; ?>

                <div id="setter-sig-box" class="<?= !empty($person['signature']) ? 'hidden' : '' ?>" style="background: var(--bg-alt); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px dashed var(--border-color); max-width: 500px;">
                    <!-- Canvas Draw -->
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.88rem;"><strong>Option A: Draw Signature on Pad</strong> <span style="color: var(--vignan-red);">*</span></label>
                        <div style="border: 2px dashed #94a3b8; border-radius: var(--radius-sm); background: #FFF; width: 100%; max-width: 450px;">
                            <canvas id="sig-canvas" width="450" height="140" style="display: block; width: 100%; height: 140px; cursor: crosshair; touch-action: none;"></canvas>
                        </div>
                        <div style="margin-top: 0.35rem; display: flex; justify-content: space-between; align-items: center;">
                            <button type="button" id="sig-canvas-clear" class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-rotate-left"></i> Clear Pad
                            </button>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Draw using touch, pen, or mouse</span>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <label class="form-label" style="font-size: 0.88rem;"><strong>Option B: Or Upload Signature Image</strong></label>
                        <input type="file" name="sig_file" accept="image/png, image/jpeg" class="form-control" style="font-size: 0.85rem;">
                        <label class="custom-checkbox" style="margin-top: 0.4rem; font-size: 0.8rem;">
                            <input type="checkbox" name="remove_bg" value="1" checked>
                            <span>Auto-remove white background for clean stamping</span>
                        </label>
                    </div>
                </div>

                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 1rem;">
                    <i class="fa-solid fa-circle-info" style="color: var(--vignan-blue);"></i> Your digital signature will be embedded onto the Action Taken Report. HoD and Dean signatures will be acquired on the physical copy.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <?php if (!$isSubmitted): ?>
        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
            <button type="submit" name="submit_action" value="draft" class="btn btn-secondary btn-lg">
                <i class="fa-solid fa-floppy-disk"></i> Save Draft
            </button>
            <button type="submit" name="submit_action" value="submit" class="btn btn-primary btn-lg" onclick="return confirm('Submit final Action Taken Report? The report will be locked upon submission.');">
                <i class="fa-solid fa-circle-check"></i> Submit Action Taken Report
            </button>
        </div>
    <?php endif; ?>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
