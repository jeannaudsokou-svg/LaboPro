<?php
/**
 * Configuration de la base de données
 * Gestion Laboratoire Médical
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'labo_medical');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Connexion PDO
function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Configuration générale
define('SITE_NAME', 'LaboPro - Gestion Laboratoire Médical');
define('SITE_URL', 'http://localhost/labo');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

// Session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Mettre à 1 en production avec HTTPS
