<?php
/**
 * Page d'inscription pour les patients uniquement
 * Gestion Laboratoire Médical
 */

require_once __DIR__ . '/includes/functions.php';

// Si déjà connecté, rediriger vers le dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize($_POST['nom'] ?? '');
    $prenom = sanitize($_POST['prenom'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $telephone = sanitize($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $pdo = getConnection();
        
        // Vérifier si l'email existe déjà
        $checkStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $error = 'Cet email est déjà utilisé. Veuillez vous connecter ou utiliser un autre email.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Créer le patient dans la table patients
                $patientNumber = generatePatientNumber();
                $stmtPatient = $pdo->prepare("
                    INSERT INTO patients (numero_dossier, nom, prenom, date_naissance, sexe, telephone, email)
                    VALUES (?, ?, ?, '1990-01-01', 'M', ?, ?)
                ");
                $stmtPatient->execute([$patientNumber, $nom, $prenom, $telephone, $email]);
                $patientId = $pdo->lastInsertId();
                
                // Créer l'utilisateur avec le rôle patient
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("
                    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, role, statut, patient_id)
                    VALUES (?, ?, ?, ?, ?, 'patient', 'actif', ?)
                ");
                $stmtUser->execute([$nom, $prenom, $email, $hashedPassword, $telephone, $patientId]);
                
                $pdo->commit();
                
                $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de l\'inscription. Veuillez réessayer.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="login-container">
        <div class="login-card" style="max-width: 500px;">
            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <h1 class="login-title">LaboPro</h1>
                <p class="login-subtitle">Créer un compte patient</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <div class="text-center mb-3">
                    <a href="login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                    </a>
                </div>
            <?php else: ?>
            
            <form method="POST" action="" class="login-form">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="prenom" class="form-label">
                            <i class="bi bi-person me-1"></i>Prénom <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="prenom" name="prenom" 
                               placeholder="Votre prénom" required
                               value="<?= isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : '' ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="nom" class="form-label">
                            <i class="bi bi-person me-1"></i>Nom <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               placeholder="Votre nom" required
                               value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-1"></i>Adresse email <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="votre@email.com" required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                
                <div class="mb-3">
                    <label for="telephone" class="form-label">
                        <i class="bi bi-phone me-1"></i>Téléphone
                    </label>
                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                           placeholder="06 00 00 00 00"
                           value="<?= isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : '' ?>">
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i>Mot de passe <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" 
                               name="password" placeholder="Minimum 6 caractères" required minlength="6">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        <i class="bi bi-lock-fill me-1"></i>Confirmer le mot de passe <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" 
                               name="confirm_password" placeholder="Confirmez votre mot de passe" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-person-plus me-2"></i>S'inscrire
                </button>
            </form>
            
            <?php endif; ?>
            
            <div class="login-footer">
                <p class="text-muted mb-0">
                    Vous avez déjà un compte ? 
                    <a href="login.php" class="text-primary">Se connecter</a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>
