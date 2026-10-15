<?php
define('CLI_SCRIPT', true);
require('/home/milton/Projects/elearn/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/url/lib.php');
require_once($CFG->libdir . '/questionlib.php');

global $DB, $CFG, $USER;
$USER = get_admin();

$payloadFile = '/tmp/moodle_sync_payload.json';
if (!file_exists($payloadFile)) {
    die("Error: Payload file not found.\n");
}

$payload = json_decode(file_get_contents($payloadFile), true);
$requestedCourseId = $payload['course_id'] ?? null;
$fullname = trim($payload['fullname'] ?? '');
$shortname = trim($payload['shortname'] ?? '');
$startDateStr = $payload['start_date'] ?? null;
$categoryId = (int) ($payload['category_id'] ?? 2);
$curriculum = $payload['curriculum'] ?? [];
$processedVideos = $payload['processed_videos'] ?? [];
$generalDocs = $payload['general_docs'] ?? [];

echo "====================================================\n";
echo "Starting Moodle Course Synchronization / Auto-Provision\n";
echo "Target Course Name: {$fullname}\n";
echo "Target Course Code: {$shortname}\n";
echo "Requested Course ID: " . ($requestedCourseId ?: 'None (Auto-discover/create)') . "\n";
echo "Semester Start Date: " . ($startDateStr ?: 'Auto (Current date)') . "\n";
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

// ---------------------------------------------------------
// SEMESTER START & END TIMESTAMPS
// ---------------------------------------------------------
if (!empty($startDateStr)) {
    $semesterStart = strtotime(date('Y-m-d 00:00:00', strtotime($startDateStr)));
} elseif (!empty($course->startdate) && $course->startdate > 0) {
    $semesterStart = $course->startdate;
} else {
    $semesterStart = strtotime(date('Y-m-d 00:00:00'));
}
$semesterEnd = strtotime('+16 weeks', $semesterStart);

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
    $cData->startdate = $semesterStart;
    $cData->enddate = $semesterEnd;
    $cData->visible = 1;
    $cData->timecreated = time();
    $cData->timemodified = time();

    $course = create_course($cData);
    echo "✔ Successfully created new Moodle course with ID: {$course->id} ('{$course->fullname}')\n";
} else {
    // Update existing course details to ensure name and semester dates consistency
    $course->fullname = $fullname;
    $course->shortname = $shortname;
    $course->startdate = $semesterStart;
    $course->enddate = $semesterEnd;
    $course->visible = 1;
    $DB->update_record('course', $course);
    echo "✔ Course details & semester dates updated: ID {$course->id} ('{$course->fullname}')\n";
}

$courseid = (int) $course->id;
echo "FINAL_MOODLE_COURSE_ID:{$courseid}\n";
echo "✔ Semester Duration: " . date('M d, Y', $semesterStart) . " to " . date('M d, Y', $semesterEnd) . " (16 Weeks)\n";

// ---------------------------------------------------------
// HELPER FUNCTIONS FOR REAL MOODLE ACTIVITIES
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

/**
 * Remove obsolete static label placeholder if real activity is present
 */
function remove_obsolete_label($courseid, $sectionnum, $labelSearch) {
    global $DB;
    $modinfo = get_fast_modinfo($courseid);
    if (isset($modinfo->sections[$sectionnum])) {
        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname === 'label' && (str_contains($cm->name, $labelSearch) || str_contains($cm->text ?? '', $labelSearch))) {
                course_delete_module($cmid);
            }
        }
    }
}

/**
 * Synchronize Real Moodle Assignment (mod_assign) with Semester Dates
 * Students can submit files (PDF, Word) or online text
 * Teachers can grade and provide feedback
 */
function sync_assignment_activity($courseid, $sectionnum, $assignTitle, $assignmentData, $weekDates = []) {
    global $DB;
    $assignmodule = $DB->get_record('modules', ['name' => 'assign']);
    $modinfo = get_fast_modinfo($courseid);

    $taskText = is_array($assignmentData) ? ($assignmentData['task'] ?? '') : $assignmentData;
    $blooms = is_array($assignmentData) ? ($assignmentData['blooms_level'] ?? 'Analyze, Apply, Evaluate') : 'Analyze, Apply, Evaluate';
    $rubric = is_array($assignmentData) ? ($assignmentData['rubric_raw'] ?? '') : '';

    $openFormatted = !empty($weekDates['start']) ? date('M d, Y h:i A', $weekDates['start']) : 'Semester Start';
    $dueFormatted = !empty($weekDates['due']) ? date('M d, Y h:i A', $weekDates['due']) : 'End of Week';

    $introHtml = '<div style="font-family: inherit; line-height: 1.6; color: #1e293b;">';
    $introHtml .= '<div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 16px 20px; border-radius: 8px; margin-bottom: 16px;">';
    $introHtml .= '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">';
    $introHtml .= '<h4 style="color: #166534; margin: 0; font-weight: 700;"><i class="fa fa-file-text-o mr-2"></i> Assignment Instructions & Task Guidelines</h4>';
    if (!empty($weekDates['due'])) {
        $introHtml .= '<span style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 4px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600;"><i class="fa fa-clock-o mr-1"></i> Due Date: ' . $dueFormatted . '</span>';
    }
    $introHtml .= '</div>';
    $introHtml .= '<p style="margin: 0; font-size: 1rem; color: #1e293b; line-height: 1.6;">' . nl2br(htmlspecialchars($taskText)) . '</p>';
    $introHtml .= '<div style="margin-top: 12px; font-size: 0.88rem; color: #15803d;"><strong>Bloom’s Taxonomy Level:</strong> ' . htmlspecialchars($blooms) . '</div>';
    if (!empty($weekDates['start'])) {
        $introHtml .= '<div style="margin-top: 6px; font-size: 0.82rem; color: #166534;"><strong>Submissions Open:</strong> ' . $openFormatted . '</div>';
    }
    $introHtml .= '</div>';

    if (!empty($rubric)) {
        $introHtml .= '<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px 18px; border-radius: 8px; margin-bottom: 16px;">';
        $introHtml .= '<h5 style="color: #334155; margin: 0 0 8px 0; font-weight: 700;"><i class="fa fa-table mr-2"></i> Evaluation Criteria & Grading Rubric</h5>';
        $introHtml .= '<pre style="white-space: pre-wrap; font-family: inherit; font-size: 0.88rem; color: #475569; background: #ffffff; padding: 12px; border-radius: 6px; border: 1px solid #cbd5e1; margin: 0;">' . htmlspecialchars($rubric) . '</pre>';
        $introHtml .= '</div>';
    }

    $introHtml .= '<div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 8px;">';
    $introHtml .= '<p style="margin: 0; font-size: 0.9rem; color: #1e40af;"><strong>Submission Options:</strong> Students can submit their assignment as a <strong>PDF or Word document (.pdf, .doc, .docx)</strong> or type their answer directly in the online text box. Teachers can grade and provide feedback comments in the gradebook.</p>';
    $introHtml .= '</div>';
    $introHtml .= '</div>';

    // 1. Check existing assignment in section
    if (isset($modinfo->sections[$sectionnum])) {
        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname === 'assign') {
                $assignRecord = $DB->get_record('assign', ['id' => $cm->instance]);
                if ($assignRecord) {
                    $assignRecord->name = $assignTitle;
                    $assignRecord->intro = $introHtml;
                    if (!empty($weekDates['start'])) {
                        $assignRecord->allowsubmissionsfromdate = $weekDates['start'];
                        $assignRecord->duedate = $weekDates['due'];
                        $assignRecord->cutoffdate = $weekDates['cutoff'];
                        $assignRecord->gradingduedate = $weekDates['gradingdue'];
                    }
                    $assignRecord->timemodified = time();
                    $DB->update_record('assign', $assignRecord);
                    return $cmid;
                }
            }
        }
    }

    // 2. Create new assignment course module
    $cm = new stdClass();
    $cm->course = $courseid;
    $cm->module = $assignmodule->id;
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
    $cmid = $DB->insert_record('course_modules', $cm);

    $assignData = new stdClass();
    $assignData->course = $courseid;
    $assignData->coursemodule = $cmid;
    $assignData->name = $assignTitle;
    $assignData->intro = $introHtml;
    $assignData->introformat = FORMAT_HTML;
    $assignData->alwaysshowdescription = 1;
    $assignData->submissiondrafts = 0;
    $assignData->requiresubmissionstatement = 0;
    $assignData->sendnotifications = 0;
    $assignData->sendlatenotifications = 0;
    $assignData->sendstudentnotifications = 1;

    // Dates
    $assignData->allowsubmissionsfromdate = !empty($weekDates['start']) ? $weekDates['start'] : time();
    $assignData->duedate = !empty($weekDates['due']) ? $weekDates['due'] : 0;
    $assignData->cutoffdate = !empty($weekDates['cutoff']) ? $weekDates['cutoff'] : 0;
    $assignData->gradingduedate = !empty($weekDates['gradingdue']) ? $weekDates['gradingdue'] : 0;

    $assignData->grade = 100;
    $assignData->teamsubmission = 0;
    $assignData->requireallteammemberssubmit = 0;
    $assignData->blindmarking = 0;
    $assignData->markingworkflow = 0;
    $assignData->markingallocation = 0;

    // Submission plugins: Online text + PDF / Word file submission
    $assignData->assignsubmission_onlinetext_enabled = 1;
    $assignData->assignsubmission_file_enabled = 1;
    $assignData->assignsubmission_file_maxfiles = 2;
    $assignData->assignsubmission_file_maxsizebytes = 0;
    $assignData->assignsubmission_file_filetypes = '.pdf,.doc,.docx';

    // Feedback plugins: Comments + EditPDF annotation
    $assignData->assignfeedback_comments_enabled = 1;
    $assignData->assignfeedback_editpdf_enabled = 1;

    $assignId = assign_add_instance($assignData);
    $DB->set_field('course_modules', 'instance', $assignId, ['id' => $cmid]);
    course_add_cm_to_section($courseid, $cmid, $sectionnum);

    return $cmid;
}

/**
 * Synchronize Real Moodle Discussion Forum (mod_forum)
 * Students and instructors can participate and reply
 */
function sync_forum_activity($courseid, $sectionnum, $forumTitle, $discussionData, $weekDates = []) {
    global $DB, $USER;
    if (empty($USER->id)) {
        $USER = get_admin();
    }
    $forummodule = $DB->get_record('modules', ['name' => 'forum']);
    $modinfo = get_fast_modinfo($courseid);

    $promptText = is_array($discussionData) ? ($discussionData['prompt'] ?? '') : $discussionData;

    $introHtml = '<div style="font-family: inherit; line-height: 1.6; color: #1e293b;">';
    $introHtml .= '<div style="background: #fffdf5; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 8px; margin-bottom: 14px;">';
    $introHtml .= '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">';
    $introHtml .= '<h4 style="color: #92400e; margin: 0; font-weight: 700;"><i class="fa fa-comments mr-2"></i> Weekly Discussion Topic & Discussion Prompt</h4>';
    if (!empty($weekDates['date_str'])) {
        $introHtml .= '<span style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; padding: 4px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600;"><i class="fa fa-calendar mr-1"></i> ' . $weekDates['date_str'] . '</span>';
    }
    $introHtml .= '</div>';
    $introHtml .= '<p style="margin: 0; font-size: 1rem; color: #78350f; line-height: 1.6;">' . nl2br(htmlspecialchars($promptText)) . '</p>';
    $introHtml .= '</div>';
    $introHtml .= '<p style="color: #64748b; font-size: 0.9rem; margin: 0;"><i class="fa fa-info-circle mr-1"></i> Post your response and engage in academic debate with your fellow classmates below.</p>';
    $introHtml .= '</div>';

    // 1. Check existing forum in section
    if (isset($modinfo->sections[$sectionnum])) {
        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname === 'forum') {
                $forumRecord = $DB->get_record('forum', ['id' => $cm->instance]);
                if ($forumRecord) {
                    $forumRecord->name = $forumTitle;
                    $forumRecord->intro = $introHtml;
                    $forumRecord->timemodified = time();
                    $DB->update_record('forum', $forumRecord);
                    return $cmid;
                }
            }
        }
    }

    // 2. Create new forum course module
    $fcm = new stdClass();
    $fcm->course = $courseid;
    $fcm->module = $forummodule->id;
    $fcm->instance = 0;
    $fcm->section = 0;
    $fcm->idnumber = '';
    $fcm->added = time();
    $fcm->visible = 1;
    $fcm->visibleoncoursepage = 1;
    $fcm->visibleold = 1;
    $fcm->groupmode = 0;
    $fcm->groupingid = 0;
    $fcm->completion = 0;
    $fcmid = $DB->insert_record('course_modules', $fcm);

    $forumData = new stdClass();
    $forumData->course = $courseid;
    $forumData->coursemodule = $fcmid;
    $forumData->name = $forumTitle;
    $forumData->intro = $introHtml;
    $forumData->introformat = FORMAT_HTML;
    $forumData->type = 'single'; // single simple discussion allows direct inline replies
    $forumData->assessed = 0;
    $forumData->maxbytes = 0;
    $forumData->maxattachments = 1;
    $forumData->forcesubscribe = 0;
    $forumData->trackingtype = 1;

    $forumId = forum_add_instance($forumData);
    $DB->set_field('course_modules', 'instance', $forumId, ['id' => $fcmid]);
    course_add_cm_to_section($courseid, $fcmid, $sectionnum);

    // Guarantee all discussions & posts in this forum have a valid author (admin user)
    $discussions = $DB->get_records('forum_discussions', ['forum' => $forumId]);
    foreach ($discussions as $disc) {
        if (empty($disc->userid)) {
            $disc->userid = $USER->id;
            $disc->usermodified = $USER->id;
            $DB->update_record('forum_discussions', $disc);
        }
        if (!empty($disc->firstpost)) {
            $fpost = $DB->get_record('forum_posts', ['id' => $disc->firstpost]);
            if ($fpost && empty($fpost->userid)) {
                $fpost->userid = $USER->id;
                $DB->update_record('forum_posts', $fpost);
            }
        }
    }

    return $fcmid;
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
// 3. SYNCHRONIZE SECTIONS 1 TO 16 (WITH CALCULATED DATES & DEADLINES)
// ---------------------------------------------------------
$quizmodule = $DB->get_record('modules', ['name' => 'quiz']);

for ($wnum = 1; $wnum <= 16; $wnum++) {
    if (!isset($curriculum[$wnum]) && !isset($curriculum[strval($wnum)])) {
        continue;
    }

    $w = $curriculum[$wnum] ?? $curriculum[strval($wnum)];
    $title = $w['title'] ?? "Week {$wnum}";

    // Calculate Week Schedule based on Semester Start Date
    $weekStartTime = strtotime("+".($wnum - 1)." weeks", $semesterStart);
    $weekDueTime = strtotime("+".$wnum." weeks - 1 second", $semesterStart);
    $weekCutoffTime = $weekDueTime + (3 * 86400); // 3 days grace period
    $weekGradingDueTime = $weekDueTime + (7 * 86400); // 7 days after due date for teacher grading
    $weekDateRangeStr = date('M d', $weekStartTime) . ' – ' . date('M d, Y', $weekDueTime);

    $weekDates = [
        'start' => $weekStartTime,
        'due' => $weekDueTime,
        'cutoff' => $weekCutoffTime,
        'gradingdue' => $weekGradingDueTime,
        'date_str' => $weekDateRangeStr
    ];

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

    // A. 100% Verbatim Overview Card (Handbook Overview + Schedule Badge + Objectives)
    $overviewHtml = '<div class="card p-3 my-2" style="border-left: 4px solid #3b82f6; border-radius: 8px; background: #f8fafc;">';
    $overviewHtml .= '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">';
    $overviewHtml .= '<h5 style="color: #1e3a8a; font-weight: 700; margin: 0;">Week ' . sprintf('%02d', $wnum) . ' Overview: ' . htmlspecialchars($title) . '</h5>';
    $overviewHtml .= '<span class="badge bg-primary" style="font-size: 0.8rem; padding: 5px 10px; border-radius: 6px;"><i class="fa fa-calendar mr-1"></i> ' . $weekDateRangeStr . '</span>';
    $overviewHtml .= '</div>';
    $overviewHtml .= '<p style="color: #334155; line-height: 1.6; margin-bottom: 14px;">' . nl2br(htmlspecialchars($w['overview'] ?? '')) . '</p>';
    if (!empty($w['objectives'])) {
        $overviewHtml .= '<h6 style="color: #1e40af; font-weight: 600; text-transform: uppercase; font-size: 0.82rem; letter-spacing: 0.5px; margin-bottom: 8px;">Student Learning Objectives:</h6>';
        $overviewHtml .= '<ul style="color: #475569; padding-left: 20px; margin-bottom: 0;">';
        foreach ($w['objectives'] as $obj) {
            $overviewHtml .= '<li style="margin-bottom: 6px; line-height: 1.5;">' . htmlspecialchars($obj) . '</li>';
        }
        $overviewHtml .= '</ul>';
    }
    $overviewHtml .= '</div>';
    sync_label_activity($courseid, $wnum, 'Overview', 'Overview', $overviewHtml);

    // B. 100% Verbatim Required Readings & Key Terms Card
    $readingsHtml = '<div class="card p-3 my-2" style="border-left: 4px solid #10b981; border-radius: 8px; background: #f0fdf4;">';
    $readingsHtml .= '<h5 style="color: #065f46; font-weight: 700; margin-bottom: 10px;"><i class="fa fa-book mr-1"></i> Required Readings & Core Concepts</h5>';
    $readingsHtml .= '<p style="margin-bottom: 10px;"><strong>Student Handbook:</strong> Chapter ' . $wnum . ' — <em>' . htmlspecialchars($title) . '</em></p>';
    if (!empty($w['key_terms'])) {
        $readingsHtml .= '<h6 style="color: #047857; font-weight: 600; font-size: 0.85rem; margin-bottom: 8px;">Key Terms & Definitions:</h6>';
        $readingsHtml .= '<ul style="color: #374151; padding-left: 20px; margin-bottom: 0;">';
        foreach ($w['key_terms'] as $t) {
            $readingsHtml .= '<li style="margin-bottom: 6px; line-height: 1.5;">' . htmlspecialchars($t) . '</li>';
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

    // Remove any old placeholder labels for Discussion and Assignment
    remove_obsolete_label($courseid, $wnum, 'Weekly Discussion');
    remove_obsolete_label($courseid, $wnum, 'Weekly Assignment');

    // D. REAL MOODLE DISCUSSION FORUM (mod_forum)
    if (!empty($w['discussion'])) {
        $forumTitle = sprintf("Week %02d Discussion Forum: %s", $wnum, $title);
        $forumCmid = sync_forum_activity($courseid, $wnum, $forumTitle, $w['discussion'], $weekDates);
        echo "  ✔ Week {$wnum} Discussion Forum synced (CMID: {$forumCmid})\n";
    }

    // E. REAL MOODLE ASSIGNMENT (mod_assign) WITH SEMESTER DUE DATES
    if (!empty($w['assignment'])) {
        $assignTitle = sprintf("Week %02d Assignment: %s", $wnum, $title);
        $assignCmid = sync_assignment_activity($courseid, $wnum, $assignTitle, $w['assignment'], $weekDates);
        echo "  ✔ Week {$wnum} Assignment Activity synced (CMID: {$assignCmid}, Due: {$weekDates['date_str']})\n";
    }

    // F. NATIVE MOODLE QUIZ FOR THIS WEEK WITH OPEN/CLOSE TIMESTAMPS
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

            // Insert quiz record with Semester Schedule
            $quiz = new stdClass();
            $quiz->course = $courseid;
            $quiz->name = $quizName;
            $quiz->intro = '<p>Complete this interactive assessment to test your mastery of Week ' . sprintf('%02d', $wnum) . ' (' . htmlspecialchars($title) . ') concepts.</p>'
                . '<p><strong>Quiz Period:</strong> ' . $weekDateRangeStr . '</p>';
            $quiz->introformat = FORMAT_HTML;
            $quiz->timeopen = $weekDates['start'];
            $quiz->timeclose = $weekDates['due'];
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
                $q->name = sprintf("W%02d_Q%02d: %s", $wnum, $qIdx + 1, substr($mcq['question'], 0, 45));
                $q->questiontext = '<p>' . htmlspecialchars($mcq['question']) . '</p>';
                $q->questiontextformat = FORMAT_HTML;
                $q->generalfeedback = !empty($mcq['explanation']) ? '<p>' . htmlspecialchars($mcq['explanation']) . '</p>' : '';
                $q->generalfeedbackformat = FORMAT_HTML;
                $q->defaultmark = 1.0;
                $q->penalty = 0.3333333;
                $q->qtype = 'multichoice';
                $q->length = 1;
                $q->stamp = make_unique_id_code();
                $q->version = make_unique_id_code();
                $q->hidden = 0;
                $q->timecreated = time();
                $q->timemodified = time();
                $q->createdby = 2;
                $q->modifiedby = 2;
                $qid = $DB->insert_record('question', $q);

                // Question Version
                $qv = new stdClass();
                $qv->questionbankentryid = $entryid;
                $qv->version = 1;
                $qv->questionid = $qid;
                $qv->status = 'ready';
                $DB->insert_record('question_versions', $qv);

                // Answers
                $rawAns = trim($mcq['answer'] ?? '');
                $correctLetter = strtoupper($rawAns);

                $optionsArray = $mcq['options'] ?? [];
                foreach ($optionsArray as $optKey => $optVal) {
                    $optLetter = strtoupper($optKey);
                    $optClean = "{$optLetter}) {$optVal}";
                    $isCorrect = ($optLetter === $correctLetter);

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

                $totalMarks += 1.0;
                $slotNum++;
            }

            // Update quiz sumgrades
            $quiz->sumgrades = $totalMarks;
            $DB->update_record('quiz', $quiz);

            // Register quiz in gradebook
            quiz_grade_item_update($quiz);

            echo "  ✔ Week {$wnum} Native Quiz created with {$totalMarks} questions: '{$quizName}'\n";
        } else {
            // Update quiz open/close dates on existing quiz
            $existingQuiz->timeopen = $weekDates['start'];
            $existingQuiz->timeclose = $weekDates['due'];
            $existingQuiz->timemodified = time();
            $DB->update_record('quiz', $existingQuiz);
            echo "  ✔ Week {$wnum} Native Quiz schedule updated: '{$existingQuiz->name}' (Open: " . date('M d', $weekDates['start']) . ", Close: " . date('M d', $weekDates['due']) . ")\n";
        }
    }
}

// Rebuild Course Cache so newly added modules display immediately
rebuild_course_cache($courseid, true);
echo "\n====================================================\n";
echo "✔ Moodle Course Provisioning Complete!\n";
echo "Course ID: {$courseid}\n";
echo "URL: {$CFG->wwwroot}/course/view.php?id={$courseid}\n";
echo "====================================================\n";
