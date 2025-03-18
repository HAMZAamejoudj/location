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
    // Récupération et nettoyage des données
    $vehicule_id = isset($_POST['vehicule_id']) ? filter_var($_POST['vehicule_id'], FILTER_VALIDATE_INT) : null;
    $technicien_id = isset($_POST['technicien_id']) && !empty($_POST['technicien_id']) ? 
                    filter_var($_POST['technicien_id'], FILTER_VALIDATE_INT) : null;
    $date_creation = date('Y-m-d H:i:s'); // Date et heure actuelles
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

    // Si aucune erreur, créer l'intervention
    if (empty($errors)) {
        try {
            // Préparer la requête d'insertion avec tous les champs possibles
            $query = "INSERT INTO interventions (
                        vehicule_id, technicien_id, date_creation, date_prevue, date_debut, date_fin,
                        description, diagnostique, kilometrage, commentaire, statut
                      ) VALUES (
                        :vehicule_id, :technicien_id, :date_creation, :date_prevue, :date_debut, :date_fin,
                        :description, :diagnostique, :kilometrage, :commentaire, :statut
                      )";
            
            $stmt = $db->prepare($query);

            // Binder les paramètres
            $stmt->bindParam(':vehicule_id', $vehicule_id, PDO::PARAM_INT);
            $stmt->bindParam(':technicien_id', $technicien_id, PDO::PARAM_INT);
            $stmt->bindParam(':date_creation', $date_creation);
            $stmt->bindParam(':date_prevue', $date_prevue);
            $stmt->bindParam(':date_debut', $date_debut);
            $stmt->bindParam(':date_fin', $date_fin);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':diagnostique', $diagnostique);
            $stmt->bindParam(':kilometrage', $kilometrage, PDO::PARAM_INT);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':statut', $statut);

            // Exécuter la requête
            if ($stmt->execute()) {
                // Récupérer l'ID de l'intervention nouvellement créée
                $intervention_id = $db->lastInsertId();
                $success = true;
                
                // Stocker un message de succès dans la session
                $_SESSION['success'] = "L'intervention a été créée avec succès.";
                
                // Redirection vers la page d'index
                header('Location: index.php');
                exit;
            } else {
                $errors['database'] = 'Erreur lors de la création de l\'intervention.';
            }
        } catch (PDOException $e) {
            // Gérer les erreurs de base de données
            $errors['database'] = 'Erreur lors de la création de l\'intervention: ' . $e->getMessage();
            
            // Log l'erreur pour le débogage
            error_log('Erreur PDO dans la création d\'intervention: ' . $e->getMessage());
        }
    }
    
    // Si des erreurs se sont produites, les stocker dans la session pour les afficher après redirection
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST; // Sauvegarder les données du formulaire pour les réafficher
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Récupérer les véhicules pour le formulaire
$vehicules = [];
try {
    $query = "SELECT v.id, v.marque, v.modele, v.immatriculation, 
                     CONCAT(c.nom, ' ', c.prenom) AS client_nom
              FROM vehicules v
              INNER JOIN clients c ON v.client_id = c.id
              ORDER BY v.marque, v.modele";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erreur lors de la récupération des véhicules: ' . $e->getMessage());
}

// Récupérer les techniciens pour le formulaire
$techniciens = [];
try {
    $query = "SELECT t.id, CONCAT(t.prenom, ' ', t.nom) AS nom_complet, t.specialite 
              FROM technicien t 
              ORDER BY t.nom ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $techniciens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erreur lors de la récupération des techniciens: ' . $e->getMessage());
}

// Récupérer les messages d'erreur ou de succès de la session
$sessionErrors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$formData = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
$sessionSuccess = isset($_SESSION['success']) ? $_SESSION['success'] : false;

// Nettoyer la session
unset($_SESSION['errors'], $_SESSION['form_data'], $_SESSION['success']);

// Inclure l'en-tête et le contenu de la page...
?>
