@use('App\Models\Setting')
@extends('layouts.app', ['title' => 'Curriculum Preview: ' . $course->name])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary text-uppercase">{{ $course->course_code ?? 'Course' }}</span>
        <h3 class="fw-bold brand-font mb-1 mt-1">{{ $course->name }}</h3>
        <p class="text-muted small mb-0">Google Drive: <a href="https://drive.google.com/drive/folders/{{ $course->gdrive_folder_id }}" target="_blank">{{ $course->gdrive_folder_id }}</a> &bull; Storage: <strong class="text-uppercase">{{ $course->storage_driver }}</strong></p>
    </div>
    <div class="d-flex gap-2">
        @if($course->moodle_course_id)
            <a href="{{ Setting::get('moodle_url', 'http://elearn.local') }}/course/view.php?id={{ $course->moodle_course_id }}" target="_blank" class="btn btn-success">
                <i class="fa fa-external-link me-1"></i> Open in Moodle
            </a>
        @endif
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

@if(empty($course->extracted_data))
    <div class="custom-card p-5 text-center text-muted">
        <i class="fa fa-cogs fs-1 mb-3 text-secondary opacity-50"></i>
        <h5>Curriculum not extracted yet</h5>
        <p class="small mb-3">Click "Sync Moodle" on the dashboard to trigger AI extraction and video mapping.</p>
        <form action="{{ route('courses.process', $course->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary-custom">
                <i class="fa fa-play me-1"></i> Start Processing Course
            </button>
        </form>
    </div>
@else
    <div class="accordion" id="curriculumAccordion">
        @foreach($course->extracted_data as $wnum => $w)
        <div class="accordion-item mb-3 custom-card border-0 overflow-hidden">
            <h2 class="accordion-header" id="heading{{ $wnum }}">
                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-bold brand-font py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $wnum }}">
                    <span class="badge bg-indigo text-white me-2 px-2 py-1" style="background: #4f46e5;">Week {{ sprintf('%02d', $wnum) }}</span>
                    <span>{{ $w['title'] ?? 'Week ' . $wnum }}</span>
                    @if(isset($course->video_map[$wnum]) && count($course->video_map[$wnum]) > 0)
                        <span class="badge bg-light text-primary border ms-auto me-3">
                            <i class="fa fa-video me-1"></i> {{ count($course->video_map[$wnum]) }} Video(s)
                        </span>
                    @endif
                </button>
            </h2>
            <div id="collapse{{ $wnum }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#curriculumAccordion">
                <div class="accordion-body p-4 bg-light bg-opacity-25">
                    
                    <!-- Overview -->
                    <div class="mb-3 p-3 bg-white border rounded-3">
                        <h6 class="fw-bold text-primary mb-2"><i class="fa fa-info-circle me-1"></i> Overview</h6>
                        <p class="mb-2 text-dark">{{ $w['overview'] ?? '' }}</p>
                        @if(!empty($w['objectives']))
                            <strong class="small text-muted text-uppercase">Learning Objectives:</strong>
                            <ul class="small text-muted mb-0 mt-1">
                                @foreach($w['objectives'] as $obj)
                                    <li>{{ $obj }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <!-- Readings -->
                    @if(!empty($w['key_terms']))
                    <div class="mb-3 p-3 bg-white border rounded-3">
                        <h6 class="fw-bold text-success mb-2"><i class="fa fa-book me-1"></i> Key Terms & Readings</h6>
                        <ul class="small text-muted mb-0">
                            @foreach($w['key_terms'] as $term)
                                <li>{{ $term }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <!-- Videos -->
                    @if(isset($course->video_map[$wnum]) && !empty($course->video_map[$wnum]))
                    <div class="mb-3 p-3 bg-white border rounded-3">
                        <h6 class="fw-bold text-indigo mb-2" style="color: #4f46e5;"><i class="fa fa-play-circle me-1"></i> Video Lectures ({{ count($course->video_map[$wnum]) }})</h6>
                        <div class="row g-2">
                            @foreach($course->video_map[$wnum] as $v)
                            <div class="col-md-6">
                                <div class="border rounded p-2 d-flex justify-content-between align-items-center bg-light">
                                    <div class="text-truncate me-2 small fw-semibold">
                                        <i class="fa fa-film text-secondary me-1"></i> {{ $v['filename'] }}
                                    </div>
                                    <a href="{{ $v['direct_url'] ?? '#' }}" target="_blank" class="btn btn-sm btn-outline-primary text-nowrap">
                                        Preview <i class="fa fa-external-link"></i>
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Discussion & Assignment -->
                    <div class="row g-3">
                        @if(!empty($w['discussion']))
                        <div class="col-md-6">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <h6 class="fw-bold text-warning mb-2"><i class="fa fa-comments me-1"></i> Discussion Prompt</h6>
                                <p class="small text-muted mb-0">{{ $w['discussion'] }}</p>
                            </div>
                        </div>
                        @endif

                        @if(!empty($w['assignment']))
                        <div class="col-md-6">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <h6 class="fw-bold text-purple mb-2" style="color: #8b5cf6;"><i class="fa fa-tasks me-1"></i> Assignment Task</h6>
                                <p class="small text-muted mb-0">{{ $w['assignment'] }}</p>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Quizzes -->
                    @if(!empty($w['mcqs']))
                    <div class="mt-3 p-3 bg-white border rounded-3">
                        <h6 class="fw-bold text-danger mb-2"><i class="fa fa-check-square me-1"></i> Knowledge Check MCQs ({{ count($w['mcqs']) }})</h6>
                        @foreach($w['mcqs'] as $q)
                            <div class="mb-2 p-2 border-bottom">
                                <p class="small fw-bold mb-1">{{ $q['question'] }}</p>
                                <div class="small text-muted ms-2">
                                    @foreach($q['options'] as $opt)
                                        <div>{{ $opt }}</div>
                                    @endforeach
                                    <div class="mt-1 text-success fw-semibold">✔ Answer: {{ $q['answer'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
