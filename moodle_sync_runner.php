<?php
define('CLI_SCRIPT', true);
require('/home/milton/Projects/elearn/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/url/lib.php');
require_once($CFG->libdir . '/questionlib.php');

global $DB, $CFG;

$payloadFile = '/tmp/moodle_sync_payload.json';
if (!file_exists($payloadFile)) {
    die("Error: Payload file not found.\n");
}

$payload = json_decode(file_get_contents($payloadFile), true);
$requestedCourseId = $payload['course_id'] ?? null;
$fullname = trim($payload['fullname'] ?? '');
$shortname = trim($payload['shortname'] ?? '');
$categoryId = (int) ($payload['category_id'] ?? 2);
$curriculum = $payload['curriculum'] ?? [];
$processedVideos = $payload['processed_videos'] ?? [];
$generalDocs = $payload['general_docs'] ?? [];

echo "====================================================\n";
echo "Starting Moodle Course Synchronization / Auto-Provision\n";
echo "Target Course Name: {$fullname}\n";
echo "Target Course Code: {$shortname}\n";
echo "Requested Course ID: " . ($requestedCourseId ?: 'None (Auto-discover/create)') . "\n";
echo "====================================================\n";

// ---------------------------------------------------------
// 1. DYNAMIC COURSE DISCOVERY & AUTO-CREATION
// ---------------------------------------------------------
$course = null;

// Priority 1: Check if requestedCourseId exists AND actually matches this course
if (!empty($requestedCourseId) && $requestedCourseId > 1) {
    $existing = $DB->get_record('course', ['id' => $requestedCourseId]);
    if ($existing) {
        $cShortClean = strtoupper(str_replace([' ', '-', '_'], '', $existing->shortname));
        $targetShortClean = strtoupper(str_replace([' ', '-', '_'], '', $shortname));
        if ($cShortClean === $targetShortClean || stripos($existing->fullname, $shortname) !== false) {
            $course = $existing;
            echo "✔ Verified match by requested Course ID {$requestedCourseId}: '{$course->fullname}' ({$course->shortname})\n";
        } else {
            echo "ℹ Requested Course ID {$requestedCourseId} ('{$existing->shortname}') does not match target '{$shortname}'. Searching Moodle...\n";
        }
    }
}

// Priority 2: Search Moodle by Shortname / Course Code
if (!$course && !empty($shortname)) {
    $cleanShort = strtoupper(str_replace([' ', '-', '_'], '', $shortname));
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $candidates = $DB->get_records_sql("SELECT id, fullname, shortname FROM {course} WHERE id > 1");
        foreach ($candidates as $cand) {
            $candClean = strtoupper(str_replace([' ', '-', '_'], '', $cand->shortname));
            if ($candClean === $cleanShort) {
                $course = $cand;
                echo "✔ Found matching course by shortname: ID {$course->id} ('{$course->fullname}')\n";
                break;
            }
        }
    } else {
        echo "✔ Found exact matching course by shortname: ID {$course->id} ('{$course->fullname}')\n";
    }
}

// Priority 3: Search Moodle by Fullname
if (!$course && !empty($fullname)) {
    $course = $DB->get_record('course', ['fullname' => $fullname]);
    if (!$course) {
        $course = $DB->get_record_sql("SELECT * FROM {course} WHERE fullname LIKE ? AND id > 1 LIMIT 1", ['%' . $fullname . '%']);
    }
    if ($course) {
        echo "✔ Found matching course by fullname: ID {$course->id} ('{$course->fullname}')\n";
    }
}

// Priority 4: Course NOT FOUND -> AUTO-CREATE IN MOODLE!
if (!$course) {
    echo "⚡ Course not found in Moodle! Auto-creating new course...\n";
    
    // Ensure valid category
    if (!$DB->record_exists('course_categories', ['id' => $categoryId])) {
        $firstCat = $DB->get_record_sql("SELECT id FROM {course_categories} ORDER BY id ASC LIMIT 1");
        $categoryId = $firstCat ? $firstCat->id : 1;
    }

    // Ensure unique shortname
    $uniqueShortname = $shortname ?: 'COURSE_' . time();
    $counter = 1;
    while ($DB->record_exists('course', ['shortname' => $uniqueShortname])) {
        $uniqueShortname = $shortname . '_' . $counter++;
    }

    $cData = new stdClass();
    $cData->fullname = $fullname ?: 'New University Course';
    $cData->shortname = $uniqueShortname;
    $cData->category = $categoryId;
    $cData->format = 'topics';
    $cData->numsections = 16;
    $cData->visible = 1;
    $cData->timecreated = time();
    $cData->timemodified = time();

    $course = create_course($cData);
    echo "✔ Successfully created new Moodle course with ID: {$course->id} ('{$course->fullname}')\n";
} else {
    // Update existing course details to ensure name consistency
    $course->fullname = $fullname;
    $course->shortname = $shortname;
    $course->visible = 1;
    $DB->update_record('course', $course);
    echo "✔ Course details updated: ID {$course->id} ('{$course->fullname}')\n";
}

$courseid = (int) $course->id;
echo "FINAL_MOODLE_COURSE_ID:{$courseid}\n";

// ---------------------------------------------------------
// HELPER FUNCTIONS FOR IDEMPOTENT MODULE CREATION / UPDATE
// ---------------------------------------------------------

function get_or_create_url($courseid, $sectionnum, $urlName, $externalUrl, $intro = '') {
    global $DB;
    $urlmodule = $DB->get_record('modules', ['name' => 'url']);
    $modinfo = get_fast_modinfo($courseid);

    // Check existing
    if (isset($modinfo->sections[$sectionnum])) {
        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname === 'url' && (str_contains($cm->name, $urlName) || str_contains($urlName, $cm->name))) {
                $u = $DB->get_record('url', ['id' => $cm->instance]);
                if ($u) {
                    $u->externalurl = $externalUrl;
                    if ($intro) $u->intro = $intro;
                    $u->timemodified = time();
                    $DB->update_record('url', $u);
                    return $cmid;
                }
            }
        }
    }

    // Create new
    $url = new stdClass();
    $url->course = $courseid;
    $url->name = $urlName;
    $url->intro = $intro ?: "<p>{$urlName}</p>";
    $url->introformat = FORMAT_HTML;
    $url->externalurl = $externalUrl;
    $url->timemodified = time();
    $urlid = $DB->insert_record('url', $url);

    $cm = new stdClass();
    $cm->course = $courseid;
    $cm->module = $urlmodule->id;
    $cm->instance = $urlid;
    $cm->section = 0;
    $cm->added = time();
    $cm->visible = 1;
    $cm->visibleoncoursepage = 1;
    $cm->visibleold = 1;
    $cmid = $DB->insert_record('course_modules', $cm);
    course_add_cm_to_section($courseid, $cmid, $sectionnum);
    return $cmid;
}

function sync_label_activity($courseid, $sectionnum, $searchKeyword, $labelName, $htmlContent) {
    global $DB;
    $labelmodule = $DB->get_record('modules', ['name' => 'label']);
    $modinfo = get_fast_modinfo($courseid);

    // 1. Check existing in this section
    if (isset($modinfo->sections[$sectionnum])) {
        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname === 'label') {
                $l = $DB->get_record('label', ['id' => $cm->instance]);
                if ($l && (str_contains($cm->name, $searchKeyword) || str_contains($l->intro, $searchKeyword))) {
                    $l->intro = $htmlContent;
                    $l->timemodified = time();
                    $DB->update_record('label', $l);
                    return $cmid;
                }
            } elseif ($cm->modname === 'page' && str_contains($cm->name, $searchKeyword)) {
                $p = $DB->get_record('page', ['id' => $cm->instance]);
                if ($p) {
                    $p->content = $htmlContent;
                    $p->timemodified = time();
                    $DB->update_record('page', $p);
                    return $cmid;
                }
            }
        }
    }

    // 2. Create new label if not found
    $label = new stdClass();
    $label->course = $courseid;
    $label->name = $labelName;
    $label->intro = $htmlContent;
    $label->introformat = FORMAT_HTML;
    $label->timemodified = time();
    $labelid = $DB->insert_record('label', $label);

    $cm = new stdClass();
    $cm->course = $courseid;
    $cm->module = $labelmodule->id;
    $cm->instance = $labelid;
    $cm->section = 0;
    $cm->idnumber = '';
    $cm->added = time();
    $cm->visible = 1;
    $cm->visibleoncoursepage = 1;
    $cm->visibleold = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    $cm->completion = 0;
    $cmid = $DB->insert_record('course_modules', $cm);

    course_add_cm_to_section($courseid, $cmid, $sectionnum);
    return $cmid;
}

// ---------------------------------------------------------
// 2. SYNCHRONIZE GENERAL DOCS (SECTION 0)
// ---------------------------------------------------------
foreach ($generalDocs as $doc) {
    if ($doc['type'] === 'slides') {
        get_or_create_url($courseid, 0, 'Lecture Slides', $doc['view_url'], '<p>Course Lecture Slides & Presentations</p>');
    } elseif ($doc['type'] === 'student_handbook') {
        get_or_create_url($courseid, 0, 'Course Student Handbook', $doc['view_url'], '<p>Official University Student Course Handbook</p>');
    } elseif ($doc['type'] === 'instructor_handbook') {
        get_or_create_url($courseid, 0, 'Course Instructor Handbook', $doc['view_url'], '<p>Official University Instructor Handbook & Guidelines</p>');
    }
}
echo "✔ Section 0 Course Materials & Documents synchronized.\n";

// ---------------------------------------------------------
// 3. SYNCHRONIZE SECTIONS 1 TO 16 (CURRICULUM, VIDEOS, QUIZZES)
// ---------------------------------------------------------
$quizmodule = $DB->get_record('modules', ['name' => 'quiz']);

for ($wnum = 1; $wnum <= 16; $wnum++) {
    if (!isset($curriculum[$wnum]) && !isset($curriculum[strval($wnum)])) {
        continue;
    }

    $w = $curriculum[$wnum] ?? $curriculum[strval($wnum)];
    $title = $w['title'] ?? "Week {$wnum}";

    // Ensure section exists and rename
    $sec_rec = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $wnum]);
    if (!$sec_rec) {
        $sec_rec = new stdClass();
        $sec_rec->course = $courseid;
        $sec_rec->section = $wnum;
        $sec_rec->name = sprintf("Week %02d - %s", $wnum, $title);
        $sec_rec->summary = '';
        $sec_rec->summaryformat = FORMAT_HTML;
        $sec_rec->visible = 1;
        $sec_rec->id = $DB->insert_record('course_sections', $sec_rec);
    } else {
        $sec_rec->name = sprintf("Week %02d - %s", $wnum, $title);
        $DB->update_record('course_sections', $sec_rec);
    }

    // A. Overview (Instructor Overview + Student Learning Objectives)
    $overviewHtml = '<div class="card p-3 my-2" style="border-left: 4px solid #3b82f6; border-radius: 8px; background: #f8fafc;">';
    $overviewHtml .= '<h5 style="color: #1e3a8a; font-weight: 700; margin-bottom: 12px;">Week ' . sprintf('%02d', $wnum) . ' Overview: ' . htmlspecialchars($title) . '</h5>';
    $overviewHtml .= '<p style="color: #334155; line-height: 1.6; margin-bottom: 14px;">' . htmlspecialchars($w['overview'] ?? '') . '</p>';
    if (!empty($w['objectives'])) {
        $overviewHtml .= '<h6 style="color: #1e40af; font-weight: 600; text-transform: uppercase; font-size: 0.82rem; letter-spacing: 0.5px;">Student Learning Objectives:</h6>';
        $overviewHtml .= '<ul style="color: #475569; padding-left: 20px; margin-bottom: 0;">';
        foreach ($w['objectives'] as $obj) {
            $overviewHtml .= '<li style="margin-bottom: 4px;">' . htmlspecialchars($obj) . '</li>';
        }
        $overviewHtml .= '</ul>';
    }
    $overviewHtml .= '</div>';
    sync_label_activity($courseid, $wnum, 'Overview', 'Overview', $overviewHtml);

    // B. Required Readings & Key Terms
    $readingsHtml = '<div class="card p-3 my-2" style="border-left: 4px solid #10b981; border-radius: 8px; background: #f0fdf4;">';
    $readingsHtml .= '<h5 style="color: #065f46; font-weight: 700; margin-bottom: 10px;"><i class="fa fa-book mr-1"></i> Required Readings & Core Concepts</h5>';
    $readingsHtml .= '<p style="margin-bottom: 10px;"><strong>Student Handbook:</strong> Chapter ' . $wnum . ' — <em>' . htmlspecialchars($title) . '</em></p>';
    if (!empty($w['key_terms'])) {
        $readingsHtml .= '<h6 style="color: #047857; font-weight: 600; font-size: 0.85rem;">Key Terms & Definitions:</h6>';
        $readingsHtml .= '<ul style="color: #374151; padding-left: 20px; margin-bottom: 0;">';
        foreach ($w['key_terms'] as $t) {
            $readingsHtml .= '<li style="margin-bottom: 4px;">' . htmlspecialchars($t) . '</li>';
        }
        $readingsHtml .= '</ul>';
    }
    $readingsHtml .= '</div>';
    sync_label_activity($courseid, $wnum, 'Required Readings', 'Required Readings', $readingsHtml);

    // C. Lecture & Video Lessons
    $videos = $processedVideos[$wnum] ?? [];
    $videoHtml = '<div class="card p-3 my-2" style="border: 1px solid #e0e7ff; background: #ffffff; border-radius: 12px;">';
    $videoHtml .= '<div class="d-flex align-items-center mb-3"><span style="background: #4f46e5; color: white; padding: 4px 10px; border-radius: 6px; font-weight: 600; margin-right: 10px;">WEEK ' . sprintf('%02d', $wnum) . '</span><h5 class="m-0" style="font-weight: 700;">Lecture & Video Lessons</h5></div>';

    if (!empty($videos)) {
        if (count($videos) === 1) {
            $v = $videos[0];
            $videoHtml .= '<div class="d-flex align-items-center mb-2">';
            $videoHtml .= '<i class="fa fa-play-circle text-primary mr-2 me-2" style="font-size: 1.1rem;"></i>';
            $videoHtml .= '<strong style="color: #334155; font-size: 0.95rem;">' . htmlspecialchars($v['filename']) . '</strong>';
            $videoHtml .= '</div>';
            $videoHtml .= $v['embed_code'];
        } else {
            $videoHtml .= '<div class="mb-3 p-2 px-3" style="background: #eef2ff; border-left: 4px solid #4f46e5; border-radius: 6px;">';
            $videoHtml .= '<span style="font-size: 0.88rem; color: #3730a3; font-weight: 600;"><i class="fa fa-list-ol mr-2 me-2"></i> This week includes ' . count($videos) . ' lecture parts. All parts are playable directly below:</span>';
            $videoHtml .= '</div>';

            foreach ($videos as $idx => $v) {
                $partNum = $idx + 1;
                $cleanFilename = htmlspecialchars($v['filename']);
                // Part 1 is open by default; subsequent parts are collapsed until clicked
                $isOpen = ($idx === 0) ? 'open' : '';
                $bgHeader = ($idx === 0) ? '#eef2ff' : '#f8fafc';
                $badgeBg = ($idx === 0) ? '#4f46e5' : '#64748b';

                $videoHtml .= '<details class="video-accordion mb-3" ' . $isOpen . ' style="border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">';
                $videoHtml .= '<summary style="padding: 12px 16px; background: ' . $bgHeader . '; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 600; list-style: none; user-select: none;">';
                $videoHtml .= '<div class="d-flex align-items-center">';
                $videoHtml .= '<span style="background: ' . $badgeBg . '; color: #ffffff; padding: 3px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; margin-right: 10px;">PART ' . $partNum . '</span>';
                $videoHtml .= '<strong style="color: #1e293b; font-size: 0.92rem;"><i class="fa fa-play-circle text-primary mr-1 me-1"></i> ' . $cleanFilename . '</strong>';
                $videoHtml .= '</div>';
                if ($idx === 0) {
                    $videoHtml .= '<span class="badge bg-light text-primary border" style="font-size: 0.75rem;">Default Open ▾</span>';
                } else {
                    $videoHtml .= '<span class="badge bg-white text-secondary border" style="font-size: 0.75rem;"><i class="fa fa-play text-success mr-1"></i> Click to Watch ▾</span>';
                }
                $videoHtml .= '</summary>';
                $videoHtml .= '<div style="padding: 14px; background: #ffffff; border-top: 1px solid #e2e8f0;">';
                $videoHtml .= $v['embed_code'];
                $videoHtml .= '</div>';
                $videoHtml .= '</details>';
            }
        }
    } else {
        $videoHtml .= '<p class="text-muted mb-0"><i class="fa fa-info-circle mr-1"></i> No video recordings currently for this week.</p>';
    }
    $videoHtml .= '</div>';
    sync_label_activity($courseid, $wnum, 'Lecture & Video Lessons', 'Lecture / Lesson', $videoHtml);

    // D. Discussion Forum
    if (!empty($w['discussion'])) {
        $discHtml = '<div class="card p-3 my-2" style="border-left: 4px solid #f59e0b; border-radius: 8px; background: #fffdf5;">';
        $discHtml .= '<h5 style="color: #92400e; font-weight: 700; margin-bottom: 8px;"><i class="fa fa-comments mr-1"></i> Weekly Discussion</h5>';
        $discHtml .= '<div style="background: #fef3c7; padding: 12px; border-radius: 6px;"><p style="margin: 0; color: #78350f;">' . htmlspecialchars($w['discussion']) . '</p></div>';
        $discHtml .= '</div>';
        sync_label_activity($courseid, $wnum, 'Weekly Discussion', 'Discussion', $discHtml);
    }

    // E. Assignment
    if (!empty($w['assignment'])) {
        $assignHtml = '<div class="card p-3 my-2" style="border-left: 4px solid #8b5cf6; border-radius: 8px; background: #faf5ff;">';
        $assignHtml .= '<h5 style="color: #5b21b6; font-weight: 700; margin-bottom: 8px;"><i class="fa fa-tasks mr-1"></i> Weekly Assignment</h5>';
        $assignHtml .= '<div style="background: #ede9fe; padding: 12px; border-radius: 6px;"><p style="margin: 0; color: #4c1d95;">' . htmlspecialchars($w['assignment']) . '</p></div>';
        $assignHtml .= '</div>';
        sync_label_activity($courseid, $wnum, 'Weekly Assignment', 'Learning Activity / Assignment', $assignHtml);
    }

    // F. NATIVE MOODLE QUIZ FOR THIS WEEK
    if (!empty($w['mcqs'])) {
        $quizName = match ($wnum) {
            8 => 'Week 08: Midterm Examination',
            16 => 'Week 16: Final Comprehensive Examination & Capstone Assessment',
            default => sprintf("Week %02d Quiz: %s Knowledge Check", $wnum, $title)
        };

        $timeLimit = match ($wnum) {
            8, 16 => 7200, // 2 hours
            default => 1800 // 30 mins
        };

        $maxQuestions = match ($wnum) {
            8, 16 => 20,
            default => 10
        };

        // Check existing quiz
        $existingQuiz = null;
        $modinfo_refresh = get_fast_modinfo($courseid);
        if (isset($modinfo_refresh->sections[$wnum])) {
            foreach ($modinfo_refresh->sections[$wnum] as $chk_cmid) {
                $chk_cm = $modinfo_refresh->cms[$chk_cmid];
                if ($chk_cm->modname === 'quiz' && str_contains($chk_cm->name, "Week " . sprintf('%02d', $wnum))) {
                    $existingQuiz = $DB->get_record('quiz', ['id' => $chk_cm->instance]);
                    $existingQuiz->cmid = $chk_cmid;
                    break;
                }
            }
        }

        if (!$existingQuiz) {
            // Pre-create course module
            $cm = new stdClass();
            $cm->course = $courseid;
            $cm->module = $quizmodule->id;
            $cm->instance = 0;
            $cm->section = 0;
            $cm->idnumber = '';
            $cm->added = time();
            $cm->visible = 1;
            $cm->visibleoncoursepage = 1;
            $cm->visibleold = 1;
            $cm->groupmode = 0;
            $cm->groupingid = 0;
            $cm->completion = 0;

            $new_cmid = $DB->insert_record('course_modules', $cm);
            course_add_cm_to_section($courseid, $new_cmid, $wnum, null, 'quiz');

            // Insert quiz record
            $quiz = new stdClass();
            $quiz->course = $courseid;
            $quiz->name = $quizName;
            $quiz->intro = '<p>Complete this interactive assessment to test your mastery of Week ' . sprintf('%02d', $wnum) . ' (' . htmlspecialchars($title) . ') concepts.</p>';
            $quiz->introformat = FORMAT_HTML;
            $quiz->timeopen = 0;
            $quiz->timeclose = 0;
            $quiz->timelimit = $timeLimit;
            $quiz->overduehandling = 'autoabandon';
            $quiz->graceperiod = 0;
            $quiz->preferredbehaviour = 'deferredfeedback';
            $quiz->canredoquestions = 0;
            $quiz->attempts = 0;
            $quiz->attemptonlast = 0;
            $quiz->grademethod = 1;
            $quiz->decimalpoints = 2;
            $quiz->questiondecimalpoints = -1;
            $quiz->questionsperpage = 1;
            $quiz->navmethod = 'free';
            $quiz->shuffleanswers = 1;
            $quiz->sumgrades = floatval($maxQuestions);
            $quiz->grade = 100.0;
            $quiz->timecreated = time();
            $quiz->timemodified = time();
            $quiz->password = '';
            $quiz->subnet = '';
            $quiz->browsersecurity = '';
            $quiz->delay1 = 0;
            $quiz->delay2 = 0;
            $quiz->showuserpicture = 0;
            $quiz->showblocks = 0;
            $quiz->completionattemptsexhausted = 0;
            $quiz->completionminattempts = 0;
            $quiz->allowofflineattempts = 0;

            $quiz->reviewattempt          = 69888;
            $quiz->reviewcorrectness      = 4352;
            $quiz->reviewmaxmarks         = 4352;
            $quiz->reviewmarks            = 4352;
            $quiz->reviewspecificfeedback = 4352;
            $quiz->reviewgeneralfeedback  = 4352;
            $quiz->reviewrightanswer      = 4352;
            $quiz->reviewoverallfeedback  = 4352;

            $quizid = $DB->insert_record('quiz', $quiz);
            $quiz->id = $quizid;
            $quiz->cmid = $new_cmid;
            $quiz->coursemodule = $new_cmid;

            $DB->set_field('course_modules', 'instance', $quizid, ['id' => $new_cmid]);

            $DB->insert_record('quiz_sections', [
                'quizid' => $quizid,
                'firstslot' => 1,
                'heading' => '',
                'shufflequestions' => 0
            ]);

            $quizcm = get_coursemodule_from_id('quiz', $new_cmid, $courseid, true, MUST_EXIST);
            $quizcontext = context_module::instance($quizcm->id);

            // Category
            $cat = new stdClass();
            $cat->name = 'Default for ' . $quizName;
            $cat->info = 'Default question category for ' . $quizName;
            $cat->infoformat = FORMAT_HTML;
            $cat->contextid = $quizcontext->id;
            $cat->parent = 0;
            $cat->sortorder = 999;
            $cat->stamp = make_unique_id_code();
            $catid = $DB->insert_record('question_categories', $cat);

            // Add Questions
            $slotNum = 1;
            $totalMarks = 0;

            foreach (array_slice($w['mcqs'], 0, $maxQuestions) as $qIdx => $mcq) {
                // Question Bank Entry
                $entry = new stdClass();
                $entry->questioncategoryid = $catid;
                $entry->ownerid = 2;
                $entryid = $DB->insert_record('question_bank_entries', $entry);

                // Question Record
                $q = new stdClass();
                $q->category = $catid;
                $q->parent = 0;
                $q->name = sprintf("W%02d Q%02d: %s", $wnum, ($qIdx + 1), mb_substr($mcq['question'], 0, 45, 'UTF-8'));
                $q->questiontext = '<p>' . htmlspecialchars($mcq['question']) . '</p>';
                $q->questiontextformat = FORMAT_HTML;
                $q->generalfeedback = '<p>' . htmlspecialchars($mcq['explanation'] ?? '') . '</p>';
                $q->generalfeedbackformat = FORMAT_HTML;
                $q->defaultmark = 1.0;
                $q->penalty = 0.3333333;
                $q->qtype = 'multichoice';
                $q->length = 1;
                $q->stamp = make_unique_id_code();
                $q->version = 1;
                $q->hidden = 0;
                $q->timecreated = time();
                $q->timemodified = time();
                $q->createdby = 2;
                $q->modifiedby = 2;
                $qid = $DB->insert_record('question', $q);

                // Version
                $qv = new stdClass();
                $qv->questionbankentryid = $entryid;
                $qv->version = 1;
                $qv->questionid = $qid;
                $qv->status = 'ready';
                $DB->insert_record('question_versions', $qv);

                // Answers
                $rawAns = trim($mcq['answer']);
                $correctLetter = '';
                if (preg_match('/^([A-D])\b/i', $rawAns, $am)) {
                    $correctLetter = strtoupper($am[1]);
                }

                foreach ($mcq['options'] as $optStr) {
                    $optClean = trim($optStr);
                    $optLetter = '';
                    if (preg_match('/^([A-D])\)/i', $optClean, $om)) {
                        $optLetter = strtoupper($om[1]);
                    }
                    $optTextOnly = preg_replace('/^[A-D]\)\s*/i', '', $optClean);

                    $isCorrect = false;
                    if ($correctLetter && $optLetter === $correctLetter) {
                        $isCorrect = true;
                    } elseif (stripos($rawAns, $optTextOnly) !== false || stripos($optTextOnly, $rawAns) !== false) {
                        $isCorrect = true;
                    }

                    $ans = new stdClass();
                    $ans->question = $qid;
                    $ans->answer = $optClean;
                    $ans->answerformat = FORMAT_HTML;
                    $ans->fraction = $isCorrect ? 1.0 : 0.0;
                    $ans->feedback = $isCorrect ? 'Correct!' : 'Incorrect.';
                    $ans->feedbackformat = FORMAT_HTML;
                    $DB->insert_record('question_answers', $ans);
                }

                // Multichoice options
                $mc = new stdClass();
                $mc->questionid = $qid;
                $mc->layout = 0;
                $mc->single = 1;
                $mc->shuffleanswers = 1;
                $mc->correctfeedback = '<p>Your answer is correct.</p>';
                $mc->correctfeedbackformat = FORMAT_HTML;
                $mc->partiallycorrectfeedback = '<p>Your answer is partially correct.</p>';
                $mc->partiallycorrectfeedbackformat = FORMAT_HTML;
                $mc->incorrectfeedback = '<p>Your answer is incorrect.</p>';
                $mc->incorrectfeedbackformat = FORMAT_HTML;
                $mc->answernumbering = 'abc';
                $mc->shownumcorrect = 0;
                $mc->showstandardinstruction = 1;
                $DB->insert_record('qtype_multichoice_options', $mc);

                // Slot
                $slot = new stdClass();
                $slot->slot = $slotNum;
                $slot->quizid = $quizid;
                $slot->page = $slotNum;
                $slot->requireprevious = 0;
                $slot->maxmark = 1.0;
                $slotid = $DB->insert_record('quiz_slots', $slot);

                // Reference
                $qr = new stdClass();
                $qr->usingcontextid = $quizcontext->id;
                $qr->component = 'mod_quiz';
                $qr->questionarea = 'slot';
                $qr->itemid = $slotid;
                $qr->questionbankentryid = $entryid;
                $qr->version = null;
                $DB->insert_record('question_references', $qr);

                $slotNum++;
                $totalMarks += 1.0;
            }

            $DB->set_field('quiz', 'sumgrades', $totalMarks, ['id' => $quizid]);
            quiz_update_grades($quiz);
            echo "  ✔ Created native Quiz: '{$quizName}' with {$totalMarks} questions in Section {$wnum}\n";
        }
    }
}

// ---------------------------------------------------------
// 4. FINALIZE & REBUILD CACHES
// ---------------------------------------------------------
rebuild_course_cache($courseid, true);
purge_all_caches();

echo "✔ Course ID {$courseid} successfully provisioned and synchronized with native Moodle structure!\n";
