<?php
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;

    // Vérification et nettoyage des données
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $vehicule_id = isset($_POST['vehicule_id']) ? intval($_POST['vehicule_id']) : 0;
    $technicien_id = isset($_POST['technicien_id']) ? intval($_POST['technicien_id']) : null;
    $date_prevue = !empty($_POST['date_prevue']) ? date('Y-m-d', strtotime($_POST['date_prevue'])) : null;
    $description = trim($_POST['description']);
    $diagnostique = trim($_POST['diagnostique']);
    $kilometrage = isset($_POST['kilometrage']) ? intval($_POST['kilometrage']) : null;

    if ($id > 0) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            $query = "UPDATE interventions 
                      SET vehicule_id = :vehicule_id, 
                          technicien_id = :technicien_id, 
                          date_prevue = :date_prevue, 
                          description = :description, 
                          diagnostique = :diagnostique, 
                          kilometrage = :kilometrage 
                      WHERE id = :id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':vehicule_id', $vehicule_id, PDO::PARAM_INT);
            $stmt->bindParam(':technicien_id', $technicien_id, PDO::PARAM_INT);
            $stmt->bindParam(':date_prevue', $date_prevue);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':diagnostique', $diagnostique);
            $stmt->bindParam(':kilometrage', $kilometrage, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $success = true;
                header("Location: index.php?success=true");
                exit;
            } else {
                $errors['database'] = "Erreur lors de la mise à jour de l'intervention.";
            }
        } catch (PDOException $e) {
            $errors['database'] = "Erreur: " . $e->getMessage();
        }
    } else {
        $errors['id'] = "ID d'intervention invalide.";
    }
}

// Redirection en cas d'erreur
header("Location: edit.php?id=$id&error=true");
exit;
