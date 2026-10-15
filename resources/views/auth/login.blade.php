<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &bull; MoodleAutoFlow LMS Automation</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome & Bootstrap -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #0ea5e9;
            --dark-bg: #0b0f19;
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at 15% 50%, rgba(79, 70, 229, 0.25), transparent 45%),
                        radial-gradient(circle at 85% 30%, rgba(14, 165, 233, 0.2), transparent 45%),
                        linear-gradient(135deg, #0b0f19 0%, #111827 50%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        h1, h2, h3, h4, h5, .brand-font {
            font-family: 'Outfit', sans-serif;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .login-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
            padding: 32px 30px;
            text-align: center;
            color: #ffffff;
            position: relative;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #0ea5e9, #10b981);
        }

        .logo-badge {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.4);
            margin-bottom: 14px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            border: none;
            color: #ffffff;
            font-weight: 600;
            padding: 13px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
            color: #ffffff;
        }

        .quick-badge {
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.75rem;
            padding: 6px 10px;
            border-radius: 8px;
        }

        .quick-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="logo-badge">
            <i class="fa fa-graduation-cap fa-2x text-white"></i>
        </div>
        <h3 class="fw-bold brand-font mb-1">MoodleAutoFlow</h3>
        <p class="text-white-50 small mb-0">Multi-Tenant University Course Provisioning Studio</p>
    </div>

    <div class="p-4 p-md-5">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                <i class="fa fa-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
                <i class="fa fa-exclamation-circle me-1"></i> {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf
            
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Work Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa fa-envelope"></i></span>
                    <input type="email" name="email" id="inputEmail" class="form-control" value="{{ old('email', 'milton@nextgen.edu') }}" required autofocus placeholder="name@institution.edu">
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label small fw-semibold text-secondary">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa fa-lock"></i></span>
                    <input type="password" name="password" id="inputPassword" class="form-control" value="Diu@123456" required placeholder="••••••••">
                    <button class="btn btn-outline-secondary" type="button" id="togglePass">
                        <i class="fa fa-eye" id="passIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" checked>
                    <label class="form-check-label small text-muted" for="remember">
                        Remember this device
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 mb-4">
                <i class="fa fa-sign-in-alt me-2"></i> Access University Workspace
            </button>
        </form>

        <!-- Quick Demo Switcher -->
        <div class="border-top pt-3 text-center">
            <span class="text-muted small d-block mb-2 fw-semibold">Quick Login by Role:</span>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle quick-badge" onclick="fillCreds('admin@nextgen.edu', 'Admin@123456')">
                    <i class="fa fa-crown me-1"></i> Super Admin
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle quick-badge" onclick="fillCreds('milton@nextgen.edu', 'Diu@123456')">
                    <i class="fa fa-university me-1"></i> Uni Admin (Milton)
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle quick-badge" onclick="fillCreds('instructor@nextgen.edu', 'Teacher@123456')">
                    <i class="fa fa-chalkboard-teacher me-1"></i> Instructor
                </span>
            </div>
        </div>
    </div>
</div>

<script>
    function fillCreds(email, pass) {
        document.getElementById('inputEmail').value = email;
        document.getElementById('inputPassword').value = pass;
    }

    document.getElementById('togglePass').addEventListener('click', function() {
        const input = document.getElementById('inputPassword');
        const icon = document.getElementById('passIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fa fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fa fa-eye';
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
