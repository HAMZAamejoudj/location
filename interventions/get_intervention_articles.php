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

// Récupérer l'ID de l'intervention
$intervention_id = isset($_GET['intervention_id']) ? filter_var($_GET['intervention_id'], FILTER_VALIDATE_INT) : null;

if (!$intervention_id) {
    echo json_encode([
        'success' => false,
        'error' => 'ID d\'intervention non valide'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les articles associés à l'intervention
    $query = "SELECT ia.article_id, ia.quantite, ia.prix_unitaire, ia.remise, 
                     a.reference, a.designation
              FROM interventions_articles ia
              INNER JOIN article a ON ia.article_id = a.id
              WHERE ia.intervention_id = :intervention_id
              ORDER BY a.designation";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':intervention_id', $intervention_id, PDO::PARAM_INT);
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
    error_log('Erreur lors de la récupération des articles de l\'intervention: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des articles de l\'intervention'
    ]);
}
?>
