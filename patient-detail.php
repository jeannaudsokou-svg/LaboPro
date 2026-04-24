<?php
/**
 * Détail d'un patient
 * Gestion Laboratoire Médical
 */

require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getConnection();

// Récupérer le patient
$patientId = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    setFlashMessage('error', 'Patient non trouvé.');
    header('Location: patients.php');
    exit;
}

$pageTitle = $patient['prenom'] . ' ' . $patient['nom'];

// Récupérer les rendez-vous
$rdvStmt = $pdo->prepare("
    SELECT r.*, u.prenom as cree_prenom, u.nom as cree_nom
    FROM rendez_vous r
    LEFT JOIN utilisateurs u ON r.cree_par = u.id
    WHERE r.patient_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 10
");
$rdvStmt->execute([$patientId]);
$rendezvous = $rdvStmt->fetchAll();

// Récupérer les demandes d'examens
$examensStmt = $pdo->prepare("
    SELECT de.*, 
           GROUP_CONCAT(te.nom SEPARATOR ', ') as examens_noms,
           COUNT(e.id) as nb_examens,
           SUM(CASE WHEN e.statut = 'termine' THEN 1 ELSE 0 END) as nb_termines
    FROM demandes_examens de
    LEFT JOIN examens e ON e.demande_id = de.id
    LEFT JOIN types_examens te ON e.type_examen_id = te.id
    WHERE de.patient_id = ?
    GROUP BY de.id
    ORDER BY de.date_demande DESC
    LIMIT 10
");
$examensStmt->execute([$patientId]);
$demandes = $examensStmt->fetchAll();

// Récupérer les derniers résultats
$resultatsStmt = $pdo->prepare("
    SELECT r.*, te.nom as examen_nom, te.code as examen_code, r.date_saisie
    FROM resultats r
    JOIN examens e ON r.examen_id = e.id
    JOIN demandes_examens de ON e.demande_id = de.id
    JOIN types_examens te ON e.type_examen_id = te.id
    WHERE de.patient_id = ? AND r.valide = TRUE
    ORDER BY r.date_saisie DESC
    LIMIT 15
");
$resultatsStmt->execute([$patientId]);
$resultats = $resultatsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- En-tête Patient -->
<div class="page-header mb-4">
    <div class="d-flex align-items-start justify-content-between">
        <div class="d-flex align-items-center">
            <a href="patients.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
            <div class="patient-avatar <?= $patient['sexe'] === 'M' ? 'male' : 'female' ?>" style="width:70px;height:70px;font-size:1.5rem;">
                <?= strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)) ?>
            </div>
            <div class="ms-3">
                <h1 class="page-title mb-1"><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></h1>
                <p class="page-subtitle mb-0">
                    <code class="me-2"><?= htmlspecialchars($patient['numero_dossier']) ?></code>
                    <span class="badge bg-secondary"><?= $patient['sexe'] === 'M' ? 'Homme' : 'Femme' ?></span>
                    <span class="ms-2"><?= calculateAge($patient['date_naissance']) ?> ans</span>
                    <?php if ($patient['groupe_sanguin']): ?>
                        <span class="badge bg-danger ms-2"><?= $patient['groupe_sanguin'] ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="btn-group">
            <a href="patients.php?action=edit&id=<?= $patient['id'] ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Modifier
            </a>
            <a href="rendez-vous.php?action=new&patient=<?= $patient['id'] ?>" class="btn btn-outline-success">
                <i class="bi bi-calendar-plus me-1"></i>Nouveau RDV
            </a>
            <a href="examens.php?action=new&patient=<?= $patient['id'] ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Demander examen
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Informations Patient -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Informations</h5>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted small">Date de naissance</dt>
                    <dd><?= formatDate($patient['date_naissance']) ?></dd>
                    
                    <dt class="text-muted small">Téléphone</dt>
                    <dd><a href="tel:<?= $patient['telephone'] ?>"><?= htmlspecialchars($patient['telephone']) ?></a></dd>
                    
                    <?php if ($patient['email']): ?>
                        <dt class="text-muted small">Email</dt>
                        <dd><a href="mailto:<?= $patient['email'] ?>"><?= htmlspecialchars($patient['email']) ?></a></dd>
                    <?php endif; ?>
                    
                    <?php if ($patient['adresse']): ?>
                        <dt class="text-muted small">Adresse</dt>
                        <dd><?= nl2br(htmlspecialchars($patient['adresse'])) ?></dd>
                    <?php endif; ?>
                    
                    <dt class="text-muted small">Inscrit le</dt>
                    <dd><?= formatDateTime($patient['date_inscription']) ?></dd>
                </dl>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>Dossier médical</h5>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted small">Groupe sanguin</dt>
                    <dd>
                        <?php if ($patient['groupe_sanguin']): ?>
                            <span class="badge bg-danger"><?= $patient['groupe_sanguin'] ?></span>
                        <?php else: ?>
                            <span class="text-muted">Non renseigné</span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="text-muted small">Allergies</dt>
                    <dd>
                        <?php if ($patient['allergies']): ?>
                            <span class="text-danger"><?= nl2br(htmlspecialchars($patient['allergies'])) ?></span>
                        <?php else: ?>
                            <span class="text-muted">Aucune connue</span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="text-muted small">Antécédents médicaux</dt>
                    <dd>
                        <?php if ($patient['antecedents_medicaux']): ?>
                            <?= nl2br(htmlspecialchars($patient['antecedents_medicaux'])) ?>
                        <?php else: ?>
                            <span class="text-muted">Aucun renseigné</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    
    <!-- Historique -->
    <div class="col-lg-8">
        <!-- Rendez-vous -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Rendez-vous</h5>
                <a href="rendez-vous.php?patient=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rendezvous)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucun rendez-vous</p>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rendezvous as $rdv): ?>
                                    <tr>
                                        <td><?= formatDate($rdv['date_rdv']) ?></td>
                                        <td><?= date('H:i', strtotime($rdv['heure_rdv'])) ?></td>
                                        <td><?= htmlspecialchars($rdv['motif'] ?: '-') ?></td>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Examens -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Demandes d'examens</h5>
                <a href="examens.php?patient=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($demandes)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucune demande d'examen</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Examens</th>
                                    <th>Progression</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes as $demande): ?>
                                    <tr>
                                        <td><?= formatDate($demande['date_demande']) ?></td>
                                        <td>
                                            <small><?= htmlspecialchars($demande['examens_noms'] ?: 'Aucun') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($demande['nb_examens'] > 0): ?>
                                                <div class="progress" style="width: 100px; height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?= ($demande['nb_termines'] / $demande['nb_examens']) * 100 ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?= $demande['nb_termines'] ?>/<?= $demande['nb_examens'] ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= match($demande['statut']) {
                                                'termine' => 'success',
                                                'en_cours' => 'primary',
                                                'annule' => 'danger',
                                                default => 'warning'
                                            } ?>">
                                                <?= ucfirst(str_replace('_', ' ', $demande['statut'])) ?>
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
        
        <!-- Résultats récents -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-medical me-2"></i>Résultats récents</h5>
                <a href="resultats.php?patient=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($resultats)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucun résultat validé</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Examen</th>
                                    <th>Valeur</th>
                                    <th>Référence</th>
                                    <th>Interprétation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultats as $resultat): ?>
                                    <tr>
                                        <td><?= formatDate($resultat['date_saisie']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= $resultat['examen_code'] ?></span>
                                            <?= htmlspecialchars($resultat['examen_nom']) ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($resultat['valeur']) ?> <?= $resultat['unite'] ?></strong></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($resultat['valeur_normale'] ?: '-') ?></small></td>
                                        <td>
                                            <span class="badge bg-<?= match($resultat['interpretation']) {
                                                'normal' => 'success',
                                                'anormal_bas' => 'warning',
                                                'anormal_haut' => 'warning',
                                                'critique' => 'danger',
                                                default => 'secondary'
                                            } ?>">
                                                <?= ucfirst(str_replace('_', ' ', $resultat['interpretation'])) ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
