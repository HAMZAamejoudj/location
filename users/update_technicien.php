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
// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $date_naissance = isset($_POST['date_naissance']) ? trim($_POST['date_naissance']) : '';
    $specialite = isset($_POST['specialite']) ? trim($_POST['specialite']) : '';
    
    // Valider les données
    $errors = [];
    
    if ($id <= 0) {
        $errors[] = "ID de technicien invalide";
    }
    
    if (empty($nom)) {
        $errors[] = "Le nom est requis";
    }
    
    if (empty($prenom)) {
        $errors[] = "Le prénom est requis";
    }
    
    if (empty($date_naissance)) {
        $errors[] = "La date de naissance est requise";
    } elseif (strtotime($date_naissance) > time()) {
        $errors[] = "La date de naissance ne peut pas être dans le futur";
    }
    
    if (empty($specialite)) {
        $errors[] = "La spécialité est requise";
    }
    
    // Si pas d'erreurs, mettre à jour dans la base de données
    if (empty($errors)) {
        try {
            // Connexion à la base de données
            $database = new Database();
            $db = $database->getConnection();
            
            // Préparer la requête
            $query = "UPDATE technicien 
                      SET nom = :nom, prenom = :prenom, date_naissance = :date_naissance, specialite = :specialite 
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            
            // Lier les paramètres
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':date_naissance', $date_naissance);
            $stmt->bindParam(':specialite', $specialite);
            
            // Exécuter la requête
            if ($stmt->execute()) {
                $_SESSION['success'] = "Le technicien a été mis à jour avec succès";
            } else {
                $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour du technicien";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    // Rediriger vers la page des techniciens
    header('Location: index.php');
    exit;
} else {
    // Si la méthode n'est pas POST, rediriger vers la page des techniciens
    header('Location: index.php');
    exit;
}

