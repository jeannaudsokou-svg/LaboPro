<?php
/**
 * Gestion des Utilisateurs
 * Accessible uniquement aux administrateurs
 */

$pageTitle = 'Gestion des Utilisateurs';
require_once 'includes/header.php';
requireRole('administrateur');

$pdo = getConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nom = sanitize($_POST['nom']);
        $prenom = sanitize($_POST['prenom']);
        $email = sanitize($_POST['email']);
        $telephone = sanitize($_POST['telephone']);
        $role = sanitize($_POST['role']);
        $password = $_POST['password'];
        
        // Vérifier si l'email existe déjà
        $checkStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            setFlashMessage('error', 'Cet email est déjà utilisé.');
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, role, statut)
                VALUES (?, ?, ?, ?, ?, ?, 'actif')
            ");
            
            if ($stmt->execute([$nom, $prenom, $email, $hashedPassword, $telephone, $role])) {
                $newId = $pdo->lastInsertId();
                logAction('creation', 'utilisateurs', $newId, "Création de l'utilisateur: $prenom $nom");
                
                // Notifier les administrateurs de la création
                notifyAdminUserCreated($newId, "$prenom $nom", $role);
                
                setFlashMessage('success', 'Utilisateur créé avec succès.');
            } else {
                setFlashMessage('error', 'Erreur lors de la création de l\'utilisateur.');
            }
        }
        header('Location: utilisateurs.php');
        exit;
    }
    
    if ($action === 'update') {
        $id = $_POST['id'];
        $nom = sanitize($_POST['nom']);
        $prenom = sanitize($_POST['prenom']);
        $email = sanitize($_POST['email']);
        $telephone = sanitize($_POST['telephone']);
        $role = sanitize($_POST['role']);
        $statut = sanitize($_POST['statut']);
        
        // Vérifier si l'email existe déjà (pour un autre utilisateur)
        $checkStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $id]);
        
        if ($checkStmt->fetch()) {
            setFlashMessage('error', 'Cet email est déjà utilisé par un autre utilisateur.');
        } else {
            $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ?, role = ?, statut = ?";
            $params = [$nom, $prenom, $email, $telephone, $role, $statut];
            
            // Si un nouveau mot de passe est fourni
            if (!empty($_POST['password'])) {
                $sql .= ", mot_de_passe = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($params)) {
                logAction('modification', 'utilisateurs', $id, "Modification de l'utilisateur: $prenom $nom");
                setFlashMessage('success', 'Utilisateur mis à jour avec succès.');
            } else {
                setFlashMessage('error', 'Erreur lors de la mise à jour.');
            }
        }
        header('Location: utilisateurs.php');
        exit;
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        // Empêcher la suppression de son propre compte
        if ($id == $_SESSION['user_id']) {
            setFlashMessage('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            // Vérifier si l'utilisateur a des actions associées
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM historique_actions WHERE utilisateur_id = ?");
            $checkStmt->execute([$id]);
            
            // Désactiver plutôt que supprimer si des actions existent
            $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'inactif' WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                logAction('desactivation', 'utilisateurs', $id, "Désactivation de l'utilisateur");
                setFlashMessage('success', 'Utilisateur désactivé avec succès.');
            } else {
                setFlashMessage('error', 'Erreur lors de la désactivation.');
            }
        }
        header('Location: utilisateurs.php');
        exit;
    }
}

// Récupérer les utilisateurs
$searchTerm = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterStatut = $_GET['statut'] ?? '';

$sql = "SELECT * FROM utilisateurs WHERE 1=1";
$params = [];

if ($searchTerm) {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($filterRole) {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}

if ($filterStatut) {
    $sql .= " AND statut = ?";
    $params[] = $filterStatut;
}

$sql .= " ORDER BY date_creation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

// Récupérer un utilisateur pour l'édition
$utilisateur = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$id]);
    $utilisateur = $stmt->fetch();
}

// Statistiques
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs,
        SUM(CASE WHEN role = 'administrateur' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'receptionniste' THEN 1 ELSE 0 END) as receptionnistes,
        SUM(CASE WHEN role = 'technicien' THEN 1 ELSE 0 END) as techniciens
    FROM utilisateurs
");
$stats = $statsStmt->fetch();
?>

<!-- En-tête de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-person-badge text-primary"></i>
            Gestion des Utilisateurs
        </h1>
        <p class="page-subtitle">Gérer les comptes utilisateurs du système</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUtilisateur">
            <i class="bi bi-plus-lg me-2"></i>Nouvel Utilisateur
        </button>
    </div>
</div>

<!-- Statistiques -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['actifs'] ?></div>
                <div class="stat-label">Actifs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['admins'] ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['techniciens'] ?></div>
                <div class="stat-label">Techniciens</div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="role">
                    <option value="">Tous les rôles</option>
                    <option value="administrateur" <?= $filterRole === 'administrateur' ? 'selected' : '' ?>>Administrateur</option>
                    <option value="receptionniste" <?= $filterRole === 'receptionniste' ? 'selected' : '' ?>>Réceptionniste</option>
                    <option value="technicien" <?= $filterRole === 'technicien' ? 'selected' : '' ?>>Technicien</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= $filterStatut === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $filterStatut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des utilisateurs -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Liste des utilisateurs (<?= count($utilisateurs) ?>)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($utilisateurs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Aucun utilisateur trouvé</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($utilisateurs as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-<?= $user['role'] === 'administrateur' ? 'primary' : ($user['role'] === 'technicien' ? 'success' : 'info') ?>">
                                        <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-medium"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
                                        <small class="text-muted">Créé le <?= formatDate($user['date_creation']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['telephone'] ?? '-') ?></td>
                            <td>
                                <?php
                                $roleBadge = match($user['role']) {
                                    'administrateur' => 'bg-primary',
                                    'technicien' => 'bg-success',
                                    'receptionniste' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $roleBadge ?>"><?= ucfirst($user['role']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $user['statut'] === 'actif' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= ucfirst($user['statut']) ?>
                                </span>
                            </td>
                            <td><?= $user['derniere_connexion'] ? formatDateTime($user['derniere_connexion']) : 'Jamais' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" 
                                            onclick="editUtilisateur(<?= htmlspecialchars(json_encode($user)) ?>)"
                                            title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>')"
                                                title="Désactiver">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Créer/Modifier Utilisateur -->
<div class="modal fade" id="modalUtilisateur" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formUtilisateur">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="userId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvel Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="prenom" id="userPrenom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" id="userNom" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="userEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" id="userTelephone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="userRole" required>
                                <option value="receptionniste">Réceptionniste</option>
                                <option value="technicien">Technicien</option>
                                <option value="administrateur">Administrateur</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="statutField" style="display: none;">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut" id="userStatut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mot de passe <span class="text-danger" id="passwordRequired">*</span></label>
                            <input type="password" class="form-control" name="password" id="userPassword" minlength="6">
                            <small class="text-muted" id="passwordHelp">Minimum 6 caractères</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmation Suppression -->
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la désactivation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Voulez-vous vraiment désactiver <strong id="deleteName"></strong> ?</p>
                    <p class="text-muted small">L'utilisateur ne pourra plus se connecter.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger btn-sm">Désactiver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Éditer un utilisateur
function editUtilisateur(user) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('userId').value = user.id;
    document.getElementById('userNom').value = user.nom;
    document.getElementById('userPrenom').value = user.prenom;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userTelephone').value = user.telephone || '';
    document.getElementById('userRole').value = user.role;
    document.getElementById('userStatut').value = user.statut;
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').removeAttribute('required');
    document.getElementById('passwordRequired').textContent = '';
    document.getElementById('passwordHelp').textContent = 'Laisser vide pour conserver le mot de passe actuel';
    document.getElementById('modalTitle').textContent = 'Modifier l\'Utilisateur';
    document.getElementById('submitBtn').textContent = 'Enregistrer';
    document.getElementById('statutField').style.display = 'block';
    
    new bootstrap.Modal(document.getElementById('modalUtilisateur')).show();
}

// Réinitialiser le formulaire pour la création
document.getElementById('modalUtilisateur').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formUtilisateur').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('userPassword').setAttribute('required', '');
    document.getElementById('passwordRequired').textContent = '*';
    document.getElementById('passwordHelp').textContent = 'Minimum 6 caractères';
    document.getElementById('modalTitle').textContent = 'Nouvel Utilisateur';
    document.getElementById('submitBtn').textContent = 'Créer';
    document.getElementById('statutField').style.display = 'none';
});

// Confirmer la suppression
function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('modalDelete')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
