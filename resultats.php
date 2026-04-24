<?php
/**
 * Gestion des résultats
 * Gestion Laboratoire Médical
 */

$pageTitle = 'Résultats';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$action = $_GET['action'] ?? 'list';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'saisir_resultat') {
        $examenId = intval($_POST['examen_id']);
        
        $stmt = $pdo->prepare("
            INSERT INTO resultats (examen_id, valeur, unite, valeur_normale, interpretation, commentaire, saisi_par)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                $examenId,
                sanitize($_POST['valeur']),
                sanitize($_POST['unite'] ?? ''),
                sanitize($_POST['valeur_normale'] ?? ''),
                $_POST['interpretation'],
                sanitize($_POST['commentaire'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            // Mettre à jour le statut de l'examen
            $pdo->prepare("UPDATE examens SET statut = 'termine' WHERE id = ?")->execute([$examenId]);
            
            // Mettre à jour la demande si tous les examens sont terminés
            $checkStmt = $pdo->prepare("
                SELECT de.id, 
                       COUNT(e.id) as total,
                       SUM(CASE WHEN e.statut = 'termine' THEN 1 ELSE 0 END) as termines
                FROM examens e
                JOIN demandes_examens de ON e.demande_id = de.id
                WHERE e.id = ?
                GROUP BY de.id
            ");
            $checkStmt->execute([$examenId]);
            $check = $checkStmt->fetch();
            
            if ($check && $check['total'] == $check['termines']) {
                $pdo->prepare("UPDATE demandes_examens SET statut = 'termine' WHERE id = ?")
                    ->execute([$check['id']]);
            } else {
                $pdo->prepare("UPDATE demandes_examens SET statut = 'en_cours' WHERE id = ?")
                    ->execute([$check['id']]);
            }
            
            logAction('saisie_resultat', 'resultats', $pdo->lastInsertId(), "Résultat saisi");
            setFlashMessage('success', "Résultat enregistré avec succès.");
            header("Location: resultats.php");
            exit;
        } catch (PDOException $e) {
            setFlashMessage('error', "Erreur: " . $e->getMessage());
        }
    }
    
    if ($postAction === 'valider') {
        $resultatId = intval($_POST['resultat_id']);
        
        $pdo->prepare("UPDATE resultats SET valide = TRUE, date_validation = NOW(), valide_par = ? WHERE id = ?")
            ->execute([$_SESSION['user_id'], $resultatId]);
        
        logAction('validation_resultat', 'resultats', $resultatId, "Résultat validé");
        setFlashMessage('success', "Résultat validé.");
        header("Location: resultats.php");
        exit;
    }
    
    if ($postAction === 'valider_multiple') {
        $ids = $_POST['resultat_ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$_SESSION['user_id']], $ids);
            
            $pdo->prepare("UPDATE resultats SET valide = TRUE, date_validation = NOW(), valide_par = ? WHERE id IN ($placeholders)")
                ->execute($params);
            
            setFlashMessage('success', count($ids) . " résultat(s) validé(s).");
        }
        header("Location: resultats.php");
        exit;
    }
}

// Saisie de résultat
if ($action === 'saisir' && isset($_GET['examen'])): 
    $examenId = intval($_GET['examen']);
    $examenStmt = $pdo->prepare("
        SELECT e.*, te.code, te.nom as type_nom, te.valeurs_normales, te.description,
               p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
               de.date_demande, de.medecin_prescripteur
        FROM examens e
        JOIN types_examens te ON e.type_examen_id = te.id
        JOIN demandes_examens de ON e.demande_id = de.id
        JOIN patients p ON de.patient_id = p.id
        WHERE e.id = ?
    ");
    $examenStmt->execute([$examenId]);
    $examen = $examenStmt->fetch();
    
    if (!$examen) {
        setFlashMessage('error', 'Examen non trouvé.');
        header('Location: examens.php');
        exit;
    }
?>

    <!-- Formulaire Saisie Résultat -->
    <div class="page-header">
        <h1 class="page-title">
            <a href="examens.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
            Saisir résultat
        </h1>
    </div>
    
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <code class="me-2"><?= $examen['code'] ?></code>
                        <?= htmlspecialchars($examen['type_nom']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="resultats.php" data-validate>
                        <input type="hidden" name="action" value="saisir_resultat">
                        <input type="hidden" name="examen_id" value="<?= $examenId ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Valeur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" name="valeur" required autofocus
                                       placeholder="Ex: 1.05">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unité</label>
                                <input type="text" class="form-control form-control-lg" name="unite"
                                       placeholder="Ex: g/L, mUI/L...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Valeurs normales</label>
                                <input type="text" class="form-control" name="valeur_normale"
                                       value="<?= htmlspecialchars($examen['valeurs_normales'] ?? '') ?>"
                                       placeholder="Ex: 0.70 - 1.10 g/L">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Interprétation <span class="text-danger">*</span></label>
                                <select class="form-select" name="interpretation" required>
                                    <option value="normal">Normal</option>
                                    <option value="anormal_bas">Anormal (bas)</option>
                                    <option value="anormal_haut">Anormal (haut)</option>
                                    <option value="critique">Critique</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Commentaire</label>
                                <textarea class="form-control" name="commentaire" rows="3"
                                          placeholder="Observations, recommandations..."></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="examens.php" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Enregistrer le résultat
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Patient</h5>
                </div>
                <div class="card-body">
                    <h6><?= htmlspecialchars($examen['patient_prenom'] . ' ' . $examen['patient_nom']) ?></h6>
                    <p class="text-muted mb-2"><code><?= $examen['numero_dossier'] ?></code></p>
                    <p class="small text-muted mb-0">
                        <strong>Demande:</strong> <?= formatDateTime($examen['date_demande']) ?><br>
                        <?php if ($examen['medecin_prescripteur']): ?>
                            <strong>Prescripteur:</strong> <?= htmlspecialchars($examen['medecin_prescripteur']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <?php if ($examen['description']): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Description</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0"><?= htmlspecialchars($examen['description']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    
    <?php
    // Filtres
    $valideFilter = $_GET['valide'] ?? '';
    $patientFilter = isset($_GET['patient']) ? intval($_GET['patient']) : null;
    
    // Construire la requête
    $whereConditions = [];
    $params = [];
    
    if ($valideFilter !== '') {
        $whereConditions[] = "r.valide = ?";
        $params[] = $valideFilter === '1' ? 1 : 0;
    }
    
    if ($patientFilter) {
        $whereConditions[] = "de.patient_id = ?";
        $params[] = $patientFilter;
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Récupérer les résultats
    $stmt = $pdo->prepare("
        SELECT r.*, 
               e.id as examen_id,
               te.code, te.nom as type_nom,
               p.id as patient_id, p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
               u.prenom as saisi_prenom, uv.prenom as valide_prenom
        FROM resultats r
        JOIN examens e ON r.examen_id = e.id
        JOIN types_examens te ON e.type_examen_id = te.id
        JOIN demandes_examens de ON e.demande_id = de.id
        JOIN patients p ON de.patient_id = p.id
        LEFT JOIN utilisateurs u ON r.saisi_par = u.id
        LEFT JOIN utilisateurs uv ON r.valide_par = uv.id
        $whereClause
        ORDER BY r.date_saisie DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $resultats = $stmt->fetchAll();
    
    // Compter les non validés
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM resultats WHERE valide = FALSE")->fetchColumn();
    ?>
    
    <!-- Liste des Résultats -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Résultats</h1>
            <p class="page-subtitle">
                <?= count($resultats) ?> résultats
                <?php if ($pendingCount > 0): ?>
                    - <span class="text-warning"><?= $pendingCount ?> en attente de validation</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Validation</label>
                    <select class="form-select" name="valide">
                        <option value="">Tous</option>
                        <option value="0" <?= $valideFilter === '0' ? 'selected' : '' ?>>Non validés</option>
                        <option value="1" <?= $valideFilter === '1' ? 'selected' : '' ?>>Validés</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                    <a href="resultats.php" class="btn btn-outline-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tableau -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($resultats)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x display-4 mb-3 d-block"></i>
                    <p>Aucun résultat trouvé</p>
                </div>
            <?php else: ?>
                <form method="POST" id="validationForm">
                    <input type="hidden" name="action" value="valider_multiple">
                    
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>Patient</th>
                                    <th>Examen</th>
                                    <th>Valeur</th>
                                    <th>Interprétation</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultats as $resultat): ?>
                                    <tr class="<?= $resultat['interpretation'] === 'critique' ? 'table-danger' : '' ?>">
                                        <td>
                                            <?php if (!$resultat['valide']): ?>
                                                <input type="checkbox" class="form-check-input result-checkbox" 
                                                       name="resultat_ids[]" value="<?= $resultat['id'] ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="patient-detail.php?id=<?= $resultat['patient_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($resultat['patient_prenom'] . ' ' . $resultat['patient_nom']) ?>
                                            </a>
                                            <div class="small text-muted"><?= $resultat['numero_dossier'] ?></div>
                                        </td>
                                        <td>
                                            <code class="text-primary"><?= $resultat['code'] ?></code>
                                            <div class="small"><?= htmlspecialchars($resultat['type_nom']) ?></div>
                                        </td>
                                        <td>
                                            <strong class="<?= match($resultat['interpretation']) {
                                                'normal' => 'text-success',
                                                'anormal_bas', 'anormal_haut' => 'text-warning',
                                                'critique' => 'text-danger',
                                                default => ''
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
                                            <?= formatDateTime($resultat['date_saisie']) ?>
                                            <div class="small text-muted">par <?= $resultat['saisi_prenom'] ?></div>
                                        </td>
                                        <td>
                                            <?php if ($resultat['valide']): ?>
                                                <span class="badge bg-success"><i class="bi bi-check me-1"></i>Validé</span>
                                                <div class="small text-muted"><?= $resultat['valide_prenom'] ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">En attente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$resultat['valide'] && hasRole(['administrateur', 'technicien'])): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="valider">
                                                    <input type="hidden" name="resultat_id" value="<?= $resultat['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Valider">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#printModal<?= $resultat['id'] ?>"
                                                    title="Imprimer">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (hasRole(['administrateur', 'technicien'])): ?>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success" id="validateSelected" disabled>
                                <i class="bi bi-check-all me-1"></i>Valider la sélection
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.result-checkbox');
        const validateBtn = document.getElementById('validateSelected');
        
        function updateButton() {
            const checked = document.querySelectorAll('.result-checkbox:checked').length;
            if (validateBtn) {
                validateBtn.disabled = checked === 0;
                validateBtn.textContent = checked > 0 
                    ? `Valider la sélection (${checked})` 
                    : 'Valider la sélection';
            }
        }
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateButton();
            });
        }
        
        checkboxes.forEach(cb => cb.addEventListener('change', updateButton));
    });
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
