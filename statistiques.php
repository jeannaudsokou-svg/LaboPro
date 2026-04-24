<?php
/**
 * Statistiques et Rapports
 * Accessible uniquement aux administrateurs
 */

$pageTitle = 'Statistiques';
require_once __DIR__ . '/includes/header.php';
requireRole('administrateur');

$pdo = getConnection();

// Période de filtre
$periode = $_GET['periode'] ?? 'mois';
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-t');

// Ajuster les dates selon la période
switch ($periode) {
    case 'semaine':
        $dateDebut = date('Y-m-d', strtotime('monday this week'));
        $dateFin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mois':
        $dateDebut = date('Y-m-01');
        $dateFin = date('Y-m-t');
        break;
    case 'annee':
        $dateDebut = date('Y-01-01');
        $dateFin = date('Y-12-31');
        break;
    case 'personnalise':
        // Garder les dates du formulaire
        break;
}

// Statistiques générales
$statsGeneralesStmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM patients) as total_patients,
        (SELECT COUNT(*) FROM patients WHERE DATE(date_inscription) BETWEEN '$dateDebut' AND '$dateFin') as nouveaux_patients,
        (SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN '$dateDebut' AND '$dateFin') as total_rdv,
        (SELECT COUNT(*) FROM examens) as total_examens,
        (SELECT COUNT(*) FROM examens e 
            JOIN demandes_examens d ON e.demande_id = d.id 
            WHERE DATE(d.date_demande) BETWEEN '$dateDebut' AND '$dateFin') as examens_periode,
        (SELECT COUNT(*) FROM resultats WHERE valide = TRUE AND DATE(date_validation) BETWEEN '$dateDebut' AND '$dateFin') as resultats_valides
");
$statsGenerales = $statsGeneralesStmt->fetch();

// Chiffre d'affaires
$caStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(montant_total), 0) as ca_total,
        COALESCE(SUM(montant_paye), 0) as ca_encaisse,
        COUNT(*) as nb_paiements
    FROM paiements
    WHERE DATE(date_paiement) BETWEEN ? AND ?
");
$caStmt->execute([$dateDebut, $dateFin]);
$chiffreAffaires = $caStmt->fetch();

// Examens par catégorie
$examensParCategorieStmt = $pdo->prepare("
    SELECT 
        te.categorie,
        COUNT(e.id) as nombre,
        SUM(te.prix) as montant
    FROM examens e
    JOIN types_examens te ON e.type_examen_id = te.id
    JOIN demandes_examens d ON e.demande_id = d.id
    WHERE DATE(d.date_demande) BETWEEN ? AND ?
    GROUP BY te.categorie
    ORDER BY nombre DESC
");
$examensParCategorieStmt->execute([$dateDebut, $dateFin]);
$examensParCategorie = $examensParCategorieStmt->fetchAll();

// Top 10 examens les plus demandés
$topExamensStmt = $pdo->prepare("
    SELECT 
        te.code,
        te.nom,
        COUNT(e.id) as nombre,
        SUM(te.prix) as montant
    FROM examens e
    JOIN types_examens te ON e.type_examen_id = te.id
    JOIN demandes_examens d ON e.demande_id = d.id
    WHERE DATE(d.date_demande) BETWEEN ? AND ?
    GROUP BY te.id
    ORDER BY nombre DESC
    LIMIT 10
");
$topExamensStmt->execute([$dateDebut, $dateFin]);
$topExamens = $topExamensStmt->fetchAll();

// Rendez-vous par statut
$rdvParStatutStmt = $pdo->prepare("
    SELECT 
        statut,
        COUNT(*) as nombre
    FROM rendez_vous
    WHERE date_rdv BETWEEN ? AND ?
    GROUP BY statut
");
$rdvParStatutStmt->execute([$dateDebut, $dateFin]);
$rdvParStatut = $rdvParStatutStmt->fetchAll();

// Évolution mensuelle (12 derniers mois)
$evolutionStmt = $pdo->query("
    SELECT 
        DATE_FORMAT(d.date_demande, '%Y-%m') as mois,
        COUNT(DISTINCT d.patient_id) as patients,
        COUNT(e.id) as examens,
        COALESCE(SUM(te.prix), 0) as revenus
    FROM demandes_examens d
    LEFT JOIN examens e ON d.id = e.demande_id
    LEFT JOIN types_examens te ON e.type_examen_id = te.id
    WHERE d.date_demande >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(d.date_demande, '%Y-%m')
    ORDER BY mois ASC
");
$evolution = $evolutionStmt->fetchAll();

// Activité par jour de la semaine
$activiteJourStmt = $pdo->prepare("
    SELECT 
        DAYNAME(date_rdv) as jour,
        DAYOFWEEK(date_rdv) as jour_num,
        COUNT(*) as nombre
    FROM rendez_vous
    WHERE date_rdv BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(date_rdv), DAYNAME(date_rdv)
    ORDER BY jour_num
");
$activiteJourStmt->execute([$dateDebut, $dateFin]);
$activiteJour = $activiteJourStmt->fetchAll();

// Dernières actions
$dernieresActionsStmt = $pdo->query("
    SELECT 
        ha.*,
        u.nom,
        u.prenom
    FROM historique_actions ha
    JOIN utilisateurs u ON ha.utilisateur_id = u.id
    ORDER BY ha.date_action DESC
    LIMIT 15
");
$dernieresActions = $dernieresActionsStmt->fetchAll();

// Préparer les données pour les graphiques
$chartLabels = [];
$chartExamens = [];
$chartRevenus = [];
foreach ($evolution as $row) {
    $chartLabels[] = date('M Y', strtotime($row['mois'] . '-01'));
    $chartExamens[] = $row['examens'];
    $chartRevenus[] = $row['revenus'];
}

$categoriesLabels = [];
$categoriesData = [];
foreach ($examensParCategorie as $row) {
    $categoriesLabels[] = $row['categorie'] ?? 'Non classé';
    $categoriesData[] = $row['nombre'];
}

// Traductions des jours
$joursTraduction = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche'
];
?>

<!-- En-tête de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-bar-chart-line text-primary"></i>
            Statistiques & Rapports
        </h1>
        <p class="page-subtitle">Analyse de l'activité du laboratoire</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer me-2"></i>Imprimer
        </button>
    </div>
</div>

<!-- Filtres de période -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Période</label>
                <select class="form-select" name="periode" id="periodeSelect" onchange="toggleCustomDates()">
                    <option value="semaine" <?= $periode === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                    <option value="mois" <?= $periode === 'mois' ? 'selected' : '' ?>>Ce mois</option>
                    <option value="annee" <?= $periode === 'annee' ? 'selected' : '' ?>>Cette année</option>
                    <option value="personnalise" <?= $periode === 'personnalise' ? 'selected' : '' ?>>Personnalisé</option>
                </select>
            </div>
            <div class="col-md-3" id="dateDebutField" style="<?= $periode !== 'personnalise' ? 'display:none;' : '' ?>">
                <label class="form-label">Date début</label>
                <input type="date" class="form-control" name="date_debut" value="<?= $dateDebut ?>">
            </div>
            <div class="col-md-3" id="dateFinField" style="<?= $periode !== 'personnalise' ? 'display:none;' : '' ?>">
                <label class="form-label">Date fin</label>
                <input type="date" class="form-control" name="date_fin" value="<?= $dateFin ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Appliquer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($statsGenerales['total_patients']) ?></div>
                <div class="stat-label">Patients total</div>
                <small class="text-success">+<?= $statsGenerales['nouveaux_patients'] ?> nouveaux</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($statsGenerales['total_rdv']) ?></div>
                <div class="stat-label">Rendez-vous</div>
                <small class="text-muted">sur la période</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($statsGenerales['examens_periode']) ?></div>
                <div class="stat-label">Examens</div>
                <small class="text-muted"><?= $statsGenerales['resultats_valides'] ?> validés</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-currency-euro"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($chiffreAffaires['ca_encaisse'], 0, ',', ' ') ?> &euro;</div>
                <div class="stat-label">Chiffre d'affaires</div>
                <small class="text-muted"><?= $chiffreAffaires['nb_paiements'] ?> paiements</small>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row g-4 mb-4">
    <!-- Évolution mensuelle -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>Évolution sur 12 mois
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Répartition par catégorie -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart me-2"></i>Par catégorie
                </h5>
            </div>
            <div class="card-body">
                <canvas id="categoriesChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux détaillés -->
<div class="row g-4 mb-4">
    <!-- Top examens -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-trophy me-2"></i>Top 10 des examens
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Examen</th>
                            <th class="text-center">Nombre</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topExamens as $index => $exam): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= $index + 1 ?></span></td>
                                <td>
                                    <span class="badge bg-dark font-monospace me-1"><?= htmlspecialchars($exam['code']) ?></span>
                                    <?= htmlspecialchars($exam['nom']) ?>
                                </td>
                                <td class="text-center"><?= $exam['nombre'] ?></td>
                                <td class="text-end fw-medium"><?= number_format($exam['montant'], 2) ?> &euro;</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topExamens)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Aucune donnée pour cette période</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Activité par jour -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-week me-2"></i>Activité par jour
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php 
                    $maxActivite = max(array_column($activiteJour, 'nombre') ?: [1]);
                    foreach ($activiteJour as $jour): 
                        $pourcentage = ($jour['nombre'] / $maxActivite) * 100;
                    ?>
                        <div class="col-12">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?= $joursTraduction[$jour['jour']] ?? $jour['jour'] ?></span>
                                <span class="fw-medium"><?= $jour['nombre'] ?> RDV</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: <?= $pourcentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($activiteJour)): ?>
                        <div class="col-12 text-center text-muted py-4">Aucune donnée pour cette période</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rendez-vous par statut -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>Rendez-vous par statut
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php 
                    $statutLabels = [
                        'planifie' => ['Planifié', 'bg-secondary'],
                        'confirme' => ['Confirmé', 'bg-info'],
                        'en_cours' => ['En cours', 'bg-warning'],
                        'termine' => ['Terminé', 'bg-success'],
                        'annule' => ['Annulé', 'bg-danger']
                    ];
                    foreach ($rdvParStatut as $statut): 
                        $info = $statutLabels[$statut['statut']] ?? [$statut['statut'], 'bg-secondary'];
                    ?>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 text-center">
                                <div class="fs-3 fw-bold"><?= $statut['nombre'] ?></div>
                                <span class="badge <?= $info[1] ?>"><?= $info[0] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dernières actions -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Dernières actions
                </h5>
            </div>
            <div class="table-responsive" style="max-height: 300px;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="sticky-top bg-white">
                        <tr>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernieresActions as $action): ?>
                            <tr>
                                <td><?= htmlspecialchars($action['prenom'] . ' ' . $action['nom']) ?></td>
                                <td>
                                    <small><?= htmlspecialchars($action['action']) ?></small>
                                    <?php if ($action['table_concernee']): ?>
                                        <span class="badge bg-light text-dark"><?= $action['table_concernee'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= formatDateTime($action['date_action']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Toggle dates personnalisées
function toggleCustomDates() {
    const periode = document.getElementById('periodeSelect').value;
    document.getElementById('dateDebutField').style.display = periode === 'personnalise' ? '' : 'none';
    document.getElementById('dateFinField').style.display = periode === 'personnalise' ? '' : 'none';
}

// Graphique d'évolution
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Examens',
                data: <?= json_encode($chartExamens) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Revenus (€)',
                data: <?= json_encode($chartRevenus) ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                title: { display: true, text: 'Examens' }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                title: { display: true, text: 'Revenus (€)' },
                grid: { drawOnChartArea: false }
            }
        }
    }
});

// Graphique par catégorie
const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
new Chart(categoriesCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($categoriesLabels) ?>,
        datasets: [{
            data: <?= json_encode($categoriesData) ?>,
            backgroundColor: [
                '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1',
                '#0dcaf0', '#fd7e14', '#20c997', '#6c757d'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
