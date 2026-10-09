<?php
/**
 * Reports and Template Data Export Endpoint (CSV / Excel format)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services.php';

$type = $_GET['type'] ?? 'status';

require_auth('admin');

switch ($type) {
    case 'status':
        $filename = "Vignan_Course_Status_Report_" . date('Ymd_His') . ".csv";
        $data = services_generate_status_csv();
        break;

    case 'ratings':
        $filename = "Vignan_Ratings_Matrix_" . date('Ymd_His') . ".csv";
        $data = services_generate_ratings_csv();
        break;

    case 'credentials':
        $filename = "Vignan_Faculty_Credentials_" . date('Ymd_His') . ".csv";
        $data = services_generate_credentials_csv();
        break;

    case 'template':
        $filename = "course_upload_template.csv";
        $data = services_generate_template_csv();
        break;

    default:
        die("Invalid export type.");
}

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Length: " . strlen($data));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

// Output UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";
echo $data;
exit;
