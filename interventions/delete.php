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
    

    if ($id > 0) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "DELETE FROM interventions WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success = true;
                header("Location: index.php");
                exit;
            } else {
                $errors['database'] = "Erreur lors de la mise à jour du intervention.";
            }
        } catch (PDOException $e) {
            $errors['database'] = "Erreur: " . $e->getMessage();
        }
    } else {
        $errors['id'] = "ID de intervention invalide.";
    }
}

header("Location: edit.php?id=$id&error=true");
exit;
?>
