@use('App\Models\Setting')
@extends('layouts.app', ['title' => 'Multi-AI, Moodle & Storage Settings'])

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="mb-2">
                    <span class="badge bg-primary text-white px-3 py-2 rounded-pill fs-6 shadow-sm">
                        <i class="fa fa-building-columns me-2"></i> Workspace: {{ $university->name }}
                    </span>
                </div>
                <h3 class="fw-bold brand-font mb-1">Institutional Configurations & Multi-Engine Settings</h3>
                <p class="text-muted small mb-0">Configure Moodle LMS credentials, Multi-AI models, and video CDN storage for <strong>{{ $university->name }}</strong>.</p>
            </div>
        </div>

        @if(auth()->user()->isInstructor())
            <div class="alert alert-warning border-warning shadow-sm rounded-3 mb-4 d-flex align-items-center">
                <i class="fa fa-lock fs-3 text-warning me-3"></i>
                <div>
                    <h6 class="fw-bold mb-1">Read-Only Access for Instructors</h6>
                    <small>You are currently logged in with the <strong>Instructor</strong> role. You can inspect institutional Moodle connections and AI models, but modifying or saving credentials requires <strong>University Admin</strong> or <strong>Super Admin</strong> authorization.</small>
                </div>
            </div>
        @endif
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <form action="{{ route('settings.save') }}" method="POST">
            @csrf

            <!-- ================= CARD 1: MOODLE LMS CONFIGURATION ================= -->
            <div class="custom-card p-4 mb-4" style="border-top: 4px solid #f97316;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-warning text-dark mb-1">Target LMS Environment</span>
                        <h4 class="fw-bold brand-font text-dark mb-0">
                            <i class="fa fa-graduation-cap text-warning me-2"></i> 1. Moodle LMS Configuration
                        </h4>
                    </div>
                    <button type="button" class="btn btn-outline-warning text-dark fw-bold" id="btnTestMoodle" onclick="window.testMoodle(event)">
                        <i class="fa fa-plug me-1"></i> Test Moodle Connection
                    </button>
                </div>
                <p class="text-muted small">Where all courses, weeks, videos, and quizzes are provisioned and synchronized.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Moodle Base URL <span class="text-danger">*</span></label>
                        <input type="text" name="moodle_url" class="form-control" value="{{ $settings['moodle_url'] }}" placeholder="http://elearn.local or https://lms.yourdomain.com" required>
                        <small class="text-muted">Local development URL or live production Moodle domain.</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Master Template Course ID <span class="text-danger">*</span></label>
                        <input type="number" name="moodle_template_course_id" class="form-control" value="{{ $settings['moodle_template_course_id'] }}" required>
                        <small class="text-muted">The skeleton course cloned for new courses (Default: 129).</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Category ID</label>
                        <input type="number" name="moodle_category_id" class="form-control" value="{{ $settings['moodle_category_id'] }}">
                        <small class="text-muted">Target Moodle category (Default: 1).</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-primary"><i class="fa fa-calendar-alt me-1"></i> Default Semester Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="semester_start_date" class="form-control fw-bold border-primary" value="{{ $settings['semester_start_date'] ?? date('Y-m-d') }}" required>
                        <small class="text-muted">Baseline date for computing the 16-week timetable (weekly deadlines, assignments, midterm & final quiz periods).</small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold small">Moodle Web Services REST Token (For Remote API)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa fa-key text-secondary"></i></span>
                            <input type="password" name="moodle_token" class="form-control" value="{{ $settings['moodle_token'] }}" placeholder="e.g. 8a3f4b8219c08472...">
                        </div>
                        <small class="text-muted">
                            <i class="fa fa-info-circle text-primary"></i> <strong>How to generate token:</strong> In Moodle, go to <em>Site administration > Server > Web services > Manage tokens</em>. (Optional if running on the same local server).
                        </small>
                    </div>
                </div>
                <div id="moodleStatusAlert" class="mt-3 d-none"></div>
            </div>

            <!-- ================= CARD 2: MULTI-AI INTELLIGENCE ENGINE ================= -->
            <div class="custom-card p-4 mb-4" style="border-top: 4px solid #6366f1;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-indigo text-white mb-1" style="background: #4f46e5;">Curriculum Intelligence</span>
                        <h4 class="fw-bold brand-font text-dark mb-0">
                            <i class="fa fa-brain text-indigo me-2" style="color: #4f46e5;"></i> 2. AI Intelligence Provider (Multi-Model Support)
                        </h4>
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="btnTestAi" onclick="window.testAi(event)">
                        <i class="fa fa-robot me-1"></i> Test AI Connection
                    </button>
                </div>
                <p class="text-muted small">Select your preferred AI engine to extract syllabus text, learning objectives, discussion prompts, assignments, and quizzes from PDF and Word handbooks.</p>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Choose AI Provider</label>
                    <select name="ai_provider" id="aiProviderSelect" class="form-select form-select-lg fw-bold" style="color: #4f46e5; border-color: #c7d2fe;">
                        <option value="gemini" {{ $settings['ai_provider'] === 'gemini' ? 'selected' : '' }}>Google Gemini (Gemini 2.5 Flash / 1.5 Pro - Recommended & Ultra-Fast)</option>
                        <option value="openai" {{ $settings['ai_provider'] === 'openai' ? 'selected' : '' }}>OpenAI (ChatGPT GPT-4o / GPT-4o-mini)</option>
                        <option value="claude" {{ $settings['ai_provider'] === 'claude' ? 'selected' : '' }}>Anthropic Claude (Claude 3.5 Sonnet / Haiku)</option>
                        <option value="local" {{ $settings['ai_provider'] === 'local' ? 'selected' : '' }}>Built-in Smart Heuristic Parser (Offline / No API Key Required)</option>
                    </select>
                </div>

                <div id="aiStatusAlert" class="mb-3 d-none"></div>

                <!-- Provider 1: Gemini -->
                <div id="geminiBox" class="ai-box border rounded-3 p-3 bg-light mb-2 {{ $settings['ai_provider'] === 'gemini' ? '' : 'd-none' }}">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa fa-google text-danger me-1"></i> Google Gemini Configuration</h6>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Google Gemini API Key</label>
                            <input type="password" name="gemini_api_key" class="form-control" value="{{ $settings['gemini_api_key'] }}" placeholder="AIzaSy...">
                            <small class="text-muted">Obtain from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Model Version</label>
                            <input type="text" name="gemini_model" class="form-control" value="{{ $settings['gemini_model'] }}" placeholder="gemini-2.5-flash">
                        </div>
                    </div>
                </div>

                <!-- Provider 2: OpenAI -->
                <div id="openaiBox" class="ai-box border rounded-3 p-3 bg-light mb-2 {{ $settings['ai_provider'] === 'openai' ? '' : 'd-none' }}">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa fa-bolt text-success me-1"></i> OpenAI (ChatGPT) Configuration</h6>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">OpenAI API Key</label>
                            <input type="password" name="openai_api_key" class="form-control" value="{{ $settings['openai_api_key'] }}" placeholder="sk-proj-...">
                            <small class="text-muted">Obtain from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Model Version</label>
                            <input type="text" name="openai_model" class="form-control" value="{{ $settings['openai_model'] }}" placeholder="gpt-4o-mini">
                        </div>
                    </div>
                </div>

                <!-- Provider 3: Claude -->
                <div id="claudeBox" class="ai-box border rounded-3 p-3 bg-light mb-2 {{ $settings['ai_provider'] === 'claude' ? '' : 'd-none' }}">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa fa-circle text-warning me-1"></i> Anthropic Claude Configuration</h6>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Anthropic API Key</label>
                            <input type="password" name="claude_api_key" class="form-control" value="{{ $settings['claude_api_key'] }}" placeholder="sk-ant-api...">
                            <small class="text-muted">Obtain from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Model Version</label>
                            <input type="text" name="claude_model" class="form-control" value="{{ $settings['claude_model'] }}" placeholder="claude-3-5-haiku-20241022">
                        </div>
                    </div>
                </div>

                <!-- Provider 4: Local -->
                <div id="localBox" class="ai-box border rounded-3 p-3 bg-light mb-2 {{ $settings['ai_provider'] === 'local' ? '' : 'd-none' }}">
                    <div class="d-flex align-items-center">
                        <i class="fa fa-shield-alt text-success fs-3 me-3"></i>
                        <div>
                            <div class="fw-bold">Smart Heuristic Engine Active</div>
                            <small class="text-muted">Parses and structures course curricula offline using native document processing rules without sending data to any external AI API.</small>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Institutional AI Guidelines & System Prompt -->
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="fa fa-scroll text-primary me-2"></i> Institutional AI Curriculum Prompt & Pedagogical Guidelines
                            </h6>
                            <small class="text-muted">Configure university-specific curriculum rules, Bloom's Taxonomy standards, or assessment policies. Use <code>{course_name}</code> as dynamic placeholder.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDefaultAiPrompt()">
                            <i class="fa fa-rotate-left me-1"></i> Reset to Recommended Template
                        </button>
                    </div>

                    <textarea name="ai_system_prompt" id="aiSystemPrompt" class="form-control font-monospace" rows="13" style="font-size: 0.82rem; line-height: 1.5; background: #f8fafc; border: 1px solid #cbd5e1;">{{ $settings['ai_system_prompt'] ?? App\Models\Setting::getDefaultAiPrompt() }}</textarea>

                    <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                        <span class="small text-muted fw-bold">Supported Tags:</span>
                        <span class="badge bg-light text-primary border font-monospace">{course_name}</span>
                        <span class="badge bg-light text-secondary border font-monospace">16 Weeks Structure</span>
                        <span class="badge bg-light text-success border">Bloom's Taxonomy Compliant</span>
                        <span class="badge bg-light text-purple border" style="color: #6366f1;">JSON Schema</span>
                    </div>
                </div>
            </div>

            <!-- ================= CARD 3: GOOGLE DRIVE INGESTION & ACCESS CREDENTIALS ================= -->
            <div class="custom-card p-4 mb-4" style="border-top: 4px solid #0ea5e9;">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <span class="badge bg-info text-dark mb-1">Source Repository</span>
                        <h4 class="fw-bold brand-font text-dark mb-0">
                            <i class="fa fa-google-drive text-info me-2"></i> 3. Google Drive Ingestion & Access Credentials
                        </h4>
                    </div>
                    <button type="button" class="btn btn-outline-info" id="btnTestDrive" onclick="window.testDrive(event)">
                        <i class="fa fa-check-circle me-1"></i> Test Drive Connection
                    </button>
                </div>
                <p class="text-muted small">Configure the course parent root folder and choose access mode (Public Folder or Private / Institutional Drive with Google Service Account).</p>

                <div id="driveStatusAlert" class="mb-3 d-none"></div>

                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold small">Default Google Drive Root Folder ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa fa-folder-open text-primary"></i></span>
                            <input type="text" name="gdrive_root_folder_id" id="gdriveRootFolderId" class="form-control font-monospace" value="{{ $settings['gdrive_root_folder_id'] }}" placeholder="e.g. 1BcFVmPVoN9HMPSUNw1vqnOuPYKkDPcnR" required>
                        </div>
                        <small class="text-muted">The parent root folder where all course folders reside. When clicking <strong>"Scan Drive"</strong> on the dashboard, this folder is queried.</small>
                    </div>
                </div>

                <!-- Google Drive Access / Credentials Options -->
                <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa fa-shield-alt text-primary me-2"></i> Google Drive Authentication & Access Mode
                        </h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary small">
                            Institutional & Private Folders Support
                        </span>
                    </div>
                    <p class="text-muted small mb-3">
                        If your university course folder is private, requires login, or is stored in a Google Workspace Shared Drive, provide a <strong>Google Service Account JSON key</strong> below. For public folders (<em>"Anyone with the link can view"</em>), no credentials are required.
                    </p>

                    @php
                        $activeClientEmail = App\Services\GoogleDriveService::getServiceAccountEmail($settings['gdrive_service_account_json'] ?? '');
                    @endphp

                    @if($activeClientEmail)
                    <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 small">
                        <i class="fa fa-check-circle fs-5 me-2 text-success"></i>
                        <div>
                            <strong>Active Service Account Connected:</strong> 
                            <code class="user-select-all bg-white px-2 py-1 rounded border ms-1 fw-bold text-dark">{{ $activeClientEmail }}</code>
                            <div class="text-muted mt-1" style="font-size: 0.78rem;">
                                Ensure your private Google Drive course folders are shared with this email as <strong>Viewer</strong>.
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0">
                                    Google Service Account JSON Key <span class="badge bg-secondary ms-1">Recommended for Private Folders</span>
                                </label>
                                <small class="text-muted">Download JSON key from Google Cloud Console &rarr; Service Accounts</small>
                            </div>
                            <textarea name="gdrive_service_account_json" id="gdriveServiceAccountJson" class="form-control font-monospace" rows="5" placeholder='{"type": "service_account", "project_id": "...", "private_key_id": "...", "private_key": "-----BEGIN RSA PRIVATE KEY-----\n...", "client_email": "...", ...}' style="font-size: 0.8rem; background: #fff;">{{ $settings['gdrive_service_account_json'] ?? '' }}</textarea>
                            <div class="form-text small text-muted">
                                <i class="fa fa-info-circle text-info me-1"></i> <strong>How to connect Private / Institutional Folders:</strong>
                                1. In Google Cloud Console, create a free Service Account and download its JSON key.<br>
                                2. Open Google Drive, right-click your course root folder &rarr; <strong>Share</strong> &rarr; add the Service Account's <code>client_email</code> with <strong>Viewer</strong> access.<br>
                                3. Paste the JSON key here and click <strong>"Test Drive Connection"</strong> above.
                            </div>
                        </div>

                        <div class="col-md-12 mt-2">
                            <label class="form-label small fw-semibold">Google Drive API Key (Optional / Public API quota boost)</label>
                            <input type="password" name="gdrive_api_key" id="gdriveApiKey" class="form-control form-control-sm font-monospace" value="{{ $settings['gdrive_api_key'] ?? '' }}" placeholder="AIzaSy...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= CARD 4: COURSE DOCUMENT NAMING RULES & FILE MAPPINGS ================= -->
            <div class="custom-card p-4 mb-4" style="border-top: 4px solid #10b981;">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <span class="badge bg-success text-white mb-1">Document Discovery & Classification</span>
                        <h4 class="fw-bold brand-font text-dark mb-0">
                            <i class="fa fa-file-invoice text-success me-2"></i> 4. Course Document Naming Rules & File Mappings
                        </h4>
                    </div>
                </div>
                <p class="text-muted small">Specify the filename keywords used across course folders in Google Drive. If a field is left empty, the system automatically uses smart heuristic detection. You can also map extra documents (e.g. Lab Manual, Reference Book) to be provisioned in Moodle Section 0.</p>

                <!-- Core 4 Files Section -->
                <div class="bg-light p-3 rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="fa fa-folder-tree text-primary me-2"></i> Standard 4 Core Course Files (Exact Standardized Filenames)</h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary small">Exact Full Filename / Case-Insensitive</span>
                    </div>
                    <p class="text-muted small mb-3">Enter the exact standardized filename used across all course subfolders in Google Drive (e.g. <code>Course Student Handbook.pdf</code>). If a folder uses slightly different naming or is left blank, the system automatically uses smart heuristic fallback rules.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">1. Student Handbook Exact Filename</label>
                            <input type="text" name="doc_pattern_student_handbook" class="form-control form-control-sm font-monospace" value="{{ $settings['doc_pattern_student_handbook'] ?? '' }}" placeholder="e.g. Course Student Handbook.pdf">
                            <small class="text-muted" style="font-size: 0.75rem;">Primary source for Weekly Overviews & Student Learning Objectives (SLOs).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">2. Instructor Handbook Exact Filename</label>
                            <input type="text" name="doc_pattern_instructor_handbook" class="form-control form-control-sm font-monospace" value="{{ $settings['doc_pattern_instructor_handbook'] ?? '' }}" placeholder="e.g. Course Instructor Handbook.pdf">
                            <small class="text-muted" style="font-size: 0.75rem;">Official faculty guidelines provisioned as a resource in Moodle Section 0.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">3. Test Bank / Questions Exact Filename</label>
                            <input type="text" name="doc_pattern_test_bank" class="form-control form-control-sm font-monospace" value="{{ $settings['doc_pattern_test_bank'] ?? '' }}" placeholder="e.g. testbank.docx (or Question Bank.docx)">
                            <small class="text-muted" style="font-size: 0.75rem;">Source for Weekly Quizzes, Midterm Exam & Final Exam MCQs.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">4. Lecture Slides Exact Filename</label>
                            <input type="text" name="doc_pattern_slides" class="form-control form-control-sm font-monospace" value="{{ $settings['doc_pattern_slides'] ?? '' }}" placeholder="e.g. Lecture Slides.pptx (or Lecture Slides.pdf)">
                            <small class="text-muted" style="font-size: 0.75rem;">Course presentations provisioned as a resource in Moodle Section 0.</small>
                        </div>
                    </div>
                </div>

                <!-- Extra / Custom Documents Mapping -->
                <div class="border rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="fa fa-plus-circle text-success me-1"></i> Additional / Extra Course Documents Mapping</h6>
                            <small class="text-muted">Define any additional files (e.g. Lab Manual, Formula Sheet, Project Guidelines) to be synced to Moodle Section 0.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addCustomDocRow()">
                            <i class="fa fa-plus me-1"></i> Add Document Mapping
                        </button>
                    </div>

                    @php
                        $customDocs = json_decode($settings['doc_custom_mappings'] ?? '[]', true) ?: [];
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="customDocsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%;">Resource Title in Moodle (Section 0)</th>
                                    <th style="width: 45%;">Exact Filename in Google Drive (e.g. Lab Manual.pdf)</th>
                                    <th class="text-center" style="width: 10%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="customDocsBody">
                                @forelse($customDocs as $cd)
                                <tr>
                                    <td>
                                        <input type="text" name="custom_doc_labels[]" class="form-control form-control-sm" value="{{ $cd['label'] }}" placeholder="e.g. Lab Manual" required>
                                    </td>
                                    <td>
                                        <input type="text" name="custom_doc_patterns[]" class="form-control form-control-sm font-monospace" value="{{ $cd['pattern'] }}" placeholder="e.g. Chemistry Lab Manual.pdf" required>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCustomDocRow(this)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr id="noCustomDocsRow">
                                    <td colspan="3" class="text-center text-muted small py-3">
                                        <em>No additional custom files mapped. Standard 4 core files will be discovered automatically. Click <strong>"+ Add Document Mapping"</strong> above if you have Lab Manuals or Reference Books.</em>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================= CARD 4: VIDEO STORAGE & STREAMING ENGINE ================= -->
            <div class="custom-card p-4 mb-4" style="border-top: 4px solid #10b981;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-success text-white mb-1">CDN & Storage</span>
                        <h4 class="fw-bold brand-font text-dark mb-0">
                            <i class="fa fa-film text-success me-2"></i> 4. Video Storage & Streaming Driver
                        </h4>
                    </div>
                    <button type="button" class="btn btn-outline-success" id="btnTestStorage" onclick="window.testStorage(event)">
                        <i class="fa fa-check-circle me-1"></i> Test Storage Driver
                    </button>
                </div>
                <p class="text-muted small">Choose how lecture videos from Google Drive should be delivered into Moodle.</p>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Select Active Video Driver</label>
                    <select name="video_storage_driver" id="storageDriverSelect" class="form-select form-select-lg fw-bold text-success" style="border-color: #a7f3d0;">
                        <option value="gdrive" {{ $settings['video_storage_driver'] === 'gdrive' ? 'selected' : '' }}>Google Drive (Direct Links & Responsive Embed Player - Zero Extra Cost)</option>
                        <option value="bunny" {{ $settings['video_storage_driver'] === 'bunny' ? 'selected' : '' }}>Bunny.net Stream (Fast HLS Video CDN & Responsive Iframe Player)</option>
                        <option value="cloudflare_r2" {{ $settings['video_storage_driver'] === 'cloudflare_r2' ? 'selected' : '' }}>Cloudflare R2 (S3-Compatible Object Storage with Zero Egress Fees)</option>
                        <option value="aws_s3" {{ $settings['video_storage_driver'] === 'aws_s3' ? 'selected' : '' }}>AWS S3 / CloudFront (Amazon Cloud Storage)</option>
                    </select>
                </div>

                <div id="storageStatusAlert" class="mb-3 d-none"></div>

                <!-- Driver A: Bunny -->
                <div id="bunnySettings" class="driver-settings border rounded-3 p-3 bg-light mb-3 {{ $settings['video_storage_driver'] === 'bunny' ? '' : 'd-none' }}">
                    <h6 class="fw-bold text-dark mb-3"><i class="fa fa-bolt text-warning me-1"></i> Bunny.net Stream Credentials</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bunny API Key (AccessKey)</label>
                            <input type="password" name="bunny_api_key" class="form-control" value="{{ $settings['bunny_api_key'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Video Library ID</label>
                            <input type="text" name="bunny_library_id" class="form-control" value="{{ $settings['bunny_library_id'] }}" placeholder="e.g. 123456">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Player Hostname</label>
                            <input type="text" name="bunny_hostname" class="form-control" value="{{ $settings['bunny_hostname'] }}" placeholder="iframe.mediadelivery.net">
                        </div>
                    </div>
                </div>

                <!-- Driver B: Cloudflare R2 -->
                <div id="r2Settings" class="driver-settings border rounded-3 p-3 bg-light mb-3 {{ $settings['video_storage_driver'] === 'cloudflare_r2' ? '' : 'd-none' }}">
                    <h6 class="fw-bold text-dark mb-3"><i class="fa fa-cloud text-info me-1"></i> Cloudflare R2 Credentials</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">R2 Account ID</label>
                            <input type="text" name="r2_account_id" class="form-control" value="{{ $settings['r2_account_id'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">R2 Access Key ID</label>
                            <input type="text" name="r2_access_key_id" class="form-control" value="{{ $settings['r2_access_key_id'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">R2 Secret Access Key</label>
                            <input type="password" name="r2_secret_access_key" class="form-control" value="{{ $settings['r2_secret_access_key'] }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bucket Name</label>
                            <input type="text" name="r2_bucket" class="form-control" value="{{ $settings['r2_bucket'] }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Public Custom Domain / Worker URL</label>
                            <input type="text" name="r2_custom_domain" class="form-control" value="{{ $settings['r2_custom_domain'] }}" placeholder="https://cdn.yourdomain.com">
                        </div>
                    </div>
                </div>

                <!-- Driver C: AWS S3 -->
                <div id="s3Settings" class="driver-settings border rounded-3 p-3 bg-light mb-3 {{ $settings['video_storage_driver'] === 'aws_s3' ? '' : 'd-none' }}">
                    <h6 class="fw-bold text-dark mb-3"><i class="fa fa-amazon text-warning me-1"></i> AWS S3 & CloudFront Credentials</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">AWS Access Key ID</label>
                            <input type="text" name="s3_key" class="form-control" value="{{ $settings['s3_key'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">AWS Secret Access Key</label>
                            <input type="password" name="s3_secret" class="form-control" value="{{ $settings['s3_secret'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Region</label>
                            <input type="text" name="s3_region" class="form-control" value="{{ $settings['s3_region'] }}" placeholder="us-east-1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">S3 Bucket Name</label>
                            <input type="text" name="s3_bucket" class="form-control" value="{{ $settings['s3_bucket'] }}">
                        </div>
                    </div>
                </div>

                <!-- Driver D: Google Drive (Active notice) -->
                <div id="gdriveSettings" class="driver-settings border rounded-3 p-3 bg-light mb-3 {{ $settings['video_storage_driver'] === 'gdrive' ? '' : 'd-none' }}">
                    <div class="d-flex align-items-center">
                        <i class="fa fa-check-circle text-success fs-3 me-3"></i>
                        <div>
                            <div class="fw-bold">Google Drive Direct Engine Active</div>
                            <small class="text-muted">Videos stream directly from Google Drive with zero extra upload time and responsive video embed players.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Action Button -->
            <div class="text-end mb-5">
                @if(auth()->user()->canManageSettings())
                    <button type="submit" class="btn btn-primary-custom px-5 py-3 fs-5 shadow">
                        <i class="fa fa-save me-2"></i> Save All Configurations
                    </button>
                @else
                    <button type="button" class="btn btn-secondary px-5 py-3 fs-5 shadow opacity-75" disabled title="Instructors cannot alter institutional credentials">
                        <i class="fa fa-lock me-2"></i> Read-Only (Instructors Cannot Save)
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // --- RESET AI PROMPT TEMPLATE ---
    const defaultAiTemplate = @json(App\Models\Setting::getDefaultAiPrompt());
    function resetDefaultAiPrompt() {
        if (confirm("Reset the AI System Prompt to the university recommended template?")) {
            const el = document.getElementById('aiSystemPrompt');
            if (el) el.value = defaultAiTemplate;
        }
    }
    window.resetDefaultAiPrompt = resetDefaultAiPrompt;

    // --- DYNAMIC CUSTOM DOCUMENT MAPPINGS ---
    function addCustomDocRow(label, pattern) {
        label = label || '';
        pattern = pattern || '';
        const noRow = document.getElementById('noCustomDocsRow');
        if (noRow) noRow.remove();

        const tbody = document.getElementById('customDocsBody');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" name="custom_doc_labels[]" class="form-control form-control-sm" value="${label}" placeholder="e.g. Lab Manual" required>
            </td>
            <td>
                <input type="text" name="custom_doc_patterns[]" class="form-control form-control-sm font-monospace" value="${pattern}" placeholder="e.g. Chemistry Lab Manual.pdf" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCustomDocRow(this)">
                    <i class="fa fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }
    window.addCustomDocRow = addCustomDocRow;

    function removeCustomDocRow(btn) {
        if (!btn) return;
        const tr = btn.closest('tr');
        if (tr) tr.remove();

        const tbody = document.getElementById('customDocsBody');
        if (tbody && tbody.querySelectorAll('tr').length === 0) {
            tbody.innerHTML = `
                <tr id="noCustomDocsRow">
                    <td colspan="3" class="text-center text-muted small py-3">
                        <em>No additional custom files mapped. Standard 4 core files will be discovered automatically. Click <strong>"+ Add Document Mapping"</strong> above if you have Lab Manuals or Reference Books.</em>
                    </td>
                </tr>
            `;
        }
    }
    window.removeCustomDocRow = removeCustomDocRow;
    // AI Provider Switcher
    const aiSelect = document.getElementById('aiProviderSelect');
    if (aiSelect) {
        aiSelect.addEventListener('change', function() {
            document.querySelectorAll('.ai-box').forEach(el => el.classList.add('d-none'));
            const val = this.value;
            if (val === 'gemini') document.getElementById('geminiBox').classList.remove('d-none');
            else if (val === 'openai') document.getElementById('openaiBox').classList.remove('d-none');
            else if (val === 'claude') document.getElementById('claudeBox').classList.remove('d-none');
            else if (val === 'local') document.getElementById('localBox').classList.remove('d-none');
        });
    }

    // Video Storage Switcher
    const storageSelect = document.getElementById('storageDriverSelect');
    if (storageSelect) {
        storageSelect.addEventListener('change', function() {
            document.querySelectorAll('.driver-settings').forEach(el => el.classList.add('d-none'));
            const val = this.value;
            if (val === 'bunny') document.getElementById('bunnySettings').classList.remove('d-none');
            else if (val === 'cloudflare_r2') document.getElementById('r2Settings').classList.remove('d-none');
            else if (val === 'aws_s3') document.getElementById('s3Settings').classList.remove('d-none');
            else document.getElementById('gdriveSettings').classList.remove('d-none');
        });
    }

    // --- TEST GOOGLE DRIVE CONNECTION ---
    window.testDrive = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        const btn = document.getElementById('btnTestDrive');
        const alertBox = document.getElementById('driveStatusAlert');
        if (!btn || !alertBox) return;

        const originalText = btn.innerHTML;
        const folderId = (document.getElementById('gdriveRootFolderId') || {}).value || '';
        const saJson = (document.getElementById('gdriveServiceAccountJson') || {}).value || '';
        const apiKey = (document.getElementById('gdriveApiKey') || {}).value || '';

        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Testing Drive...';
        btn.disabled = true;

        alertBox.className = 'mb-3 alert alert-info';
        alertBox.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Connecting to Google Drive... Validating folder accessibility.';
        alertBox.classList.remove('d-none');

        fetch('/settings/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                target: 'gdrive',
                folder_id: folderId,
                service_account_json: saJson,
                api_key: apiKey
            })
        })
        .then(function(res) {
            return res.json().catch(function() {
                return { success: false, message: 'Server returned HTTP ' + res.status };
            });
        })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mb-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
            let icon = data.success ? 'fa-check-circle' : 'fa-times-circle';
            let extra = '';
            if (data.client_email) {
                extra = '<div class="mt-1 small text-dark"><strong>Authenticated as:</strong> <code>' + data.client_email + '</code></div>';
            }
            alertBox.innerHTML = '<i class="fa ' + icon + ' me-2"></i> ' + (data.message || 'Completed') + extra;
        })
        .catch(function(err) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mb-3 alert alert-danger';
            alertBox.innerHTML = '<i class="fa fa-times-circle me-2"></i> Network Request Error: ' + err.message;
        });
    };

    // --- TEST MOODLE FUNCTION ---
    window.testMoodle = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        const btn = document.getElementById('btnTestMoodle');
        const alertBox = document.getElementById('moodleStatusAlert');
        if (!btn || !alertBox) return;

        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Testing Moodle...';
        btn.disabled = true;

        alertBox.className = 'mt-3 alert alert-info';
        alertBox.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Connecting to Moodle Web Services API... Please wait.';
        alertBox.classList.remove('d-none');

        const moodleUrl = (document.querySelector('input[name="moodle_url"]') || {}).value || '';
        const moodleToken = (document.querySelector('input[name="moodle_token"]') || {}).value || '';

        fetch('/settings/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target: 'moodle',
                moodle_url: moodleUrl,
                moodle_token: moodleToken
            })
        })
        .then(function(res) {
            return res.json().catch(function() {
                return { success: false, message: 'Server returned HTTP ' + res.status + ' ' + res.statusText };
            });
        })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mt-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
            alertBox.innerHTML = '<i class="fa ' + (data.success ? 'fa-check-circle' : 'fa-times-circle') + ' me-2"></i> ' + (data.message || 'Unknown response');
        })
        .catch(function(err) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mt-3 alert alert-danger';
            alertBox.innerHTML = '<i class="fa fa-times-circle me-2"></i> Network Request Error: ' + err.message;
        });
    };

    // --- TEST AI FUNCTION ---
    window.testAi = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        const btn = document.getElementById('btnTestAi');
        const alertBox = document.getElementById('aiStatusAlert');
        if (!btn || !alertBox) return;

        const originalText = btn.innerHTML;
        const provider = (document.getElementById('aiProviderSelect') || {}).value || 'gemini';

        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Testing ' + provider.toUpperCase() + '...';
        btn.disabled = true;

        alertBox.className = 'mb-3 alert alert-info';
        alertBox.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Testing ' + provider.toUpperCase() + ' API connection...';
        alertBox.classList.remove('d-none');

        // Grab current dynamic inputs
        let apiKey = '';
        let model = '';
        if (provider === 'gemini') {
            apiKey = (document.querySelector('input[name="gemini_api_key"]') || {}).value || '';
            model = (document.querySelector('input[name="gemini_model"]') || {}).value || '';
        } else if (provider === 'openai') {
            apiKey = (document.querySelector('input[name="openai_api_key"]') || {}).value || '';
            model = (document.querySelector('input[name="openai_model"]') || {}).value || '';
        } else if (provider === 'claude') {
            apiKey = (document.querySelector('input[name="claude_api_key"]') || {}).value || '';
            model = (document.querySelector('input[name="claude_model"]') || {}).value || '';
        }

        fetch('/settings/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target: provider,
                api_key: apiKey,
                model: model
            })
        })
        .then(function(res) {
            return res.json().catch(function() {
                return { success: false, message: 'Server returned HTTP ' + res.status };
            });
        })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mb-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
            alertBox.innerHTML = '<i class="fa ' + (data.success ? 'fa-check-circle' : 'fa-times-circle') + ' me-2"></i> ' + (data.message || 'Completed');
        })
        .catch(function(err) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mb-3 alert alert-danger';
            alertBox.innerHTML = '<i class="fa fa-times-circle me-2"></i> Network Request Error: ' + err.message;
        });
    };

    // --- TEST STORAGE FUNCTION ---
    window.testStorage = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        const btn = document.getElementById('btnTestStorage');
        const alertBox = document.getElementById('storageStatusAlert');
        if (!btn || !alertBox) return;

        const originalText = btn.innerHTML;
        const driver = (document.getElementById('storageDriverSelect') || {}).value || 'gdrive';

        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Testing ' + driver + '...';
        btn.disabled = true;

        alertBox.className = 'mb-3 alert alert-info';
        alertBox.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Testing ' + driver + ' storage configuration...';
        alertBox.classList.remove('d-none');

        fetch('/settings/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target: driver
            })
        })
        .then(function(res) {
            return res.json().catch(function() {
                return { success: false, message: 'Server returned HTTP ' + res.status };
            });
        })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mb-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
            alertBox.innerHTML = '<i class="fa ' + (data.success ? 'fa-check-circle' : 'fa-times-circle') + ' me-2"></i> ' + (data.message || 'Completed');
        })
        .catch(function(err) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alertBox.className = 'mb-3 alert alert-danger';
            alertBox.innerHTML = '<i class="fa fa-times-circle me-2"></i> Network Request Error: ' + err.message;
        });
    };

    // Also attach standard event listeners on DOM ready for standard behavior
    document.addEventListener('DOMContentLoaded', function() {
        const btnDrive = document.getElementById('btnTestDrive');
        if (btnDrive) btnDrive.addEventListener('click', window.testDrive);

        const btnMoodle = document.getElementById('btnTestMoodle');
        if (btnMoodle) btnMoodle.addEventListener('click', window.testMoodle);

        const btnAi = document.getElementById('btnTestAi');
        if (btnAi) btnAi.addEventListener('click', window.testAi);

        const btnStorage = document.getElementById('btnTestStorage');
        if (btnStorage) btnStorage.addEventListener('click', window.testStorage);
    });
</script>
@endpush
