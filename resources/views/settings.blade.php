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
            </div>

            <!-- ================= CARD 3: GOOGLE DRIVE INGESTION ================= -->
            <div class="custom-card p-4 mb-4" style="border-top: 4px solid #0ea5e9;">
                <h4 class="fw-bold brand-font text-dark mb-2">
                    <i class="fa fa-google-drive text-info me-2"></i> 3. Google Drive Ingestion Pipeline
                </h4>
                <p class="text-muted small">The parent root folder where all course folders (containing documents and Videos/) reside.</p>

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold small">Default Google Drive Root Folder ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa fa-folder-open text-primary"></i></span>
                            <input type="text" name="gdrive_root_folder_id" class="form-control font-monospace" value="{{ $settings['gdrive_root_folder_id'] }}" placeholder="e.g. 1BcFVmPVoN9HMPSUNw1vqnOuPYKkDPcnR" required>
                        </div>
                        <small class="text-muted">When you click <strong>"Scan Drive"</strong> on the dashboard, the system scans this root folder to detect courses.</small>
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
                <button type="submit" class="btn btn-primary-custom px-5 py-3 fs-5 shadow">
                    <i class="fa fa-save me-2"></i> Save All Configurations
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
        const btnMoodle = document.getElementById('btnTestMoodle');
        if (btnMoodle) btnMoodle.addEventListener('click', window.testMoodle);

        const btnAi = document.getElementById('btnTestAi');
        if (btnAi) btnAi.addEventListener('click', window.testAi);

        const btnStorage = document.getElementById('btnTestStorage');
        if (btnStorage) btnStorage.addEventListener('click', window.testStorage);
    });
</script>
@endpush
