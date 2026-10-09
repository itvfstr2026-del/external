<?php
/**
 * RESTful JSON API Endpoint for Vignan Question Paper Feedback Portal
 */
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/criteria.php';
require_once __DIR__ . '/../includes/services.php';
require_once __DIR__ . '/../includes/docgen.php';

function json_response(mixed $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_auth_user(): ?array {
    $adminKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
    if ($adminKey && $adminKey === API_ADMIN_KEY) {
        return ['role' => 'admin', 'name' => 'Administrator', 'id' => 0];
    }

    $role = $_SERVER['HTTP_X_ROLE'] ?? '';
    $email = $_SERVER['HTTP_X_EMAIL'] ?? '';
    $accessCode = $_SERVER['HTTP_X_ACCESS_CODE'] ?? '';

    if ($role && $email && $accessCode) {
        return services_authenticate($role, $email, $accessCode);
    }

    return null;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = get_auth_user();

try {
    switch ($action) {
        case 'form-definition':
            json_response([
                'institution'     => INSTITUTION_NAME,
                'subtitle'        => INSTITUTION_SUBTITLE,
                'criteria'        => CRITERIA,
                'rating_labels'   => RATING_LABELS,
                'recommendations' => RECOMMENDATIONS,
                'declarations'    => DECLARATIONS,
                'retained_docs'   => RETAINED_DOCS,
                'limits' => [
                    'summary_max_chars' => SUMMARY_MAX_CHARS,
                    'remark_max_chars'  => REMARK_MAX_CHARS,
                    'overall_max_chars' => OVERALL_MAX_CHARS
                ]
            ]);
            break;

        case 'courses':
            if ($method === 'GET') {
                $filters = [
                    'department'    => $_GET['department'] ?? null,
                    'programme'     => $_GET['programme'] ?? null,
                    'academic_year' => $_GET['academic_year'] ?? null,
                    'semester'      => $_GET['semester'] ?? null,
                    'status'        => $_GET['status'] ?? null,
                    'search'        => $_GET['search'] ?? null,
                ];
                if ($user && $user['role'] === 'expert') {
                    $filters['expert_id'] = $user['id'];
                } elseif ($user && $user['role'] === 'setter') {
                    $filters['setter_id'] = $user['id'];
                }
                json_response(services_list_courses(array_filter($filters)));
            } elseif ($method === 'POST') {
                if (!$user || $user['role'] !== 'admin') {
                    json_response(['error' => 'Admin authorization required'], 403);
                }
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
                [$cid, $how] = services_save_course($body);
                json_response(['id' => $cid, 'status' => $how]);
            }
            break;

        case 'course':
            $courseId = (int)($_GET['id'] ?? 0);
            if ($courseId <= 0) json_response(['error' => 'Course ID required'], 400);
            json_response(services_get_course($courseId));
            break;

        case 'feedback':
            $courseId = (int)($_GET['id'] ?? 0);
            if (!$user || $user['role'] !== 'expert') {
                json_response(['error' => 'External expert authentication required'], 403);
            }
            if ($method === 'GET') {
                json_response(services_get_feedback($courseId) ?: new stdClass());
            } elseif ($method === 'POST' || $method === 'PUT') {
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
                $res = services_save_feedback(
                    $courseId,
                    $user['id'],
                    $body['ratings'] ?? [],
                    $body['remarks'] ?? [],
                    $body['recommendation'] ?? '',
                    $body['overall_remarks'] ?? '',
                    $body['form_date'] ?? '',
                    $body['designation_institution'] ?? '',
                    $body['contact'] ?? '',
                    !empty($body['submit'])
                );
                json_response($res);
            }
            break;

        case 'action-report':
            $courseId = (int)($_GET['id'] ?? 0);
            if (!$user || $user['role'] !== 'setter') {
                json_response(['error' => 'Paper setter authentication required'], 403);
            }
            if ($method === 'GET') {
                json_response(services_get_action_report($courseId) ?: new stdClass());
            } elseif ($method === 'POST' || $method === 'PUT') {
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
                $res = services_save_action_report(
                    $courseId,
                    $user['id'],
                    $body['summary'] ?? '',
                    $body['declarations'] ?? [],
                    $body['retained'] ?? [],
                    $body['form_date'] ?? '',
                    $body['contact'] ?? '',
                    !empty($body['submit'])
                );
                json_response($res);
            }
            break;

        default:
            json_response(['error' => 'Endpoint not found', 'available_actions' => ['form-definition', 'courses', 'course', 'feedback', 'action-report']], 404);
    }
} catch (ValidationException $e) {
    json_response(['error' => 'Validation Error', 'details' => $e->errors], 422);
} catch (PermissionDeniedException $e) {
    json_response(['error' => 'Permission Denied', 'message' => $e->getMessage()], 403);
} catch (NotFoundException $e) {
    json_response(['error' => 'Not Found', 'message' => $e->getMessage()], 404);
} catch (Exception $e) {
    json_response(['error' => 'Server Error', 'message' => $e->getMessage()], 500);
}
