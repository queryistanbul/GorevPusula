<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

if (Auth::check()) {
    redirect('/public/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (Auth::attempt($email, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'E-posta veya şifre hatalı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Task System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-image: url('img/login_bg.png') !important;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Override auth-container for this page specifically */
        .auth-container {
            justify-content: flex-start !important;
            /* Align to start (left) */
            padding-left: 150px;
        }

        /* Mobile responsiveness adjustment */
        @media (max-width: 768px) {
            .auth-container {
                justify-content: center !important;
                padding-left: 20px;
                padding-right: 20px;
            }
        }
    </style>
</head>

<body>

    <div class="auth-container">
        <div class="glass glass-card auth-card">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="img/logo.png" alt="Logo" style="max-height: 50px;">
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>E-Posta Adresi</label>
                    <input type="email" name="email" required placeholder="ornek@sirket.com">
                </div>

                <div class="form-group">
                    <label>Şifre</label>
                    <input type="password" name="password" required placeholder="******">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
            </form>

            <div class="auth-footer">
                <span style="color: var(--text-muted);">Hesabınız yok mu?</span> <a href="#" class="auth-link">Yönetici
                    ile iletişime geçin</a>
            </div>
        </div>
    </div>

</body>

</html>