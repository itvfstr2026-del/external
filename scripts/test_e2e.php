<?php
/**
 * End-to-End Verification Test Script
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/services.php';
require_once dirname(__DIR__) . '/includes/docgen.php';

echo "=== STARTING E2E VERIFICATION TEST ===\n";

// 1. Check Database connection & Tables
$pdo = DB::getConnection();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "1. Database Tables: " . implode(", ", $tables) . "\n";

// 2. Fetch Course & People
$courses = services_list_courses();
echo "2. Total Courses: " . count($courses) . "\n";
assert(count($courses) >= 4, "Expected at least 4 courses");

$course = $courses[0];
$cid = $course['id'];
services_reopen_feedback($cid);
echo "   Testing with Course: {$course['course_code']} - {$course['course_name']} (ID: $cid)\n";

// 3. Test External Expert Auth
$expert = services_get_person((int)$course['expert_id'], true);
$authExpert = services_authenticate('expert', $expert['email'], $expert['access_code']);
assert($authExpert !== null, "Expert authentication failed");
echo "3. Expert Authentication OK: {$expert['name']} ({$expert['email']})\n";

// 4. Test Setting a Digital Signature
$testImg = imagecreatetruecolor(120, 50);
$bg = imagecolorallocate($testImg, 255, 255, 255);
imagefilledrectangle($testImg, 0, 0, 120, 50, $bg);
$ink = imagecolorallocate($testImg, 0, 0, 139);
imagestring($testImg, 5, 10, 15, "TEST SIGN", $ink);
ob_start();
imagepng($testImg);
$dummyPng = ob_get_clean();
imagedestroy($testImg);

services_set_signature($expert['id'], $dummyPng, 'signature', false);
$expert = services_get_person($expert['id'], true);
assert(!empty($expert['signature']), "Expert signature save failed");
echo "4. Expert Digital Signature Upload OK\n";

// 5. Test External Expert Feedback Submission
$ratings = [1 => 5, 2 => 4, 3 => 5, 4 => 4, 5 => 5, 6 => 5, 7 => 4, 8 => 4, 9 => 5];
$remarks = [1 => "Well distributed across units", 3 => "Good application problems included"];
$reco = "Approved as it is";
$overall = "The question paper maintains rigorous standards and complies with Bloom's taxonomy levels.";
$fdate = date('Y-m-d');

$fb = services_save_feedback(
    $cid,
    $expert['id'],
    $ratings,
    $remarks,
    $reco,
    $overall,
    $fdate,
    $expert['designation_institution'],
    $expert['phone'],
    true // Final submit
);
assert($fb['status'] === 'submitted', "Feedback submission failed");
echo "5. Expert Feedback Form Submitted OK (Status: {$fb['status']})\n";

// 6. Test Paper Setter Auth & Signature
$setter = services_get_person((int)$course['setter_id'], true);
$authSetter = services_authenticate('setter', $setter['email'], $setter['access_code']);
assert($authSetter !== null, "Setter authentication failed");
echo "6. Setter Authentication OK: {$setter['name']} ({$setter['email']})\n";

services_set_signature($setter['id'], $dummyPng, 'signature', false);
$setter = services_get_person($setter['id'], true);
assert(!empty($setter['signature']), "Setter signature save failed");
echo "   Setter Digital Signature Upload OK\n";

// 7. Test Action Taken Report (ATR) Submission
$decl = [true, true, true, true];
$ret  = [true, true, true];
$summary = "All minor formatting suggestions incorporated. Question marks and weightage re-verified against syllabus blueprint.";

$atr = services_save_action_report(
    $cid,
    $setter['id'],
    $summary,
    $decl,
    $ret,
    $fdate,
    $setter['phone'],
    true // Final submit
);
assert($atr['status'] === 'submitted', "ATR submission failed");
echo "7. Action Taken Report Submitted OK (Status: {$atr['status']})\n";

// 8. Test Course Status Flow
$updatedCourse = services_get_course($cid);
echo "8. Course Final Status: {$updatedCourse['status']}\n";
assert($updatedCourse['status'] === ST_COMPLETE, "Course status should be Completed");

// 9. Test DOCX Document Generation
$docx = docgen_render_document($cid, 'docx');
assert(!empty($docx) && strlen($docx) > 5000, "DOCX generation failed or empty");
echo "9. DOCX Form Generation OK (" . strlen($docx) . " bytes)\n";

// 10. Test CSV Exports
$statusCsv = services_generate_status_csv();
$ratingsCsv = services_generate_ratings_csv();
$credCsv = services_generate_credentials_csv();
assert(str_contains($statusCsv, $course['course_code']), "Status CSV missing course");
assert(str_contains($ratingsCsv, "Average Rating"), "Ratings CSV missing header");
assert(str_contains($credCsv, $expert['email']), "Credentials CSV missing expert");
echo "10. Status, Ratings Matrix & Credentials CSV Exports OK\n";

// 11. Test Bulk ZIP Generation
$zipBytes = docgen_build_zip([$cid], ['docx']);
assert(!empty($zipBytes) && strlen($zipBytes) > 1000, "ZIP generation failed");
echo "11. Bulk ZIP Archive Generation OK (" . strlen($zipBytes) . " bytes)\n";

echo "=== ALL E2E VERIFICATION TESTS PASSED SUCCESSFULLY! ===\n";
