<?php
/**
 * Gestion des patients
 * Gestion Laboratoire Médical
 */

$pageTitle = 'Patients';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$action = $_GET['action'] ?? 'list';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Créer un nouveau patient
        $numeroDossier = generatePatientNumber();
        
        $stmt = $pdo->prepare("
            INSERT INTO patients (numero_dossier, nom, prenom, date_naissance, sexe, adresse, telephone, email, groupe_sanguin, allergies, antecedents_medicaux, enregistre_par)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                $numeroDossier,
                sanitize($_POST['nom']),
                sanitize($_POST['prenom']),
                $_POST['date_naissance'],
                $_POST['sexe'],
                sanitize($_POST['adresse'] ?? ''),
                sanitize($_POST['telephone']),
                sanitize($_POST['email'] ?? ''),
                sanitize($_POST['groupe_sanguin'] ?? ''),
                sanitize($_POST['allergies'] ?? ''),
                sanitize($_POST['antecedents'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            $patientId = $pdo->lastInsertId();
            logAction('creation_patient', 'patients', $patientId, "Patient créé: {$_POST['prenom']} {$_POST['nom']}");
            setFlashMessage('success', "Patient enregistré avec succès. N° Dossier: $numeroDossier");
            header("Location: patient-detail.php?id=$patientId");
            exit;
        } catch (PDOException $e) {
            setFlashMessage('error', "Erreur lors de l'enregistrement: " . $e->getMessage());
        }
    }
    
    if ($action === 'update') {
        $patientId = intval($_POST['patient_id']);
        
        $stmt = $pdo->prepare("
            UPDATE patients SET 
                nom = ?, prenom = ?, date_naissance = ?, sexe = ?, 
                adresse = ?, telephone = ?, email = ?, groupe_sanguin = ?,
                allergies = ?, antecedents_medicaux = ?
            WHERE id = ?
        ");
        
        try {
            $stmt->execute([
                sanitize($_POST['nom']),
                sanitize($_POST['prenom']),
                $_POST['date_naissance'],
                $_POST['sexe'],
                sanitize($_POST['adresse'] ?? ''),
                sanitize($_POST['telephone']),
                sanitize($_POST['email'] ?? ''),
                sanitize($_POST['groupe_sanguin'] ?? ''),
                sanitize($_POST['allergies'] ?? ''),
                sanitize($_POST['antecedents'] ?? ''),
                $patientId
            ]);
            
            logAction('modification_patient', 'patients', $patientId, "Patient modifié");
            setFlashMessage('success', "Patient mis à jour avec succès.");
            header("Location: patient-detail.php?id=$patientId");
            exit;
        } catch (PDOException $e) {
            setFlashMessage('error', "Erreur lors de la mise à jour: " . $e->getMessage());
        }
    }
    
    if ($action === 'delete') {
        $patientId = intval($_POST['patient_id']);
        
        // Vérifier s'il y a des examens associés
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM demandes_examens WHERE patient_id = ?");
        $checkStmt->execute([$patientId]);
        $hasExams = $checkStmt->fetch()['count'] > 0;
        
        if ($hasExams) {
            setFlashMessage('error', "Impossible de supprimer ce patient car il a des examens associés.");
        } else {
            $pdo->prepare("DELETE FROM rendez_vous WHERE patient_id = ?")->execute([$patientId]);
            $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$patientId]);
            logAction('suppression_patient', 'patients', $patientId, "Patient supprimé");
            setFlashMessage('success', "Patient supprimé avec succès.");
        }
        header("Location: patients.php");
        exit;
    }
}

// Recherche et filtres
$search = sanitize($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Construire la requête
$whereClause = '';
$params = [];

if ($search) {
    $whereClause = "WHERE (nom LIKE ? OR prenom LIKE ? OR numero_dossier LIKE ? OR telephone LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Compter le total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM patients $whereClause");
$countStmt->execute($params);
$totalPatients = $countStmt->fetch()['total'];
$totalPages = ceil($totalPatients / $limit);

// Récupérer les patients
$stmt = $pdo->prepare("
    SELECT p.*, u.prenom as enregistre_prenom, u.nom as enregistre_nom
    FROM patients p
    LEFT JOIN utilisateurs u ON p.enregistre_par = u.id
    $whereClause
    ORDER BY p.date_inscription DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
    <?php
    $patient = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $editStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $editStmt->execute([intval($_GET['id'])]);
        $patient = $editStmt->fetch();
    }
    ?>
    
    <!-- Formulaire Patient -->
    <div class="page-header">
        <h1 class="page-title">
            <a href="patients.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
            <?= $patient ? 'Modifier le patient' : 'Nouveau patient' ?>
        </h1>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="patients.php" data-validate>
                <input type="hidden" name="action" value="<?= $patient ? 'update' : 'create' ?>">
                <?php if ($patient): ?>
                    <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3"><i class="bi bi-person me-2"></i>Informations personnelles</h6>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nom" required
                                       value="<?= $patient ? htmlspecialchars($patient['nom']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="prenom" required
                                       value="<?= $patient ? htmlspecialchars($patient['prenom']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_naissance" required
                                       value="<?= $patient ? $patient['date_naissance'] : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sexe <span class="text-danger">*</span></label>
                                <select class="form-select" name="sexe" required>
                                    <option value="">Sélectionner</option>
                                    <option value="M" <?= ($patient && $patient['sexe'] === 'M') ? 'selected' : '' ?>>Masculin</option>
                                    <option value="F" <?= ($patient && $patient['sexe'] === 'F') ? 'selected' : '' ?>>Féminin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="telephone" required data-phone
                                       value="<?= $patient ? htmlspecialchars($patient['telephone']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?= $patient ? htmlspecialchars($patient['email']) : '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="adresse" rows="2"><?= $patient ? htmlspecialchars($patient['adresse']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3"><i class="bi bi-heart-pulse me-2"></i>Informations médicales</h6>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Groupe sanguin</label>
                                <select class="form-select" name="groupe_sanguin">
                                    <option value="">Non renseigné</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $gs): ?>
                                        <option value="<?= $gs ?>" <?= ($patient && $patient['groupe_sanguin'] === $gs) ? 'selected' : '' ?>><?= $gs ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Allergies connues</label>
                                <textarea class="form-control" name="allergies" rows="2" 
                                          placeholder="Ex: Pénicilline, Aspirine..."><?= $patient ? htmlspecialchars($patient['allergies']) : '' ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Antécédents médicaux</label>
                                <textarea class="form-control" name="antecedents" rows="3"
                                          placeholder="Maladies chroniques, opérations..."><?= $patient ? htmlspecialchars($patient['antecedents_medicaux']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="patients.php" class="btn btn-outline-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        <?= $patient ? 'Enregistrer les modifications' : 'Créer le patient' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    
    <!-- Liste des patients -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Patients</h1>
            <p class="page-subtitle"><?= number_format($totalPatients) ?> patients enregistrés</p>
        </div>
        <a href="patients.php?action=new" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nouveau patient
        </a>
    </div>
    
    <!-- Recherche -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Rechercher un patient</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Nom, prénom, N° dossier ou téléphone..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">Rechercher</button>
                    <?php if ($search): ?>
                        <a href="patients.php" class="btn btn-outline-secondary">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tableau des patients -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($patients)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people display-4 mb-3 d-block"></i>
                    <p>Aucun patient trouvé</p>
                    <a href="patients.php?action=new" class="btn btn-primary">Créer un patient</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>N° Dossier</th>
                                <th>Patient</th>
                                <th>Age</th>
                                <th>Téléphone</th>
                                <th>Groupe sanguin</th>
                                <th>Inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($patient['numero_dossier']) ?></code></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="patient-avatar <?= $patient['sexe'] === 'M' ? 'male' : 'female' ?> me-2" style="width:40px;height:40px;">
                                                <?= strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></strong>
                                                <div class="small text-muted"><?= $patient['sexe'] === 'M' ? 'Homme' : 'Femme' ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= calculateAge($patient['date_naissance']) ?> ans</td>
                                    <td><?= htmlspecialchars($patient['telephone']) ?></td>
                                    <td>
                                        <?php if ($patient['groupe_sanguin']): ?>
                                            <span class="badge bg-danger"><?= htmlspecialchars($patient['groupe_sanguin']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatDate($patient['date_inscription']) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="patient-detail.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="patients.php?action=edit&id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="rendez-vous.php?action=new&patient=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-success" title="Nouveau RDV">
                                                <i class="bi bi-calendar-plus"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination mb-0 justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
