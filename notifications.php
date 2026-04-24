<?php
/**
 * Page des Notifications
 * Accessible à tous les utilisateurs connectés
 */

$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notifId = intval($_GET['mark_read']);
    $stmt = $pdo->prepare("UPDATE notifications SET lu = TRUE, date_lecture = NOW() WHERE id = ? AND destinataire_id = ?");
    $stmt->execute([$notifId, $userId]);
    
    // Rediriger vers le lien si présent
    $linkStmt = $pdo->prepare("SELECT lien FROM notifications WHERE id = ?");
    $linkStmt->execute([$notifId]);
    $notif = $linkStmt->fetch();
    
    if ($notif && $notif['lien']) {
        header('Location: ' . $notif['lien']);
        exit;
    }
    
    header('Location: notifications.php');
    exit;
}

// Marquer toutes comme lues
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET lu = TRUE, date_lecture = NOW() WHERE destinataire_id = ? AND destinataire_type = 'utilisateur' AND lu = FALSE");
    $stmt->execute([$userId]);
    setFlashMessage('success', 'Toutes les notifications ont été marquées comme lues.');
    header('Location: notifications.php');
    exit;
}

// Supprimer une notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notifId = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND destinataire_id = ?");
    $stmt->execute([$notifId, $userId]);
    setFlashMessage('success', 'Notification supprimée.');
    header('Location: notifications.php');
    exit;
}

// Récupérer les notifications de l'utilisateur
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM notifications WHERE destinataire_type = 'utilisateur' AND destinataire_id = ?";
$params = [$userId];

if ($filter === 'unread') {
    $sql .= " AND lu = FALSE";
} elseif ($filter === 'read') {
    $sql .= " AND lu = TRUE";
}

$sql .= " ORDER BY date_creation DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Compter les non lues
$unreadCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE destinataire_type = 'utilisateur' AND destinataire_id = ? AND lu = FALSE");
$unreadCount->execute([$userId]);
$unreadTotal = $unreadCount->fetchColumn();
?>

<!-- En-tête de page -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="bi bi-bell text-primary me-2"></i>
            Notifications
        </h1>
        <p class="page-subtitle">
            <?php if ($unreadTotal > 0): ?>
                <span class="badge bg-danger"><?= $unreadTotal ?></span> notification(s) non lue(s)
            <?php else: ?>
                Vous êtes à jour !
            <?php endif; ?>
        </p>
    </div>
    <?php if ($unreadTotal > 0): ?>
        <div class="page-actions">
            <a href="?action=mark_all_read" class="btn btn-outline-primary">
                <i class="bi bi-check-all me-1"></i>Tout marquer comme lu
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2">
            <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Toutes
            </a>
            <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Non lues
                <?php if ($unreadTotal > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unreadTotal ?></span>
                <?php endif; ?>
            </a>
            <a href="?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Lues
            </a>
        </div>
    </div>
</div>

<!-- Liste des notifications -->
<div class="card">
    <?php if (empty($notifications)): ?>
        <div class="card-body text-center py-5">
            <i class="bi bi-bell-slash display-4 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Aucune notification</h5>
            <p class="text-muted">Vous n'avez pas encore de notification.</p>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($notifications as $notif): ?>
                <li class="list-group-item notification-item <?= !$notif['lu'] ? 'bg-light' : '' ?>">
                    <div class="d-flex align-items-start">
                        <div class="notification-icon me-3">
                            <?php
                            $iconClass = match($notif['type']) {
                                'resultat' => 'bi-file-earmark-medical text-success',
                                'alerte' => 'bi-exclamation-triangle text-danger',
                                'rappel' => 'bi-clock text-warning',
                                default => 'bi-info-circle text-primary'
                            };
                            ?>
                            <i class="bi <?= $iconClass ?> fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 <?= !$notif['lu'] ? 'fw-bold' : '' ?>">
                                        <?= htmlspecialchars($notif['titre']) ?>
                                    </h6>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($notif['message']) ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= formatDateTime($notif['date_creation']) ?>
                                        <?php if ($notif['lu']): ?>
                                            <span class="ms-2"><i class="bi bi-check2-all text-success"></i> Lu</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if (!$notif['lu']): ?>
                                        <a href="?mark_read=<?= $notif['id'] ?>" class="btn btn-sm btn-outline-primary" title="Marquer comme lu">
                                            <i class="bi bi-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($notif['lien']): ?>
                                        <a href="?mark_read=<?= $notif['id'] ?>" class="btn btn-sm btn-primary" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $notif['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Supprimer cette notification ?')" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<style>
.notification-item {
    transition: background-color 0.2s;
}
.notification-item:hover {
    background-color: #f8f9fa;
}
.notification-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 50%;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
