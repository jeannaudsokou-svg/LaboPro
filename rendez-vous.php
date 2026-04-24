<?php
/**
 * Gestion des rendez-vous
 * Gestion Laboratoire Médical
 */

$pageTitle = 'Rendez-vous';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$action = $_GET['action'] ?? 'list';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create') {
        $stmt = $pdo->prepare("
            INSERT INTO rendez_vous (patient_id, date_rdv, heure_rdv, motif, notes, cree_par)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                intval($_POST['patient_id']),
                $_POST['date_rdv'],
                $_POST['heure_rdv'],
                sanitize($_POST['motif'] ?? ''),
                sanitize($_POST['notes'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            $rdvId = $pdo->lastInsertId();
            logAction('creation_rdv', 'rendez_vous', $rdvId, "RDV créé");
            setFlashMessage('success', "Rendez-vous créé avec succès.");
            header("Location: rendez-vous.php");
            exit;
        } catch (PDOException $e) {
            setFlashMessage('error', "Erreur: " . $e->getMessage());
        }
    }
    
    if ($postAction === 'update_status') {
        $rdvId = intval($_POST['rdv_id']);
        $newStatus = sanitize($_POST['statut']);
        
        $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = ? WHERE id = ?");
        $stmt->execute([$newStatus, $rdvId]);
        
        logAction('modification_rdv', 'rendez_vous', $rdvId, "Statut changé: $newStatus");
        setFlashMessage('success', "Statut mis à jour.");
        header("Location: rendez-vous.php");
        exit;
    }
    
    if ($postAction === 'delete') {
        $rdvId = intval($_POST['rdv_id']);
        $pdo->prepare("DELETE FROM rendez_vous WHERE id = ?")->execute([$rdvId]);
        logAction('suppression_rdv', 'rendez_vous', $rdvId, "RDV supprimé");
        setFlashMessage('success', "Rendez-vous supprimé.");
        header("Location: rendez-vous.php");
        exit;
    }
}

// Filtres
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$statutFilter = $_GET['statut'] ?? '';
$patientFilter = isset($_GET['patient']) ? intval($_GET['patient']) : null;

// Construire la requête
$whereConditions = [];
$params = [];

if ($dateFilter) {
    $whereConditions[] = "r.date_rdv = ?";
    $params[] = $dateFilter;
}

if ($statutFilter) {
    $whereConditions[] = "r.statut = ?";
    $params[] = $statutFilter;
}

if ($patientFilter) {
    $whereConditions[] = "r.patient_id = ?";
    $params[] = $patientFilter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Récupérer les rendez-vous
$stmt = $pdo->prepare("
    SELECT r.*, 
           p.nom as patient_nom, p.prenom as patient_prenom, p.telephone as patient_tel,
           p.numero_dossier, p.sexe as patient_sexe,
           u.prenom as cree_prenom
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    LEFT JOIN utilisateurs u ON r.cree_par = u.id
    $whereClause
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
");
$stmt->execute($params);
$rendezvous = $stmt->fetchAll();

// Liste des patients pour le formulaire
$patientsStmt = $pdo->query("SELECT id, numero_dossier, nom, prenom FROM patients ORDER BY nom, prenom");
$patients = $patientsStmt->fetchAll();

// Stats du jour
$todayStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termines,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules,
        SUM(CASE WHEN statut IN ('planifie', 'confirme') THEN 1 ELSE 0 END) as en_attente
    FROM rendez_vous WHERE date_rdv = CURDATE()
")->fetch();
?>

<?php if ($action === 'new'): ?>
    
    <!-- Formulaire Nouveau RDV -->
    <div class="page-header">
        <h1 class="page-title">
            <a href="rendez-vous.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
            Nouveau rendez-vous
        </h1>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="rendez-vous.php" data-validate>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row g-3">
                            <div class="col-12">
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
                            
                            <div class="col-md-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_rdv" required 
                                       min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Heure <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="heure_rdv" required 
                                       value="<?= date('H:00', strtotime('+1 hour')) ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Motif</label>
                                <select class="form-select" name="motif">
                                    <option value="">Sélectionner un motif</option>
                                    <option value="Bilan sanguin">Bilan sanguin</option>
                                    <option value="Prélèvement urinaire">Prélèvement urinaire</option>
                                    <option value="Consultation">Consultation</option>
                                    <option value="Retrait résultats">Retrait résultats</option>
                                    <option value="Autre">Autre</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Informations complémentaires..."></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="rendez-vous.php" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Créer le rendez-vous
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Information</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Créez un nouveau rendez-vous pour un patient. 
                        Assurez-vous de vérifier la disponibilité du créneau horaire choisi.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    
    <!-- Liste des RDV -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Rendez-vous</h1>
            <p class="page-subtitle">
                <?= $dateFilter === date('Y-m-d') ? "Aujourd'hui" : formatDate($dateFilter) ?> - 
                <?= count($rendezvous) ?> rendez-vous
            </p>
        </div>
        <a href="rendez-vous.php?action=new" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nouveau RDV
        </a>
    </div>
    
    <!-- Stats du jour -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h4 mb-0"><?= $todayStats['total'] ?></div>
                            <small>Total aujourd'hui</small>
                        </div>
                        <i class="bi bi-calendar3 fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h4 mb-0"><?= $todayStats['en_attente'] ?></div>
                            <small>En attente</small>
                        </div>
                        <i class="bi bi-clock fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h4 mb-0"><?= $todayStats['termines'] ?></div>
                            <small>Terminés</small>
                        </div>
                        <i class="bi bi-check-circle fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h4 mb-0"><?= $todayStats['annules'] ?></div>
                            <small>Annulés</small>
                        </div>
                        <i class="bi bi-x-circle fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="date" value="<?= $dateFilter ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select class="form-select" name="statut">
                        <option value="">Tous</option>
                        <option value="planifie" <?= $statutFilter === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                        <option value="confirme" <?= $statutFilter === 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                        <option value="en_cours" <?= $statutFilter === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="termine" <?= $statutFilter === 'termine' ? 'selected' : '' ?>>Terminé</option>
                        <option value="annule" <?= $statutFilter === 'annule' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="rendez-vous.php" class="btn btn-outline-secondary">Réinitialiser</a>
                        <a href="rendez-vous.php?date=<?= date('Y-m-d', strtotime($dateFilter . ' -1 day')) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <a href="rendez-vous.php?date=<?= date('Y-m-d', strtotime($dateFilter . ' +1 day')) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Liste RDV -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($rendezvous)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x display-4 mb-3 d-block"></i>
                    <p>Aucun rendez-vous pour cette date</p>
                    <a href="rendez-vous.php?action=new" class="btn btn-primary">Créer un rendez-vous</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Heure</th>
                                <th>Patient</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rendezvous as $rdv): ?>
                                <tr class="<?= $rdv['statut'] === 'annule' ? 'table-secondary' : '' ?>">
                                    <td>
                                        <strong class="text-primary"><?= date('H:i', strtotime($rdv['heure_rdv'])) ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="patient-avatar <?= $rdv['patient_sexe'] === 'M' ? 'male' : 'female' ?> me-2" style="width:40px;height:40px;">
                                                <?= strtoupper(substr($rdv['patient_prenom'], 0, 1) . substr($rdv['patient_nom'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="patient-detail.php?id=<?= $rdv['patient_id'] ?>" class="text-decoration-none">
                                                    <strong><?= htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']) ?></strong>
                                                </a>
                                                <div class="small text-muted">
                                                    <code><?= $rdv['numero_dossier'] ?></code> | <?= $rdv['patient_tel'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($rdv['motif'] ?: '-') ?>
                                        <?php if ($rdv['notes']): ?>
                                            <i class="bi bi-chat-dots text-muted ms-1" title="<?= htmlspecialchars($rdv['notes']) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-status bg-<?= match($rdv['statut']) {
                                            'confirme' => 'success',
                                            'termine' => 'secondary',
                                            'annule' => 'danger',
                                            'en_cours' => 'primary',
                                            default => 'warning'
                                        } ?>">
                                            <?= ucfirst(str_replace('_', ' ', $rdv['statut'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($rdv['statut'] !== 'termine' && $rdv['statut'] !== 'annule'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($rdv['statut'] === 'planifie'): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                                                <input type="hidden" name="statut" value="confirme">
                                                                <button type="submit" class="dropdown-item"><i class="bi bi-check text-success me-2"></i>Confirmer</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($rdv['statut'] !== 'en_cours'): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                                                <input type="hidden" name="statut" value="en_cours">
                                                                <button type="submit" class="dropdown-item"><i class="bi bi-play text-primary me-2"></i>En cours</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                                            <input type="hidden" name="statut" value="termine">
                                                            <button type="submit" class="dropdown-item"><i class="bi bi-check-all text-success me-2"></i>Terminer</button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                                            <input type="hidden" name="statut" value="annule">
                                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i>Annuler</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            <?php endif; ?>
                                            <a href="examens.php?action=new&patient=<?= $rdv['patient_id'] ?>&rdv=<?= $rdv['id'] ?>" class="btn btn-sm btn-outline-success" title="Créer examen">
                                                <i class="bi bi-plus-lg"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce rendez-vous ?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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
