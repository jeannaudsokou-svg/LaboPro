<?php
/**
 * Mon Profil
 * Page de gestion du profil utilisateur
 */

$pageTitle = 'Mon Profil';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Recuperer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Recuperer les informations du patient si c'est un patient
$patient = null;
if ($userRole === 'patient' && $user['patient_id']) {
    $stmtPatient = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmtPatient->execute([$user['patient_id']]);
    $patient = $stmtPatient->fetch();
}

// Traitement des mises a jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Mise a jour des informations personnelles
    if ($action === 'update_profile') {
        $prenom = sanitize($_POST['prenom']);
        $nom = sanitize($_POST['nom']);
        $telephone = sanitize($_POST['telephone']);
        
        $stmt = $pdo->prepare("UPDATE utilisateurs SET prenom = ?, nom = ?, telephone = ? WHERE id = ?");
        
        if ($stmt->execute([$prenom, $nom, $telephone, $userId])) {
            // Mettre a jour la session
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_nom'] = $nom;
            
            logAction('modification_profil', 'utilisateurs', $userId, "Mise a jour du profil");
            setFlashMessage('success', 'Profil mis a jour avec succes.');
        } else {
            setFlashMessage('error', 'Erreur lors de la mise a jour du profil.');
        }
        header('Location: profil.php');
        exit;
    }
    
    // Changement de mot de passe
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verifier le mot de passe actuel
        if (!password_verify($currentPassword, $user['mot_de_passe'])) {
            setFlashMessage('error', 'Le mot de passe actuel est incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            setFlashMessage('error', 'Les nouveaux mots de passe ne correspondent pas.');
        } elseif (strlen($newPassword) < 6) {
            setFlashMessage('error', 'Le nouveau mot de passe doit contenir au moins 6 caracteres.');
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            
            if ($stmt->execute([$hashedPassword, $userId])) {
                logAction('changement_mdp', 'utilisateurs', $userId, "Changement de mot de passe");
                setFlashMessage('success', 'Mot de passe modifie avec succes.');
            } else {
                setFlashMessage('error', 'Erreur lors du changement de mot de passe.');
            }
        }
        header('Location: profil.php');
        exit;
    }
}

// Historique des connexions (dernieres 10)
$historyStmt = $pdo->prepare("
    SELECT * FROM historique_actions 
    WHERE utilisateur_id = ? AND action IN ('connexion', 'deconnexion')
    ORDER BY date_action DESC LIMIT 10
");
$historyStmt->execute([$userId]);
$loginHistory = $historyStmt->fetchAll();
?>

<!-- En-tete de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-person-circle text-primary me-2"></i>
            Mon Profil
        </h1>
        <p class="page-subtitle">
            Gerez vos informations personnelles et parametres de compte
        </p>
    </div>
    <div class="page-actions">
        <a href="parametres.php" class="btn btn-outline-primary">
            <i class="bi bi-gear me-1"></i>Parametres
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Colonne gauche - Info utilisateur -->
    <div class="col-lg-4">
        <!-- Carte profil -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="avatar avatar-xl mx-auto mb-3 bg-<?= match($userRole) {
                    'administrateur' => 'primary',
                    'technicien' => 'success',
                    'receptionniste' => 'info',
                    'patient' => 'warning',
                    default => 'secondary'
                } ?>" style="width: 100px; height: 100px; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                    <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                </div>
                <h4 class="mb-1"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h4>
                <span class="badge bg-<?= match($userRole) {
                    'administrateur' => 'primary',
                    'technicien' => 'success',
                    'receptionniste' => 'info',
                    'patient' => 'warning',
                    default => 'secondary'
                } ?> mb-3"><?= ucfirst($userRole) ?></span>
                
                <div class="text-start mt-3">
                    <p class="mb-2">
                        <i class="bi bi-envelope text-muted me-2"></i>
                        <?= htmlspecialchars($user['email']) ?>
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-telephone text-muted me-2"></i>
                        <?= htmlspecialchars($user['telephone'] ?: 'Non renseigne') ?>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-calendar3 text-muted me-2"></i>
                        Membre depuis <?= formatDate($user['date_creation']) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <?php if ($patient): ?>
        <!-- Informations patient -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-medical me-2"></i>Dossier Medical</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <small class="text-muted d-block">Numero de dossier</small>
                        <code><?= htmlspecialchars($patient['numero_dossier']) ?></code>
                    </li>
                    <li class="mb-2">
                        <small class="text-muted d-block">Date de naissance</small>
                        <strong><?= formatDate($patient['date_naissance']) ?></strong>
                        <span class="text-muted">(<?= calculateAge($patient['date_naissance']) ?> ans)</span>
                    </li>
                    <li class="mb-2">
                        <small class="text-muted d-block">Sexe</small>
                        <strong><?= $patient['sexe'] === 'M' ? 'Masculin' : 'Feminin' ?></strong>
                    </li>
                    <?php if ($patient['groupe_sanguin']): ?>
                    <li class="mb-2">
                        <small class="text-muted d-block">Groupe sanguin</small>
                        <span class="badge bg-danger"><?= htmlspecialchars($patient['groupe_sanguin']) ?></span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <small class="text-muted d-block">Adresse</small>
                        <strong><?= htmlspecialchars($patient['adresse'] ?: 'Non renseignee') ?></strong>
                    </li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Activite recente -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Activite recente</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($loginHistory)): ?>
                    <p class="text-muted p-3 mb-0">Aucune activite enregistree</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($loginHistory as $log): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-<?= $log['action'] === 'connexion' ? 'box-arrow-in-right text-success' : 'box-arrow-right text-danger' ?> me-2"></i>
                                        <?= ucfirst($log['action']) ?>
                                    </div>
                                    <small class="text-muted"><?= formatDateTime($log['date_action']) ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Colonne droite - Formulaires -->
    <div class="col-lg-8">
        <!-- Informations personnelles -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Informations personnelles</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prenom</label>
                            <input type="text" class="form-control" name="prenom" 
                                   value="<?= htmlspecialchars($user['prenom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            <small class="text-muted">L'email ne peut pas etre modifie.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telephone</label>
                            <input type="tel" class="form-control" name="telephone" 
                                   value="<?= htmlspecialchars($user['telephone'] ?? '') ?>" 
                                   placeholder="+229 XX XX XX XX">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Securite - Mot de passe -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Securite</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                            <small class="text-muted">Minimum 6 caracteres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-1"></i>Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
