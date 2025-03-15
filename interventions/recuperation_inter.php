<?php
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions

if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $intervention_id = intval($_GET['id']);

    try {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT CONCAT(v.marque, ' ', v.modele, ' ', v.immatriculation) AS vehicule, i.*
        FROM interventions i 
        INNER JOIN vehicules v ON i.vehicule_id = v.id 
        WHERE i.id = :id";


        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $intervention_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            echo json_encode(["error" => "Véhicule non trouvé"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Erreur lors de la récupération: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "ID de véhicule invalide"]);
}
?>
