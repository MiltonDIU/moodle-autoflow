@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold brand-font mb-1">
            <i class="fa fa-university text-primary me-2"></i> University & Institution Management
        </h3>
        <p class="text-muted small mb-0">Manage multi-tenant higher education institutions. Each university maintains its own isolated Moodle connection, AI models, cloud storage, and course inventory.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary-custom shadow-sm" data-bs-toggle="modal" data-bs-target="#newUniModal">
            <i class="fa fa-plus-circle me-1"></i> Register New University
        </button>
    </div>
</div>

<div class="row g-4">
    @foreach($universities as $uni)
        <div class="col-md-6 col-lg-4">
            <div class="custom-card p-4 h-100 position-relative {{ $uni->id == $activeUniId ? 'border-primary shadow' : '' }}" style="{{ $uni->id == $activeUniId ? 'border: 2px solid #4f46e5;' : '' }}">
                @if($uni->id == $activeUniId)
                    <span class="position-absolute top-0 end-0 m-3 badge bg-primary">
                        <i class="fa fa-check-circle me-1"></i> Active Workspace
                    </span>
                @endif

                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 p-3 bg-light border me-3 text-primary fs-3">
                        <i class="fa fa-building-columns"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">{{ $uni->name }}</h5>
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
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-arrow-right-arrow-left me-1"></i> Switch Workspace
                            </button>
                        </form>
                    @else
                        <a href="{{ route('settings') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-cog me-1"></i> Manage Settings
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Register New University Modal -->
<div class="modal fade" id="newUniModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold brand-font text-dark">
                    <i class="fa fa-plus-circle text-primary me-2"></i> Register New University
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('universities.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">University / Institution Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Daffodil International University" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Institutional Web Domain</label>
                        <input type="text" name="domain" class="form-control" placeholder="e.g. daffodilvarsity.edu.bd">
                        <small class="text-muted">Used for institutional identification and email mapping.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Primary Contact / Admin Email</label>
                        <input type="email" name="contact_email" class="form-control" placeholder="e.g. lms-admin@daffodilvarsity.edu.bd">
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fa fa-info-circle me-1"></i> <strong>Automated Provisioning:</strong> Default course templates and Moodle web service placeholders will be cloned automatically for this institution.
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-4">
                        <i class="fa fa-save me-1"></i> Create University Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
