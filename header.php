<?php
/**
 * En-tête et sidebar
 * Gestion Laboratoire Médical
 */

require_once __DIR__ . '/functions.php';
requireLogin();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$notificationCount = countUnreadNotifications($_SESSION['user_id']);
$userRole = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <div class="sidebar-brand-icon">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <span class="sidebar-brand-text">LaboPro</span>
                </a>
                <button class="sidebar-toggle d-none d-lg-block" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Menu Principal -->
                <div class="sidebar-section-title">Principal</div>
                
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <i class="bi bi-grid-1x2"></i>
                        <span class="nav-link-text">Tableau de bord</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="notifications.php" class="nav-link <?= $currentPage === 'notifications' ? 'active' : '' ?>">
                        <i class="bi bi-bell"></i>
                        <span class="nav-link-text">Notifications</span>
                        <?php if ($notificationCount > 0): ?>
                            <span class="badge bg-danger"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <?php if ($userRole === 'patient'): ?>
                <!-- Menu Patient -->
                <div class="sidebar-section-title">Mes informations</div>
                
                <div class="nav-item">
                    <a href="mes-resultats.php" class="nav-link <?= $currentPage === 'mes-resultats' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-medical"></i>
                        <span class="nav-link-text">Mes Résultats</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="mes-rendez-vous.php" class="nav-link <?= $currentPage === 'mes-rendez-vous' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span class="nav-link-text">Mes Rendez-vous</span>
                    </a>
                </div>
                
                <?php else: ?>
                <!-- Menu Gestion (pour réceptionniste, technicien, admin) -->
                <div class="sidebar-section-title">Gestion</div>
                
                <?php if (hasRole(['administrateur', 'receptionniste'])): ?>
                <div class="nav-item">
                    <a href="patients.php" class="nav-link <?= $currentPage === 'patients' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i>
                        <span class="nav-link-text">Patients</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="rendez-vous.php" class="nav-link <?= $currentPage === 'rendez-vous' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span class="nav-link-text">Rendez-vous</span>
                        <?php if (countTodayAppointments() > 0): ?>
                            <span class="badge bg-primary"><?= countTodayAppointments() ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Laboratoire -->
                <div class="sidebar-section-title">Laboratoire</div>
                
                <div class="nav-item">
                    <a href="examens.php" class="nav-link <?= $currentPage === 'examens' ? 'active' : '' ?>">
                        <i class="bi bi-clipboard2-pulse"></i>
                        <span class="nav-link-text">Examens</span>
                        <?php if (countPendingExams() > 0): ?>
                            <span class="badge bg-warning text-dark"><?= countPendingExams() ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <?php if (hasRole(['administrateur', 'technicien'])): ?>
                <div class="nav-item">
                    <a href="resultats.php" class="nav-link <?= $currentPage === 'resultats' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-medical"></i>
                        <span class="nav-link-text">Résultats</span>
                        <?php if (countPendingResults() > 0): ?>
                            <span class="badge bg-danger"><?= countPendingResults() ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if (hasRole('administrateur')): ?>
                <!-- Administration -->
                <div class="sidebar-section-title">Administration</div>
                
                <div class="nav-item">
                    <a href="utilisateurs.php" class="nav-link <?= $currentPage === 'utilisateurs' ? 'active' : '' ?>">
                        <i class="bi bi-person-badge"></i>
                        <span class="nav-link-text">Utilisateurs</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="types-examens.php" class="nav-link <?= $currentPage === 'types-examens' ? 'active' : '' ?>">
                        <i class="bi bi-list-check"></i>
                        <span class="nav-link-text">Types d'examens</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="statistiques.php" class="nav-link <?= $currentPage === 'statistiques' ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart-line"></i>
                        <span class="nav-link-text">Statistiques</span>
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <button class="btn btn-light d-lg-none" id="mobileMenuBtn">
                    <i class="bi bi-list"></i>
                </button>
                
                <div class="header-search d-none d-md-block">
                    <?php if ($userRole !== 'patient'): ?>
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" placeholder="Rechercher un patient, examen...">
                    <?php endif; ?>
                </div>
                
                <div class="header-actions">
                    <a href="notifications.php" class="header-btn" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="badge bg-danger"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="dropdown user-dropdown">
                        <button class="dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <?= strtoupper(substr($_SESSION['user_prenom'], 0, 1) . substr($_SESSION['user_nom'], 0, 1)) ?>
                            </div>
                            <div class="user-info d-none d-md-block">
                                <div class="user-name"><?= htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?></div>
                                <div class="user-role"><?= ucfirst(htmlspecialchars($_SESSION['user_role'])) ?></div>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                            <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                        </ul>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="content">
                <?php displayFlashMessage(); ?>
