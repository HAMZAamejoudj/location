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

// Récupérer le terme de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (empty($search)) {
    echo json_encode([
        'success' => false,
        'error' => 'Terme de recherche non spécifié'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Rechercher les articles correspondant au terme de recherche
    $query = "SELECT a.id, a.reference, a.designation, a.prix_vente_ht, a.stock 
             FROM article a 
             WHERE a.reference LIKE :search 
                OR a.designation LIKE :search 
             ORDER BY a.designation 
             LIMIT 50";
    
    $searchTerm = '%' . $search . '%';
    $stmt = $db->prepare($query);
    $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'articles' => $articles
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'articles' => []
        ]);
    }
} catch (PDOException $e) {
    error_log('Erreur lors de la recherche des articles: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la recherche des articles'
    ]);
}
?>
