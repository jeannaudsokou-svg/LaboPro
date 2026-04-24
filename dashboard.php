<?php
/**
 * Tableau de bord
 * Gestion Laboratoire Médical
 */

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$userRole = $_SESSION['user_role'];

// Si l'utilisateur est un patient, rediriger vers le dashboard patient
if ($userRole === 'patient') {
    $userId = $_SESSION['user_id'];
    
    // Recuperer le patient_id lie a cet utilisateur
    $stmtPatient = $pdo->prepare("SELECT patient_id FROM utilisateurs WHERE id = ?");
    $stmtPatient->execute([$userId]);
    $userData = $stmtPatient->fetch();
    $patientId = $userData['patient_id'];
    
    // Statistiques patient
    $patientStats = [
        'resultats' => 0,
        'rdv_a_venir' => 0,
        'examens_en_cours' => 0
    ];
    
    if ($patientId) {
        // Nombre de resultats valides
        $stmtRes = $pdo->prepare("
            SELECT COUNT(*) as count FROM resultats r
            JOIN examens e ON r.examen_id = e.id
            JOIN demandes_examens de ON e.demande_id = de.id
            WHERE de.patient_id = ? AND r.valide = TRUE
        ");
        $stmtRes->execute([$patientId]);
        $patientStats['resultats'] = $stmtRes->fetch()['count'];
        
        // Rendez-vous a venir
        $stmtRdvPatient = $pdo->prepare("
            SELECT COUNT(*) as count FROM rendez_vous 
            WHERE patient_id = ? AND date_rdv >= CURDATE() AND statut IN ('planifie', 'confirme')
        ");
        $stmtRdvPatient->execute([$patientId]);
        $patientStats['rdv_a_venir'] = $stmtRdvPatient->fetch()['count'];
        
        // Examens en cours
        $stmtExamPatient = $pdo->prepare("
            SELECT COUNT(*) as count FROM examens e
            JOIN demandes_examens de ON e.demande_id = de.id
            WHERE de.patient_id = ? AND e.statut IN ('en_attente', 'preleve', 'en_analyse')
        ");
        $stmtExamPatient->execute([$patientId]);
        $patientStats['examens_en_cours'] = $stmtExamPatient->fetch()['count'];
        
        // Derniers resultats
        $stmtDerniersRes = $pdo->prepare("
            SELECT r.*, te.nom as type_nom, te.code
            FROM resultats r
            JOIN examens e ON r.examen_id = e.id
            JOIN demandes_examens de ON e.demande_id = de.id
            JOIN types_examens te ON e.type_examen_id = te.id
            WHERE de.patient_id = ? AND r.valide = TRUE
            ORDER BY r.date_saisie DESC LIMIT 5
        ");
        $stmtDerniersRes->execute([$patientId]);
        $derniersResultats = $stmtDerniersRes->fetchAll();
        
        // Prochains rendez-vous
        $stmtProchainsRdv = $pdo->prepare("
            SELECT * FROM rendez_vous 
            WHERE patient_id = ? AND date_rdv >= CURDATE() AND statut IN ('planifie', 'confirme')
            ORDER BY date_rdv ASC, heure_rdv ASC LIMIT 3
        ");
        $stmtProchainsRdv->execute([$patientId]);
        $prochainsRdv = $stmtProchainsRdv->fetchAll();
    }
} else {
    // Dashboard pour les autres roles (admin, technicien, receptionniste)
    // Statistiques générales
    $totalPatients = countPatients();
    $todayRdv = countTodayAppointments();
    $pendingExams = countPendingExams();
    $pendingResults = countPendingResults();

    // Derniers patients inscrits
    $stmtPatients = $pdo->query("
        SELECT p.*, u.prenom as enregistre_prenom 
        FROM patients p 
        LEFT JOIN utilisateurs u ON p.enregistre_par = u.id 
        ORDER BY p.date_inscription DESC LIMIT 5
    ");
    $dernierPatients = $stmtPatients->fetchAll();

    // Rendez-vous du jour
    $stmtRdv = $pdo->query("
        SELECT r.*, p.nom as patient_nom, p.prenom as patient_prenom 
        FROM rendez_vous r 
        JOIN patients p ON r.patient_id = p.id 
        WHERE r.date_rdv = CURDATE() AND r.statut != 'annule'
        ORDER BY r.heure_rdv ASC LIMIT 8
    ");
    $rdvDuJour = $stmtRdv->fetchAll();

    // Examens en attente
    $stmtExamens = $pdo->query("
        SELECT e.*, te.nom as type_nom, te.code as type_code,
               p.nom as patient_nom, p.prenom as patient_prenom
        FROM examens e
        JOIN demandes_examens de ON e.demande_id = de.id
        JOIN patients p ON de.patient_id = p.id
        JOIN types_examens te ON e.type_examen_id = te.id
        WHERE e.statut IN ('en_attente', 'preleve', 'en_analyse')
        ORDER BY de.priorite DESC, e.id ASC LIMIT 8
    ");
    $examensEnCours = $stmtExamens->fetchAll();
}
?>

<?php if ($userRole === 'patient'): ?>
<!-- DASHBOARD PATIENT -->

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Tableau de bord</h1>
        <p class="page-subtitle">Bienvenue, <?= htmlspecialchars($_SESSION['user_prenom']) ?> ! Voici un apercu de votre dossier medical.</p>
    </div>
    <div>
        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('l d F Y') ?></span>
    </div>
</div>

<!-- Statistiques Patient -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary">
                <i class="bi bi-file-earmark-medical"></i>
            </div>
            <div class="stat-card-value"><?= $patientStats['resultats'] ?></div>
            <div class="stat-card-label">Resultats disponibles</div>
            <a href="mes-resultats.php" class="btn btn-sm btn-outline-primary mt-2">Consulter</a>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon bg-success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-card-value"><?= $patientStats['rdv_a_venir'] ?></div>
            <div class="stat-card-label">Rendez-vous a venir</div>
            <a href="mes-rendez-vous.php" class="btn btn-sm btn-outline-success mt-2">Voir</a>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon bg-warning">
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
            <div class="stat-card-value"><?= $patientStats['examens_en_cours'] ?></div>
            <div class="stat-card-label">Examens en cours</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Prochains rendez-vous -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-calendar-event me-2"></i>Prochains rendez-vous</h5>
                <a href="mes-rendez-vous.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Demander un RDV
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($prochainsRdv)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x display-4 mb-3 d-block"></i>
                        <p>Aucun rendez-vous prevu</p>
                        <a href="mes-rendez-vous.php" class="btn btn-sm btn-primary">Demander un rendez-vous</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($prochainsRdv as $rdv): ?>
                        <div class="appointment-item">
                            <div class="appointment-time">
                                <?= date('d/m', strtotime($rdv['date_rdv'])) ?><br>
                                <small><?= date('H:i', strtotime($rdv['heure_rdv'])) ?></small>
                            </div>
                            <div class="appointment-info">
                                <div class="appointment-patient">
                                    <?= date('l d F', strtotime($rdv['date_rdv'])) ?>
                                </div>
                                <div class="appointment-type">
                                    <?= htmlspecialchars($rdv['motif'] ?: 'Consultation') ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= $rdv['statut'] === 'confirme' ? 'success' : 'warning' ?>">
                                <?= ucfirst($rdv['statut']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Derniers resultats -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-file-earmark-medical me-2"></i>Derniers resultats</h5>
                <a href="mes-resultats.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($derniersResultats)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-file-earmark-x display-4 mb-3 d-block"></i>
                        <p>Aucun resultat disponible</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Examen</th>
                                    <th>Resultat</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($derniersResultats as $res): ?>
                                    <tr>
                                        <td>
                                            <code class="me-1"><?= htmlspecialchars($res['code']) ?></code>
                                            <?= htmlspecialchars($res['type_nom']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= match($res['interpretation']) {
                                                'normal' => 'success',
                                                'anormal_bas', 'anormal_haut' => 'warning',
                                                'critique' => 'danger',
                                                default => 'secondary'
                                            } ?>">
                                                <?= ucfirst(str_replace('_', ' ', $res['interpretation'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="mes-resultats.php?view=<?= $res['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions rapides</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="mes-resultats.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="bi bi-file-earmark-medical d-block fs-3 mb-2"></i>
                            Mes Resultats
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="mes-rendez-vous.php" class="btn btn-outline-success w-100 py-3">
                            <i class="bi bi-calendar-plus d-block fs-3 mb-2"></i>
                            Mes Rendez-vous
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="profil.php" class="btn btn-outline-secondary w-100 py-3">
                            <i class="bi bi-person-circle d-block fs-3 mb-2"></i>
                            Mon Profil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- DASHBOARD STAFF (Admin, Technicien, Receptionniste) -->

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Tableau de bord</h1>
        <p class="page-subtitle">Bienvenue, <?= htmlspecialchars($_SESSION['user_prenom']) ?> ! Voici un apercu de votre activite.</p>
    </div>
    <div>
        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('l d F Y') ?></span>
    </div>
</div>

<!-- Statistiques Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-card-value"><?= number_format($totalPatients) ?></div>
            <div class="stat-card-label">Patients enregistres</div>
            <div class="stat-card-trend up">
                <i class="bi bi-arrow-up"></i> 12% ce mois
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-card-value"><?= $todayRdv ?></div>
            <div class="stat-card-label">Rendez-vous aujourd'hui</div>
            <a href="rendez-vous.php" class="btn btn-sm btn-outline-success mt-2">Voir l'agenda</a>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-warning">
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
            <div class="stat-card-value"><?= $pendingExams ?></div>
            <div class="stat-card-label">Examens en cours</div>
            <a href="examens.php" class="btn btn-sm btn-outline-warning mt-2">Gerer</a>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-info">
                <i class="bi bi-file-earmark-medical"></i>
            </div>
            <div class="stat-card-value"><?= $pendingResults ?></div>
            <div class="stat-card-label">Resultats a valider</div>
            <a href="resultats.php" class="btn btn-sm btn-outline-info mt-2">Valider</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Rendez-vous du jour -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-calendar-event me-2"></i>Rendez-vous du jour</h5>
                <a href="rendez-vous.php?action=new" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rdvDuJour)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x display-4 mb-3 d-block"></i>
                        <p>Aucun rendez-vous prévu aujourd'hui</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($rdvDuJour as $rdv): ?>
                        <div class="appointment-item">
                            <div class="appointment-time">
                                <?= date('H:i', strtotime($rdv['heure_rdv'])) ?>
                            </div>
                            <div class="appointment-info">
                                <div class="appointment-patient">
                                    <?= htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']) ?>
                                </div>
                                <div class="appointment-type">
                                    <?= htmlspecialchars($rdv['motif'] ?: 'Consultation') ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= match($rdv['statut']) {
                                'confirme' => 'success',
                                'en_cours' => 'primary',
                                'termine' => 'secondary',
                                default => 'warning'
                            } ?>">
                                <?= ucfirst(str_replace('_', ' ', $rdv['statut'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Examens en cours -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-clipboard2-pulse me-2"></i>Examens en cours</h5>
                <a href="examens.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($examensEnCours)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clipboard-check display-4 mb-3 d-block"></i>
                        <p>Tous les examens sont traités</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Examen</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examensEnCours as $examen): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($examen['patient_prenom'] . ' ' . $examen['patient_nom']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= htmlspecialchars($examen['type_code']) ?></span>
                                            <?= htmlspecialchars($examen['type_nom']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status bg-<?= match($examen['statut']) {
                                                'en_attente' => 'warning',
                                                'preleve' => 'info',
                                                'en_analyse' => 'primary',
                                                default => 'secondary'
                                            } ?>">
                                                <?= ucfirst(str_replace('_', ' ', $examen['statut'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Derniers patients -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-person-plus me-2"></i>Derniers patients enregistrés</h5>
                <a href="patients.php?action=new" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau patient
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>N° Dossier</th>
                                <th>Patient</th>
                                <th>Date de naissance</th>
                                <th>Téléphone</th>
                                <th>Date inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernierPatients as $patient): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($patient['numero_dossier']) ?></code></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="patient-avatar <?= $patient['sexe'] === 'M' ? 'male' : 'female' ?> me-2" style="width:35px;height:35px;font-size:0.875rem;">
                                                <?= strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></strong>
                                                <div class="small text-muted"><?= calculateAge($patient['date_naissance']) ?> ans</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= formatDate($patient['date_naissance']) ?></td>
                                    <td><?= htmlspecialchars($patient['telephone']) ?></td>
                                    <td><?= formatDateTime($patient['date_inscription']) ?></td>
                                    <td>
                                        <a href="patient-detail.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="examens.php?action=new&patient=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-success" title="Nouvel examen">
                                            <i class="bi bi-plus-lg"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
