<?php
/**
 * External Expert - Question Paper Scrutiny Feedback Form
 */
$pageTitle = "Question Paper Scrutiny Feedback";
require_once __DIR__ . '/includes/header.php';
require_auth('expert');

$user = current_user();
$courseId = (int)($_GET['course_id'] ?? 0);

if ($courseId <= 0) {
    header("Location: " . BASE_URL . "/expert_courses.php");
    exit;
}

try {
    $course = services_get_course($courseId);
    if ((int)$course['expert_id'] !== (int)$user['id']) {
        throw new PermissionDeniedException("This course is not allocated to you.");
    }
} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

$person = services_get_person($user['id'], true);
$fb = services_get_feedback($courseId);
$isSubmitted = $fb && ($fb['status'] === 'submitted');

$message = "";
$messageType = "info";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isSubmitted) {
    $action = $_POST['submit_action'] ?? 'draft';
    $isFinalSubmit = ($action === 'submit');

    $ratings = $_POST['ratings'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    $reco    = $_POST['recommendation'] ?? '';
    $overall = $_POST['overall_remarks'] ?? '';
    $fdate   = $_POST['form_date'] ?? date('Y-m-d');
    $desig   = $_POST['designation_institution'] ?? '';
    $contact = $_POST['contact'] ?? '';

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

        // 2. Process Official Seal if uploaded
        if (!empty($_FILES['seal_file']['tmp_name'])) {
            $rawSeal = file_get_contents($_FILES['seal_file']['tmp_name']);
            services_set_signature($user['id'], $rawSeal, 'seal', false);
            $person = services_get_person($user['id'], true);
        }

        // 3. Save Scrutiny Feedback
        $fb = services_save_feedback(
            $courseId,
            $user['id'],
            $ratings,
            $remarks,
            $reco,
            $overall,
            $fdate,
            $desig,
            $contact,
            $isFinalSubmit
        );

        $isSubmitted = ($fb['status'] === 'submitted');
        if ($isFinalSubmit) {
            $message = "Your feedback and digital endorsement have been successfully submitted and locked. Thank you!";
            $messageType = "success";
        } else {
            $message = "Draft and signature saved successfully. You can continue editing anytime.";
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
$curRatings = $fb['ratings'] ?? [];
$curRemarks = $fb['remarks'] ?? [];
$curReco    = $fb['recommendation'] ?? '';
$curOverall = $fb['overall_remarks'] ?? '';
$curDate    = $fb['form_date'] ?? date('Y-m-d');
$curDesig   = $fb['designation_institution'] ?? $person['designation_institution'];
$curContact = $fb['contact'] ?? $person['phone'];
?>

<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/expert_courses.php" class="btn btn-outline btn-sm" style="margin-bottom: 0.5rem;">
            <i class="fa-solid fa-arrow-left"></i> Back to My Courses
        </a>
        <h1 class="page-title"><i class="fa-solid fa-file-signature" style="color: var(--vignan-blue);"></i> Summative Question Paper Scrutiny Feedback</h1>
        <p class="page-subtitle">Review of Question Paper &bull; <?= INSTITUTION_NAME ?></p>
    </div>
    <div class="header-actions">
        <?php if ($isSubmitted): ?>
            <a href="<?= BASE_URL ?>/view_form.php?id=<?= $courseId ?>" class="btn btn-primary" target="_blank">
                <i class="fa-solid fa-eye"></i> View Completed Form
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

<?php if ($isSubmitted): ?>
    <div class="alert alert-success" style="padding: 1.25rem;">
        <i class="fa-solid fa-circle-check" style="font-size: 1.3rem;"></i>
        <div>
            <strong>Feedback Locked &amp; Submitted</strong> on <?= date('d/m/Y', strtotime($fb['submitted_at'])) ?>.<br>
            <span style="font-size: 0.85rem;">The paper setter can now prepare the Action Taken Report. If you need to make changes, please contact the administrator to unlock the form.</span>
        </div>
    </div>
<?php endif; ?>

<!-- Course Summary Card -->
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
        <div><strong>Paper Setter:</strong> <?= htmlspecialchars($course['setter_name']) ?> (<?= htmlspecialchars($course['setter_email']) ?>)</div>
    </div>
</div>

<!-- Scrutiny Feedback Form -->
<form method="POST" action="<?= BASE_URL ?>/expert_feedback.php?course_id=<?= $courseId ?>" enctype="multipart/form-data">
    <!-- External Scrutinizer Personal Details Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-user-check" style="color: var(--vignan-blue);"></i> Scrutinizer Information</h2>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Name of External Scrutinizer</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($person['name']) ?>" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">E-mail ID</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($person['email']) ?>" readonly disabled>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Designation &amp; Institution</label>
                <input type="text" name="designation_institution" class="form-control" value="<?= htmlspecialchars($curDesig) ?>" placeholder="e.g. Professor, Department of CSE, IIT Madras" <?= $isSubmitted ? 'readonly disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label class="form-label">Contact No.</label>
                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($curContact) ?>" placeholder="e.g. 9876543210" <?= $isSubmitted ? 'readonly disabled' : '' ?>>
            </div>
        </div>
    </div>

    <!-- 9 Criteria Scrutiny Cards -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><i class="fa-solid fa-clipboard-check" style="color: var(--vignan-blue);"></i> Assessment Criteria Evaluation</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                    Rating Scale: <strong>5 = Excellent</strong> &bull; <strong>4 = Very Good</strong> &bull; <strong>3 = Good</strong> &bull; <strong>2 = Needs Improvement</strong> &bull; <strong>1 = Unsatisfactory</strong>
                </p>
            </div>
        </div>

        <?php foreach (CRITERIA as $idx => $c): ?>
            <?php 
                $cNum = $idx + 1;
                $savedRating = (int)($curRatings[$cNum] ?? ($curRatings[(string)$cNum] ?? 0));
                $savedRemark = $curRemarks[$cNum] ?? ($curRemarks[(string)$cNum] ?? '');
            ?>
            <div class="criterion-card">
                <div class="criterion-header">
                    <span class="criterion-num"><?= $cNum ?>.</span>
                    <span class="criterion-title"><?= htmlspecialchars($c['title']) ?></span>
                    <div class="criterion-desc"><?= htmlspecialchars($c['desc']) ?></div>
                </div>

                <div class="criterion-body">
                    <!-- Rating Options -->
                    <div>
                        <div class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Select Rating:</div>
                        <div class="rating-group">
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                <label class="rating-option">
                                    <input type="radio" name="ratings[<?= $cNum ?>]" value="<?= $r ?>" <?= $savedRating === $r ? 'checked' : '' ?> <?= $isSubmitted ? 'disabled' : '' ?>>
                                    <div class="rating-label">
                                        <span class="rating-num"><?= $r ?></span>
                                        <span class="rating-text"><?= RATING_LABELS[$r] ?></span>
                                    </div>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Remarks Input -->
                    <div>
                        <div class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Specific Remarks / Suggestions (Optional):</div>
                        <textarea name="remarks[<?= $cNum ?>]" class="form-control" style="min-height: 58px;" placeholder="Remarks for this criterion (max 150 chars)" data-max-chars="<?= REMARK_MAX_CHARS ?>" data-counter-id="counter_<?= $cNum ?>" <?= $isSubmitted ? 'readonly disabled' : '' ?>><?= htmlspecialchars($savedRemark) ?></textarea>
                        <div class="form-help">
                            <span></span>
                            <span class="char-counter" id="counter_<?= $cNum ?>">0 / <?= REMARK_MAX_CHARS ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Overall Recommendation & Remarks -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-stamp" style="color: var(--vignan-blue);"></i> Overall Recommendation &amp; Suggestions</h2>
        </div>

        <div class="form-group">
            <label class="form-label" style="font-size: 1rem;">Overall Expert Recommendation *</label>
            <div class="recommendation-grid">
                <?php foreach (RECOMMENDATIONS as $recoOption): ?>
                    <label class="reco-card">
                        <input type="radio" name="recommendation" value="<?= htmlspecialchars($recoOption) ?>" <?= $curReco === $recoOption ? 'checked' : '' ?> <?= $isSubmitted ? 'disabled' : '' ?> required>
                        <div class="reco-label">
                            <i class="fa-solid fa-circle-dot"></i>
                            <span><?= htmlspecialchars($recoOption) ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Overall Remarks / Specific Suggestions for Improvement</label>
            <textarea name="overall_remarks" class="form-control" style="min-height: 140px;" placeholder="Provide comprehensive observations or actionable suggestions for the paper setter..." data-max-chars="<?= OVERALL_MAX_CHARS ?>" data-counter-id="overall_counter" <?= $isSubmitted ? 'readonly disabled' : '' ?>><?= htmlspecialchars($curOverall) ?></textarea>
            <div class="form-help">
                <span>Maximum <?= OVERALL_MAX_CHARS ?> characters</span>
                <span class="char-counter" id="overall_counter">0 / <?= OVERALL_MAX_CHARS ?></span>
            </div>
        </div>

        <div class="form-row" style="margin-top: 1.5rem; align-items: center;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Date of Scrutiny *</label>
                <input type="date" name="form_date" class="form-control" value="<?= htmlspecialchars($curDate) ?>" <?= $isSubmitted ? 'readonly disabled' : '' ?> required>
            </div>
        </div>
    </div>

    <!-- Signature & Official Seal Endorsement Card at the End of Form -->
    <div class="card" style="border: 2px solid var(--vignan-blue-light); background: #FFFFFF;">
        <div class="card-header" style="background: linear-gradient(135deg, rgba(0, 112, 235, 0.06), rgba(0, 112, 235, 0.01));">
            <h2 class="card-title" style="color: var(--vignan-blue-dark);">
                <i class="fa-solid fa-signature" style="color: var(--vignan-blue);"></i> External Expert Endorsement: Signature &amp; Official Seal
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
            <div class="form-row" style="align-items: flex-start; gap: 2rem; margin-top: 1rem;">
                <div style="flex: 1;">
                    <label class="form-label"><strong>Submitted Scrutinizer Signature</strong></label>
                    <?php if (!empty($fb['signature'])): ?>
                        <div class="signature-preview-box">
                            <img src="data:image/png;base64,<?= base64_encode($fb['signature']) ?>" alt="Signature" class="signature-preview-img">
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-style: italic;">No signature recorded.</p>
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <label class="form-label"><strong>Submitted Official Seal</strong></label>
                    <?php if (!empty($fb['seal'])): ?>
                        <div class="signature-preview-box">
                            <img src="data:image/png;base64,<?= base64_encode($fb['seal']) ?>" alt="Seal" class="signature-preview-img">
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-style: italic;">No official seal attached.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Interactive Signature & Seal Section Before Submitting -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; margin-top: 1rem;">
                <!-- 1. Digital Signature Subsection -->
                <div>
                    <h3 style="font-size: 1rem; color: var(--dark-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-pen-nib" style="color: var(--vignan-blue);"></i> 1. Digital Signature <span style="color: var(--vignan-red);">*</span>
                    </h3>

                    <?php if (!empty($person['signature'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Current Active Signature:</span>
                            <div class="signature-preview-box" style="margin-top: 0.35rem; min-height: 90px;">
                                <img src="data:image/png;base64,<?= base64_encode($person['signature']) ?>" alt="Saved Signature" class="signature-preview-img">
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('expert-sig-box').classList.toggle('hidden');">
                                <i class="fa-solid fa-arrows-rotate"></i> Change / Re-draw Signature
                            </button>
                        </div>
                    <?php endif; ?>

                    <div id="expert-sig-box" class="<?= !empty($person['signature']) ? 'hidden' : '' ?>" style="background: var(--bg-alt); padding: 1rem; border-radius: var(--radius-sm); border: 1px dashed var(--border-color);">
                        <!-- Canvas Draw -->
                        <div style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.85rem;"><strong>Option A: Draw Signature on Pad</strong></label>
                            <div style="border: 2px dashed #94a3b8; border-radius: var(--radius-sm); background: #FFF; width: 100%; max-width: 420px;">
                                <canvas id="sig-canvas" width="420" height="140" style="display: block; width: 100%; height: 140px; cursor: crosshair; touch-action: none;"></canvas>
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
                            <label class="form-label" style="font-size: 0.85rem;"><strong>Option B: Or Upload Signature Image</strong></label>
                            <input type="file" name="sig_file" accept="image/png, image/jpeg" class="form-control" style="font-size: 0.85rem;">
                            <label class="custom-checkbox" style="margin-top: 0.4rem; font-size: 0.8rem;">
                                <input type="checkbox" name="remove_bg" value="1" checked>
                                <span>Auto-remove white background for clean stamping</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Official Seal Subsection -->
                <div>
                    <h3 style="font-size: 1rem; color: var(--dark-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-stamp" style="color: var(--vignan-shield);"></i> 2. Official Institutional Seal <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Optional)</span>
                    </h3>

                    <?php if (!empty($person['seal'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Current Active Seal:</span>
                            <div class="signature-preview-box" style="margin-top: 0.35rem; min-height: 90px; max-width: 260px;">
                                <img src="data:image/png;base64,<?= base64_encode($person['seal']) ?>" alt="Saved Seal" class="signature-preview-img">
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('expert-seal-box').classList.toggle('hidden');">
                                <i class="fa-solid fa-arrows-rotate"></i> Replace Official Seal
                            </button>
                        </div>
                    <?php endif; ?>

                    <div id="expert-seal-box" class="<?= !empty($person['seal']) ? 'hidden' : '' ?>" style="background: var(--bg-alt); padding: 1rem; border-radius: var(--radius-sm); border: 1px dashed var(--border-color);">
                        <label class="form-label" style="font-size: 0.85rem;">Upload Scanned Stamp / Seal Image (PNG / JPG)</label>
                        <input type="file" name="seal_file" accept="image/png, image/jpeg" class="form-control" style="font-size: 0.85rem;">
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.4rem;">
                            The institutional seal will appear in the official scrutinizer endorsement box alongside your signature.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <?php if (!$isSubmitted): ?>
        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
            <button type="submit" name="submit_action" value="draft" class="btn btn-secondary btn-lg">
                <i class="fa-solid fa-floppy-disk"></i> Save Draft
            </button>
            <button type="submit" name="submit_action" value="submit" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to submit your final scrutiny feedback? The form will be locked upon submission.');">
                <i class="fa-solid fa-circle-check"></i> Submit Final Feedback
            </button>
        </div>
    <?php endif; ?>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
