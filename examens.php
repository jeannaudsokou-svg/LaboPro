<?php
/**
 * Gestion des examens
 * Gestion Laboratoire Médical
 */

$pageTitle = 'Examens';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$action = $_GET['action'] ?? 'list';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create_demande') {
        // Créer une demande d'examen
        $pdo->beginTransaction();
        
        try {
            // Créer la demande
            $stmt = $pdo->prepare("
                INSERT INTO demandes_examens (patient_id, rendez_vous_id, medecin_prescripteur, priorite, observations, cree_par)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                intval($_POST['patient_id']),
                !empty($_POST['rdv_id']) ? intval($_POST['rdv_id']) : null,
                sanitize($_POST['medecin'] ?? ''),
                $_POST['priorite'] ?? 'normale',
                sanitize($_POST['observations'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            $demandeId = $pdo->lastInsertId();
            
            // Ajouter les examens sélectionnés
            if (!empty($_POST['examens'])) {
                $insertExamen = $pdo->prepare("INSERT INTO examens (demande_id, type_examen_id) VALUES (?, ?)");
                foreach ($_POST['examens'] as $typeId) {
                    $insertExamen->execute([$demandeId, intval($typeId)]);
                }
            }
            
            // Calculer et créer le paiement
            $totalStmt = $pdo->prepare("
                SELECT SUM(te.prix) as total 
                FROM examens e 
                JOIN types_examens te ON e.type_examen_id = te.id 
                WHERE e.demande_id = ?
            ");
            $totalStmt->execute([$demandeId]);
            $total = $totalStmt->fetch()['total'] ?? 0;
            
            if ($total > 0) {
                $pdo->prepare("INSERT INTO paiements (demande_id, montant_total) VALUES (?, ?)")
                    ->execute([$demandeId, $total]);
            }
            
            $pdo->commit();
            logAction('creation_demande', 'demandes_examens', $demandeId, "Demande créée");
            setFlashMessage('success', "Demande d'examen créée avec succès. Total: " . number_format($total, 2) . " €");
            header("Location: examens.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlashMessage('error', "Erreur: " . $e->getMessage());
        }
    }
    
    if ($postAction === 'update_statut') {
        $examenId = intval($_POST['examen_id']);
        $newStatut = sanitize($_POST['statut']);
        
        $updateData = ['statut' => $newStatut];
        $sql = "UPDATE examens SET statut = ?";
        $params = [$newStatut];
        
        if ($newStatut === 'preleve') {
            $sql .= ", date_prelevement = NOW(), preleve_par = ?";
            $params[] = $_SESSION['user_id'];
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $examenId;
        
        $pdo->prepare($sql)->execute($params);
        logAction('modification_examen', 'examens', $examenId, "Statut: $newStatut");
        setFlashMessage('success', "Statut mis à jour.");
        header("Location: examens.php");
        exit;
    }
}

// Filtres
$statutFilter = $_GET['statut'] ?? '';
$patientFilter = isset($_GET['patient']) ? intval($_GET['patient']) : null;
$dateFilter = $_GET['date'] ?? '';

// Construire la requête
$whereConditions = [];
$params = [];

if ($statutFilter) {
    $whereConditions[] = "e.statut = ?";
    $params[] = $statutFilter;
}

if ($patientFilter) {
    $whereConditions[] = "de.patient_id = ?";
    $params[] = $patientFilter;
}

if ($dateFilter) {
    $whereConditions[] = "DATE(de.date_demande) = ?";
    $params[] = $dateFilter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Récupérer les examens
$stmt = $pdo->prepare("
    SELECT e.*, 
           te.code as type_code, te.nom as type_nom, te.prix,
           de.date_demande, de.priorite, de.medecin_prescripteur,
           p.id as patient_id, p.nom as patient_nom, p.prenom as patient_prenom, 
           p.numero_dossier, p.sexe as patient_sexe
    FROM examens e
    JOIN demandes_examens de ON e.demande_id = de.id
    JOIN patients p ON de.patient_id = p.id
    JOIN types_examens te ON e.type_examen_id = te.id
    $whereClause
    ORDER BY de.priorite DESC, de.date_demande DESC, e.id ASC
    LIMIT 100
");
$stmt->execute($params);
$examens = $stmt->fetchAll();

// Liste des patients et types d'examens pour le formulaire
$patientsStmt = $pdo->query("SELECT id, numero_dossier, nom, prenom FROM patients ORDER BY nom, prenom");
$patients = $patientsStmt->fetchAll();

$typesStmt = $pdo->query("SELECT * FROM types_examens WHERE actif = TRUE ORDER BY categorie, nom");
$typesExamens = $typesStmt->fetchAll();

// Grouper par catégorie
$typesByCategorie = [];
foreach ($typesExamens as $type) {
    $typesByCategorie[$type['categorie']][] = $type;
}
?>

<?php if ($action === 'new'): ?>
    
    <!-- Formulaire Nouvelle Demande -->
    <div class="page-header">
        <h1 class="page-title">
            <a href="examens.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
            Nouvelle demande d'examen
        </h1>
    </div>
    
    <form method="POST" action="examens.php" data-validate>
        <input type="hidden" name="action" value="create_demande">
        <?php if (isset($_GET['rdv'])): ?>
            <input type="hidden" name="rdv_id" value="<?= intval($_GET['rdv']) ?>">
        <?php endif; ?>
        
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person me-2"></i>Informations patient</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-select" name="patient_id" required>
                                    <option value="">Sélectionner un patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ($patientFilter == $p['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['numero_dossier'] . ' - ' . $p['prenom'] . ' ' . $p['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Priorité</label>
                                <select class="form-select" name="priorite">
                                    <option value="normale">Normale</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médecin prescripteur</label>
                                <input type="text" class="form-control" name="medecin" 
                                       placeholder="Dr. ...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Observations</label>
                                <input type="text" class="form-control" name="observations" 
                                       placeholder="Notes particulières...">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Sélection des examens</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($typesByCategorie as $categorie => $types): ?>
                            <div class="mb-4">
                                <h6 class="text-muted border-bottom pb-2 mb-3">
                                    <i class="bi bi-tag me-1"></i><?= htmlspecialchars($categorie) ?>
                                </h6>
                                <div class="row g-2">
                                    <?php foreach ($types as $type): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="form-check border rounded p-3">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="examens[]" value="<?= $type['id'] ?>"
                                                       id="exam_<?= $type['id'] ?>" data-price="<?= $type['prix'] ?>">
                                                <label class="form-check-label w-100" for="exam_<?= $type['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <code class="text-primary"><?= $type['code'] ?></code>
                                                            <div class="small"><?= htmlspecialchars($type['nom']) ?></div>
                                                        </div>
                                                        <span class="badge bg-secondary"><?= number_format($type['prix'], 2) ?> €</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 80px;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Récapitulatif</h5>
                    </div>
                    <div class="card-body">
                        <div id="selectedExams" class="mb-3">
                            <p class="text-muted">Aucun examen sélectionné</p>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong>Total:</strong>
                            <span class="h4 mb-0 text-primary" id="totalAmount">0.00 €</span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-1"></i>Créer la demande
                        </button>
                        <a href="examens.php" class="btn btn-outline-secondary w-100 mt-2">Annuler</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('input[name="examens[]"]');
        const selectedDiv = document.getElementById('selectedExams');
        const totalSpan = document.getElementById('totalAmount');
        
        function updateSummary() {
            let total = 0;
            let html = '';
            
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    const label = cb.closest('.form-check').querySelector('label');
                    const code = label.querySelector('code').textContent;
                    const price = parseFloat(cb.dataset.price);
                    total += price;
                    html += `<div class="d-flex justify-content-between small mb-1">
                        <span>${code}</span>
                        <span>${price.toFixed(2)} €</span>
                    </div>`;
                }
            });
            
            selectedDiv.innerHTML = html || '<p class="text-muted">Aucun examen sélectionné</p>';
            totalSpan.textContent = total.toFixed(2) + ' €';
        }
        
        checkboxes.forEach(cb => cb.addEventListener('change', updateSummary));
    });
    </script>

<?php else: ?>
    
    <!-- Liste des Examens -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Examens</h1>
            <p class="page-subtitle"><?= count($examens) ?> examens trouvés</p>
        </div>
        <a href="examens.php?action=new" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nouvelle demande
        </a>
    </div>
    
    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select class="form-select" name="statut">
                        <option value="">Tous</option>
                        <option value="en_attente" <?= $statutFilter === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="preleve" <?= $statutFilter === 'preleve' ? 'selected' : '' ?>>Prélevé</option>
                        <option value="en_analyse" <?= $statutFilter === 'en_analyse' ? 'selected' : '' ?>>En analyse</option>
                        <option value="termine" <?= $statutFilter === 'termine' ? 'selected' : '' ?>>Terminé</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="date" value="<?= $dateFilter ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                    <a href="examens.php" class="btn btn-outline-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tableau -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($examens)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard-x display-4 mb-3 d-block"></i>
                    <p>Aucun examen trouvé</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Examen</th>
                                <th>Date demande</th>
                                <th>Priorité</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($examens as $examen): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="patient-avatar <?= $examen['patient_sexe'] === 'M' ? 'male' : 'female' ?> me-2" style="width:35px;height:35px;font-size:0.8rem;">
                                                <?= strtoupper(substr($examen['patient_prenom'], 0, 1) . substr($examen['patient_nom'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="patient-detail.php?id=<?= $examen['patient_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($examen['patient_prenom'] . ' ' . $examen['patient_nom']) ?>
                                                </a>
                                                <div class="small text-muted"><?= $examen['numero_dossier'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="text-primary"><?= $examen['type_code'] ?></code>
                                        <div class="small"><?= htmlspecialchars($examen['type_nom']) ?></div>
                                    </td>
                                    <td><?= formatDateTime($examen['date_demande']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $examen['priorite'] === 'urgente' ? 'danger' : 'secondary' ?>">
                                            <?= ucfirst($examen['priorite']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-status bg-<?= match($examen['statut']) {
                                            'en_attente' => 'warning',
                                            'preleve' => 'info',
                                            'en_analyse' => 'primary',
                                            'termine' => 'success',
                                            default => 'secondary'
                                        } ?>">
                                            <?= ucfirst(str_replace('_', ' ', $examen['statut'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($examen['statut'] !== 'termine'): ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($examen['statut'] === 'en_attente'): ?>
                                                        <li>
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="update_statut">
                                                                <input type="hidden" name="examen_id" value="<?= $examen['id'] ?>">
                                                                <input type="hidden" name="statut" value="preleve">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="bi bi-droplet text-info me-2"></i>Marquer prélevé
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($examen['statut'] === 'preleve'): ?>
                                                        <li>
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="update_statut">
                                                                <input type="hidden" name="examen_id" value="<?= $examen['id'] ?>">
                                                                <input type="hidden" name="statut" value="en_analyse">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="bi bi-hourglass-split text-primary me-2"></i>En analyse
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($examen['statut'] === 'en_analyse'): ?>
                                                        <li>
                                                            <a href="resultats.php?action=saisir&examen=<?= $examen['id'] ?>" class="dropdown-item">
                                                                <i class="bi bi-pencil-square text-success me-2"></i>Saisir résultat
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <a href="resultats.php?examen=<?= $examen['id'] ?>" class="btn btn-sm btn-outline-success" title="Voir résultat">
                                                <i class="bi bi-file-earmark-medical"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
