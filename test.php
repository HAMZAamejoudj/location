<?php
// Détruire toute session existante
session_start();
session_destroy();

// Démarrer une nouvelle session
session_start();

// Se connecter à la base de données
require_once "config/database.php";
$database = new Database();
$db = $database->getConnection();

// Vérifier si l'utilisateur admin existe
$admin_query = "SELECT * FROM users WHERE username = 'admin'";
$admin_stmt = $db->prepare($admin_query);
$admin_stmt->execute();

if ($admin_stmt->rowCount() > 0) {
    $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Utilisateur admin trouvé (ID: {$admin['id']})</p>";
    
    // Vérifier les permissions
    $perm_query = "SELECT p.nom, p.id FROM permissions p
                  JOIN user_permissions up ON p.id = up.permission_id
                  WHERE up.user_id = ?";
    $perm_stmt = $db->prepare($perm_query);
    $perm_stmt->bindParam(1, $admin['id']);
    $perm_stmt->execute();
    
    if ($perm_stmt->rowCount() > 0) {
        echo "<p>Permissions trouvées:</p><ul>";
        $permissions = [];
        while($perm = $perm_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<li>{$perm['nom']} (ID: {$perm['id']})</li>";
            $permissions[] = $perm['nom'];
        }
        echo "</ul>";
        
        // Vérifier si lecture_stats est présente
        if (in_array('lecture_stats', $permissions)) {
            echo "<p style='color:green;'>La permission 'lecture_stats' est présente ✓</p>";
        } else {
            echo "<p style='color:red;'>La permission 'lecture_stats' n'est PAS présente ✗</p>";
            
            // Ajouter la permission manquante
            echo "<p>Tentative d'ajout de la permission 'lecture_stats'...</p>";
            
            // Trouver l'ID de la permission
            $find_perm = "SELECT id FROM permissions WHERE nom = 'lecture_stats'";
            $find_stmt = $db->prepare($find_perm);
            $find_stmt->execute();
            
            if ($find_stmt->rowCount() > 0) {
                $perm_id = $find_stmt->fetch(PDO::FETCH_ASSOC)['id'];
                
                // Ajouter la permission
                $add_perm = "INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)";
                $add_stmt = $db->prepare($add_perm);
                $add_stmt->bindParam(1, $admin['id']);
                $add_stmt->bindParam(2, $perm_id);
                
                if ($add_stmt->execute()) {
                    echo "<p style='color:green;'>Permission 'lecture_stats' ajoutée avec succès ✓</p>";
                } else {
                    echo "<p style='color:red;'>Erreur lors de l'ajout de la permission</p>";
                }
            } else {
                echo "<p style='color:red;'>Permission 'lecture_stats' introuvable dans la table des permissions</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Aucune permission trouvée pour l'utilisateur admin</p>";
        
        // Ajouter toutes les permissions
        echo "<p>Tentative d'ajout de toutes les permissions...</p>";
        
        $add_all = "INSERT INTO user_permissions (user_id, permission_id) SELECT ?, id FROM permissions";
        $add_all_stmt = $db->prepare($add_all);
        $add_all_stmt->bindParam(1, $admin['id']);
        
        if ($add_all_stmt->execute()) {
            echo "<p style='color:green;'>Toutes les permissions ont été ajoutées ✓</p>";
        } else {
            echo "<p style='color:red;'>Erreur lors de l'ajout des permissions</p>";
        }
    }
} else {
    echo "<p style='color:red;'>Utilisateur admin non trouvé</p>";
}

echo "<hr>";
echo "<p>Veuillez maintenant <a href='login.php'>vous connecter</a> avec le compte admin.</p>";
?>