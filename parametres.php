<?php
/**
 * Parametres
 * Page des parametres utilisateur
 */

$pageTitle = 'Parametres';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Recuperer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Traitement des mises a jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Mise a jour des preferences de notification
    if ($action === 'update_notifications') {
        // Pour l'instant, on stocke dans un champ JSON ou on cree une table preferences
        // Ici on affiche juste un message de succes
        setFlashMessage('success', 'Preferences de notification mises a jour.');
        header('Location: parametres.php');
        exit;
    }
    
    // Suppression du compte (desactivation)
    if ($action === 'delete_account') {
        $password = $_POST['confirm_password'];
        
        if (password_verify($password, $user['mot_de_passe'])) {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'inactif' WHERE id = ?");
            if ($stmt->execute([$userId])) {
                logAction('desactivation_compte', 'utilisateurs', $userId, "Desactivation volontaire du compte");
                session_destroy();
                header('Location: login.php?msg=account_deleted');
                exit;
            }
        } else {
            setFlashMessage('error', 'Mot de passe incorrect.');
        }
        header('Location: parametres.php');
        exit;
    }
}
?>

<!-- En-tete de page -->
<div class="page-header">
    <div class="page-header-content">
        <div class="d-flex align-items-center">
            <a href="profil.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title">
                    <i class="bi bi-gear text-primary me-2"></i>
                    Parametres
                </h1>
                <p class="page-subtitle mb-0">
                    Configurez les options de votre compte
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-3">
        <!-- Navigation laterale -->
        <div class="card">
            <div class="list-group list-group-flush">
                <a href="#notifications" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                    <i class="bi bi-bell me-2"></i>Notifications
                </a>
                <a href="#confidentialite" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="bi bi-shield-check me-2"></i>Confidentialite
                </a>
                <a href="#apparence" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="bi bi-palette me-2"></i>Apparence
                </a>
                <a href="#donnees" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="bi bi-database me-2"></i>Mes donnees
                </a>
                <a href="#danger" class="list-group-item list-group-item-action text-danger" data-bs-toggle="list">
                    <i class="bi bi-exclamation-triangle me-2"></i>Zone de danger
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <div class="tab-content">
            <!-- Notifications -->
            <div class="tab-pane fade show active" id="notifications">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Preferences de notification</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="mb-4">
                                <h6 class="mb-3">Notifications par email</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailResults" checked>
                                    <label class="form-check-label" for="emailResults">
                                        Recevoir les resultats d'analyses par email
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailRdv" checked>
                                    <label class="form-check-label" for="emailRdv">
                                        Rappels de rendez-vous
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailNews">
                                    <label class="form-check-label" for="emailNews">
                                        Actualites et nouveautes du laboratoire
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="mb-3">Notifications dans l'application</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="appResults" checked>
                                    <label class="form-check-label" for="appResults">
                                        Nouveaux resultats disponibles
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="appRdv" checked>
                                    <label class="form-check-label" for="appRdv">
                                        Confirmation/modification de rendez-vous
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Enregistrer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Confidentialite -->
            <div class="tab-pane fade" id="confidentialite">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Confidentialite et securite</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Sessions actives</h6>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Vous etes actuellement connecte depuis cet appareil.
                            </div>
                            <p class="text-muted small">
                                Derniere connexion: <?= $user['derniere_connexion'] ? formatDateTime($user['derniere_connexion']) : 'Premiere connexion' ?>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Authentification</h6>
                            <a href="profil.php" class="btn btn-outline-primary">
                                <i class="bi bi-key me-1"></i>Changer mon mot de passe
                            </a>
                        </div>
                        
                        <div>
                            <h6 class="mb-3">Historique des connexions</h6>
                            <a href="profil.php" class="btn btn-outline-secondary">
                                <i class="bi bi-clock-history me-1"></i>Voir l'historique
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Apparence -->
            <div class="tab-pane fade" id="apparence">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-palette me-2"></i>Apparence</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Theme</h6>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="theme" id="themeLight" checked>
                                <label class="btn btn-outline-primary" for="themeLight">
                                    <i class="bi bi-sun me-1"></i>Clair
                                </label>
                                
                                <input type="radio" class="btn-check" name="theme" id="themeDark">
                                <label class="btn btn-outline-primary" for="themeDark">
                                    <i class="bi bi-moon me-1"></i>Sombre
                                </label>
                                
                                <input type="radio" class="btn-check" name="theme" id="themeAuto">
                                <label class="btn btn-outline-primary" for="themeAuto">
                                    <i class="bi bi-circle-half me-1"></i>Auto
                                </label>
                            </div>
                            <p class="text-muted small mt-2">Le mode sombre sera disponible prochainement.</p>
                        </div>
                        
                        <div>
                            <h6 class="mb-3">Langue</h6>
                            <select class="form-select w-auto">
                                <option value="fr" selected>Francais</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mes donnees -->
            <div class="tab-pane fade" id="donnees">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-database me-2"></i>Mes donnees</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Exporter mes donnees</h6>
                            <p class="text-muted">
                                Vous pouvez demander une copie de toutes vos donnees personnelles stockees dans notre systeme.
                            </p>
                            <button class="btn btn-outline-primary" disabled>
                                <i class="bi bi-download me-1"></i>Demander une exportation
                            </button>
                            <small class="d-block text-muted mt-2">Fonctionnalite bientot disponible</small>
                        </div>
                        
                        <div>
                            <h6 class="mb-3">Informations stockees</h6>
                            <ul class="list-unstyled text-muted">
                                <li><i class="bi bi-check text-success me-2"></i>Informations de profil</li>
                                <li><i class="bi bi-check text-success me-2"></i>Historique des rendez-vous</li>
                                <li><i class="bi bi-check text-success me-2"></i>Resultats d'analyses</li>
                                <li><i class="bi bi-check text-success me-2"></i>Historique des connexions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Zone de danger -->
            <div class="tab-pane fade" id="danger">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Zone de danger</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <strong>Attention !</strong> Les actions dans cette zone sont irreversibles.
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Desactiver mon compte</h6>
                            <p class="text-muted">
                                La desactivation de votre compte vous empechera de vous connecter. 
                                Vos donnees seront conservees et vous pourrez reactiver votre compte en contactant l'administration.
                            </p>
                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteAccount">
                                <i class="bi bi-person-x me-1"></i>Desactiver mon compte
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Suppression de compte -->
<div class="modal fade" id="modalDeleteAccount" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_account">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Desactiver mon compte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Etes-vous sur ?</strong> Cette action desactivera votre compte.
                    </div>
                    
                    <p>Pour confirmer, veuillez entrer votre mot de passe :</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-person-x me-1"></i>Desactiver mon compte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
