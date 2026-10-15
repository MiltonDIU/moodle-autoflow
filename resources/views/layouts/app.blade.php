@use('App\Models\Setting')
@use('App\Models\University')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MoodleAutoFlow &bull; NextGen LMS Course Automator' }}</title>
    <!-- Modern Font: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #0ea5e9;
            --dark-bg: #0f172a;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4, h5, h6, .brand-font {
            font-family: 'Outfit', sans-serif;
        }

        .navbar-custom {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            transition: all 0.25s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.08);
            border-color: #c7d2fe;
        }

        .custom-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            border: none;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-primary-custom:hover {
            opacity: 0.95;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .badge-completed { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-processing { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge-discovered { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-failed { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        .footer {
            margin-top: auto;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 16px 0;
            font-size: 0.9rem;
            color: #64748b;
        }

        .uni-pill {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border-radius: 30px;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .uni-pill:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
    </style>
</head>
<body>

    @php
        $activeUniId = session('active_university_id', auth()->user()?->university_id ?: 1);
        $currentUni = University::find($activeUniId) ?: University::first();
        $allUniversities = University::where('status', 'active')->get();
    @endphp

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 brand-font fs-4 fw-bold" href="{{ route('dashboard') }}">
                <span style="background: linear-gradient(135deg, #6366f1, #0ea5e9); padding: 8px 12px; border-radius: 10px; color: white;">
                    <i class="fa fa-graduation-cap"></i>
                </span>
                <span>Moodle<span style="color: #38bdf8;">AutoFlow</span></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navMenu">
                <!-- University Selector / Badge -->
                <div class="ms-lg-4 my-2 my-lg-0">
                    @if(auth()->check() && auth()->user()->isSuperAdmin())
                        <div class="dropdown">
                            <button class="btn uni-pill dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                                <i class="fa fa-building-columns text-warning"></i>
                                <span>{{ $currentUni->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark shadow-lg border-0" style="border-radius: 12px;">
                                <li class="dropdown-header text-uppercase small text-muted">Switch Institutional Workspace</li>
                                @foreach($allUniversities as $u)
                                    <li>
                                        <form action="{{ route('universities.switch', $u->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $u->id == $currentUni->id ? 'active' : '' }}">
                                                <span><i class="fa fa-university me-2"></i> {{ $u->name }}</span>
                                                @if($u->id == $currentUni->id)
                                                    <i class="fa fa-check text-success"></i>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-primary" href="{{ route('universities.index') }}">
                                        <i class="fa fa-cog me-2"></i> Manage All Universities
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @else
                        <div class="uni-pill d-inline-flex align-items-center gap-2">
                            <i class="fa fa-building-columns text-warning"></i>
                            <span>{{ $currentUni->name }}</span>
                        </div>
                    @endif
                </div>

                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item">
                        <a class="nav-link px-3 {{ request()->routeIs('dashboard') ? 'active fw-bold text-white' : '' }}" href="{{ route('dashboard') }}">
                            <i class="fa fa-dashboard me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 {{ request()->routeIs('settings') ? 'active fw-bold text-white' : '' }}" href="{{ route('settings') }}">
                            @if(auth()->check() && !auth()->user()->isUniversityAdmin() && !auth()->user()->isSuperAdmin())
                                <i class="fa fa-lock me-1 text-warning"></i> Settings <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Read-Only</span>
                            @else
                                <i class="fa fa-sliders me-1"></i> Configurations
                            @endif
                        </a>
                    </li>
                    @if(auth()->check() && auth()->user()->isSuperAdmin())
                        <li class="nav-item">
                            <a class="nav-link px-3 {{ request()->routeIs('universities.*') ? 'active fw-bold text-white' : '' }}" href="{{ route('universities.index') }}">
                                <i class="fa fa-university me-1 text-warning"></i> Institutions <span class="badge bg-danger" style="font-size: 0.65rem;">Super Admin</span>
                            </a>
                        </li>
                    @endif

                    <!-- User Account Dropdown -->
                    @if(auth()->check())
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 text-white" href="#" data-bs-toggle="dropdown">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </div>
                                <div class="text-start d-none d-md-block">
                                    <div class="small fw-semibold lh-1">{{ auth()->user()->name }}</div>
                                    <span class="badge {{ auth()->user()->getRoleBadgeClass() }}" style="font-size: 0.65rem;">
                                        {{ auth()->user()->getRoleDisplayName() }}
                                    </span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px; min-width: 220px;">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="fw-bold text-dark">{{ auth()->user()->name }}</div>
                                    <div class="small text-muted text-truncate">{{ auth()->user()->email }}</div>
                                    <div class="mt-1">
                                        <span class="badge {{ auth()->user()->getRoleBadgeClass() }}">
                                            {{ auth()->user()->getRoleDisplayName() }}
                                        </span>
                                    </div>
                                </li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger py-2">
                                            <i class="fa fa-sign-out-alt me-2"></i> Sign Out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="container my-4 flex-grow-1">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center" role="alert">
                <i class="fa fa-check-circle fs-5 me-2"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center" role="alert">
                <i class="fa fa-exclamation-triangle fs-5 me-2"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <p class="mb-0">MoodleAutoFlow Studio &bull; Multi-Tenant Enterprise Course Automation &bull; NextGen University</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
