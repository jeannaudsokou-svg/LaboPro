<?php
/**
 * Page d'accès non autorisé
 */
$pageTitle = 'Accès non autorisé';
require_once __DIR__ . '/includes/header.php';
?>

<div class="text-center py-5">
    <i class="bi bi-shield-x text-danger" style="font-size: 5rem;"></i>
    <h1 class="mt-4">Accès non autorisé</h1>
    <p class="text-muted mb-4">Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
    <a href="dashboard.php" class="btn btn-primary">
        <i class="bi bi-house me-1"></i>Retour au tableau de bord
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
