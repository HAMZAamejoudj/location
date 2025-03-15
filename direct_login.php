<?php
// Initialisation de la session
session_start();

// Connexion à la base de données
require_once "config/database.php";
$database = new Database();
$db = $database->getConnection();

// Récupérer l'utilisateur admin
$query = "SELECT * FROM users WHERE username = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Définir les variables de session manuellement
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    
    // Récupérer les permissions
    $perm_query = "SELECT p.nom FROM permissions p
                  JOIN user_permissions up ON p.id = up.permission_id
                  WHERE up.user_id = ?";
    $perm_stmt = $db->prepare($perm_query);
    $perm_stmt->bindParam(1, $user['id']);
    $perm_stmt->execute();
    
    $_SESSION['user_permissions'] = [];
    while($perm = $perm_stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['user_permissions'][] = $perm['nom'];
    }
    
    // Ajouter explicitement la permission lecture_stats si nécessaire
    if (!in_array('lecture_stats', $_SESSION['user_permissions'])) {
        $_SESSION['user_permissions'][] = 'lecture_stats';
    }
    
    // Mise à jour de la dernière connexion
    $update_query = "UPDATE users SET derniere_connexion = NOW() WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(1, $user['id']);
    $update_stmt->execute();
    
    echo "<h3>Connexion directe réussie!</h3>";
    echo "<p>Vous êtes maintenant connecté en tant qu'administrateur.</p>";
    echo "<p><a href='dashboard.php'>Aller au tableau de bord</a></p>";
} else {
    echo "<h3>Erreur</h3>";
    echo "<p>L'utilisateur admin n'existe pas dans la base de données.</p>";
}
?>