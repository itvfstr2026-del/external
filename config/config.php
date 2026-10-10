<?php
/**
 * Vignan's Question Paper Feedback Portal
 * Central Configuration File
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base Directories
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('TEMPLATE_DOCX_PATH', TEMPLATES_PATH . '/feedback_template.docx');

// Ensure essential directories exist safely
if (!file_exists(UPLOADS_PATH)) {
    @mkdir(UPLOADS_PATH, 0777, true);
}
if (!file_exists(UPLOADS_PATH . '/signatures')) {
    @mkdir(UPLOADS_PATH . '/signatures', 0777, true);
}
if (!file_exists(UPLOADS_PATH . '/seals')) {
    @mkdir(UPLOADS_PATH . '/seals', 0777, true);
}

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: 'sql211.infinityfree.com');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_43129095_externalfeedback');
define('DB_USER', getenv('DB_USER') ?: 'if0_43129095');
define('DB_PASS', getenv('DB_PASS') ?: 'L23cGvfcDKR5qpE');

// Application & Security Configuration
define('APP_NAME', "Vignan's Question Paper Feedback Portal");
define('INSTITUTION_NAME', "Vignan's Foundation for Science, Technology & Research");
define('INSTITUTION_SUBTITLE', "(Deemed to be University) - Estd. u/s 3 of UGC Act 1956");
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123'); // Default admin password
define('API_ADMIN_KEY', getenv('API_ADMIN_KEY') ?: ADMIN_PASSWORD);

// Form Content Constraints (Aligned with template boxes)
define('SUMMARY_MAX_CHARS', 800);
define('REMARK_MAX_CHARS', 150);
define('OVERALL_MAX_CHARS', 1000);

// Base URL detection
if (getenv('BASE_URL')) {
    define('BASE_URL', rtrim(getenv('BASE_URL'), '/'));
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    if ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\') {
        $baseUri = '';
    } else {
        $baseUri = rtrim($scriptDir, '/');
        $baseUri = preg_replace('/(\/api|\/includes|\/scripts)$/', '', $baseUri);
    }
    define('BASE_URL', rtrim($protocol . $host . $baseUri, '/'));
}

// Timezone
date_default_timezone_set('Asia/Kolkata');
