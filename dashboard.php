<?php
/**
 * Tableau de bord
 * Gestion Laboratoire Médical
 */

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';

// Récupérer les statistiques
$pdo = getConnection();

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
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Tableau de bord</h1>
        <p class="page-subtitle">Bienvenue, <?= htmlspecialchars($_SESSION['user_prenom']) ?> ! Voici un aperçu de votre activité.</p>
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
            <div class="stat-card-label">Patients enregistrés</div>
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
            <a href="examens.php" class="btn btn-sm btn-outline-warning mt-2">Gérer</a>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-info">
                <i class="bi bi-file-earmark-medical"></i>
            </div>
            <div class="stat-card-value"><?= $pendingResults ?></div>
            <div class="stat-card-label">Résultats à valider</div>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
