<?php
/**
 * Verification Test for New Requirements:
 * 1. Expert and Paper Setter cannot download forms (Admin only).
 * 2. Signature & Seal removed from navbar.
 * 3. Signature & Seal embedded at the end of the form before submitting for expert & setter.
 * 4. Admin retains full access.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/services.php';
require_once dirname(__DIR__) . '/includes/auth.php';

echo "=== TESTING NEW REQUIREMENTS ===\n";

// Helper to simulate GET / POST output with session
function test_render(string $scriptFile, array $sessionUser, array $get = [], array $post = []): string {
    $_SESSION = ['user' => $sessionUser];
    $_GET = $get;
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = !empty($post) ? 'POST' : 'GET';
    $_SERVER['SCRIPT_NAME'] = '/' . $scriptFile;

    ob_start();
    include dirname(__DIR__) . '/' . $scriptFile;
    return ob_get_clean();
}

$courses = services_list_courses();
$course = $courses[0];
$cid = $course['id'];
$expert = services_get_person((int)$course['expert_id'], true);
$setter = services_get_person((int)$course['setter_id'], true);

// 1. Check Navbar for Expert and Setter
echo "1. Checking Navbar items for each role...\n";
$expertNav = test_render('includes/navbar.php', ['id' => $expert['id'], 'role' => 'expert', 'name' => $expert['name']]);
assert(!str_contains($expertNav, 'profile.php'), "Navbar for expert should NOT contain profile.php / My Signature");
assert(!str_contains($expertNav, 'My Signature'), "Navbar for expert should NOT contain 'My Signature'");
echo "   Expert Navbar: Cleaned (No signature nav link)\n";

$setterNav = test_render('includes/navbar.php', ['id' => $setter['id'], 'role' => 'setter', 'name' => $setter['name']]);
assert(!str_contains($setterNav, 'profile.php'), "Navbar for setter should NOT contain profile.php / My Signature");
assert(!str_contains($setterNav, 'My Signature'), "Navbar for setter should NOT contain 'My Signature'");
echo "   Setter Navbar: Cleaned (No signature nav link)\n";

$adminNav = test_render('includes/navbar.php', ['id' => 0, 'role' => 'admin', 'name' => 'Administrator']);
assert(str_contains($adminNav, 'dashboard.php'), "Navbar for admin should contain dashboard");
assert(str_contains($adminNav, 'downloads.php'), "Navbar for admin should contain downloads");
echo "   Admin Navbar: Full access preserved\n";

// 2. Check Expert Form for Signature and Seal at the end
echo "2. Checking Expert Feedback Form for Signature and Seal sections...\n";
services_reopen_feedback($cid);
$expertFormHtml = test_render('expert_feedback.php', ['id' => $expert['id'], 'role' => 'expert', 'name' => $expert['name']], ['course_id' => $cid]);
assert(str_contains($expertFormHtml, 'External Expert Endorsement: Signature &amp; Official Seal') || str_contains($expertFormHtml, 'External Expert Endorsement: Signature'), "Expert form must contain Endorsement card at the end");
assert(str_contains($expertFormHtml, 'sig-canvas'), "Expert form must contain signature canvas");
assert(str_contains($expertFormHtml, 'seal_file'), "Expert form must contain seal upload");
assert(!str_contains($expertFormHtml, 'Download Word (.docx)'), "Expert form header should not have download docx");
echo "   Expert Feedback Form: Signature & Seal Endorsement integrated, downloads removed\n";

// 3. Check Setter ATR Form for Signature at the end
echo "3. Checking Setter ATR Form for Signature section...\n";
// Submit expert feedback first so setter ATR is accessible
$dummyPng = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==");
services_set_signature($expert['id'], $dummyPng, 'signature', false);
services_save_feedback($cid, $expert['id'], [1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5], [], "Approved as it is", "Good", date('Y-m-d'), "Prof", "999", true);

$setterFormHtml = test_render('setter_atr.php', ['id' => $setter['id'], 'role' => 'setter', 'name' => $setter['name']], ['course_id' => $cid]);
assert(str_contains($setterFormHtml, 'Paper Setter Endorsement: Digital Signature'), "Setter form must contain Signature card at the end");
assert(str_contains($setterFormHtml, 'sig-canvas'), "Setter form must contain signature canvas");
assert(!str_contains($setterFormHtml, 'Download Word (.docx)'), "Setter form header should not have download docx");
echo "   Setter ATR Form: Digital Signature integrated, downloads removed\n";

// 4. Test Submission of Form WITH inline signature & seal
echo "4. Testing Form submission with inline canvas signature...\n";
services_reopen_feedback($cid);
$dataUriSig = "data:image/png;base64," . base64_encode($dummyPng);

$submitPost = [
    'submit_action' => 'submit',
    'ratings' => [1=>5, 2=>5, 3=>5, 4=>5, 5=>5, 6=>5, 7=>5, 8=>5, 9=>5],
    'remarks' => [],
    'recommendation' => 'Approved as it is',
    'overall_remarks' => 'Everything verified.',
    'form_date' => date('Y-m-d'),
    'sig_canvas_data' => $dataUriSig,
    'remove_bg' => '1'
];
test_render('expert_feedback.php', ['id' => $expert['id'], 'role' => 'expert', 'name' => $expert['name']], ['course_id' => $cid], $submitPost);

$fbUpdated = services_get_feedback($cid);
assert($fbUpdated['status'] === 'submitted', "Feedback should be submitted");
assert(!empty($fbUpdated['signature']), "Submitted feedback must have stored signature");
echo "   Expert Feedback submitted with inline canvas signature OK\n";

// 5. Test Setter ATR submission with inline canvas signature
echo "5. Testing Setter ATR submission with inline canvas signature...\n";
$atrPost = [
    'submit_action' => 'submit',
    'summary' => 'Minor improvements incorporated.',
    'declarations' => ['1', '1', '1', '1'],
    'retained' => ['1', '1', '1'],
    'form_date' => date('Y-m-d'),
    'sig_canvas_data' => $dataUriSig,
    'remove_bg' => '1'
];
test_render('setter_atr.php', ['id' => $setter['id'], 'role' => 'setter', 'name' => $setter['name']], ['course_id' => $cid], $atrPost);

$atrUpdated = services_get_action_report($cid);
assert($atrUpdated['status'] === 'submitted', "ATR should be submitted");
assert(!empty($atrUpdated['signature']), "Submitted ATR must have stored signature");
echo "   Setter ATR submitted with inline canvas signature OK\n";

// 6. Test Download Access Control
echo "6. Testing Download Access Control...\n";
// download_doc.php requires admin
assert(file_exists(dirname(__DIR__) . '/download_doc.php'), "download_doc.php must exist");
$docCode = file_get_contents(dirname(__DIR__) . '/download_doc.php');
assert(str_contains($docCode, "require_auth('admin')"), "download_doc.php must strictly enforce require_auth('admin')");

$exportCode = file_get_contents(dirname(__DIR__) . '/export.php');
assert(str_contains($exportCode, "require_auth('admin')"), "export.php must strictly enforce require_auth('admin')");

$viewFormCode = file_get_contents(dirname(__DIR__) . '/view_form.php');
assert(str_contains($viewFormCode, "\$user['role'] === 'admin'"), "view_form.php must only show download buttons to admin");

echo "   Download restrictions: Verified strict admin-only protection!\n";

echo "=== ALL NEW REQUIREMENT VERIFICATIONS PASSED! ===\n";
