<?php
/**
 * Form Criteria, Static Labels, and Constants
 * Preserved verbatim from original specification and Word template.
 */

const RATING_LABELS = [
    5 => "Excellent",
    4 => "Very Good",
    3 => "Good",
    2 => "Needs Improvement",
    1 => "Unsatisfactory"
];

const CRITERIA = [
    [
        "title" => "Syllabus Coverage",
        "desc"  => "The question paper adequately covers the breadth and depth of the prescribed course content."
    ],
    [
        "title" => "Course Outcome (CO) Alignment",
        "desc"  => "Questions appropriately assess the intended Course Outcomes of the course."
    ],
    [
        "title" => "Cognitive Level",
        "desc"  => "Questions assess appropriate levels of learning, including understanding, application, analysis and problem-solving, wherever applicable."
    ],
    [
        "title" => "Difficulty Level & Balance",
        "desc"  => "There is an appropriate balance of easy, moderate and difficult questions for UG students."
    ],
    [
        "title" => "Clarity & Ambiguity",
        "desc"  => "Questions are clearly worded, unambiguous and sufficiently specific for students to understand what is expected."
    ],
    [
        "title" => "Technical Accuracy",
        "desc"  => "Questions, data, diagrams, terminology, numerical values and answer requirements are technically correct."
    ],
    [
        "title" => "Marks & Weightage",
        "desc"  => "Marks allotted are appropriate to the scope and expected effort of the questions."
    ],
    [
        "title" => "Time Adequacy",
        "desc"  => "A student can reasonably complete the question paper within the stipulated examination time."
    ],
    [
        "title" => "Assessment Quality",
        "desc"  => "The paper appropriately assesses the knowledge, skills and competencies expected from students."
    ]
];

const RECOMMENDATIONS = [
    "Approved as it is",
    "Approved with minor modifications",
    "Revision recommended",
    "Major revision required"
];

const DECLARATIONS = [
    "I have reviewed all observations/suggestions provided by the External Expert.",
    "Necessary corrections/modifications have been incorporated in the question paper wherever applicable.",
    "The revised question paper has been checked for syllabus coverage, CO alignment, cognitive level, difficulty, clarity, technical accuracy, marks and time adequacy.",
    "The revised question paper submitted with this report is the final version recommended for approval."
];

const RETAINED_DOCS = [
    "External Scrutinizer Feedback Form",
    "Action Taken Report",
    "Final Question Paper Used"
];

// Status Constants
const ST_UNALLOCATED  = "Not allocated";
const ST_AWAIT_EXPERT = "Awaiting expert feedback";
const ST_AWAIT_SETTER = "Awaiting action taken report";
const ST_COMPLETE     = "Completed";

const ALL_STATUSES = [
    ST_UNALLOCATED,
    ST_AWAIT_EXPERT,
    ST_AWAIT_SETTER,
    ST_COMPLETE
];

// UI Status Badge Helper
function get_status_badge(string $status): string {
    switch ($status) {
        case ST_COMPLETE:
            return '<span class="status-badge status-complete"><span class="badge-dot dot-green"></span> Completed</span>';
        case ST_AWAIT_SETTER:
            return '<span class="status-badge status-setter"><span class="badge-dot dot-orange"></span> Awaiting ATR</span>';
        case ST_AWAIT_EXPERT:
            return '<span class="status-badge status-expert"><span class="badge-dot dot-yellow"></span> Awaiting Feedback</span>';
        case ST_UNALLOCATED:
        default:
            return '<span class="status-badge status-unallocated"><span class="badge-dot dot-gray"></span> Not Allocated</span>';
    }
}
