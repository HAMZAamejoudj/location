<?php
// Connexion à la base de données
require_once "config/database.php";
$database = new Database();
$db = $database->getConnection();

// Vérifier si l'utilisateur admin existe déjà
$check_query = "SELECT id FROM users WHERE username = 'admin'";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute();

if ($check_stmt->rowCount() == 0) {
    // L'utilisateur n'existe pas, on le crée
    echo "<p>Création de l'utilisateur admin...</p>";
    
    $password_hash = '$2y$10$RHCt1BeGxFXwyzRZgiMT1.KQxivfdV1mj6R6e5iuJZqk6.9ebSOJa'; // admin123
    
    $create_query = "INSERT INTO users (username, password, nom, prenom, email, role, date_creation, actif) 
                     VALUES ('admin', :password, 'Administrateur', 'Système', 'admin@sas-reparation.fr', 'admin', NOW(), 1)";
    $create_stmt = $db->prepare($create_query);
    $create_stmt->bindParam(':password', $password_hash);
    
    if ($create_stmt->execute()) {
        $admin_id = $db->lastInsertId();
        echo "<p>Utilisateur admin créé avec l'ID: {$admin_id}</p>";
        
        // Attribuer les permissions
        echo "<p>Attribution des permissions...</p>";
        $perm_query = "INSERT INTO user_permissions (user_id, permission_id) SELECT :user_id, id FROM permissions";
        $perm_stmt = $db->prepare($perm_query);
        $perm_stmt->bindParam(':user_id', $admin_id);
        
        if ($perm_stmt->execute()) {
            echo "<p>Permissions attribuées avec succès.</p>";
        } else {
            echo "<p>Erreur lors de l'attribution des permissions.</p>";
            print_r($perm_stmt->errorInfo());
        }
    } else {
        echo "<p>Erreur lors de la création de l'utilisateur admin.</p>";
        print_r($create_stmt->errorInfo());
    }
} else {
    $admin = $check_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>L'utilisateur admin existe déjà (ID: {$admin['id']})</p>";
    
    // Vérifier et attribuer les permissions manquantes
    echo "<p>Vérification des permissions...</p>";
    $admin_id = $admin['id'];
    
    // Trouver les permissions manquantes
    $missing_query = "SELECT p.id FROM permissions p 
                      LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = :user_id
                      WHERE up.permission_id IS NULL";
    $missing_stmt = $db->prepare($missing_query);
    $missing_stmt->bindParam(':user_id', $admin_id);
    $missing_stmt->execute();
    
    if ($missing_stmt->rowCount() > 0) {
        echo "<p>Attribution des permissions manquantes...</p>";
        
        while($perm = $missing_stmt->fetch(PDO::FETCH_ASSOC)) {
            $add_query = "INSERT INTO user_permissions (user_id, permission_id) VALUES (:user_id, :perm_id)";
            $add_stmt = $db->prepare($add_query);
            $add_stmt->bindParam(':user_id', $admin_id);
            $add_stmt->bindParam(':perm_id', $perm['id']);
            $add_stmt->execute();
        }
        
        echo "<p>{$missing_stmt->rowCount()} permissions ont été ajoutées.</p>";
    } else {
        echo "<p>Toutes les permissions sont déjà attribuées.</p>";
    }
}

echo "<hr>";
echo "<p>Vous pouvez maintenant <a href='login.php'>vous connecter</a> avec:</p>";
echo "<ul>";
echo "<li>Nom d'utilisateur: <b>admin</b></li>";
echo "<li>Mot de passe: <b>admin123</b></li>";
echo "</ul>";
?>