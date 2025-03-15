<?php
// Initialisation de la session
session_start();

// Connexion à la base de données
require_once "config/database.php";
$database = new Database();
$db = $database->getConnection();

echo "<h3>Débogage de la connexion</h3>";

// Vérifier l'utilisateur admin
$query = "SELECT id, username, password, actif FROM users WHERE username = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Utilisateur trouvé : ID " . $user['id'] . "<br>";
    echo "Statut actif : " . ($user['actif'] ? "Oui" : "Non") . "<br>";
    echo "Hash du mot de passe : " . $user['password'] . "<br>";
    
    // Tester le mot de passe
    $test_password = 'admin123';
    $verify_result = password_verify($test_password, $user['password']);
    
    echo "Test de mot de passe 'admin123' : " . ($verify_result ? "Succès" : "Échec") . "<br>";
    
    if (!$verify_result) {
        // Générer un nouveau hash et montrer la requête SQL
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<hr>";
        echo "Le test a échoué. Voici un nouveau hash pour le mot de passe 'admin123' :<br>";
        echo "<code>UPDATE users SET password = '$new_hash' WHERE username = 'admin';</code>";
    } else {
        echo "<hr>";
        echo "Le mot de passe est correct, mais vous n'arrivez pas à vous connecter.<br>";
        echo "Voici une simulation complète du processus de connexion :";
        
        // Simuler le processus de connexion
        if (!$user['actif']) {
            echo "<p>Erreur : Ce compte est désactivé.</p>";
        } else {
            // Récupérer les infos complètes
            $full_query = "SELECT * FROM users WHERE id = ?";
            $full_stmt = $db->prepare($full_query);
            $full_stmt->bindParam(1, $user['id']);
            $full_stmt->execute();
            $full_user = $full_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Récupérer les permissions
            $perm_query = "SELECT p.nom FROM permissions p
                           JOIN user_permissions up ON p.id = up.permission_id
                           WHERE up.user_id = ?";
            $perm_stmt = $db->prepare($perm_query);
            $perm_stmt->bindParam(1, $user['id']);
            $perm_stmt->execute();
            
            $permissions = [];
            while($perm = $perm_stmt->fetch(PDO::FETCH_ASSOC)) {
                $permissions[] = $perm['nom'];
            }
            
            echo "<p>Connexion réussie! Voici les informations de session qui seraient créées :</p>";
            echo "<pre>";
            echo "user_id = " . $user['id'] . "\n";
            echo "user_name = " . $full_user['prenom'] . ' ' . $full_user['nom'] . "\n";
            echo "username = " . $full_user['username'] . "\n";
            echo "user_email = " . $full_user['email'] . "\n";
            echo "user_role = " . $full_user['role'] . "\n";
            echo "user_permissions = [" . implode(", ", $permissions) . "]\n";
            echo "</pre>";
            
            echo "<p>Vérification de la permission 'lecture_stats' : ";
            echo (in_array('lecture_stats', $permissions) ? "Présente ✓" : "Absente ✗") . "</p>";
            
            echo "<p><a href='login.php'>Retourner à la page de connexion</a></p>";
        }
    }
} else {
    echo "Aucun utilisateur avec le nom d'utilisateur 'admin' n'a été trouvé.";
}
?>