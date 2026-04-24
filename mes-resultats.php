<?php
/**
 * Mes Résultats - Page Patient
 * Accessible uniquement aux patients pour consulter leurs résultats médicaux
 */

$pageTitle = 'Mes Résultats';
require_once __DIR__ . '/includes/header.php';
requireRole('patient');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Récupérer le patient_id lié à cet utilisateur
$stmtPatient = $pdo->prepare("SELECT patient_id FROM utilisateurs WHERE id = ?");
$stmtPatient->execute([$userId]);
$userData = $stmtPatient->fetch();
$patientId = $userData['patient_id'];

if (!$patientId) {
    setFlashMessage('error', 'Votre compte n\'est pas lié à un dossier patient. Veuillez contacter l\'administration.');
    header('Location: dashboard.php');
    exit;
}

// Afficher un résultat spécifique
$viewResult = isset($_GET['view']) ? intval($_GET['view']) : null;

if ($viewResult):
    // Récupérer le résultat spécifique
    $stmt = $pdo->prepare("
        SELECT r.*, 
               e.id as examen_id,
               te.code, te.nom as type_nom, te.description as type_description,
               de.date_demande, de.medecin_prescripteur,
               u.prenom as saisi_prenom, u.nom as saisi_nom,
               uv.prenom as valide_prenom, uv.nom as valide_nom
        FROM resultats r
        JOIN examens e ON r.examen_id = e.id
        JOIN types_examens te ON e.type_examen_id = te.id
        JOIN demandes_examens de ON e.demande_id = de.id
        LEFT JOIN utilisateurs u ON r.saisi_par = u.id
        LEFT JOIN utilisateurs uv ON r.valide_par = uv.id
        WHERE de.patient_id = ? AND r.id = ? AND r.valide = TRUE
    ");
    $stmt->execute([$patientId, $viewResult]);
    $resultat = $stmt->fetch();
    
    if (!$resultat) {
        setFlashMessage('error', 'Résultat non trouvé ou non disponible.');
        header('Location: mes-resultats.php');
        exit;
    }
?>

<!-- Détail du résultat -->
<div class="page-header">
    <div class="page-header-content">
        <a href="mes-resultats.php" class="btn btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title">Détail du résultat</h1>
            <p class="page-subtitle"><?= htmlspecialchars($resultat['type_nom']) ?></p>
        </div>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-outline-primary">
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <code class="me-2"><?= htmlspecialchars($resultat['code']) ?></code>
                    <?= htmlspecialchars($resultat['type_nom']) ?>
                </h5>
                <span class="badge bg-<?= match($resultat['interpretation']) {
                    'normal' => 'success',
                    'anormal_bas', 'anormal_haut' => 'warning',
                    'critique' => 'danger',
                    default => 'secondary'
                } ?> fs-6">
                    <?= ucfirst(str_replace('_', ' ', $resultat['interpretation'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="result-box p-4 bg-light rounded text-center">
                            <div class="text-muted small mb-2">Résultat</div>
                            <div class="display-4 fw-bold text-<?= match($resultat['interpretation']) {
                                'normal' => 'success',
                                'anormal_bas', 'anormal_haut' => 'warning',
                                'critique' => 'danger',
                                default => 'dark'
                            } ?>">
                                <?= htmlspecialchars($resultat['valeur']) ?>
                                <?php if ($resultat['unite']): ?>
                                    <span class="fs-5 text-muted"><?= htmlspecialchars($resultat['unite']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="result-box p-4 bg-light rounded text-center">
                            <div class="text-muted small mb-2">Valeurs de référence</div>
                            <div class="fs-4">
                                <?= htmlspecialchars($resultat['valeur_normale'] ?: 'Non spécifié') ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($resultat['commentaire']): ?>
                    <div class="mt-4 p-3 border-start border-4 border-primary bg-light">
                        <h6 class="text-primary mb-2"><i class="bi bi-chat-text me-2"></i>Commentaire du technicien</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($resultat['commentaire'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($resultat['type_description']): ?>
                    <div class="mt-4">
                        <h6 class="text-muted"><i class="bi bi-info-circle me-2"></i>À propos de cet examen</h6>
                        <p class="text-muted mb-0"><?= htmlspecialchars($resultat['type_description']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Informations</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3">
                        <small class="text-muted d-block">Date de la demande</small>
                        <strong><?= formatDateTime($resultat['date_demande']) ?></strong>
                    </li>
                    <?php if ($resultat['medecin_prescripteur']): ?>
                        <li class="mb-3">
                            <small class="text-muted d-block">Médecin prescripteur</small>
                            <strong><?= htmlspecialchars($resultat['medecin_prescripteur']) ?></strong>
                        </li>
                    <?php endif; ?>
                    <li class="mb-3">
                        <small class="text-muted d-block">Date du résultat</small>
                        <strong><?= formatDateTime($resultat['date_saisie']) ?></strong>
                    </li>
                    <li class="mb-3">
                        <small class="text-muted d-block">Validé le</small>
                        <strong><?= formatDateTime($resultat['date_validation']) ?></strong>
                    </li>
                    <li>
                        <small class="text-muted d-block">Validé par</small>
                        <strong><?= htmlspecialchars($resultat['valide_prenom'] . ' ' . $resultat['valide_nom']) ?></strong>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3 border-warning">
            <div class="card-body">
                <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Important</h6>
                <p class="small text-muted mb-0">
                    Ces résultats sont fournis à titre informatif. Pour toute interprétation médicale, 
                    veuillez consulter votre médecin traitant.
                </p>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<?php
// Liste de tous les résultats du patient
$stmt = $pdo->prepare("
    SELECT r.*, 
           e.id as examen_id,
           te.code, te.nom as type_nom, te.categorie,
           de.date_demande
    FROM resultats r
    JOIN examens e ON r.examen_id = e.id
    JOIN types_examens te ON e.type_examen_id = te.id
    JOIN demandes_examens de ON e.demande_id = de.id
    WHERE de.patient_id = ? AND r.valide = TRUE
    ORDER BY r.date_saisie DESC
");
$stmt->execute([$patientId]);
$resultats = $stmt->fetchAll();

// Grouper par catégorie
$categories = [];
foreach ($resultats as $r) {
    $cat = $r['categorie'] ?: 'Autres';
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = $r;
}
?>

<!-- En-tête de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-file-earmark-medical text-primary me-2"></i>
            Mes Résultats
        </h1>
        <p class="page-subtitle">
            Consultez vos résultats d'analyses médicales validés
        </p>
    </div>
</div>

<?php if (empty($resultats)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-x display-4 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Aucun résultat disponible</h5>
            <p class="text-muted">Vos résultats d'analyses apparaîtront ici une fois validés par le laboratoire.</p>
        </div>
    </div>
<?php else: ?>

<!-- Statistiques -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-primary"><?= count($resultats) ?></div>
            <div class="text-muted small">Total résultats</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <?php 
            $normalCount = count(array_filter($resultats, fn($r) => $r['interpretation'] === 'normal'));
            ?>
            <div class="fs-2 fw-bold text-success"><?= $normalCount ?></div>
            <div class="text-muted small">Normaux</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <?php 
            $anormalCount = count(array_filter($resultats, fn($r) => in_array($r['interpretation'], ['anormal_bas', 'anormal_haut'])));
            ?>
            <div class="fs-2 fw-bold text-warning"><?= $anormalCount ?></div>
            <div class="text-muted small">Anormaux</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <?php 
            $critiqueCount = count(array_filter($resultats, fn($r) => $r['interpretation'] === 'critique'));
            ?>
            <div class="fs-2 fw-bold text-danger"><?= $critiqueCount ?></div>
            <div class="text-muted small">Critiques</div>
        </div>
    </div>
</div>

<!-- Résultats par catégorie -->
<?php foreach ($categories as $categorie => $catResultats): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-folder me-2"></i><?= htmlspecialchars($categorie) ?>
                <span class="badge bg-secondary ms-2"><?= count($catResultats) ?></span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Examen</th>
                        <th>Résultat</th>
                        <th>Interprétation</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catResultats as $resultat): ?>
                        <tr class="<?= $resultat['interpretation'] === 'critique' ? 'table-danger' : '' ?>">
                            <td>
                                <code class="text-primary me-2"><?= htmlspecialchars($resultat['code']) ?></code>
                                <span><?= htmlspecialchars($resultat['type_nom']) ?></span>
                            </td>
                            <td>
                                <strong class="text-<?= match($resultat['interpretation']) {
                                    'normal' => 'success',
                                    'anormal_bas', 'anormal_haut' => 'warning',
                                    'critique' => 'danger',
                                    default => 'dark'
                                } ?>">
                                    <?= htmlspecialchars($resultat['valeur']) ?>
                                </strong>
                                <?php if ($resultat['unite']): ?>
                                    <span class="text-muted"><?= htmlspecialchars($resultat['unite']) ?></span>
                                <?php endif; ?>
                                <?php if ($resultat['valeur_normale']): ?>
                                    <div class="small text-muted">Réf: <?= htmlspecialchars($resultat['valeur_normale']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($resultat['interpretation']) {
                                    'normal' => 'success',
                                    'anormal_bas', 'anormal_haut' => 'warning',
                                    'critique' => 'danger',
                                    default => 'secondary'
                                } ?>">
                                    <?= ucfirst(str_replace('_', ' ', $resultat['interpretation'])) ?>
                                </span>
                            </td>
                            <td>
                                <span><?= formatDate($resultat['date_saisie']) ?></span>
                            </td>
                            <td>
                                <a href="?view=<?= $resultat['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
