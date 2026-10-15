@use('App\Models\Setting')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NextGen LMS Course Automator' }}</title>
    <!-- Modern Font: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS for clean utilities & fast layout -->
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
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.3px;
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
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 brand-font fs-4 fw-bold" href="{{ route('dashboard') }}">
                <span style="background: linear-gradient(135deg, #6366f1, #0ea5e9); padding: 8px 12px; border-radius: 10px; color: white;">
                    <i class="fa fa-graduation-cap"></i>
                </span>
                <span>NextGen <span style="color: #38bdf8;">Studio</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item">
                        <a class="nav-link px-3 {{ request()->routeIs('dashboard') ? 'active fw-bold text-white' : '' }}" href="{{ route('dashboard') }}">
                            <i class="fa fa-dashboard me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 {{ request()->routeIs('settings') ? 'active fw-bold text-white' : '' }}" href="{{ route('settings') }}">
                            <i class="fa fa-sliders me-1"></i> Multi-Settings
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a href="{{ Setting::get('moodle_url', 'http://elearn.local') }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3">
                            <i class="fa fa-external-link me-1"></i> Open Moodle
                        </a>
                    </li>
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
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center" role="alert">
                <i class="fa fa-exclamation-triangle fs-5 me-2"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <p class="mb-0">NextGen LMS Course Provisioning Studio &bull; Google Drive &bull; Multi-Storage (Drive, Bunny, R2, S3) &bull; Moodle Web Services</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
