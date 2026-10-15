@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary text-white px-3 py-1 rounded-pill">
                <i class="fa fa-building-columns me-1"></i> {{ $university->name }}
            </span>
            @if(auth()->user()->isSuperAdmin())
                <span class="badge bg-danger text-white"><i class="fa fa-crown me-1"></i> Super Admin (Platform Owner)</span>
            @elseif(auth()->user()->isUniversityAdmin())
                <span class="badge bg-primary text-white"><i class="fa fa-shield-alt me-1"></i> University Admin (Institution Manager)</span>
            @else
                <span class="badge bg-success text-white"><i class="fa fa-chalkboard-teacher me-1"></i> Instructor (Course Author)</span>
            @endif
        </div>
        <h3 class="fw-bold brand-font text-dark mb-0">Course Provisioning & Ingestion Studio</h3>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->check() && auth()->user()->isSuperAdmin())
            <a href="{{ route('universities.index') }}" class="btn btn-primary-custom btn-sm">
                <i class="fa fa-university me-1"></i> Manage Institutions
            </a>
        @endif
    </div>
</div>

<!-- Role Permission Summary Strip -->
<div class="alert alert-light border rounded-3 p-3 mb-4 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small fw-bold text-uppercase"><i class="fa fa-user-shield me-1 text-primary"></i> Your Role Permissions:</span>
        @if(auth()->user()->isSuperAdmin())
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Create Universities</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Switch Workspaces</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Edit All Settings</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Provision Courses</span>
        @elseif(auth()->user()->isUniversityAdmin())
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa fa-times me-1"></i> Multi-University Governance (Super Admin Only)</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Edit Moodle & AI Settings</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Scan & Ingest Courses</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Sync Moodle</span>
        @else
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa fa-times me-1"></i> University Creation Restricted</span>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa fa-lock me-1"></i> Institutional Settings (Read-Only)</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Ingest Drive Courses</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa fa-check me-1"></i> Review 16-Week Curriculum</span>
        @endif
    </div>
    <div class="small text-muted">
        Logged in as: <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }})
    </div>
</div>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Stat 1: Courses under Root Folder -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Courses in Root Folder</div>
                    <div class="fs-2 fw-bold text-dark brand-font">{{ $stats['total_courses'] }}</div>
                </div>
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
                    <i class="fa fa-folder-open fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Synced to Moodle -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Synced in Moodle</div>
                    <div class="fs-2 fw-bold text-success brand-font">{{ $stats['synced_courses'] }}</div>
                </div>
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
                    <i class="fa fa-check-double fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Active Video Storage -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Active Video Driver</div>
                    <div class="fs-4 fw-bold brand-font text-capitalize" style="color: #4f46e5;">
                        {{ $stats['active_driver'] === 'gdrive' ? 'Google Drive' : ($stats['active_driver'] === 'bunny' ? 'Bunny Stream' : $stats['active_driver']) }}
                    </div>
                </div>
                <div class="p-3 bg-info bg-opacity-10 text-info rounded-3">
                    <i class="fa fa-cloud-upload-alt fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 4: Target LMS -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Target LMS</div>
                    <div class="fs-5 fw-bold text-dark text-truncate brand-font" style="max-width: 170px;">
                        @if(!empty($stats['moodle_url']))
                            {{ parse_url($stats['moodle_url'], PHP_URL_HOST) ?? $stats['moodle_url'] }}
                        @else
                            <span class="text-muted small fst-italic">Not Set</span>
                        @endif
                    </div>
                </div>
                <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3">
                    <i class="fa fa-server fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scan Toolbar Card -->
<div class="custom-card p-4 mb-3">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <h4 class="fw-bold mb-1 brand-font text-dark">Google Drive Course Ingestion Pipeline</h4>
            <p class="text-muted mb-0 small">
                Enter your Program / Root Folder ID. The system scans all course subfolders under this root, auto-discovers each course, and provisions full 16-week structures in Moodle.
            </p>
        </div>
        <div class="col-lg-5 mt-3 mt-lg-0">
            <form action="{{ route('courses.scan') }}" method="POST" class="d-flex gap-2">
                @csrf
                <input type="text" name="folder_id" class="form-control" placeholder="Google Drive Root Folder ID" value="{{ $stats['root_folder'] }}" required>
                <button type="submit" class="btn btn-primary-custom text-nowrap">
                    <i class="fa fa-search me-1"></i> Scan Drive
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Active Root Folder Scope Badge -->
<div class="custom-card p-3 mb-4 bg-white border-start border-4 border-primary shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            @if(!empty($stats['root_folder']))
                <span class="badge bg-primary text-uppercase px-2 py-1"><i class="fa fa-folder-tree me-1"></i> Active Root Folder</span>
                <span class="font-monospace fw-bold text-dark fs-6">{{ $stats['root_folder'] }}</span>
                <a href="https://drive.google.com/drive/folders/{{ $stats['root_folder'] }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                    <i class="fa fa-external-link-alt me-1"></i> Open in Google Drive
                </a>
            @else
                <span class="badge bg-secondary text-uppercase px-2 py-1"><i class="fa fa-folder-open me-1"></i> No Folder Configured</span>
                <span class="text-muted small">No Google Drive folder linked for <strong>{{ $university->name }}</strong> yet. Enter a Folder ID above and click "Scan Drive".</span>
            @endif
        </div>
        <div>
            <span class="badge bg-light text-dark border">
                <strong>{{ $courses->count() }}</strong> Course(s) for {{ $university->name }}
            </span>
            @if(!$stats['view_all'] && $stats['total_stored'] > $courses->count())
                <a href="{{ route('dashboard', ['view' => 'all']) }}" class="btn btn-sm btn-link text-decoration-none py-0 ms-2 text-muted" style="font-size: 0.78rem;">
                    (Show all {{ $stats['total_stored'] }} stored courses)
                </a>
            @elseif($stats['view_all'])
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-link text-decoration-none py-0 ms-2 text-primary" style="font-size: 0.78rem;">
                    <i class="fa fa-filter me-1"></i> Show only current root folder
                </a>
            @endif
        </div>
    </div>
</div>

<!-- Courses Table Card -->
<div class="custom-card p-0">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 brand-font">Courses under Current Root Folder</h5>
            <small class="text-muted">Only courses belonging to the active root folder are shown below</small>
        </div>
        <span class="badge bg-secondary rounded-pill">{{ $courses->count() }} detected</span>
    </div>

    @if($courses->isEmpty())
        <div class="p-5 text-center text-muted">
            <i class="fa fa-folder-open fs-1 mb-3 text-secondary opacity-50"></i>
            <h5>No course folders found for {{ $university->name }}</h5>
            <p class="small mb-3">Enter your Google Drive Folder ID above and click <strong>"Scan Drive"</strong> to discover course subfolders for this institution.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Course Name & Code</th>
                        <th>Drive Subfolder</th>
                        <th>Storage Driver</th>
                        <th>Semester Timeline</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th class="text-end pe-4">Actions & Sync</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($courses as $c)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $c->name }}</div>
                            <span class="badge bg-light text-dark border">{{ $c->course_code ?? 'Auto' }}</span>
                            @if($c->moodle_course_id)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success ms-1">Moodle ID: {{ $c->moodle_course_id }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="https://drive.google.com/drive/folders/{{ $c->gdrive_folder_id }}" target="_blank" class="text-decoration-none font-monospace small">
                                <i class="fa fa-external-link-alt me-1"></i> {{ substr($c->gdrive_folder_id, 0, 16) }}...
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border text-uppercase">{{ $c->storage_driver }}</span>
                        </td>
                        <td>
                            @if($c->start_date)
                                <div class="fw-bold text-dark small"><i class="fa fa-calendar-check text-success me-1"></i> {{ $c->start_date->format('M d, Y') }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">16 Weeks (Ends {{ $c->start_date->copy()->addWeeks(16)->format('M d, Y') }})</div>
                            @else
                                <span class="badge bg-light text-muted border"><i class="fa fa-calendar-times me-1"></i> Not set</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge-status badge-{{ $c->status }}">
                                @if($c->status === 'completed' || $c->progress >= 100) 
                                    <i class="fa fa-check-circle text-success me-1"></i> 100% Synced
                                @elseif($c->status === 'processing') 
                                    <i class="fa fa-spinner fa-spin me-1"></i> In Progress
                                @elseif($c->status === 'failed') 
                                    <i class="fa fa-times me-1"></i> Failed
                                @else 
                                    Discovered
                                @endif
                            </span>
                        </td>
                        <td style="min-width: 130px;">
                            <div class="progress" style="height: 6px; border-radius: 4px;">
                                <div class="progress-bar {{ $c->progress >= 100 ? 'bg-success' : 'bg-primary' }}" role="progressbar" style="width: {{ $c->progress }}%;"></div>
                            </div>
                            <small class="{{ $c->progress >= 100 ? 'text-success fw-bold' : 'text-muted' }}" style="font-size: 0.75rem;">
                                {{ $c->progress }}% @if($c->progress >= 100) (Complete) @endif
                            </small>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-2">
                                <form action="{{ route('courses.process', $c->id) }}" method="POST" class="d-flex align-items-center gap-1" id="processForm{{ $c->id }}">
                                    @csrf
                                    <div class="input-group input-group-sm" style="width: 145px;" title="Semester Start Date (16-Week Schedule)">
                                        <span class="input-group-text bg-white px-2"><i class="fa fa-calendar text-primary" style="font-size: 0.8rem;"></i></span>
                                        <input type="date" name="start_date" class="form-control form-control-sm px-1 font-monospace" style="font-size: 0.8rem;" value="{{ $c->start_date ? $c->start_date->format('Y-m-d') : date('Y-m-d') }}" required>
                                    </div>
                                    @if($c->status === 'completed' || $c->progress >= 100)
                                        <button type="button" class="btn btn-sm btn-outline-warning text-nowrap fw-semibold" 
                                            onclick="confirmCourseResync('{{ $c->id }}', '{{ addslashes($c->name) }}', '{{ $c->moodle_course_id }}')" 
                                            title="Course is already 100% Synced. Click to Re-sync / Overwrite.">
                                            <i class="fa fa-redo me-1"></i> Re-sync
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-sm btn-primary-custom text-nowrap" title="Build, Schedule 16 Weeks & Push to Moodle">
                                            <i class="fa fa-sync-alt me-1"></i> Sync
                                        </button>
                                    @endif
                                </form>

                                <a href="{{ route('courses.preview', $c->id) }}" class="btn btn-sm btn-outline-secondary" title="Preview Curriculum">
                                    <i class="fa fa-eye"></i>
                                </a>

                                @if($c->moodle_course_id)
                                    <a href="{{ $stats['moodle_url'] }}/course/view.php?id={{ $c->moodle_course_id }}" target="_blank" class="btn btn-sm btn-outline-success" title="View in Moodle">
                                        <i class="fa fa-external-link-alt"></i> Moodle
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<!-- Re-sync Overwrite Confirmation Modal -->
<div class="modal fade" id="resyncConfirmModal" tabindex="-1" aria-labelledby="resyncModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-warning bg-opacity-10 border-bottom border-warning">
                <h5 class="modal-title fw-bold text-dark" id="resyncModalLabel">
                    <i class="fa fa-exclamation-triangle text-warning me-2"></i> Course Already 100% Synced
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning py-2 px-3 small mb-3 border border-warning">
                    <i class="fa fa-info-circle me-1"></i> <strong>Already Synced:</strong> This course has already been 100% processed and published to Moodle.
                </div>

                <div class="mb-3">
                    <label class="text-muted small fw-semibold text-uppercase">Target Course</label>
                    <div class="fw-bold text-dark fs-6" id="resyncModalCourseName"></div>
                </div>

                <div class="mb-3" id="resyncModalMoodleInfo">
                    <label class="text-muted small fw-semibold text-uppercase">Moodle Destination</label>
                    <div>
                        <span class="badge bg-success" id="resyncModalMoodleBadge"></span>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <div class="fw-bold text-dark small mb-1">
                        <i class="fa fa-sync-alt text-primary me-1"></i> Overwrite Confirmation
                    </div>
                    <p class="text-muted small mb-0">
                        Are you sure you want to <strong>overwrite & re-sync</strong> this course?
                        <br><br>
                        <i class="fa fa-shield-alt text-success me-1"></i> <em>Smart Incremental Protection: Existing 100% completed videos will NOT be re-encoded. Only newly added materials or schedule adjustments will be updated.</em>
                    </p>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-warning px-4 fw-bold text-dark shadow-sm" id="btnConfirmResyncAction">
                    <i class="fa fa-redo me-1"></i> Yes, Overwrite & Re-sync
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let activeResyncFormId = null;

    window.confirmCourseResync = function(courseId, courseName, moodleCourseId) {
        activeResyncFormId = 'processForm' + courseId;

        document.getElementById('resyncModalCourseName').textContent = courseName;
        const moodleBadge = document.getElementById('resyncModalMoodleBadge');
        const moodleInfo = document.getElementById('resyncModalMoodleInfo');

        if (moodleCourseId && moodleCourseId > 0) {
            moodleBadge.textContent = 'Published in Moodle (Course ID: #' + moodleCourseId + ')';
            moodleInfo.classList.remove('d-none');
        } else {
            moodleInfo.classList.add('d-none');
        }

        const modalEl = document.getElementById('resyncConfirmModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    };

    document.getElementById('btnConfirmResyncAction').addEventListener('click', function() {
        if (activeResyncFormId) {
            const form = document.getElementById(activeResyncFormId);
            if (form) {
                this.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Starting Re-sync...';
                this.disabled = true;
                form.submit();
            }
        }
    });
</script>
@endpush
