<?php
// Démarrer la session
session_start();

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action.']);
    exit;
}

// Récupérer l'ID de la catégorie (optionnel)
$categorie_id = isset($_GET['categorie_id']) ? intval($_GET['categorie_id']) : 0;

try {
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Construire la requête en fonction de la présence ou non d'une catégorie
    $query = "SELECT o.* FROM offres o";
    
    if ($categorie_id > 0) {
        $query .= " WHERE o.categorie_id = :categorie_id";
    }
    
    $query .= " ORDER BY o.nom ASC";
    
    $stmt = $db->prepare($query);
    
    if ($categorie_id > 0) {
        $stmt->bindParam(':categorie_id', $categorie_id);
    }
    
    $stmt->execute();
    
    $offres = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $offres[] = $row;
    }
    
    echo json_encode(['success' => true, 'offres' => $offres]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>