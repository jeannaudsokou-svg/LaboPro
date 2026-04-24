<?php
/**
 * Fonctions utilitaires
 * Gestion Laboratoire Médical
 */

require_once __DIR__ . '/../config/database.php';

// ============================================
// FONCTIONS D'AUTHENTIFICATION
// ============================================

/**
 * Démarrer une session sécurisée
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifier le rôle de l'utilisateur
 */
function hasRole($role) {
    if (!isLoggedIn()) return false;
    
    if (is_array($role)) {
        return in_array($_SESSION['user_role'], $role);
    }
    return $_SESSION['user_role'] === $role;
}

/**
 * Rediriger si non connecté
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Rediriger si pas le bon rôle
 */
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: unauthorized.php');
        exit;
    }
}

/**
 * Authentifier un utilisateur
 */
function authenticate($email, $password) {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND statut = 'actif'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['mot_de_passe'])) {
        // Mettre à jour la dernière connexion
        $updateStmt = $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Créer la session
        startSecureSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        // Enregistrer l'action
        logAction('connexion', 'utilisateurs', $user['id'], 'Connexion réussie');
        
        return true;
    }
    
    return false;
}

/**
 * Déconnecter l'utilisateur
 */
function logout() {
    startSecureSession();
    
    if (isset($_SESSION['user_id'])) {
        logAction('deconnexion', 'utilisateurs', $_SESSION['user_id'], 'Déconnexion');
    }
    
    session_unset();
    session_destroy();
    
    header('Location: login.php');
    exit;
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Nettoyer les entrées utilisateur
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Générer un numéro de dossier patient
 */
function generatePatientNumber() {
    $pdo = getConnection();
    $year = date('Y');
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE YEAR(date_inscription) = $year");
    $result = $stmt->fetch();
    $count = $result['count'] + 1;
    
    return 'PAT-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

/**
 * Formater une date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

/**
 * Formater une date et heure
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Calculer l'âge à partir de la date de naissance
 */
function calculateAge($dateNaissance) {
    $today = new DateTime();
    $birthDate = new DateTime($dateNaissance);
    $age = $today->diff($birthDate);
    return $age->y;
}

/**
 * Enregistrer une action dans l'historique
 */
function logAction($action, $table = null, $recordId = null, $details = null) {
    if (!isLoggedIn()) return;
    
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        INSERT INTO historique_actions (utilisateur_id, action, table_concernee, enregistrement_id, details, adresse_ip)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $table,
        $recordId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
}

/**
 * Afficher un message flash
 */
function setFlashMessage($type, $message) {
    startSecureSession();
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Récupérer et effacer le message flash
 */
function getFlashMessage() {
    startSecureSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Afficher le HTML du message flash
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $alertClass = match($flash['type']) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo $flash['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// ============================================
// FONCTIONS DE STATISTIQUES
// ============================================

/**
 * Compter les patients
 */
function countPatients() {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
    return $stmt->fetch()['count'];
}

/**
 * Compter les rendez-vous du jour
 */
function countTodayAppointments() {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rendez_vous WHERE date_rdv = CURDATE() AND statut != 'annule'");
    return $stmt->fetch()['count'];
}

/**
 * Compter les examens en cours
 */
function countPendingExams() {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM examens WHERE statut IN ('en_attente', 'preleve', 'en_analyse')");
    return $stmt->fetch()['count'];
}

/**
 * Compter les résultats à valider
 */
function countPendingResults() {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM resultats WHERE valide = FALSE");
    return $stmt->fetch()['count'];
}

/**
 * Obtenir les notifications non lues
 */
function getUnreadNotifications($userId, $type = 'utilisateur') {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE destinataire_type = ? AND destinataire_id = ? AND lu = FALSE 
        ORDER BY date_creation DESC LIMIT 10
    ");
    $stmt->execute([$type, $userId]);
    return $stmt->fetchAll();
}

/**
 * Compter les notifications non lues
 */
function countUnreadNotifications($userId, $type = 'utilisateur') {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM notifications 
        WHERE destinataire_type = ? AND destinataire_id = ? AND lu = FALSE
    ");
    $stmt->execute([$type, $userId]);
    return $stmt->fetch()['count'];
}

// ============================================
// FONCTIONS DE NOTIFICATIONS
// ============================================

/**
 * Créer une notification
 */
function createNotification($destinataireId, $titre, $message, $type = 'info', $lien = null, $referenceId = null, $referenceType = null, $destinataireType = 'utilisateur') {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        INSERT INTO notifications (destinataire_type, destinataire_id, titre, message, type, lien, reference_id, reference_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$destinataireType, $destinataireId, $titre, $message, $type, $lien, $referenceId, $referenceType]);
}

/**
 * Notifier le patient d'un nouveau résultat
 */
function notifyPatientResult($patientId, $resultatId, $examenNom) {
    // Trouver l'utilisateur patient lié à ce patient
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE patient_id = ? AND role = 'patient'");
    $stmt->execute([$patientId]);
    $user = $stmt->fetch();
    
    if ($user) {
        createNotification(
            $user['id'],
            'Nouveau résultat disponible',
            "Votre résultat pour l'examen \"$examenNom\" est maintenant disponible.",
            'resultat',
            'mes-resultats.php?view=' . $resultatId,
            $resultatId,
            'resultat'
        );
    }
}

/**
 * Notifier les techniciens et réceptionnistes d'une nouvelle demande d'examen
 */
function notifyNewExamRequest($demandeId, $patientNom) {
    $pdo = getConnection();
    
    // Notifier tous les techniciens et réceptionnistes actifs
    $stmt = $pdo->query("SELECT id, role FROM utilisateurs WHERE role IN ('technicien', 'receptionniste') AND statut = 'actif'");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        createNotification(
            $user['id'],
            'Nouvelle demande d\'examen',
            "Une nouvelle demande d'examen a été créée pour le patient $patientNom.",
            'info',
            'examens.php?demande=' . $demandeId,
            $demandeId,
            'demande_examen'
        );
    }
}

/**
 * Notifier l'administrateur de la création d'un utilisateur
 */
function notifyAdminUserCreated($newUserId, $newUserNom, $newUserRole) {
    $pdo = getConnection();
    
    // Notifier tous les administrateurs
    $stmt = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'administrateur' AND statut = 'actif'");
    $admins = $stmt->fetchAll();
    
    foreach ($admins as $admin) {
        createNotification(
            $admin['id'],
            'Nouvel utilisateur créé',
            "Un nouveau $newUserRole a été créé: $newUserNom.",
            'info',
            'utilisateurs.php',
            $newUserId,
            'utilisateur'
        );
    }
}

/**
 * Récupérer le patient_id lié à un utilisateur
 */
function getPatientIdFromUser($userId) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT patient_id FROM utilisateurs WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['patient_id'] : null;
}

/**
 * Récupérer les informations du patient lié à un utilisateur
 */
function getPatientFromUser($userId) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT p.* FROM patients p 
        JOIN utilisateurs u ON u.patient_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}
