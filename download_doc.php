<?php
/**
 * Word (.docx) & Bulk ZIP Document Downloader Endpoint
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services.php';
require_once __DIR__ . '/includes/docgen.php';

// Only Administrator is permitted to download forms and official documents
require_auth('admin');

$action = $_POST['action'] ?? ($_GET['action'] ?? 'single');

if ($action === 'bulk_zip') {
    $courseIds = $_POST['course_ids'] ?? [];
    $formats   = $_POST['formats'] ?? ['docx'];

    if (empty($courseIds)) {
        die("No courses selected.");
    }

    try {
        $zipData = docgen_build_zip($courseIds, $formats);
        $filename = "Vignan_Feedback_Forms_" . date('Ymd_His') . ".zip";

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Length: " . strlen($zipData));
        header("Cache-Control: no-cache, no-store, must-revalidate");
        echo $zipData;
        exit;
    } catch (Exception $e) {
        die("Error generating bulk ZIP archive: " . htmlspecialchars($e->getMessage()));
    }
} else {
    // Single Document Download (Admin only)
    $courseId = (int)($_GET['id'] ?? 0);
    $fmt = $_GET['fmt'] ?? 'docx';

    if ($courseId <= 0) {
        die("Invalid Course ID.");
    }

    $course = services_get_course($courseId);
    $user = current_user();

    try {
        $docData = docgen_render_document($courseId, $fmt);
        $filename = docgen_get_filename($course, $fmt);

        $mime = ($fmt === 'pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        header("Content-Type: $mime");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Length: " . strlen($docData));
        header("Cache-Control: no-cache, no-store, must-revalidate");
        echo $docData;
        exit;
    } catch (Exception $e) {
        die("Error generating document: " . htmlspecialchars($e->getMessage()));
    }
}
