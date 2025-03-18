<?php
// Démarrer la session
session_start();

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
}

// Définir le type de contenu de la réponse
header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $intervention_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$intervention_id) {
        echo json_encode(["error" => "ID d'intervention invalide"]);
        exit;
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        // Requête complète avec jointures pour récupérer toutes les informations nécessaires
        $query = "SELECT i.*, 
                         v.immatriculation, v.marque, v.modele, 
                         CONCAT(v.marque, ' ', v.modele, ' ', v.immatriculation) AS vehicule_info,
                         v.client_id,
                         CONCAT(c.nom, ' ', c.prenom) AS client_nom,
                         t.id AS technicien_id,
                         CONCAT(t.prenom, ' ', t.nom) AS technicien_nom,
                         t.specialite AS technicien_specialite
                  FROM interventions i 
                  INNER JOIN vehicules v ON i.vehicule_id = v.id 
                  INNER JOIN clients c ON v.client_id = c.id
                  LEFT JOIN technicien t ON i.technicien_id = t.id
                  WHERE i.id = :id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $intervention_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Formater les dates pour l'affichage si nécessaire
            if ($intervention['date_creation']) {
                $intervention['date_creation_formatted'] = date('d/m/Y H:i', strtotime($intervention['date_creation']));
            }
            
            if ($intervention['date_prevue']) {
                $intervention['date_prevue_formatted'] = date('d/m/Y', strtotime($intervention['date_prevue']));
            }
            
            if ($intervention['date_debut']) {
                $intervention['date_debut_formatted'] = date('d/m/Y', strtotime($intervention['date_debut']));
            }
            
            if ($intervention['date_fin']) {
                $intervention['date_fin_formatted'] = date('d/m/Y', strtotime($intervention['date_fin']));
            }
            
            // Ajouter des informations supplémentaires
            $intervention['vehicule_complet'] = $intervention['marque'] . ' ' . $intervention['modele'] . ' (' . $intervention['immatriculation'] . ')';
            
            echo json_encode([
                "success" => true,
                "intervention" => $intervention
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Intervention non trouvée"
            ]);
        }
    } catch (PDOException $e) {
        error_log('Erreur PDO dans la récupération d\'intervention: ' . $e->getMessage());
        echo json_encode([
            "success" => false,
            "error" => "Erreur lors de la récupération: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "error" => "ID d'intervention invalide ou manquant"
    ]);
}
?>
