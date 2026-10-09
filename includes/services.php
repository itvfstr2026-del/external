<?php
/**
 * Application Services & Business Logic Layer
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/criteria.php';

// Custom Exception Classes
class ServiceException extends Exception {}
class ValidationException extends ServiceException {
    public array $errors;
    public function __construct(array $errors) {
        parent::__construct(implode("; ", $errors));
        $this->errors = $errors;
    }
}
class PermissionDeniedException extends ServiceException {}
class NotFoundException extends ServiceException {}

// --------------------------------------------------------------------------- Helpers
function services_now(): string {
    return date('Y-m-d H:i:s');
}

function services_clean(?string $val): string {
    if ($val === null) return "";
    return trim(preg_replace('/\s+/', ' ', $val));
}

function services_norm_email(?string $val): string {
    return strtolower(services_clean($val));
}

function services_new_code(): string {
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"; // Removed O and I
    $code = "";
    $len = strlen($chars);
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, $len - 1)];
    }
    return $code;
}

// --------------------------------------------------------------------------- Image Processing
function services_process_image(string $raw, bool $transparent_bg = true, int $max_width = 900): string {
    if (empty($raw)) {
        throw new ValidationException(["Uploaded file is empty."]);
    }

    $im = @imagecreatefromstring($raw);
    if ($im === false) {
        throw new ValidationException(["The uploaded file is not a valid image (use PNG or JPG)."]);
    }

    $width = imagesx($im);
    $height = imagesy($im);

    // Create a truecolor image with alpha support
    $processed = imagecreatetruecolor($width, $height);
    imagealphablending($processed, false);
    imagesavealpha($processed, true);
    $transparent = imagecolorallocatealpha($processed, 255, 255, 255, 127);
    imagefilledrectangle($processed, 0, 0, $width, $height, $transparent);

    // Apply transparency for white/light pixels if requested
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($im, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $a = ($rgb >> 24) & 0x7F;

            if ($transparent_bg) {
                // Calculate luminance
                $lum = ($r * 0.299 + $g * 0.587 + $b * 0.114);
                if ($lum > 220) {
                    // Transparent for light/white backgrounds
                    $alpha = 127;
                } elseif ($lum > 160) {
                    // Smooth transition
                    $alpha = (int)(($lum - 160) / 60 * 127);
                } else {
                    $alpha = 0;
                }
                $col = imagecolorallocatealpha($processed, $r, $g, $b, $alpha);
                imagesetpixel($processed, $x, $y, $col);
            } else {
                $col = imagecolorallocatealpha($processed, $r, $g, $b, $a);
                imagesetpixel($processed, $x, $y, $col);
            }
        }
    }

    // Resize if wider than max_width
    $finalIm = $processed;
    if ($width > $max_width) {
        $newHeight = (int)max(1, ($height * $max_width / $width));
        $resized = imagecreatetruecolor($max_width, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $processed, 0, 0, 0, 0, $max_width, $newHeight, $width, $height);
        $finalIm = $resized;
        imagedestroy($processed);
    }

    ob_start();
    imagepng($finalIm);
    $output = ob_get_clean();

    imagedestroy($im);
    if ($finalIm !== $processed) {
        imagedestroy($finalIm);
    }

    return $output;
}

// --------------------------------------------------------------------------- People / Authentication
function services_authenticate(string $role, string $email, string $access_code): ?array {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("
        SELECT id, role, name, email, phone, designation_institution, department 
        FROM people 
        WHERE role = ? AND LOWER(email) = ? AND access_code = ?
    ");
    $stmt->execute([$role, services_norm_email($email), strtoupper(services_clean($access_code))]);
    $person = $stmt->fetch();
    return $person ?: null;
}

function services_get_person(int $person_id, bool $include_images = false): array {
    $pdo = DB::getConnection();
    $cols = $include_images 
        ? "*" 
        : "id, role, name, email, phone, designation_institution, department, access_code, created_at, (signature IS NOT NULL) AS has_signature, (seal IS NOT NULL) AS has_seal";
    
    $stmt = $pdo->prepare("SELECT $cols FROM people WHERE id = ?");
    $stmt->execute([$person_id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new NotFoundException("Person not found.");
    }
    return $row;
}

function services_list_people(?string $role = null): array {
    $pdo = DB::getConnection();
    $sql = "
        SELECT p.id, p.role, p.name, p.email, p.phone, p.designation_institution, p.department, 
               p.access_code, (p.signature IS NOT NULL) AS has_signature, (p.seal IS NOT NULL) AS has_seal,
               (SELECT COUNT(*) FROM courses c WHERE (p.role='expert' AND c.expert_id=p.id) 
                   OR (p.role='setter' AND c.setter_id=p.id)) AS course_count 
        FROM people p
    ";
    $args = [];
    if ($role) {
        $sql .= " WHERE p.role = ?";
        $args[] = $role;
    }
    $sql .= " ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function services_upsert_person(
    string $role, 
    string $name, 
    string $email, 
    string $phone = "", 
    string $designation_institution = "", 
    string $department = "", 
    ?PDO $pdo = null
): array {
    $name = services_clean($name);
    $email = services_norm_email($email);
    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationException(["A valid name and e-mail are required for $role ($name / $email)."]);
    }

    $db = $pdo ?? DB::getConnection();
    $stmt = $db->prepare("SELECT * FROM people WHERE role = ? AND LOWER(email) = ?");
    $stmt->execute([$role, $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upStmt = $db->prepare("
            UPDATE people 
            SET name = ?, 
                phone = CASE WHEN ? != '' THEN ? ELSE phone END,
                designation_institution = CASE WHEN ? != '' THEN ? ELSE designation_institution END,
                department = CASE WHEN ? != '' THEN ? ELSE department END
            WHERE id = ?
        ");
        $cleanPhone = services_clean($phone);
        $cleanDesig = services_clean($designation_institution);
        $cleanDept  = services_clean($department);
        $upStmt->execute([
            $name, 
            $cleanPhone, $cleanPhone, 
            $cleanDesig, $cleanDesig, 
            $cleanDept, $cleanDept, 
            $existing['id']
        ]);
        return [(int)$existing['id'], false];
    } else {
        $access_code = services_new_code();
        $insStmt = $db->prepare("
            INSERT INTO people (role, name, email, phone, designation_institution, department, access_code, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->execute([
            $role, $name, $email, 
            services_clean($phone), 
            services_clean($designation_institution), 
            services_clean($department), 
            $access_code, 
            services_now()
        ]);
        return [(int)$db->lastInsertId(), true];
    }
}

function services_update_person_profile(int $person_id, array $data): void {
    $pdo = DB::getConnection();
    $fields = [];
    $args = [];
    foreach (['name', 'phone', 'designation_institution', 'department'] as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $args[] = services_clean($data[$field]);
        }
    }
    if (empty($fields)) return;
    $args[] = $person_id;
    $stmt = $pdo->prepare("UPDATE people SET " . implode(", ", $fields) . " WHERE id = ?");
    $stmt->execute($args);
}

function services_reset_access_code(int $person_id): string {
    $code = services_new_code();
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("UPDATE people SET access_code = ? WHERE id = ?");
    $stmt->execute([$code, $person_id]);
    return $code;
}

function services_delete_person(int $person_id): void {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE setter_id = ? OR expert_id = ?");
    $stmt->execute([$person_id, $person_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new ServiceException("This person is linked to active courses. Re-allocate or delete those courses first.");
    }
    $del = $pdo->prepare("DELETE FROM people WHERE id = ?");
    $del->execute([$person_id]);
}

function services_set_signature(int $person_id, string $raw_bytes, string $kind = "signature", bool $remove_bg = true): void {
    if (!in_array($kind, ["signature", "seal"])) {
        throw new InvalidArgumentException("kind must be 'signature' or 'seal'");
    }
    $png = services_process_image($raw_bytes, $remove_bg && $kind === "signature");
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("UPDATE people SET $kind = ? WHERE id = ?");
    $stmt->bindValue(1, $png, PDO::PARAM_LOB);
    $stmt->bindValue(2, $person_id, PDO::PARAM_INT);
    $stmt->execute();
}

function services_clear_signature(int $person_id, string $kind = "signature"): void {
    if (!in_array($kind, ["signature", "seal"])) {
        throw new InvalidArgumentException("kind must be 'signature' or 'seal'");
    }
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("UPDATE people SET $kind = NULL WHERE id = ?");
    $stmt->execute([$person_id]);
}

function services_get_image(int $person_id, string $kind = "signature"): ?string {
    if (!in_array($kind, ["signature", "seal"])) {
        throw new InvalidArgumentException("kind must be 'signature' or 'seal'");
    }
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("SELECT $kind FROM people WHERE id = ?");
    $stmt->execute([$person_id]);
    $row = $stmt->fetch();
    return $row && $row[$kind] ? $row[$kind] : null;
}

// --------------------------------------------------------------------------- Courses
function services_course_status(array $row): string {
    if (empty($row['expert_id'])) {
        return ST_UNALLOCATED;
    }
    if (($row['feedback_status'] ?? '') !== 'submitted') {
        return ST_AWAIT_EXPERT;
    }
    if (($row['atr_status'] ?? '') !== 'submitted') {
        return ST_AWAIT_SETTER;
    }
    return ST_COMPLETE;
}

const _COURSE_SELECT_SQL = "
    SELECT c.id, c.department, c.programme, c.regulation, c.academic_year, c.semester, c.course_code, c.course_name,
           c.setter_id, c.expert_id,
           s.name AS setter_name, s.email AS setter_email, s.phone AS setter_phone,
           e.name AS expert_name, e.email AS expert_email, e.phone AS expert_phone,
           e.designation_institution AS expert_designation_institution,
           f.status AS feedback_status, f.submitted_at AS feedback_submitted_at,
           a.status AS atr_status, a.submitted_at AS atr_submitted_at,
           CONCAT(COALESCE(f.updated_at,''), '|', COALESCE(a.updated_at,''), '|', c.created_at) AS version
    FROM courses c
    JOIN people s ON s.id = c.setter_id
    LEFT JOIN people e ON e.id = c.expert_id
    LEFT JOIN feedback f ON f.course_id = c.id
    LEFT JOIN action_report a ON a.course_id = c.id
";

function services_list_courses(array $filters = []): array {
    $pdo = DB::getConnection();
    $where = [];
    $args = [];

    foreach (['department' => 'c.department', 'programme' => 'c.programme', 
              'academic_year' => 'c.academic_year', 'semester' => 'c.semester', 
              'expert_id' => 'c.expert_id', 'setter_id' => 'c.setter_id'] as $key => $col) {
        if (!empty($filters[$key])) {
            $where[] = "$col = ?";
            $args[] = $filters[$key];
        }
    }

    if (!empty($filters['search'])) {
        $like = "%" . strtolower(services_clean($filters['search'])) . "%";
        $where[] = "(LOWER(c.course_code) LIKE ? OR LOWER(c.course_name) LIKE ? OR LOWER(s.name) LIKE ? OR LOWER(COALESCE(e.name,'')) LIKE ?)";
        $args[] = $like;
        $args[] = $like;
        $args[] = $like;
        $args[] = $like;
    }

    $sql = _COURSE_SELECT_SQL . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") .
           " ORDER BY c.academic_year DESC, c.department ASC, c.semester ASC, c.course_code ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $r) {
        $r['status'] = services_course_status($r);
        if (!empty($filters['status']) && $r['status'] !== $filters['status']) {
            continue;
        }
        $result[] = $r;
    }
    return $result;
}

function services_get_course(int $course_id): array {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare(_COURSE_SELECT_SQL . " WHERE c.id = ?");
    $stmt->execute([$course_id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new NotFoundException("Course not found.");
    }
    $row['status'] = services_course_status($row);
    return $row;
}

function services_filter_options(): array {
    $pdo = DB::getConnection();
    $distinct = function(string $col) use ($pdo): array {
        $stmt = $pdo->query("SELECT DISTINCT $col FROM courses WHERE $col != '' ORDER BY $col ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    };
    return [
        'department'    => $distinct('department'),
        'programme'     => $distinct('programme'),
        'academic_year' => $distinct('academic_year'),
        'semester'      => $distinct('semester'),
    ];
}

function services_save_course(array $data, ?PDO $pdo = null): array {
    $vals = [
        'department'    => services_clean($data['department'] ?? ''),
        'programme'     => services_clean($data['programme'] ?? ''),
        'regulation'    => services_clean($data['regulation'] ?? ''),
        'academic_year' => services_clean($data['academic_year'] ?? ''),
        'semester'      => services_clean($data['semester'] ?? ''),
        'course_code'   => services_clean($data['course_code'] ?? ''),
        'course_name'   => services_clean($data['course_name'] ?? ''),
    ];

    $missing = [];
    foreach (['department', 'programme', 'academic_year', 'semester', 'course_code', 'course_name'] as $k) {
        if (empty($vals[$k])) {
            $missing[] = ucwords(str_replace('_', ' ', $k));
        }
    }
    if (!empty($missing)) {
        throw new ValidationException(["Missing required fields: " . implode(", ", $missing)]);
    }

    $setter_name  = services_clean($data['setter_name'] ?? '');
    $setter_email = services_clean($data['setter_email'] ?? '');
    $setter_phone = services_clean($data['setter_phone'] ?? '');
    $setter_dept  = services_clean($data['setter_department'] ?? $vals['department']);

    if (empty($setter_name) || empty($setter_email)) {
        throw new ValidationException(["Paper Setter Name and Email are required."]);
    }

    $expert_name  = services_clean($data['expert_name'] ?? '');
    $expert_email = services_clean($data['expert_email'] ?? '');
    $expert_phone = services_clean($data['expert_phone'] ?? '');
    $expert_desig = services_clean($data['expert_designation_institution'] ?? '');

    $db = $pdo ?? DB::getConnection();

    // Upsert setter
    [$setter_id, ] = services_upsert_person('setter', $setter_name, $setter_email, $setter_phone, '', $setter_dept, $db);

    // Upsert expert if given
    $expert_id = null;
    if (!empty($expert_email) || !empty($expert_name)) {
        [$expert_id, ] = services_upsert_person('expert', $expert_name, $expert_email, $expert_phone, $expert_desig, '', $db);
    }

    $course_id = !empty($data['course_id']) ? (int)$data['course_id'] : null;
    $existing = null;

    if ($course_id) {
        $chk = $db->prepare("SELECT id FROM courses WHERE id = ?");
        $chk->execute([$course_id]);
        $existing = $chk->fetch();
    } else {
        $chk = $db->prepare("
            SELECT id FROM courses 
            WHERE academic_year = ? AND semester = ? AND department = ? AND programme = ? AND course_code = ?
        ");
        $chk->execute([$vals['academic_year'], $vals['semester'], $vals['department'], $vals['programme'], $vals['course_code']]);
        $existing = $chk->fetch();
    }

    if ($existing) {
        $cid = (int)$existing['id'];
        $upSql = "
            UPDATE courses 
            SET department = ?, programme = ?, regulation = ?, academic_year = ?, semester = ?, course_code = ?, course_name = ?, setter_id = ?
        ";
        $upArgs = [
            $vals['department'], $vals['programme'], $vals['regulation'], 
            $vals['academic_year'], $vals['semester'], $vals['course_code'], 
            $vals['course_name'], $setter_id
        ];
        if ($expert_id !== null) {
            $upSql .= ", expert_id = ?";
            $upArgs[] = $expert_id;
        }
        $upSql .= " WHERE id = ?";
        $upArgs[] = $cid;
        $stmt = $db->prepare($upSql);
        $stmt->execute($upArgs);
        return [$cid, 'updated'];
    } else {
        $ins = $db->prepare("
            INSERT INTO courses (department, programme, regulation, academic_year, semester, course_code, course_name, setter_id, expert_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $vals['department'], $vals['programme'], $vals['regulation'], 
            $vals['academic_year'], $vals['semester'], $vals['course_code'], 
            $vals['course_name'], $setter_id, $expert_id, services_now()
        ]);
        return [(int)$db->lastInsertId(), 'created'];
    }
}

function services_delete_course(int $course_id): void {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
}

function services_allocate_expert(array $course_ids, ?int $expert_id, bool $force = false): array {
    $done = 0;
    $skipped = [];
    $pdo = DB::getConnection();

    if ($expert_id !== null) {
        $chk = $pdo->prepare("SELECT 1 FROM people WHERE id = ? AND role = 'expert'");
        $chk->execute([$expert_id]);
        if (!$chk->fetchColumn()) {
            throw new NotFoundException("External Expert not found.");
        }
    }

    foreach ($course_ids as $cid) {
        $cid = (int)$cid;
        $fbChk = $pdo->prepare("SELECT status FROM feedback WHERE course_id = ?");
        $fbChk->execute([$cid]);
        $fb = $fbChk->fetch();

        if ($fb && $fb['status'] === 'submitted' && !$force) {
            $skipped[] = $cid;
            continue;
        }

        $up = $pdo->prepare("UPDATE courses SET expert_id = ? WHERE id = ?");
        $up->execute([$expert_id, $cid]);

        if ($force) {
            $pdo->prepare("DELETE FROM feedback WHERE course_id = ?")->execute([$cid]);
            $pdo->prepare("DELETE FROM action_report WHERE course_id = ?")->execute([$cid]);
        }
        $done++;
    }

    return ['allocated' => $done, 'skipped_already_submitted' => $skipped];
}

// --------------------------------------------------------------------------- Bulk Import
const IMPORT_COLUMNS = [
    ["Department", "department", true],
    ["Programme", "programme", true],
    ["Regulation", "regulation", false],
    ["Academic Year", "academic_year", true],
    ["Semester", "semester", true],
    ["Course Code", "course_code", true],
    ["Course Name", "course_name", true],
    ["Paper Setter Name", "setter_name", true],
    ["Paper Setter Email", "setter_email", true],
    ["Paper Setter Contact", "setter_phone", false],
    ["External Expert Name", "expert_name", false],
    ["External Expert Email", "expert_email", false],
    ["External Expert Designation & Institution", "expert_designation_institution", false],
    ["External Expert Contact", "expert_phone", false],
];

function services_import_courses_from_csv(string $filepath): array {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        throw new ValidationException(["Cannot read uploaded CSV file."]);
    }

    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new ValidationException(["Failed to open CSV file."]);
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        throw new ValidationException(["CSV file is empty."]);
    }

    // Map headers
    $colMap = [];
    $known = [];
    foreach (IMPORT_COLUMNS as [$h, $k, $req]) {
        $known[strtolower($h)] = $k;
        $known[strtolower($k)] = $k;
    }
    // Add common aliases
    $aliases = [
        'dept' => 'department', 'branch' => 'department', 'program' => 'programme', 
        'course' => 'course_name', 'year' => 'academic_year', 'ay' => 'academic_year',
        'sem' => 'semester', 'subject code' => 'course_code', 'subject name' => 'course_name',
        'paper setter' => 'setter_name', 'faculty' => 'setter_name', 'faculty name' => 'setter_name',
        'faculty email' => 'setter_email', 'setter' => 'setter_name', 'expert' => 'expert_name',
        'expert email' => 'expert_email'
    ];
    $known = array_merge($known, $aliases);

    foreach ($headers as $idx => $header) {
        $norm = strtolower(services_clean($header));
        if (isset($known[$norm])) {
            $colMap[$idx] = $known[$norm];
        }
    }

    $requiredKeys = [];
    foreach (IMPORT_COLUMNS as [$h, $k, $req]) {
        if ($req) $requiredKeys[] = $k;
    }

    $mappedKeys = array_values($colMap);
    $absent = array_diff($requiredKeys, $mappedKeys);
    if (!empty($absent)) {
        fclose($handle);
        throw new ValidationException(["Missing required column(s): " . implode(", ", $absent)]);
    }

    $created = 0;
    $updated = 0;
    $errors = [];
    $rowNum = 1;

    $pdo = DB::getConnection();

    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (empty(array_filter($row))) continue; // skip blank rows

        $data = [];
        foreach ($row as $idx => $val) {
            if (isset($colMap[$idx])) {
                $data[$colMap[$idx]] = services_clean($val);
            }
        }

        $blanks = [];
        foreach ($requiredKeys as $rk) {
            if (empty($data[$rk])) {
                $blanks[] = $rk;
            }
        }

        if (!empty($blanks)) {
            $errors[] = ["row" => $rowNum, "error" => "Blank required fields: " . implode(", ", $blanks)];
            continue;
        }

        try {
            [, $how] = services_save_course($data, $pdo);
            if ($how === 'created') $created++;
            if ($how === 'updated') $updated++;
        } catch (Exception $e) {
            $errors[] = ["row" => $rowNum, "error" => $e->getMessage()];
        }
    }

    fclose($handle);
    return ['created' => $created, 'updated' => $updated, 'errors' => $errors, 'rows' => $rowNum - 1];
}

// --------------------------------------------------------------------------- Feedback (External Expert)
function services_get_feedback(int $course_id): ?array {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM feedback WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $row['ratings'] = json_decode($row['ratings'] ?: '{}', true) ?: [];
    $row['remarks'] = json_decode($row['remarks'] ?: '{}', true) ?: [];
    return $row;
}

function services_save_feedback(
    int $course_id, 
    int $expert_id, 
    array $ratings, 
    array $remarks = [], 
    string $recommendation = "", 
    string $overall_remarks = "", 
    string $form_date = "", 
    string $designation_institution = "", 
    string $contact = "", 
    bool $submit = false
): array {
    $pdo = DB::getConnection();

    // Verify course allocated to this expert
    $cStmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $cStmt->execute([$course_id]);
    $course = $cStmt->fetch();
    if (!$course) throw new NotFoundException("Course not found.");
    if ((int)$course['expert_id'] !== $expert_id) {
        throw new PermissionDeniedException("This course is not allocated to you.");
    }

    // Check if already submitted
    $fbStmt = $pdo->prepare("SELECT status FROM feedback WHERE course_id = ?");
    $fbStmt->execute([$course_id]);
    $existing = $fbStmt->fetch();
    if ($existing && $existing['status'] === 'submitted') {
        throw new PermissionDeniedException("Feedback has already been submitted and is locked. Ask the administrator to re-open it.");
    }

    $person = services_get_person($expert_id, true);

    // Clean ratings & remarks
    $cleanRatings = [];
    foreach ($ratings as $k => $v) {
        $kInt = (int)$k;
        $vInt = (int)$v;
        if ($vInt >= 1 && $vInt <= 5) {
            $cleanRatings[(string)$kInt] = $vInt;
        }
    }

    $cleanRemarks = [];
    $tooLongRemarks = [];
    foreach ($remarks as $k => $v) {
        $kInt = (int)$k;
        $vStr = trim((string)$v);
        if ($vStr !== "") {
            if (mb_strlen($vStr) > REMARK_MAX_CHARS) {
                $tooLongRemarks[] = $kInt;
            }
            $cleanRemarks[(string)$kInt] = $vStr;
        }
    }

    $limitErrs = [];
    if (!empty($tooLongRemarks)) {
        $limitErrs[] = "Remarks for criteria no. " . implode(", ", $tooLongRemarks) . " exceed " . REMARK_MAX_CHARS . " characters (the column in the form is narrow). Use the overall remarks box for longer comments.";
    }
    if (mb_strlen(trim($overall_remarks)) > OVERALL_MAX_CHARS) {
        $limitErrs[] = "Overall remarks are limited to " . OVERALL_MAX_CHARS . " characters.";
    }
    if (!empty($limitErrs)) {
        throw new ValidationException($limitErrs);
    }

    if ($submit) {
        $errs = [];
        $missing = [];
        for ($i = 1; $i <= count(CRITERIA); $i++) {
            if (!isset($cleanRatings[(string)$i])) {
                $missing[] = $i;
            }
        }
        if (!empty($missing)) {
            $errs[] = "Please rate criteria no. " . implode(", ", $missing) . ".";
        }
        if (!in_array($recommendation, RECOMMENDATIONS, true)) {
            $errs[] = "Select an overall recommendation.";
        }
        if (empty($form_date)) {
            $errs[] = "Enter the date.";
        }
        if (empty($person['signature'])) {
            $errs[] = "Upload your signature before submitting.";
        }
        if (!empty($errs)) {
            throw new ValidationException($errs);
        }
    }

    $status = $submit ? 'submitted' : 'draft';
    $submitted_at = $submit ? services_now() : null;

    $insSql = "
        INSERT INTO feedback (
            course_id, expert_name, designation_institution, email, contact,
            ratings, remarks, recommendation, overall_remarks, form_date,
            signature, seal, status, submitted_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            expert_name = VALUES(expert_name),
            designation_institution = VALUES(designation_institution),
            email = VALUES(email),
            contact = VALUES(contact),
            ratings = VALUES(ratings),
            remarks = VALUES(remarks),
            recommendation = VALUES(recommendation),
            overall_remarks = VALUES(overall_remarks),
            form_date = VALUES(form_date),
            signature = VALUES(signature),
            seal = VALUES(seal),
            status = VALUES(status),
            submitted_at = VALUES(submitted_at),
            updated_at = VALUES(updated_at)
    ";

    $desigToSave = services_clean($designation_institution) ?: $person['designation_institution'];
    $phoneToSave = services_clean($contact) ?: $person['phone'];

    $stmt = $pdo->prepare($insSql);
    $stmt->bindValue(1, $course_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $person['name']);
    $stmt->bindValue(3, $desigToSave);
    $stmt->bindValue(4, $person['email']);
    $stmt->bindValue(5, $phoneToSave);
    $stmt->bindValue(6, json_encode($cleanRatings, JSON_UNESCAPED_UNICODE));
    $stmt->bindValue(7, json_encode($cleanRemarks, JSON_UNESCAPED_UNICODE));
    $stmt->bindValue(8, $recommendation);
    $stmt->bindValue(9, trim($overall_remarks));
    $stmt->bindValue(10, !empty($form_date) ? $form_date : null);
    $stmt->bindValue(11, $person['signature'], PDO::PARAM_LOB);
    $stmt->bindValue(12, $person['seal'], PDO::PARAM_LOB);
    $stmt->bindValue(13, $status);
    $stmt->bindValue(14, $submitted_at);
    $stmt->bindValue(15, services_now());
    $stmt->execute();

    if (!empty($designation_institution) || !empty($contact)) {
        services_update_person_profile($expert_id, [
            'designation_institution' => $desigToSave,
            'phone' => $phoneToSave
        ]);
    }

    return services_get_feedback($course_id);
}

function services_reopen_feedback(int $course_id): void {
    $pdo = DB::getConnection();
    $now = services_now();
    $stmt1 = $pdo->prepare("UPDATE feedback SET status='draft', submitted_at=NULL, updated_at=? WHERE course_id=?");
    $stmt1->execute([$now, $course_id]);
    $stmt2 = $pdo->prepare("UPDATE action_report SET status='draft', submitted_at=NULL, updated_at=? WHERE course_id=?");
    $stmt2->execute([$now, $course_id]);
}

// --------------------------------------------------------------------------- Action Taken Report (Paper Setter)
function services_get_action_report(int $course_id): ?array {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM action_report WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $row['declarations'] = json_decode($row['declarations'] ?: '[]', true) ?: [];
    $row['retained'] = json_decode($row['retained'] ?: '[]', true) ?: [];
    return $row;
}

function services_save_action_report(
    int $course_id, 
    int $setter_id, 
    string $summary = "", 
    array $declarations = [], 
    array $retained = [], 
    string $form_date = "", 
    string $contact = "", 
    bool $submit = false
): array {
    $summary = trim($summary);
    if (mb_strlen($summary) > SUMMARY_MAX_CHARS) {
        throw new ValidationException(["The summary of changes is limited to " . SUMMARY_MAX_CHARS . " characters (it must fit the box in the form). Currently " . mb_strlen($summary) . "."]);
    }

    $pdo = DB::getConnection();
    $course = services_get_course($course_id);
    if ((int)$course['setter_id'] !== $setter_id) {
        throw new PermissionDeniedException("You are not the paper setter for this course.");
    }

    $fb = services_get_feedback($course_id);
    if (!$fb || $fb['status'] !== 'submitted') {
        throw new PermissionDeniedException("The external expert has not submitted feedback yet.");
    }

    $existing = services_get_action_report($course_id);
    if ($existing && $existing['status'] === 'submitted') {
        throw new PermissionDeniedException("The action taken report is already submitted and locked.");
    }

    $person = services_get_person($setter_id, true);

    $cleanDecl = [];
    for ($i = 0; $i < count(DECLARATIONS); $i++) {
        $cleanDecl[] = !empty($declarations[$i]);
    }

    $cleanRet = [];
    for ($i = 0; $i < count(RETAINED_DOCS); $i++) {
        $cleanRet[] = !empty($retained[$i]);
    }

    if ($submit) {
        $errs = [];
        if (count(array_filter($cleanDecl)) !== count(DECLARATIONS)) {
            $errs[] = "Tick all four declarations.";
        }
        if (empty($summary)) {
            $errs[] = "Write the summary of major changes incorporated.";
        }
        if (empty($form_date)) {
            $errs[] = "Enter the date.";
        }
        if (empty($person['signature'])) {
            $errs[] = "Upload your signature before submitting.";
        }
        if (!empty($errs)) {
            throw new ValidationException($errs);
        }
    }

    $status = $submit ? 'submitted' : 'draft';
    $submitted_at = $submit ? services_now() : null;

    $insSql = "
        INSERT INTO action_report (
            course_id, setter_name, department, contact, email,
            declarations, summary, retained, form_date,
            signature, status, submitted_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setter_name = VALUES(setter_name),
            department = VALUES(department),
            contact = VALUES(contact),
            email = VALUES(email),
            declarations = VALUES(declarations),
            summary = VALUES(summary),
            retained = VALUES(retained),
            form_date = VALUES(form_date),
            signature = VALUES(signature),
            status = VALUES(status),
            submitted_at = VALUES(submitted_at),
            updated_at = VALUES(updated_at)
    ";

    $phoneToSave = services_clean($contact) ?: $person['phone'];

    $stmt = $pdo->prepare($insSql);
    $stmt->bindValue(1, $course_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $person['name']);
    $stmt->bindValue(3, $course['department']);
    $stmt->bindValue(4, $phoneToSave);
    $stmt->bindValue(5, $person['email']);
    $stmt->bindValue(6, json_encode($cleanDecl));
    $stmt->bindValue(7, $summary);
    $stmt->bindValue(8, json_encode($cleanRet));
    $stmt->bindValue(9, !empty($form_date) ? $form_date : null);
    $stmt->bindValue(10, $person['signature'], PDO::PARAM_LOB);
    $stmt->bindValue(11, $status);
    $stmt->bindValue(12, $submitted_at);
    $stmt->bindValue(13, services_now());
    $stmt->execute();

    if (!empty($contact)) {
        services_update_person_profile($setter_id, ['phone' => $phoneToSave]);
    }

    return services_get_action_report($course_id);
}

// --------------------------------------------------------------------------- Context & CSV Exports
function services_build_context(int $course_id): array {
    return [
        'course'        => services_get_course($course_id),
        'feedback'      => services_get_feedback($course_id),
        'action_report' => services_get_action_report($course_id)
    ];
}

function services_generate_status_csv(?array $courses = null): string {
    $courses = $courses ?? services_list_courses();
    $output = fopen('php://temp', 'r+');
    
    fputcsv($output, [
        "Academic Year", "Semester", "Department", "Programme", "Regulation",
        "Course Code", "Course Name", "Paper Setter", "Setter Email",
        "External Expert", "Expert Email", "Status", "Feedback Submitted", "ATR Submitted"
    ]);

    foreach ($courses as $c) {
        fputcsv($output, [
            $c['academic_year'],
            $c['semester'],
            $c['department'],
            $c['programme'],
            $c['regulation'],
            $c['course_code'],
            $c['course_name'],
            $c['setter_name'],
            $c['setter_email'],
            $c['expert_name'] ?: '—',
            $c['expert_email'] ?: '—',
            $c['status'],
            $c['feedback_submitted_at'] ?: '',
            $c['atr_submitted_at'] ?: ''
        ]);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

function services_generate_ratings_csv(?array $courses = null): string {
    $courses = $courses ?? services_list_courses();
    $output = fopen('php://temp', 'r+');

    $header = ["Academic Year", "Semester", "Department", "Course Code", "Course Name", "External Expert"];
    for ($i = 1; $i <= count(CRITERIA); $i++) {
        $header[] = "$i. " . CRITERIA[$i - 1]['title'];
    }
    $header[] = "Average Rating";
    $header[] = "Recommendation";
    fputcsv($output, $header);

    foreach ($courses as $c) {
        $fb = services_get_feedback((int)$c['id']);
        if (!$fb || $fb['status'] !== 'submitted') continue;

        $row = [
            $c['academic_year'],
            $c['semester'],
            $c['department'],
            $c['course_code'],
            $c['course_name'],
            $c['expert_name']
        ];

        $vals = [];
        for ($i = 1; $i <= count(CRITERIA); $i++) {
            $r = $fb['ratings'][$i] ?? ($fb['ratings'][(string)$i] ?? null);
            $row[] = $r;
            if ($r) $vals[] = (int)$r;
        }

        $avg = !empty($vals) ? round(array_sum($vals) / count($vals), 2) : "";
        $row[] = $avg;
        $row[] = $fb['recommendation'];

        fputcsv($output, $row);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

function services_generate_credentials_csv(): string {
    $people = services_list_people();
    $output = fopen('php://temp', 'r+');

    fputcsv($output, ["Role", "Name", "Email (Login)", "Access Code", "Department", "Contact"]);

    foreach ($people as $p) {
        fputcsv($output, [
            $p['role'] === 'expert' ? 'External Expert' : 'Paper Setter',
            $p['name'],
            $p['email'],
            $p['access_code'],
            $p['department'],
            $p['phone']
        ]);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

function services_generate_template_csv(): string {
    $output = fopen('php://temp', 'r+');
    fputcsv($output, array_column(IMPORT_COLUMNS, 0));
    fputcsv($output, [
        "CSE", "B.Tech", "R22", "2025-26", "III", "CS301", "Data Structures",
        "Dr. A. Kumar", "a.kumar@vignan.ac.in", "9876543210",
        "Prof. External Expert", "expert@example.com", "Professor, IIT Madras", "9123456780"
    ]);
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}
