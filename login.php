<?php
/**
 * Page de connexion
 * Gestion Laboratoire Médical
 */

require_once __DIR__ . '/includes/functions.php';

// Si déjà connecté, rediriger vers le dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (authenticate($email, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Email ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <h1 class="login-title">LaboPro</h1>
                <p class="login-subtitle">Gestion de Laboratoire Médical</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-1"></i>Adresse email
                    </label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                           placeholder="votre@email.com" required autofocus
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i>Mot de passe
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control form-control-lg" id="password" 
                               name="password" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                </button>
            </form>
            
            <div class="login-footer">
                <p class="text-muted mb-2">Comptes de démonstration :</p>
                <small class="text-muted d-block">Admin: admin@labo.com</small>
                <small class="text-muted d-block">Réceptionniste: receptionniste@labo.com</small>
                <small class="text-muted d-block">Technicien: technicien@labo.com</small>
                <small class="text-muted d-block mt-1"><em>Mot de passe: password</em></small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    </script>
</body>
</html>
