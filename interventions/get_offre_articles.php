<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID de l'offre est fourni
$offre_id = isset($_GET['offre_id']) ? intval($_GET['offre_id']) : 0;

if (!$offre_id) {
    echo json_encode(['success' => false, 'error' => 'ID d\'offre non fourni']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT oa.article_id, oa.remise_specifique, a.reference, a.designation, a.prix_vente_ht
              FROM offre_articles oa
              JOIN articles a ON oa.article_id = a.id
              WHERE oa.offre_id = :offre_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':offre_id', $offre_id);
    $stmt->execute();
    
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'articles' => $articles]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>