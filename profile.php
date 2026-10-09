<?php
/**
 * User Profile & Digital Signature / Official Seal Management
 */
$pageTitle = "My Profile & Signature";
require_once __DIR__ . '/includes/header.php';
require_auth(['expert', 'setter', 'admin']);

$user = current_user();
$person = services_get_person($user['id'], true);
$isExpert = ($user['role'] === 'expert');

$message = "";
$messageType = "info";

// Handle Profile Updates and Image Uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        try {
            $data = [
                'phone' => $_POST['phone'] ?? '',
                'designation_institution' => $_POST['designation_institution'] ?? '',
                'department' => $_POST['department'] ?? '',
            ];
            services_update_person_profile($user['id'], $data);
            $person = services_get_person($user['id'], true);
            $message = "Profile details updated successfully.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'upload_signature') {
        $removeBg = isset($_POST['remove_bg']);
        $rawBytes = "";

        // Check if drawn on canvas or uploaded file
        if (!empty($_POST['sig_canvas_data']) && str_starts_with($_POST['sig_canvas_data'], 'data:image')) {
            $parts = explode(',', $_POST['sig_canvas_data']);
            $rawBytes = base64_decode($parts[1]);
        } elseif (!empty($_FILES['sig_file']['tmp_name'])) {
            $rawBytes = file_get_contents($_FILES['sig_file']['tmp_name']);
        }

        if (!empty($rawBytes)) {
            try {
                services_set_signature($user['id'], $rawBytes, 'signature', $removeBg);
                $person = services_get_person($user['id'], true);
                $message = "Digital signature uploaded and saved successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Signature error: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Please draw your signature or choose an image file.";
            $messageType = "error";
        }
    } elseif ($action === 'upload_seal' && $isExpert) {
        if (!empty($_FILES['seal_file']['tmp_name'])) {
            try {
                $rawBytes = file_get_contents($_FILES['seal_file']['tmp_name']);
                services_set_signature($user['id'], $rawBytes, 'seal', false);
                $person = services_get_person($user['id'], true);
                $message = "Official seal uploaded successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Seal upload error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === 'delete_signature') {
        services_clear_signature($user['id'], 'signature');
        $person = services_get_person($user['id'], true);
        $message = "Saved signature removed.";
        $messageType = "info";
    } elseif ($action === 'delete_seal' && $isExpert) {
        services_clear_signature($user['id'], 'seal');
        $person = services_get_person($user['id'], true);
        $message = "Saved seal removed.";
        $messageType = "info";
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-signature" style="color: var(--vignan-blue);"></i> My Profile &amp; Digital Signature</h1>
        <p class="page-subtitle">Configure your personal credentials and digital signature for automated form endorsement</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="form-row" style="align-items: flex-start;">
    <!-- Profile Info Card -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-id-card" style="color: var(--vignan-blue);"></i> Profile Information</h2>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/profile.php">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($person['name']) ?>" readonly disabled>
            </div>

            <div class="form-group">
                <label class="form-label">E-mail ID (Login)</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($person['email']) ?>" readonly disabled>
            </div>

            <?php if ($isExpert): ?>
                <div class="form-group">
                    <label class="form-label">Designation &amp; Institution</label>
                    <input type="text" name="designation_institution" class="form-control" value="<?= htmlspecialchars($person['designation_institution']) ?>" placeholder="e.g. Professor, IIT Madras">
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($person['department']) ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Contact Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($person['phone']) ?>" placeholder="e.g. 9876543210">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Update Profile
            </button>
        </form>
    </div>

    <!-- Digital Signature & Seal Card -->
    <div class="card" style="flex: 1.3;">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-pen-nib" style="color: var(--vignan-blue);"></i> Digital Signature</h2>
            <?php if ($person['signature']): ?>
                <span class="status-badge status-complete"><i class="fa-solid fa-check"></i> Active Signature</span>
            <?php else: ?>
                <span class="status-badge status-setter"><i class="fa-solid fa-exclamation"></i> Signature Missing</span>
            <?php endif; ?>
        </div>

        <!-- Current Signature Display -->
        <?php if ($person['signature']): ?>
            <div class="signature-preview-box">
                <img src="data:image/png;base64,<?= base64_encode($person['signature']) ?>" alt="Signature" class="signature-preview-img">
            </div>
            <form method="POST" action="<?= BASE_URL ?>/profile.php" style="margin-bottom: 1.5rem;" onsubmit="return confirm('Delete current saved signature?');">
                <input type="hidden" name="action" value="delete_signature">
                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--vignan-red);">
                    <i class="fa-solid fa-trash"></i> Delete Stored Signature
                </button>
            </form>
        <?php endif; ?>

        <!-- Signature Upload / Draw Tabs -->
        <h3 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--dark-primary);">Upload or Draw Signature:</h3>

        <form method="POST" action="<?= BASE_URL ?>/profile.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_signature">
            <input type="hidden" name="sig_canvas_data" id="sig-canvas-data">

            <!-- Drawing Canvas -->
            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Option A: Draw Signature on Canvas</label>
                <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-sm); background: #FFF; position: relative; width: 100%; max-width: 450px;">
                    <canvas id="sig-canvas" width="450" height="150" style="display: block; width: 100%; height: 150px; cursor: crosshair; touch-action: none;"></canvas>
                </div>
                <div style="margin-top: 0.35rem; display: flex; justify-content: space-between;">
                    <button type="button" id="sig-canvas-clear" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-rotate-left"></i> Clear Canvas
                    </button>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">Use pen, mouse, or touch</span>
                </div>
            </div>

            <!-- File Upload -->
            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Option B: Upload Scanned Signature Image (PNG / JPG)</label>
                <input type="file" name="sig_file" accept="image/png, image/jpeg" class="form-control">
                <label class="custom-checkbox" style="margin-top: 0.5rem;">
                    <input type="checkbox" name="remove_bg" value="1" checked>
                    <span>Automatically remove white paper background (recommended for clean document stamping)</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-cloud-arrow-up"></i> Save Digital Signature
            </button>
        </form>

        <!-- Official Seal (for External Experts) -->
        <?php if ($isExpert): ?>
            <div style="margin-top: 2rem; padding-top: 1.75rem; border-top: 1px solid var(--border-color);">
                <div class="card-header" style="padding-bottom: 0.5rem;">
                    <h3 class="card-title" style="font-size: 1.1rem;"><i class="fa-solid fa-stamp" style="color: var(--vignan-shield);"></i> Official Institutional Seal (Optional)</h3>
                </div>

                <?php if ($person['seal']): ?>
                    <div class="signature-preview-box" style="max-width: 250px;">
                        <img src="data:image/png;base64,<?= base64_encode($person['seal']) ?>" alt="Official Seal" class="signature-preview-img">
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>/profile.php" style="margin-bottom: 1.25rem;" onsubmit="return confirm('Delete saved seal?');">
                        <input type="hidden" name="action" value="delete_seal">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--vignan-red);">
                            <i class="fa-solid fa-trash"></i> Delete Stored Seal
                        </button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/profile.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_seal">
                    <div class="form-group">
                        <label class="form-label">Upload Official Seal Image (PNG / JPG)</label>
                        <input type="file" name="seal_file" accept="image/png, image/jpeg" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i class="fa-solid fa-stamp"></i> Save Official Seal
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
