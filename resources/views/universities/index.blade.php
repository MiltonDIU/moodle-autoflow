@extends('layouts.app', ['title' => 'Universities & Institutions'])

@section('content')
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-danger text-white"><i class="fa fa-crown me-1"></i> Super Admin Area</span>
            <span class="badge bg-light text-secondary border">Multi-Institution Governance</span>
        </div>
        <h3 class="fw-bold brand-font mb-0 text-dark">
            <i class="fa fa-university text-primary me-2"></i> University & Institution Management
        </h3>
        <p class="text-muted small mb-0 mt-1">Manage institutional workspaces. Each university maintains its own isolated Moodle LMS credentials, AI models, cloud storage, and course inventory.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-primary-custom px-4 py-2 shadow-sm" onclick="toggleNewUniForm()">
            <i class="fa fa-plus-circle me-1"></i> <span id="toggleBtnText">Register New University</span>
        </button>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fa fa-exclamation-circle me-2"></i> <strong>Validation Error:</strong> {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- ================= EMBEDDED REGISTER NEW UNIVERSITY CARD (TOGGLEABLE & DIRECT) ================= -->
<div id="newUniFormCard" class="custom-card p-4 mb-4 border-primary shadow-sm {{ $errors->any() ? '' : 'd-none' }}" style="border-top: 4px solid #4f46e5;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="badge bg-primary text-white mb-1">New Tenant Provisioning</span>
            <h5 class="fw-bold brand-font text-dark mb-0">
                <i class="fa fa-plus-circle text-primary me-2"></i> Register New University Workspace
            </h5>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleNewUniForm()">
            <i class="fa fa-times"></i> Close Form
        </button>
    </div>
    <p class="text-muted small">Enter the institutional details below. Default Moodle and AI configuration templates will be automatically cloned for this institution.</p>

    <form action="{{ route('universities.store') }}" method="POST">
        @csrf
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">University / Institution Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Daffodil International University" value="{{ old('name') }}" required>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold">Institutional Web Domain</label>
                <input type="text" name="domain" class="form-control font-monospace" placeholder="e.g. daffodilvarsity.edu.bd" value="{{ old('domain') }}">
                <small class="text-muted">Domain identification (e.g. university.edu).</small>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold">Admin / Contact Email</label>
                <input type="email" name="contact_email" class="form-control" placeholder="e.g. lms@daffodilvarsity.edu.bd" value="{{ old('contact_email') }}">
            </div>

            <div class="col-12 mt-3">
                <div class="alert alert-light border small text-muted mb-0 d-flex align-items-center">
                    <i class="fa fa-info-circle text-primary fs-5 me-2"></i>
                    <div>
                        <strong>Automatic Template Cloning:</strong> When you register this university, all 25 system configurations (Moodle REST placeholders, AI extraction defaults, video storage engine templates) are automatically provisioned.
                    </div>
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <button type="button" class="btn btn-outline-secondary me-2" onclick="toggleNewUniForm()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom px-5 py-2">
                    <i class="fa fa-save me-1"></i> Save & Provision University
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ================= LIST OF UNIVERSITIES ================= -->
<div class="row g-4">
    @foreach($universities as $uni)
        <div class="col-md-6 col-lg-4">
            <div class="custom-card p-4 h-100 position-relative {{ $uni->id == $activeUniId ? 'border-primary shadow' : '' }}" style="{{ $uni->id == $activeUniId ? 'border: 2px solid #4f46e5;' : '' }}">
                @if($uni->id == $activeUniId)
                    <span class="position-absolute top-0 end-0 m-3 badge bg-primary shadow-sm">
                        <i class="fa fa-check-circle me-1"></i> Active Workspace
                    </span>
                @endif

                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 p-3 bg-light border me-3 text-primary fs-3">
                        <i class="fa fa-building-columns"></i>
                    </div>
                    <div style="max-width: 200px;">
                        <h5 class="fw-bold mb-0 text-dark text-truncate" title="{{ $uni->name }}">{{ $uni->name }}</h5>
                        <small class="text-muted font-monospace">{{ $uni->domain ?? 'No domain set' }}</small>
                    </div>
                </div>

                <div class="row g-2 text-center py-3 border-top border-bottom my-3 bg-light rounded-3">
                    <div class="col-6">
                        <span class="text-muted small d-block">Courses Ingested</span>
                        <span class="fs-5 fw-bold text-dark">{{ $uni->courses_count }}</span>
                    </div>
                    <div class="col-6 border-start">
                        <span class="text-muted small d-block">Faculty / Staff</span>
                        <span class="fs-5 fw-bold text-dark">{{ $uni->users_count }}</span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="badge {{ $uni->status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary' }}">
                        <i class="fa fa-circle fa-2xs me-1"></i> {{ ucfirst($uni->status) }}
                    </span>

                    @if($uni->id != $activeUniId)
                        <form action="{{ route('universities.switch', $uni->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold">
                                <i class="fa fa-arrow-right-arrow-left me-1"></i> Switch to Workspace
                            </button>
                        </form>
                    @else
                        <a href="{{ route('settings') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-sliders me-1"></i> Configurations
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
    function toggleNewUniForm() {
        const card = document.getElementById('newUniFormCard');
        const btnText = document.getElementById('toggleBtnText');
        if (card.classList.contains('d-none')) {
            card.classList.remove('d-none');
            card.scrollIntoView({ behavior: 'smooth' });
            btnText.innerText = 'Close Form';
        } else {
            card.classList.add('d-none');
            btnText.innerText = 'Register New University';
        }
    }
</script>
@endpush
@endsection
