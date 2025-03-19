<?php
// Démarrer la session
session_start();

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

// Définir le type de contenu de la réponse
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Utilisateur non connecté'
    ]);
    exit;
}

// Récupérer l'ID de l'intervention
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$id) {
    echo json_encode([
        'success' => false,
        'error' => 'ID d\'intervention non valide'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les détails de l'intervention
    $query = "SELECT i.*, 
                     v.marque, v.modele, v.immatriculation, 
                     CONCAT(c.nom, ' ', c.prenom) AS client_nom,
                     CONCAT(t.prenom, ' ', t.nom) AS technicien_nom,
                     t.specialite AS technicien_specialite,
                     CONCAT(v.marque, ' ', v.modele, ' (', v.immatriculation, ')') AS vehicule_complet
              FROM interventions i
              INNER JOIN vehicules v ON i.vehicule_id = v.id
              INNER JOIN clients c ON v.client_id = c.id
              LEFT JOIN technicien t ON i.technicien_id = t.id
              WHERE i.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Formater les dates pour l'affichage
        if ($intervention['date_creation']) {
            $intervention['date_creation_formatted'] = date('d/m/Y', strtotime($intervention['date_creation']));
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
        
        echo json_encode([
            'success' => true,
            'intervention' => $intervention
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Intervention non trouvée'
        ]);
    }
} catch (PDOException $e) {
    error_log('Erreur lors de la récupération de l\'intervention: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération de l\'intervention'
    ]);
}
?>
