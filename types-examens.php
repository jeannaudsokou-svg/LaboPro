<?php
/**
 * Gestion des Types d'Examens
 * Accessible uniquement aux administrateurs
 */

$pageTitle = 'Types d\'Examens';
require_once __DIR__ . '/includes/header.php';
requireRole('administrateur');

$pdo = getConnection();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $code = strtoupper(sanitize($_POST['code']));
        $nom = sanitize($_POST['nom']);
        $description = sanitize($_POST['description']);
        $categorie = sanitize($_POST['categorie']);
        $prix = floatval($_POST['prix']);
        $delai_resultat = intval($_POST['delai_resultat']);
        $valeurs_normales = sanitize($_POST['valeurs_normales']);
        
        // Vérifier si le code existe déjà
        $checkStmt = $pdo->prepare("SELECT id FROM types_examens WHERE code = ?");
        $checkStmt->execute([$code]);
        
        if ($checkStmt->fetch()) {
            setFlashMessage('error', 'Ce code d\'examen existe déjà.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO types_examens (code, nom, description, categorie, prix, delai_resultat, valeurs_normales, actif)
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            if ($stmt->execute([$code, $nom, $description, $categorie, $prix, $delai_resultat, $valeurs_normales])) {
                $newId = $pdo->lastInsertId();
                logAction('creation', 'types_examens', $newId, "Création du type d'examen: $nom ($code)");
                setFlashMessage('success', 'Type d\'examen créé avec succès.');
            } else {
                setFlashMessage('error', 'Erreur lors de la création.');
            }
        }
        header('Location: types-examens.php');
        exit;
    }
    
    if ($action === 'update') {
        $id = $_POST['id'];
        $code = strtoupper(sanitize($_POST['code']));
        $nom = sanitize($_POST['nom']);
        $description = sanitize($_POST['description']);
        $categorie = sanitize($_POST['categorie']);
        $prix = floatval($_POST['prix']);
        $delai_resultat = intval($_POST['delai_resultat']);
        $valeurs_normales = sanitize($_POST['valeurs_normales']);
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        // Vérifier si le code existe déjà (pour un autre type)
        $checkStmt = $pdo->prepare("SELECT id FROM types_examens WHERE code = ? AND id != ?");
        $checkStmt->execute([$code, $id]);
        
        if ($checkStmt->fetch()) {
            setFlashMessage('error', 'Ce code d\'examen est déjà utilisé.');
        } else {
            $stmt = $pdo->prepare("
                UPDATE types_examens 
                SET code = ?, nom = ?, description = ?, categorie = ?, prix = ?, 
                    delai_resultat = ?, valeurs_normales = ?, actif = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$code, $nom, $description, $categorie, $prix, $delai_resultat, $valeurs_normales, $actif, $id])) {
                logAction('modification', 'types_examens', $id, "Modification du type d'examen: $nom");
                setFlashMessage('success', 'Type d\'examen mis à jour avec succès.');
            } else {
                setFlashMessage('error', 'Erreur lors de la mise à jour.');
            }
        }
        header('Location: types-examens.php');
        exit;
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        // Vérifier si des examens utilisent ce type
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM examens WHERE type_examen_id = ?");
        $checkStmt->execute([$id]);
        $check = $checkStmt->fetch();
        
        if ($check['count'] > 0) {
            // Désactiver plutôt que supprimer
            $stmt = $pdo->prepare("UPDATE types_examens SET actif = FALSE WHERE id = ?");
            if ($stmt->execute([$id])) {
                logAction('desactivation', 'types_examens', $id, "Désactivation du type d'examen");
                setFlashMessage('warning', 'Type d\'examen désactivé (des examens existants l\'utilisent).');
            }
        } else {
            $stmt = $pdo->prepare("DELETE FROM types_examens WHERE id = ?");
            if ($stmt->execute([$id])) {
                logAction('suppression', 'types_examens', $id, "Suppression du type d'examen");
                setFlashMessage('success', 'Type d\'examen supprimé avec succès.');
            }
        }
        header('Location: types-examens.php');
        exit;
    }
}

// Filtres
$searchTerm = $_GET['search'] ?? '';
$filterCategorie = $_GET['categorie'] ?? '';
$filterActif = $_GET['actif'] ?? '';

$sql = "SELECT * FROM types_examens WHERE 1=1";
$params = [];

if ($searchTerm) {
    $sql .= " AND (code LIKE ? OR nom LIKE ? OR description LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($filterCategorie) {
    $sql .= " AND categorie = ?";
    $params[] = $filterCategorie;
}

if ($filterActif !== '') {
    $sql .= " AND actif = ?";
    $params[] = $filterActif;
}

$sql .= " ORDER BY categorie, nom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$typesExamens = $stmt->fetchAll();

// Récupérer les catégories distinctes
$categoriesStmt = $pdo->query("SELECT DISTINCT categorie FROM types_examens WHERE categorie IS NOT NULL ORDER BY categorie");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as actifs,
        COUNT(DISTINCT categorie) as categories,
        AVG(prix) as prix_moyen
    FROM types_examens
");
$stats = $statsStmt->fetch();
?>

<!-- En-tête de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-list-check text-primary"></i>
            Types d'Examens
        </h1>
        <p class="page-subtitle">Configurer les examens disponibles au laboratoire</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTypeExamen">
            <i class="bi bi-plus-lg me-2"></i>Nouveau Type
        </button>
    </div>
</div>

<!-- Statistiques -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total examens</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['actifs'] ?></div>
                <div class="stat-label">Actifs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-folder"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= $stats['categories'] ?></div>
                <div class="stat-label">Catégories</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-currency-euro"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['prix_moyen'], 2) ?> &euro;</div>
                <div class="stat-label">Prix moyen</div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="categorie">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategorie === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="actif">
                    <option value="">Tous les statuts</option>
                    <option value="1" <?= $filterActif === '1' ? 'selected' : '' ?>>Actif</option>
                    <option value="0" <?= $filterActif === '0' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des types d'examens -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Catalogue des examens (<?= count($typesExamens) ?>)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Catégorie</th>
                    <th>Prix</th>
                    <th>Délai</th>
                    <th>Valeurs normales</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($typesExamens)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">Aucun type d'examen trouvé</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($typesExamens as $type): ?>
                        <tr class="<?= !$type['actif'] ? 'table-secondary' : '' ?>">
                            <td>
                                <span class="badge bg-dark font-monospace"><?= htmlspecialchars($type['code']) ?></span>
                            </td>
                            <td>
                                <div class="fw-medium"><?= htmlspecialchars($type['nom']) ?></div>
                                <?php if ($type['description']): ?>
                                    <small class="text-muted"><?= htmlspecialchars(substr($type['description'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($type['categorie'] ?? 'Non classé') ?></span>
                            </td>
                            <td class="fw-medium"><?= number_format($type['prix'], 2) ?> &euro;</td>
                            <td><?= $type['delai_resultat'] ?>h</td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars(substr($type['valeurs_normales'] ?? '-', 0, 30)) ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $type['actif'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $type['actif'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" 
                                            onclick="editTypeExamen(<?= htmlspecialchars(json_encode($type)) ?>)"
                                            title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="confirmDelete(<?= $type['id'] ?>, '<?= htmlspecialchars($type['nom']) ?>')"
                                            title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Créer/Modifier Type d'Examen -->
<div class="modal fade" id="modalTypeExamen" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formTypeExamen">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="typeId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau Type d'Examen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="code" id="typeCode" required maxlength="20" placeholder="Ex: NFS, GLY...">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" id="typeNom" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="typeDescription" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Catégorie</label>
                            <input type="text" class="form-control" name="categorie" id="typeCategorie" list="categoriesList" placeholder="Ex: Biochimie">
                            <datalist id="categoriesList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Prix (&euro;) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="prix" id="typePrix" required min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Délai résultat (heures)</label>
                            <input type="number" class="form-control" name="delai_resultat" id="typeDelai" value="24" min="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Valeurs normales</label>
                            <textarea class="form-control" name="valeurs_normales" id="typeValeurs" rows="2" placeholder="Ex: 0.70 - 1.10 g/L"></textarea>
                        </div>
                        <div class="col-12" id="actifField" style="display: none;">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="actif" id="typeActif" checked>
                                <label class="form-check-label" for="typeActif">Examen actif (disponible à la prescription)</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmation Suppression -->
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Voulez-vous vraiment supprimer <strong id="deleteName"></strong> ?</p>
                    <p class="text-muted small">Si des examens utilisent ce type, il sera désactivé.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Éditer un type d'examen
function editTypeExamen(type) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('typeId').value = type.id;
    document.getElementById('typeCode').value = type.code;
    document.getElementById('typeNom').value = type.nom;
    document.getElementById('typeDescription').value = type.description || '';
    document.getElementById('typeCategorie').value = type.categorie || '';
    document.getElementById('typePrix').value = type.prix;
    document.getElementById('typeDelai').value = type.delai_resultat;
    document.getElementById('typeValeurs').value = type.valeurs_normales || '';
    document.getElementById('typeActif').checked = type.actif == 1;
    document.getElementById('actifField').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Modifier le Type d\'Examen';
    document.getElementById('submitBtn').textContent = 'Enregistrer';
    
    new bootstrap.Modal(document.getElementById('modalTypeExamen')).show();
}

// Réinitialiser le formulaire
document.getElementById('modalTypeExamen').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formTypeExamen').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('typeId').value = '';
    document.getElementById('actifField').style.display = 'none';
    document.getElementById('modalTitle').textContent = 'Nouveau Type d\'Examen';
    document.getElementById('submitBtn').textContent = 'Créer';
});

// Confirmer la suppression
function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('modalDelete')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
