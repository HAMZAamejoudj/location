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

// Récupérer l'ID de la catégorie
$categorie_id = isset($_GET['categorie_id']) ? filter_var($_GET['categorie_id'], FILTER_VALIDATE_INT) : null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Construire la requête en fonction de si une catégorie est spécifiée ou non
    if ($categorie_id) {
        $query = "SELECT a.id, a.reference, a.designation, a.prix_vente_ht, a.stock 
                 FROM article a 
                 WHERE a.categorie_id = :categorie_id 
                 ORDER BY a.designation";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':categorie_id', $categorie_id, PDO::PARAM_INT);
    } else {
        $query = "SELECT a.id, a.reference, a.designation, a.prix_vente_ht, a.stock 
                 FROM article a 
                 ORDER BY a.designation 
                 LIMIT 50"; // Limiter le nombre d'articles si aucune catégorie n'est spécifiée
        
        $stmt = $db->prepare($query);
    }
    
    $stmt->execute();
    
    // Récupérer tous les résultats
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Renvoyer la réponse JSON
    echo json_encode([
        'success' => true,
        'articles' => $articles
    ]);
    
} catch (PDOException $e) {
    // Log l'erreur pour le débogage
    error_log('Erreur lors de la récupération des articles: ' . $e->getMessage());
    
    // Renvoyer une réponse d'erreur avec plus de détails
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des articles: ' . $e->getMessage()
    ]);
}
?>
