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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;

    // Récupération et nettoyage des données
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : 0;
    $vehicule_id = isset($_POST['vehicule_id']) ? filter_var($_POST['vehicule_id'], FILTER_VALIDATE_INT) : null;
    $technicien_id = isset($_POST['technicien_id']) && !empty($_POST['technicien_id']) ? 
                    filter_var($_POST['technicien_id'], FILTER_VALIDATE_INT) : null;
    $date_prevue = !empty($_POST['date_prevue']) ? 
                  date('Y-m-d', strtotime($_POST['date_prevue'])) : null;
    $date_debut = !empty($_POST['date_debut']) ? 
                 date('Y-m-d', strtotime($_POST['date_debut'])) : null;
    $date_fin = !empty($_POST['date_fin']) ? 
               date('Y-m-d', strtotime($_POST['date_fin'])) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $diagnostique = isset($_POST['diagnostique']) ? trim($_POST['diagnostique']) : null;
    $kilometrage = isset($_POST['kilometrage']) && !empty($_POST['kilometrage']) ? 
                  filter_var($_POST['kilometrage'], FILTER_VALIDATE_INT) : null;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : null;
    $statut = isset($_POST['statut']) && in_array($_POST['statut'], ['En attente', 'En cours', 'Terminée', 'Facturée', 'Annulée']) ? 
             $_POST['statut'] : 'En attente';

    // Validation des données
    if (!$id || $id <= 0) {
        $errors['id'] = "ID d'intervention invalide.";
    }

    if (!$vehicule_id) {
        $errors['vehicule_id'] = 'Le véhicule est requis';
    }

    if (empty($description)) {
        $errors['description'] = 'La description est requise';
    }

    // Vérifier la cohérence des dates
    if ($date_debut && $date_fin && strtotime($date_debut) > strtotime($date_fin)) {
        $errors['date'] = 'La date de début ne peut pas être postérieure à la date de fin';
    }

    // Si aucune erreur, mettre à jour l'intervention
    if (empty($errors)) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Vérifier si l'intervention existe
            $check_query = "SELECT id FROM interventions WHERE id = :id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                $errors['id'] = "L'intervention demandée n'existe pas.";
            } else {
                // Préparer la requête de mise à jour avec tous les champs possibles
                $query = "UPDATE interventions 
                          SET vehicule_id = :vehicule_id, 
                              technicien_id = :technicien_id, 
                              date_prevue = :date_prevue, 
                              date_debut = :date_debut, 
                              date_fin = :date_fin, 
                              description = :description, 
                              diagnostique = :diagnostique, 
                              kilometrage = :kilometrage,
                              commentaire = :commentaire,
                              statut = :statut
                          WHERE id = :id";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':vehicule_id', $vehicule_id, PDO::PARAM_INT);
                $stmt->bindParam(':technicien_id', $technicien_id, PDO::PARAM_INT);
                $stmt->bindParam(':date_prevue', $date_prevue);
                $stmt->bindParam(':date_debut', $date_debut);
                $stmt->bindParam(':date_fin', $date_fin);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':diagnostique', $diagnostique);
                $stmt->bindParam(':kilometrage', $kilometrage, PDO::PARAM_INT);
                $stmt->bindParam(':commentaire', $commentaire);
                $stmt->bindParam(':statut', $statut);

                if ($stmt->execute()) {
                    $success = true;
                    $_SESSION['success'] = "L'intervention a été mise à jour avec succès.";
                    header("Location: index.php");
                    exit;
                } else {
                    $errors['database'] = "Erreur lors de la mise à jour de l'intervention.";
                }
            }
        } catch (PDOException $e) {
            $errors['database'] = "Erreur: " . $e->getMessage();
            error_log('Erreur PDO dans la mise à jour d\'intervention: ' . $e->getMessage());
        }
    }

    // Si des erreurs se sont produites, les stocker dans la session pour les afficher
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST; // Sauvegarder les données du formulaire
        header("Location: edit.php?id=$id");
        exit;
    }
} else {
    // Redirection si la méthode n'est pas POST
    header("Location: index.php");
    exit;
}
?>
