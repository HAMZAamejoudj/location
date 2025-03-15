<?php
// Initialisation de la session
session_start();

// Connexion à la base de données pour journaliser la déconnexion
if(isset($_SESSION['user_id'])) {
    require_once "config/database.php";
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Journalisation de la déconnexion
    $log_query = "INSERT INTO logs (user_id, action, entite, entite_id, details, date_action, adresse_ip)
                 VALUES (?, 'Déconnexion', 'users', ?, 'Déconnexion réussie', NOW(), ?)";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(1, $_SESSION['user_id']);
    $log_stmt->bindParam(2, $_SESSION['user_id']);
    $log_stmt->bindParam(3, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
}

// Supprimer toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session si utilisé
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion avec un message de succès
header("Location: index.php?logout=success");
exit;
?>