<?php
/**
 * High-Fidelity Printable Document View of Question Paper Scrutiny Form & ATR
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services.php';
require_once __DIR__ . '/includes/criteria.php';

require_auth();
$user = current_user();

$courseId = (int)($_GET['id'] ?? 0);
if ($courseId <= 0) {
    die("Course ID missing.");
}

$ctx = services_build_context($courseId);
$course = $ctx['course'];

// Permission check: expert and setter can only view their own allocated courses
if ($user['role'] === 'expert' && (int)$course['expert_id'] !== (int)$user['id']) {
    die("Access Denied: You are not assigned to this course.");
}
if ($user['role'] === 'setter' && (int)$course['setter_id'] !== (int)$user['id']) {
    die("Access Denied: You are not assigned to this course.");
}

$fb = $ctx['feedback'] ?? [];
$atr = $ctx['action_report'] ?? [];

$ratings = $fb['ratings'] ?? [];
$remarks = $fb['remarks'] ?? [];
$reco = $fb['recommendation'] ?? '';
$declarations = $atr['declarations'] ?? [];
$retained = $atr['retained'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Form - <?= htmlspecialchars($course['course_code']) ?> | <?= INSTITUTION_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/vignan-logo.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background: #e2e8f0;
            margin: 0;
            padding: 20px 0;
            line-height: 1.35;
        }

        .action-bar {
            max-width: 850px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-family: sans-serif;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px;
            border: 1px solid #ccc;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            background: #f8fafc;
        }

        .btn-primary {
            background: #0070eb;
            color: #fff;
            border-color: #0070eb;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        /* Printable Document Sheet */
        .doc-sheet {
            max-width: 850px;
            margin: 0 auto 30px;
            background: #fff;
            padding: 40px 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            box-sizing: border-box;
        }

        .doc-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .logo-img {
            max-height: 65px;
            margin-bottom: 6px;
        }

        .doc-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 6px 0 2px;
        }

        .doc-subtitle {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }

        .section-heading {
            font-size: 15px;
            font-weight: bold;
            background: #f1f5f9;
            padding: 6px 10px;
            border: 1px solid #000;
            border-bottom: none;
            text-transform: uppercase;
            margin-top: 18px;
        }

        table.doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 16px;
        }

        table.doc-table th, table.doc-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: middle;
        }

        table.doc-table th {
            background: #f8fafc;
            text-align: center;
            font-weight: bold;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        .checkbox-box {
            display: inline-block;
            font-size: 14px;
            font-family: sans-serif;
        }

        .sig-box {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sig-img {
            max-height: 60px;
            max-width: 160px;
            object-fit: contain;
        }

        .seal-img {
            max-height: 55px;
            max-width: 120px;
            object-fit: contain;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .action-bar, .no-print {
                display: none !important;
            }
            .doc-sheet {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>

<div class="action-bar no-print">
    <div>
        <strong><?= htmlspecialchars($course['course_code']) ?> &ndash; <?= htmlspecialchars($course['course_name']) ?></strong>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <?php if ($user['role'] === 'admin'): ?>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> Print / Save as PDF
            </button>
            <a href="<?= BASE_URL ?>/download_doc.php?id=<?= $courseId ?>&fmt=docx" class="btn">
                <i class="fa-solid fa-file-word" style="color: #0070eb;"></i> Download Word (.docx)
            </a>
        <?php else: ?>
            <span style="font-size: 12px; color: #64748b; margin-right: 8px;">
                <i class="fa-solid fa-eye"></i> Form View Mode (Download reserved for Admin)
            </span>
        <?php endif; ?>
        <button onclick="window.close()" class="btn">Close</button>
    </div>
</div>

<div class="doc-sheet">
    <!-- University Header -->
    <div class="doc-header">
        <img src="<?= BASE_URL ?>/assets/img/vignan-logo.svg" alt="Vignan Logo" class="logo-img">
        <div class="doc-title">Review of Summative Assessment Question Paper</div>
        <div class="doc-subtitle">External Scrutinizer Feedback Form</div>
    </div>

    <!-- Details Table -->
    <table class="doc-table">
        <tr>
            <td class="font-bold" style="width: 25%;">Name of External Scrutinizer</td>
            <td style="width: 35%;"><?= htmlspecialchars($fb['expert_name'] ?? $course['expert_name']) ?></td>
            <td class="font-bold" style="width: 20%;">Designation &amp; Institution</td>
            <td style="width: 20%;"><?= htmlspecialchars($fb['designation_institution'] ?? $course['expert_designation_institution']) ?></td>
        </tr>
        <tr>
            <td class="font-bold">E-mail ID</td>
            <td><?= htmlspecialchars($fb['email'] ?? $course['expert_email']) ?></td>
            <td class="font-bold">Contact No.</td>
            <td><?= htmlspecialchars($fb['contact'] ?? $course['expert_phone']) ?></td>
        </tr>
        <tr>
            <td class="font-bold">Programme / Regulation</td>
            <td><?= htmlspecialchars($course['programme']) ?> / <?= htmlspecialchars($course['regulation']) ?></td>
            <td class="font-bold">Course Code</td>
            <td><strong><?= htmlspecialchars($course['course_code']) ?></strong></td>
        </tr>
        <tr>
            <td class="font-bold">Semester / Academic Year</td>
            <td>Semester <?= htmlspecialchars($course['semester']) ?> / <?= htmlspecialchars($course['academic_year']) ?></td>
            <td class="font-bold">Course Name</td>
            <td><strong><?= htmlspecialchars($course['course_name']) ?></strong></td>
        </tr>
    </table>

    <!-- Criteria Evaluation Table -->
    <table class="doc-table">
        <thead>
            <tr>
                <th style="width: 30px;">S.No</th>
                <th>Criteria</th>
                <th style="width: 38px;">5<br><small>Exc</small></th>
                <th style="width: 38px;">4<br><small>V.Gd</small></th>
                <th style="width: 38px;">3<br><small>Good</small></th>
                <th style="width: 38px;">2<br><small>Needs</small></th>
                <th style="width: 38px;">1<br><small>Unsat</small></th>
                <th style="width: 200px;">Remarks / Suggestions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (CRITERIA as $idx => $c): ?>
                <?php 
                    $cNum = $idx + 1;
                    $score = (int)($ratings[$cNum] ?? ($ratings[(string)$cNum] ?? 0));
                    $rem = $remarks[$cNum] ?? ($remarks[(string)$cNum] ?? '');
                ?>
                <tr>
                    <td class="text-center font-bold"><?= $cNum ?></td>
                    <td>
                        <strong><?= htmlspecialchars($c['title']) ?></strong><br>
                        <small style="color: #444;"><?= htmlspecialchars($c['desc']) ?></small>
                    </td>
                    <td class="text-center"><?= $score === 5 ? '&#9745;' : '&#9744;' ?></td>
                    <td class="text-center"><?= $score === 4 ? '&#9745;' : '&#9744;' ?></td>
                    <td class="text-center"><?= $score === 3 ? '&#9745;' : '&#9744;' ?></td>
                    <td class="text-center"><?= $score === 2 ? '&#9745;' : '&#9744;' ?></td>
                    <td class="text-center"><?= $score === 1 ? '&#9745;' : '&#9744;' ?></td>
                    <td style="font-size: 11px;"><?= htmlspecialchars($rem) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Recommendation Table -->
    <div style="font-weight: bold; margin-bottom: 6px;">Overall Expert Recommendation:</div>
    <table class="doc-table">
        <tr>
            <?php foreach (RECOMMENDATIONS as $rec): ?>
                <td class="text-center" style="width: 25%;">
                    <?= $reco === $rec ? '&#9745;' : '&#9744;' ?> <strong><?= htmlspecialchars($rec) ?></strong>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <!-- Overall Remarks -->
    <div style="font-weight: bold; margin-bottom: 4px;">Overall Remarks / Specific Suggestions for Improvement:</div>
    <div style="border: 1px solid #000; padding: 8px; min-height: 50px; font-size: 12px; margin-bottom: 16px; white-space: pre-wrap;">
        <?= htmlspecialchars($fb['overall_remarks'] ?? 'None') ?>
    </div>

    <!-- Scrutinizer Signature Table -->
    <table class="doc-table">
        <tr>
            <td class="text-center" style="width: 50%;">
                <div class="sig-box">
                    <?php if (!empty($fb['signature'])): ?>
                        <img src="data:image/png;base64,<?= base64_encode($fb['signature']) ?>" alt="Signature" class="sig-img">
                    <?php endif; ?>
                </div>
                <strong>Signature of External Scrutinizer</strong><br>
                Date: <?= !empty($fb['form_date']) ? date('d/m/Y', strtotime($fb['form_date'])) : '________________' ?>
            </td>
            <td class="text-center" style="width: 50%;">
                <div class="sig-box">
                    <?php if (!empty($fb['seal'])): ?>
                        <img src="data:image/png;base64,<?= base64_encode($fb['seal']) ?>" alt="Seal" class="seal-img">
                    <?php endif; ?>
                </div>
                <strong>Official Seal</strong>
            </td>
        </tr>
    </table>

    <!-- Action Taken Report Section -->
    <div style="border-top: 3px double #000; margin-top: 25px; padding-top: 15px;">
        <div class="doc-header" style="border-bottom: 1px solid #000; margin-bottom: 12px;">
            <div class="doc-title" style="font-size: 16px;">Action Taken Report</div>
            <div class="doc-subtitle">(To be filled by Paper Setter / Course Faculty)</div>
        </div>

        <table class="doc-table">
            <tr>
                <td class="font-bold" style="width: 25%;">Name of Paper Setter</td>
                <td style="width: 35%;"><?= htmlspecialchars($atr['setter_name'] ?? $course['setter_name']) ?></td>
                <td class="font-bold" style="width: 20%;">Department</td>
                <td style="width: 20%;"><?= htmlspecialchars($atr['department'] ?? $course['department']) ?></td>
            </tr>
            <tr>
                <td class="font-bold">Contact No.</td>
                <td><?= htmlspecialchars($atr['contact'] ?? $course['setter_phone']) ?></td>
                <td class="font-bold">Mail-id</td>
                <td><?= htmlspecialchars($atr['email'] ?? $course['setter_email']) ?></td>
            </tr>
        </table>

        <div style="font-weight: bold; margin-bottom: 6px;">Declaration by Paper Setter / Subject Expert:</div>
        <table class="doc-table">
            <?php foreach (DECLARATIONS as $dIdx => $dText): ?>
                <tr>
                    <td style="width: 30px;" class="text-center font-bold">
                        <?= !empty($declarations[$dIdx]) ? '&#9745;' : '&#9744;' ?>
                    </td>
                    <td><?= $dIdx + 1 ?>. <?= htmlspecialchars($dText) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div style="font-weight: bold; margin-bottom: 4px;">Summary of Major Changes Incorporated:</div>
        <div style="border: 1px solid #000; padding: 8px; min-height: 60px; font-size: 12px; margin-bottom: 16px; white-space: pre-wrap;">
            <?= htmlspecialchars($atr['summary'] ?? '') ?>
        </div>

        <!-- Setter, HoD, Dean Signatures Table -->
        <table class="doc-table">
            <tr>
                <td class="text-center" style="width: 33.3%;">
                    <div class="sig-box">
                        <?php if (!empty($atr['signature'])): ?>
                            <img src="data:image/png;base64,<?= base64_encode($atr['signature']) ?>" alt="Signature" class="sig-img">
                        <?php endif; ?>
                    </div>
                    <strong>Signature of Paper Setter</strong><br>
                    Date: <?= !empty($atr['form_date']) ? date('d/m/Y', strtotime($atr['form_date'])) : '________________' ?>
                </td>
                <td class="text-center" style="width: 33.3%;">
                    <div class="sig-box"></div>
                    <strong>Signature of HoD</strong><br>
                    Date: ________________
                </td>
                <td class="text-center" style="width: 33.3%;">
                    <div class="sig-box"></div>
                    <strong>Signature of School Dean</strong><br>
                    Date: ________________
                </td>
            </tr>
        </table>

        <div style="font-weight: bold; margin-top: 10px; font-size: 12px;">
            Documents to be Retained: 
            <?php foreach (RETAINED_DOCS as $rIdx => $rText): ?>
                <span style="margin-left: 15px;">
                    <?= !empty($retained[$rIdx]) ? '&#9745;' : '&#9744;' ?> <?= htmlspecialchars($rText) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</body>
</html>
