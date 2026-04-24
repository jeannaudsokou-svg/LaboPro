<?php
/**
 * Mes Rendez-vous - Page Patient
 * Accessible uniquement aux patients pour consulter et gerer leurs rendez-vous
 */

$pageTitle = 'Mes Rendez-vous';
require_once __DIR__ . '/includes/header.php';
requireRole('patient');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Recuperer le patient_id lie a cet utilisateur
$stmtPatient = $pdo->prepare("SELECT patient_id FROM utilisateurs WHERE id = ?");
$stmtPatient->execute([$userId]);
$userData = $stmtPatient->fetch();
$patientId = $userData['patient_id'];

if (!$patientId) {
    setFlashMessage('error', 'Votre compte n\'est pas lie a un dossier patient. Veuillez contacter l\'administration.');
    header('Location: dashboard.php');
    exit;
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    // Demander un nouveau rendez-vous
    if ($postAction === 'request') {
        $stmt = $pdo->prepare("
            INSERT INTO rendez_vous (patient_id, date_rdv, heure_rdv, motif, notes, statut)
            VALUES (?, ?, ?, ?, ?, 'planifie')
        ");
        
        try {
            $stmt->execute([
                $patientId,
                $_POST['date_rdv'],
                $_POST['heure_rdv'],
                sanitize($_POST['motif'] ?? ''),
                sanitize($_POST['notes'] ?? '')
            ]);
            
            setFlashMessage('success', "Votre demande de rendez-vous a ete enregistree. Vous serez contacte pour confirmation.");
            header("Location: mes-rendez-vous.php");
            exit;
        } catch (PDOException $e) {
            setFlashMessage('error', "Erreur lors de l'enregistrement: " . $e->getMessage());
        }
    }
    
    // Annuler un rendez-vous
    if ($postAction === 'cancel') {
        $rdvId = intval($_POST['rdv_id']);
        
        // Verifier que le rendez-vous appartient bien au patient
        $checkStmt = $pdo->prepare("SELECT id, statut FROM rendez_vous WHERE id = ? AND patient_id = ?");
        $checkStmt->execute([$rdvId, $patientId]);
        $rdv = $checkStmt->fetch();
        
        if ($rdv && !in_array($rdv['statut'], ['termine', 'annule'])) {
            $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = 'annule' WHERE id = ?");
            $stmt->execute([$rdvId]);
            setFlashMessage('success', "Le rendez-vous a ete annule.");
        } else {
            setFlashMessage('error', "Impossible d'annuler ce rendez-vous.");
        }
        header("Location: mes-rendez-vous.php");
        exit;
    }
}

// Filtres
$filter = $_GET['filter'] ?? 'upcoming';

// Recuperer les rendez-vous du patient
$whereCondition = "";
if ($filter === 'upcoming') {
    $whereCondition = "AND (r.date_rdv > CURDATE() OR (r.date_rdv = CURDATE() AND r.statut NOT IN ('termine', 'annule')))";
} elseif ($filter === 'past') {
    $whereCondition = "AND (r.date_rdv < CURDATE() OR r.statut IN ('termine', 'annule'))";
}

$stmt = $pdo->prepare("
    SELECT r.* 
    FROM rendez_vous r
    WHERE r.patient_id = ? $whereCondition
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
");
$stmt->execute([$patientId]);
$rendezvous = $stmt->fetchAll();

// Stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut IN ('planifie', 'confirme') AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) as a_venir,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termines,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules
    FROM rendez_vous WHERE patient_id = ?
");
$statsStmt->execute([$patientId]);
$stats = $statsStmt->fetch();

// Prochain rendez-vous
$nextRdvStmt = $pdo->prepare("
    SELECT * FROM rendez_vous 
    WHERE patient_id = ? AND date_rdv >= CURDATE() AND statut IN ('planifie', 'confirme')
    ORDER BY date_rdv ASC, heure_rdv ASC LIMIT 1
");
$nextRdvStmt->execute([$patientId]);
$nextRdv = $nextRdvStmt->fetch();
?>

<!-- En-tete de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-calendar-check text-primary me-2"></i>
            Mes Rendez-vous
        </h1>
        <p class="page-subtitle">
            Consultez et gerez vos rendez-vous medicaux
        </p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauRdv">
            <i class="bi bi-plus-lg me-1"></i>Demander un RDV
        </button>
    </div>
</div>

<!-- Prochain RDV -->
<?php if ($nextRdv): ?>
<div class="card mb-4 border-primary">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary text-white p-3 me-3">
                    <i class="bi bi-calendar-event fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-1">Prochain rendez-vous</h5>
                    <p class="mb-0 text-primary fs-5">
                        <strong><?= date('l d F Y', strtotime($nextRdv['date_rdv'])) ?></strong>
                        a <strong><?= date('H:i', strtotime($nextRdv['heure_rdv'])) ?></strong>
                    </p>
                    <?php if ($nextRdv['motif']): ?>
                        <small class="text-muted"><?= htmlspecialchars($nextRdv['motif']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge bg-<?= $nextRdv['statut'] === 'confirme' ? 'success' : 'warning' ?> fs-6">
                <?= ucfirst($nextRdv['statut']) ?>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Statistiques -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-primary"><?= $stats['total'] ?></div>
            <div class="text-muted small">Total</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-success"><?= $stats['a_venir'] ?></div>
            <div class="text-muted small">A venir</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-secondary"><?= $stats['termines'] ?></div>
            <div class="text-muted small">Termines</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-danger"><?= $stats['annules'] ?></div>
            <div class="text-muted small">Annules</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2">
            <a href="?filter=upcoming" class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-calendar-plus me-1"></i>A venir
            </a>
            <a href="?filter=past" class="btn btn-sm <?= $filter === 'past' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-calendar-check me-1"></i>Passes
            </a>
            <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-calendar3 me-1"></i>Tous
            </a>
        </div>
    </div>
</div>

<!-- Liste des RDV -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>
            <?= $filter === 'upcoming' ? 'Rendez-vous a venir' : ($filter === 'past' ? 'Rendez-vous passes' : 'Tous les rendez-vous') ?>
            <span class="badge bg-secondary"><?= count($rendezvous) ?></span>
        </h5>
    </div>
    
    <?php if (empty($rendezvous)): ?>
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Aucun rendez-vous</h5>
            <p class="text-muted">
                <?php if ($filter === 'upcoming'): ?>
                    Vous n'avez pas de rendez-vous prevu. Cliquez sur "Demander un RDV" pour en planifier un.
                <?php else: ?>
                    Aucun rendez-vous trouve pour ce filtre.
                <?php endif; ?>
            </p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauRdv">
                <i class="bi bi-plus-lg me-1"></i>Demander un rendez-vous
            </button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendezvous as $rdv): ?>
                        <tr class="<?= $rdv['statut'] === 'annule' ? 'table-secondary' : '' ?>">
                            <td>
                                <strong><?= formatDate($rdv['date_rdv']) ?></strong>
                                <div class="small text-muted">
                                    <?= date('l', strtotime($rdv['date_rdv'])) ?>
                                </div>
                            </td>
                            <td>
                                <span class="text-primary fw-bold"><?= date('H:i', strtotime($rdv['heure_rdv'])) ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($rdv['motif'] ?: '-') ?>
                                <?php if ($rdv['notes']): ?>
                                    <i class="bi bi-chat-dots text-muted ms-1" data-bs-toggle="tooltip" title="<?= htmlspecialchars($rdv['notes']) ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($rdv['statut']) {
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
                                <?php if (!in_array($rdv['statut'], ['termine', 'annule']) && $rdv['date_rdv'] >= date('Y-m-d')): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Etes-vous sur de vouloir annuler ce rendez-vous ?');">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-lg"></i> Annuler
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Nouveau Rendez-vous -->
<div class="modal fade" id="modalNouveauRdv" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="request">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Demander un rendez-vous</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Votre demande sera traitee par notre equipe qui vous contactera pour confirmation.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date souhaitee <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_rdv" required 
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Heure souhaitee <span class="text-danger">*</span></label>
                            <select class="form-select" name="heure_rdv" required>
                                <option value="">Choisir une heure</option>
                                <option value="08:00">08:00</option>
                                <option value="08:30">08:30</option>
                                <option value="09:00">09:00</option>
                                <option value="09:30">09:30</option>
                                <option value="10:00">10:00</option>
                                <option value="10:30">10:30</option>
                                <option value="11:00">11:00</option>
                                <option value="11:30">11:30</option>
                                <option value="14:00">14:00</option>
                                <option value="14:30">14:30</option>
                                <option value="15:00">15:00</option>
                                <option value="15:30">15:30</option>
                                <option value="16:00">16:00</option>
                                <option value="16:30">16:30</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Motif</label>
                            <select class="form-select" name="motif">
                                <option value="">Selectionner un motif</option>
                                <option value="Bilan sanguin">Bilan sanguin</option>
                                <option value="Prelevement urinaire">Prelevement urinaire</option>
                                <option value="Consultation">Consultation</option>
                                <option value="Retrait resultats">Retrait resultats</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notes / Informations complementaires</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="Precisions sur votre demande..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Envoyer la demande
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
