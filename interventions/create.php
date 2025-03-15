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

// Vérifier si l'utilisateur est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
}

// Utilisateur temporaire pour éviter l'erreur
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Récupérer la liste des véhicules et techniciens pour le formulaire
$database = new Database();
$db = $database->getConnection();

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    if (empty($_POST['vehicule_id'])) {
        $errors['vehicule_id'] = 'Le véhicule est requis';
    }

    if (empty($_POST['description'])) {
        $errors['description'] = 'La description est requise';
    }

    if (empty($_POST['kilometrage'])) {
        $errors['kilometrage'] = 'Le kilométrage est requis';
    }

    // Si aucune erreur, créer l'intervention
    if (empty($errors)) {
        try {
            // Préparer la requête d'insertion
            $query = "INSERT INTO interventions (vehicule_id, technicien_id, date_creation, date_prevue, description, diagnostique, kilometrage, statut) 
                      VALUES (:vehicule_id, :technicien_id, :date_creation, :date_prevue, :description, :diagnostique, :kilometrage, :statut)";
            
            $stmt = $db->prepare($query);

            // Binder les paramètres
            $stmt->bindParam(':vehicule_id', $_POST['vehicule_id']);
            $stmt->bindValue(':technicien_id', 1, PDO::PARAM_INT);
            $date_creation = date('Y-m-d');
            $stmt->bindParam(':date_creation', $date_creation);
            $date_prevue = !empty($_POST['date_prevue']) ? date('Y-m-d', strtotime($_POST['date_prevue'])) : null;
            $stmt->bindParam(':date_prevue', $date_prevue);
            $stmt->bindParam(':description', $_POST['description']);
            $stmt->bindParam(':diagnostique', $_POST['diagnostique']);
            $stmt->bindParam(':kilometrage', $_POST['kilometrage']);
            $statut = 'En attente'; // Statut par défaut
            $stmt->bindParam(':statut', $statut);

            // Exécuter la requête
            $stmt->execute();

            // Récupérer l'ID de l'intervention nouvellement créée
            $intervention_id = $db->lastInsertId();

            $success = true;

            // Option de redirection:
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            // Gérer les erreurs de base de données
            $errors['database'] = 'Erreur lors de la création de l\'intervention: ' . $e->getMessage();
        }
    }
}

?>