<?php
/**
 * Document Generation Service (Word .docx, PDF, and ZIP Archives)
 * Pure PHP implementation - No external Python runtime required.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/criteria.php';

function docgen_clean_filename(string $str): string {
    return preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($str));
}

function docgen_get_filename(array $course, string $fmt = 'docx'): string {
    $parts = [
        $course['academic_year'] ?? '',
        $course['semester'] ?? '',
        $course['department'] ?? '',
        $course['course_code'] ?? ''
    ];
    $cleanParts = array_map('docgen_clean_filename', array_filter($parts));
    $base = implode('_', $cleanParts);
    return "Feedback_" . ($base ?: "Form") . "." . $fmt;
}

function docgen_fmt_doc_date($value): string {
    if (!$value) return '';
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y', $ts) : (string)$value;
}

function docgen_set_cell_text(DOMDocument $dom, DOMElement $cell, string $text, int $sizePt = 10, bool $bold = false, bool $center = false): void {
    $paragraphs = [];
    foreach ($cell->childNodes as $node) {
        if ($node->nodeName === 'w:p') {
            $paragraphs[] = $node;
        }
    }
    foreach ($paragraphs as $p) {
        $cell->removeChild($p);
    }

    $lines = explode("\n", $text);
    $p = $dom->createElement('w:p');

    $pPr = $dom->createElement('w:pPr');
    $spacing = $dom->createElement('w:spacing');
    $spacing->setAttribute('w:after', '0');
    $spacing->setAttribute('w:line', '240');
    $spacing->setAttribute('w:lineRule', 'auto');
    $pPr->appendChild($spacing);

    if ($center) {
        $jc = $dom->createElement('w:jc');
        $jc->setAttribute('w:val', 'center');
        $pPr->appendChild($jc);
    }
    $p->appendChild($pPr);

    $r = $dom->createElement('w:r');
    $rPr = $dom->createElement('w:rPr');
    $rFonts = $dom->createElement('w:rFonts');
    $rFonts->setAttribute('w:ascii', 'Times New Roman');
    $rFonts->setAttribute('w:hAnsi', 'Times New Roman');
    $rFonts->setAttribute('w:cs', 'Times New Roman');
    $rFonts->setAttribute('w:eastAsia', 'Times New Roman');
    $rPr->appendChild($rFonts);

    $sz = $dom->createElement('w:sz');
    $sz->setAttribute('w:val', (string)($sizePt * 2));
    $rPr->appendChild($sz);

    if ($bold) {
        $b = $dom->createElement('w:b');
        $rPr->appendChild($b);
    }
    $r->appendChild($rPr);

    foreach ($lines as $i => $line) {
        if ($i > 0) {
            $r->appendChild($dom->createElement('w:br'));
        }
        $t = $dom->createElement('w:t');
        $t->setAttribute('xml:space', 'preserve');
        $t->textContent = $line;
        $r->appendChild($t);
    }
    $p->appendChild($r);
    $cell->appendChild($p);
}

function docgen_tick_cell(DOMElement $cell): bool {
    $boxes = ["\xE2\x98\x90", "\xE2\x96\xA1", "☐", "□", "▢"];
    $TICK = "\xE2\x98\x91"; // ☑ Ballot box with checkmark

    $tNodes = $cell->getElementsByTagName('t');
    foreach ($tNodes as $t) {
        foreach ($boxes as $box) {
            if (str_contains($t->textContent, $box)) {
                $t->textContent = preg_replace('/' . preg_quote($box, '/') . '/', $TICK, $t->textContent, 1);
                return true;
            }
        }
    }
    return false;
}

function docgen_add_picture_to_cell(DOMDocument $dom, DOMElement $cell, string $relId, string $imgBytes, float $maxWIn = 2.0, float $maxHIn = 0.5, int $picId = 100): void {
    if (empty($imgBytes)) return;

    $imgInfo = @getimagesizefromstring($imgBytes);
    $origW = $imgInfo ? $imgInfo[0] : 200;
    $origH = $imgInfo ? $imgInfo[1] : 60;
    if ($origH <= 0) $origH = 60;
    if ($origW <= 0) $origW = 200;

    $ratio = $origW / $origH;
    $hIn = $maxHIn;
    $wIn = $hIn * $ratio;
    if ($wIn > $maxWIn) {
        $wIn = $maxWIn;
        $hIn = $wIn / $ratio;
    }

    $cx = (int)round($wIn * 914400);
    $cy = (int)round($hIn * 914400);

    $paragraphs = [];
    foreach ($cell->childNodes as $node) {
        if ($node->nodeName === 'w:p') {
            $paragraphs[] = $node;
        }
    }
    foreach ($paragraphs as $p) {
        $cell->removeChild($p);
    }

    $drawingXml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
        '<w:pPr><w:spacing w:after="0"/><w:jc w:val="center"/></w:pPr>' .
        '<w:r>' .
        '<w:drawing>' .
        '<wp:inline distT="0" distB="0" distL="0" distR="0" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">' .
        '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>' .
        '<wp:effectExtent l="0" t="0" r="0" b="0"/>' .
        '<wp:docPr id="' . $picId . '" name="Picture ' . $picId . '"/>' .
        '<wp:cNvGraphicFramePr>' .
        '<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>' .
        '</wp:cNvGraphicFramePr>' .
        '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' .
        '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">' .
        '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">' .
        '<pic:nvPicPr>' .
        '<pic:cNvPr id="' . $picId . '" name="Image' . $picId . '.png"/>' .
        '<pic:cNvPicPr/>' .
        '</pic:nvPicPr>' .
        '<pic:blipFill>' .
        '<a:blip r:embed="' . $relId . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>' .
        '<a:stretch><a:fillRect/></a:stretch>' .
        '</pic:blipFill>' .
        '<pic:spPr>' .
        '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>' .
        '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>' .
        '</pic:spPr>' .
        '</pic:pic>' .
        '</a:graphicData>' .
        '</a:graphic>' .
        '</wp:inline>' .
        '</w:drawing>' .
        '</w:r>' .
        '</w:p>';

    $fragDoc = new DOMDocument();
    $fragDoc->loadXML($drawingXml);
    $importedNode = $dom->importNode($fragDoc->documentElement, true);
    $cell->appendChild($importedNode);
}

function docgen_fill_textboxes(DOMDocument $dom, string $text, int $sizePt = 9): void {
    $txbxList = $dom->getElementsByTagName('txbxContent');
    $lines = explode("\n", $text ?: "");

    foreach ($txbxList as $box) {
        while ($box->hasChildNodes()) {
            $box->removeChild($box->firstChild);
        }
        foreach ($lines as $line) {
            $p = $dom->createElement('w:p');
            $pPr = $dom->createElement('w:pPr');
            $spacing = $dom->createElement('w:spacing');
            $spacing->setAttribute('w:after', '0');
            $pPr->appendChild($spacing);
            $p->appendChild($pPr);

            $r = $dom->createElement('w:r');
            $rPr = $dom->createElement('w:rPr');
            $rFonts = $dom->createElement('w:rFonts');
            $rFonts->setAttribute('w:ascii', 'Times New Roman');
            $rFonts->setAttribute('w:hAnsi', 'Times New Roman');
            $rFonts->setAttribute('w:cs', 'Times New Roman');
            $rFonts->setAttribute('w:eastAsia', 'Times New Roman');
            $rPr->appendChild($rFonts);

            $sz = $dom->createElement('w:sz');
            $sz->setAttribute('w:val', (string)($sizePt * 2));
            $rPr->appendChild($sz);
            $r->appendChild($rPr);

            $t = $dom->createElement('w:t');
            $t->setAttribute('xml:space', 'preserve');
            $t->textContent = $line;
            $r->appendChild($t);

            $p->appendChild($r);
            $box->appendChild($p);
        }
    }
}

function docgen_get_unique_cells(DOMElement $table, int $rowIdx): array {
    $rows = $table->getElementsByTagName('tr');
    if ($rowIdx >= $rows->length) return [];
    $row = $rows->item($rowIdx);
    $cells = [];
    foreach ($row->childNodes as $child) {
        if ($child->nodeName === 'w:tc') {
            $cells[] = $child;
        }
    }
    return $cells;
}

function docgen_render_document(int $course_id, string $fmt = 'docx'): string {
    $ctx = services_build_context($course_id);
    $templatePath = TEMPLATE_DOCX_PATH;
    if (!file_exists($templatePath)) {
        throw new ServiceException("Template file not found at " . $templatePath);
    }

    $tempZipPath = tempnam(sys_get_temp_dir(), 'vignan_doc_');
    copy($templatePath, $tempZipPath);

    $zip = new ZipArchive();
    if ($zip->open($tempZipPath) !== true) {
        @unlink($tempZipPath);
        throw new ServiceException("Failed to open DOCX template archive.");
    }

    $docXml = $zip->getFromName('word/document.xml');
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');

    $dom = new DOMDocument();
    $dom->loadXML($docXml);

    $relsDom = new DOMDocument();
    $relsDom->loadXML($relsXml);

    $course = $ctx['course'] ?? [];
    $fb = $ctx['feedback'] ?? [];
    $atr = $ctx['action_report'] ?? [];

    $tables = $dom->getElementsByTagName('tbl');
    if ($tables->length >= 6) {
        // Table 0: Header
        $t0 = $tables->item(0);
        $progReg = implode(' / ', array_filter([$course['programme'] ?? '', $course['regulation'] ?? '']));
        $semAY = implode(' / ', array_filter([$course['semester'] ?? '', $course['academic_year'] ?? '']));

        $headerVals = [
            [0, 1, $fb['expert_name'] ?: ($course['expert_name'] ?? '')],
            [0, 3, $fb['designation_institution'] ?: ($course['expert_designation_institution'] ?? '')],
            [1, 1, $fb['email'] ?: ($course['expert_email'] ?? '')],
            [1, 3, $fb['contact'] ?: ($course['expert_phone'] ?? '')],
            [2, 1, $progReg],
            [2, 3, $course['course_code'] ?? ''],
            [3, 1, $semAY],
            [3, 3, $course['course_name'] ?? ''],
        ];
        foreach ($headerVals as [$r, $c, $val]) {
            $cells = docgen_get_unique_cells($t0, $r);
            if (isset($cells[$c])) {
                docgen_set_cell_text($dom, $cells[$c], (string)$val, 10, false, true);
            }
        }

        // Table 1: Ratings
        $t1 = $tables->item(1);
        $ratings = $fb['ratings'] ?? [];
        $remarks = $fb['remarks'] ?? [];
        for ($i = 1; $i <= 9; $i++) {
            $cells = docgen_get_unique_cells($t1, 2 + $i);
            $score = isset($ratings[$i]) ? (int)$ratings[$i] : (isset($ratings[(string)$i]) ? (int)$ratings[(string)$i] : 0);
            if ($score >= 1 && $score <= 5) {
                $targetCellIdx = 2 + (5 - $score);
                if (isset($cells[$targetCellIdx])) {
                    docgen_tick_cell($cells[$targetCellIdx]);
                }
            }
            $critRemark = $remarks[$i] ?? ($remarks[(string)$i] ?? '');
            if ($critRemark !== '' && isset($cells[7])) {
                docgen_set_cell_text($dom, $cells[7], (string)$critRemark, 8);
            }
        }

        // Table 2: Recommendations
        $t2 = $tables->item(2);
        $reco = $fb['recommendation'] ?? '';
        $recoOptions = [
            'Approved as it is',
            'Approved with minor modifications',
            'Revision recommended',
            'Major revision required'
        ];
        $recoCells = docgen_get_unique_cells($t2, 0);
        $recoIdx = array_search($reco, $recoOptions);
        if ($recoIdx !== false && isset($recoCells[$recoIdx])) {
            docgen_tick_cell($recoCells[$recoIdx]);
        }

        // Overall remarks (Row 2, Cell 0)
        $recoRemarksCells = docgen_get_unique_cells($t2, 2);
        if (isset($recoRemarksCells[0])) {
            docgen_set_cell_text($dom, $recoRemarksCells[0], (string)($fb['overall_remarks'] ?? ''), 10);
        }

        // Form Date (Row 4, Cell 1)
        $sigRow = docgen_get_unique_cells($t2, 4);
        if (isset($sigRow[1])) {
            docgen_set_cell_text($dom, $sigRow[1], docgen_fmt_doc_date($fb['form_date'] ?? ''), 10, false, true);
        }

        // Table 3: ATR Header
        $t3 = $tables->item(3);
        $atrVals = [
            [0, 1, $atr['setter_name'] ?: ($course['setter_name'] ?? '')],
            [0, 3, $atr['department'] ?: ($course['department'] ?? '')],
            [1, 1, $atr['contact'] ?: ($course['setter_phone'] ?? '')],
            [1, 3, $atr['email'] ?: ($course['setter_email'] ?? '')],
        ];
        foreach ($atrVals as [$r, $c, $val]) {
            $cells = docgen_get_unique_cells($t3, $r);
            if (isset($cells[$c])) {
                docgen_set_cell_text($dom, $cells[$c], (string)$val, 10, false, true);
            }
        }

        // Table 4: Declarations
        $t4 = $tables->item(4);
        $decl = $atr['declarations'] ?? [];
        for ($i = 0; $i < 4; $i++) {
            if (!empty($decl[$i])) {
                $cells = docgen_get_unique_cells($t4, $i);
                if (isset($cells[0])) {
                    docgen_tick_cell($cells[0]);
                }
            }
        }

        // Textboxes: Summary of changes
        docgen_fill_textboxes($dom, (string)($atr['summary'] ?? ''));

        // Handle Images (Expert signature, Expert seal, Setter signature)
        $relCount = 100;
        
        $addImageRel = function(string $imgData, string $filename) use ($zip, $relsDom, &$relCount) {
            $relCount++;
            $rId = 'rIdCustom' . $relCount;
            $zip->addFromString('word/media/' . $filename, $imgData);

            $relNode = $relsDom->createElement('Relationship');
            $relNode->setAttribute('Id', $rId);
            $relNode->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
            $relNode->setAttribute('Target', 'media/' . $filename);
            $relsDom->documentElement->appendChild($relNode);

            return $rId;
        };

        if (!empty($fb['signature'])) {
            $rId = $addImageRel($fb['signature'], 'expert_sig.png');
            if (isset($sigRow[0])) {
                docgen_add_picture_to_cell($dom, $sigRow[0], $rId, $fb['signature'], 2.0, 0.5, 201);
            }
        }
        if (!empty($fb['seal'])) {
            $rId = $addImageRel($fb['seal'], 'expert_seal.png');
            if (isset($sigRow[2])) {
                docgen_add_picture_to_cell($dom, $sigRow[2], $rId, $fb['seal'], 1.4, 0.55, 202);
            }
        }

        // Table 5: Setter Signatures
        $t5 = $tables->item(5);
        $setterSigRow = docgen_get_unique_cells($t5, 1);
        if (!empty($atr['signature'])) {
            $rId = $addImageRel($atr['signature'], 'setter_sig.png');
            if (isset($setterSigRow[0])) {
                docgen_add_picture_to_cell($dom, $setterSigRow[0], $rId, $atr['signature'], 2.0, 0.5, 203);
            }
        }
    }

    // Retained documents & ATR date in paragraphs
    $boxes = ["\xE2\x98\x90", "\xE2\x96\xA1", "☐", "□", "▢"];
    $TICK = "\xE2\x98\x91"; // ☑

    $paragraphs = $dom->getElementsByTagName('p');
    foreach ($paragraphs as $p) {
        $pText = $p->textContent;
        if (str_starts_with(trim($pText), 'Date:')) {
            $runs = $p->getElementsByTagName('r');
            if ($runs->length > 0) {
                $fdateStr = !empty($atr['form_date']) ? docgen_fmt_doc_date($atr['form_date']) : '________________';
                $tNodes = $runs->item(0)->getElementsByTagName('t');
                if ($tNodes->length > 0) {
                    $tNodes->item(0)->textContent = 'Date: ' . $fdateStr;
                }
                for ($ri = 1; $ri < $runs->length; $ri++) {
                    $extraTs = $runs->item($ri)->getElementsByTagName('t');
                    foreach ($extraTs as $et) {
                        $et->textContent = '';
                    }
                }
            }
        } elseif (str_starts_with(trim($pText), 'Documents to be Retained')) {
            $retained = $atr['retained'] ?? [];
            $tNodes = $p->getElementsByTagName('t');
            $boxIdx = 0;
            foreach ($tNodes as $t) {
                $hasBox = false;
                foreach ($boxes as $box) {
                    if (str_contains($t->textContent, $box)) {
                        $hasBox = true;
                        if (!empty($retained[$boxIdx])) {
                            $t->textContent = str_replace($box, $TICK, $t->textContent);
                        }
                        break;
                    }
                }
                if ($hasBox) {
                    $boxIdx++;
                }
            }
        }
    }

    // Save modified XML back into ZIP
    $zip->addFromString('word/document.xml', $dom->saveXML());
    $zip->addFromString('word/_rels/document.xml.rels', $relsDom->saveXML());
    $zip->close();

    $docxContent = file_get_contents($tempZipPath);
    @unlink($tempZipPath);
    return $docxContent;
}

function docgen_build_zip(array $course_ids, array $formats = ['docx']): string {
    $tempZip = tempnam(sys_get_temp_dir(), 'vignan_zip_');
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new ServiceException("Cannot create temporary ZIP file.");
    }

    foreach ($course_ids as $cid) {
        $course = services_get_course((int)$cid);
        $folder = implode('/', [
            docgen_clean_filename($course['academic_year']),
            docgen_clean_filename($course['semester']),
            docgen_clean_filename($course['department'])
        ]);

        foreach ($formats as $fmt) {
            try {
                $docBytes = docgen_render_document((int)$cid, $fmt);
                $filename = docgen_get_filename($course, $fmt);
                $zip->addFromString("$folder/$filename", $docBytes);
            } catch (Exception $e) {
                error_log("Failed to render $fmt for course $cid: " . $e->getMessage());
            }
        }
    }

    $zip->close();
    $zipData = file_get_contents($tempZip);
    @unlink($tempZip);
    return $zipData;
}
