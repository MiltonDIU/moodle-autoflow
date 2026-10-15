@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa fa-book text-primary me-2"></i> Course Preview: {{ $course->name }}</h3>
        <span class="badge bg-secondary">{{ $course->course_code }}</span>
        @if($course->moodle_course_id)
            <a href="http://elearn.local/course/view.php?id={{ $course->moodle_course_id }}" target="_blank" class="badge bg-success text-decoration-none">
                <i class="fa fa-external-link me-1"></i> Open in Moodle (ID: {{ $course->moodle_course_id }})
            </a>
        @endif
        @if($course->start_date)
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary ms-1">
                <i class="fa fa-calendar-alt me-1"></i> Semester: {{ $course->start_date->format('M d, Y') }} — {{ $course->start_date->copy()->addWeeks(16)->format('M d, Y') }} (16 Weeks)
            </span>
        @endif
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>

@if(empty($course->extracted_data))
    <div class="alert alert-info">
        <i class="fa fa-info-circle me-1"></i> No curriculum has been extracted for this course yet. Click "Process & Sync to Moodle" on the dashboard.
    </div>
@else
    <div class="accordion" id="weeksAccordion">
        @foreach($course->extracted_data as $wnum => $w)
        <div class="accordion-item mb-3 border rounded shadow-sm overflow-hidden">
            <h2 class="accordion-header" id="heading{{ $wnum }}">
                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $wnum }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                    <span class="badge bg-primary me-2">Week {{ sprintf('%02d', $wnum) }}</span>
                    <span class="me-auto">{{ $w['title'] ?? "Week {$wnum}" }}</span>
                    @if($course->start_date)
                        @php
                            $wStart = $course->start_date->copy()->addWeeks($wnum - 1);
                            $wEnd = $wStart->copy()->addDays(6);
                        @endphp
                        <span class="badge bg-light text-primary border me-3" style="font-size: 0.8rem; font-weight: 500;">
                            <i class="fa fa-calendar-day me-1"></i> {{ $wStart->format('M d') }} - {{ $wEnd->format('M d, Y') }}
                        </span>
                    @endif
                </button>
            </h2>
            <div id="collapse{{ $wnum }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#weeksAccordion">
                <div class="accordion-body bg-light">
                    
                    <!-- Overview -->
                    <div class="card p-3 mb-3 border-start border-primary border-4 bg-white">
                        <h6 class="fw-bold text-primary mb-2"><i class="fa fa-info-circle me-1"></i> Module Overview</h6>
                        <p class="text-muted mb-0" style="line-height: 1.6;">{!! nl2br(e($w['overview'] ?? 'No overview available.')) !!}</p>
                    </div>

                    <!-- Learning Objectives & Key Terms -->
                    <div class="row g-3 mb-3">
                        @if(!empty($w['objectives']))
                        <div class="col-md-6">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <h6 class="fw-bold text-success mb-2"><i class="fa fa-bullseye me-1"></i> Student Learning Objectives</h6>
                                <ul class="small text-muted mb-0 ps-3">
                                    @foreach($w['objectives'] as $obj)
                                        <li class="mb-1">{{ $obj }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif

                        @if(!empty($w['key_terms']))
                        <div class="col-md-6">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <h6 class="fw-bold text-info mb-2"><i class="fa fa-key me-1"></i> Key Terms & Definitions</h6>
                                <ul class="small text-muted mb-0 ps-3">
                                    @foreach($w['key_terms'] as $term)
                                        <li class="mb-1">{{ $term }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Videos -->
                    @if(isset($course->video_map[$wnum]) && count($course->video_map[$wnum]) > 0)
                    <div class="card p-3 mb-3 border-start border-indigo border-4 bg-white">
                        <h6 class="fw-bold text-primary mb-2"><i class="fa fa-video-camera me-1"></i> Lecture & Video Lessons ({{ count($course->video_map[$wnum]) }})</h6>
                        <div class="row g-2">
                            @foreach($course->video_map[$wnum] as $v)
                            <div class="col-md-6">
                                <div class="p-2 border rounded d-flex align-items-center justify-content-between">
                                    <span class="small text-truncate me-2"><i class="fa fa-play-circle text-danger me-1"></i> {{ $v['filename'] }}</span>
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
                                <h6 class="fw-bold text-warning mb-2"><i class="fa fa-comments me-1"></i> Discussion Prompt (Real Forum Activity)</h6>
                                <p class="small text-muted mb-0" style="line-height: 1.5;">{{ is_array($w['discussion']) ? ($w['discussion']['prompt'] ?? '') : $w['discussion'] }}</p>
                            </div>
                        </div>
                        @endif

                        @if(!empty($w['assignment']))
                        <div class="col-md-6">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <h6 class="fw-bold text-purple mb-2" style="color: #8b5cf6;"><i class="fa fa-tasks me-1"></i> Weekly Assignment (PDF/Word & Online Text Submission)</h6>
                                <p class="small text-muted mb-1" style="line-height: 1.5;">{{ is_array($w['assignment']) ? ($w['assignment']['task'] ?? '') : $w['assignment'] }}</p>
                                @if(is_array($w['assignment']) && !empty($w['assignment']['blooms_level']))
                                    <span class="badge bg-light text-success border mt-2">Bloom's: {{ $w['assignment']['blooms_level'] }}</span>
                                @endif
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
                                    @foreach($q['options'] as $optKey => $optVal)
                                        <div>{{ is_numeric($optKey) ? $optVal : ($optKey . ') ' . $optVal) }}</div>
                                    @endforeach
                                    <div class="mt-1 text-success fw-semibold">✔ Answer: {{ $q['answer'] }}</div>
                                    @if(!empty($q['explanation']))
                                        <div class="small text-secondary mt-1"><em>Note: {{ $q['explanation'] }}</em></div>
                                    @endif
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
