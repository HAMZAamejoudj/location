<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration
require_once $root_path . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier si on demande une offre spécifique ou une liste d'offres par catégorie
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        // Récupération d'une offre spécifique
        $id = (int)$_GET['id'];
        
        // Récupérer les informations de l'offre avec plus de détails
        $query = "SELECT o.*, c.nom as categorie_nom 
                FROM offres o 
                LEFT JOIN categorie c ON o.categorie_id = c.id 
                WHERE o.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Offre non trouvée']);
            exit;
        }
        
        $offre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les articles liés à cette offre avec plus d'informations
        $queryArticles = "SELECT a.id, a.reference, a.designation, a.prix_vente_ht as prix_vente, 
                        a.quantite_stock, a.categorie_id, c.nom as categorie_nom, oa.remise_specifique
                        FROM articles a
                        JOIN offres_articles oa ON a.id = oa.article_id
                        LEFT JOIN categorie c ON a.categorie_id = c.id
                        WHERE oa.offre_id = :id
                        ORDER BY a.reference";
        $stmtArticles = $db->prepare($queryArticles);
        $stmtArticles->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtArticles->execute();
        
        $articles = $stmtArticles->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le prix après remise pour chaque article
        foreach ($articles as &$article) {
            $remise = $article['remise_specifique'] !== null ? $article['remise_specifique'] : $offre['valeur_remise'];
            
            if ($offre['type_remise'] === 'pourcentage') {
                $article['prix_apres_remise'] = round($article['prix_vente'] * (1 - $remise / 100), 2);
            } else {
                $article['prix_apres_remise'] = max(0, $article['prix_vente'] - $remise);
            }
            
            // Ajouter l'information sur la remise appliquée
            $article['remise_appliquee'] = $remise;
            $article['type_remise'] = $offre['type_remise'];
        }
        
        // Retourner les données au format JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'offre' => $offre,
            'articles' => $articles
        ]);
        
    } else if (isset($_GET['categorie_id'])) {
        // Récupération des offres par catégorie
        $categorie_id = (int)$_GET['categorie_id'];
        
        $query = "SELECT o.id, o.code, o.nom, o.description, o.date_debut, o.date_fin, 
                o.type_remise, o.valeur_remise, o.actif, o.priorite, 
                COUNT(oa.article_id) as nombre_articles,
                CASE 
                    WHEN o.type_remise = 'pourcentage' THEN CONCAT(o.valeur_remise, '%')
                    ELSE CONCAT(o.valeur_remise, ' €')
                END as remise_formatee
                FROM offres o
                LEFT JOIN offres_articles oa ON o.id = oa.offre_id
                WHERE (o.categorie_id = :categorie_id OR :categorie_id = 0) 
                AND o.actif = 1
                AND (o.date_fin IS NULL OR o.date_fin >= CURDATE())
                GROUP BY o.id
                ORDER BY o.priorite DESC, o.date_debut DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':categorie_id', $categorie_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $offres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque offre, calculer le prix moyen des articles
        foreach ($offres as &$offre) {
            // Récupérer le prix moyen des articles de l'offre
            $queryPrixMoyen = "SELECT AVG(a.prix_vente_ht) as prix_moyen
                            FROM articles a
                            JOIN offres_articles oa ON a.id = oa.article_id
                            WHERE oa.offre_id = :offre_id";
            $stmtPrixMoyen = $db->prepare($queryPrixMoyen);
            $stmtPrixMoyen->bindParam(':offre_id', $offre['id'], PDO::PARAM_INT);
            $stmtPrixMoyen->execute();
            
            $resultat = $stmtPrixMoyen->fetch(PDO::FETCH_ASSOC);
            $offre['prix_moyen'] = $resultat['prix_moyen'] ? round($resultat['prix_moyen'], 2) : 0;
            
            // Calculer le prix moyen après remise
            if ($offre['type_remise'] === 'pourcentage') {
                $offre['prix_moyen_apres_remise'] = round($offre['prix_moyen'] * (1 - $offre['valeur_remise'] / 100), 2);
            } else {
                $offre['prix_moyen_apres_remise'] = max(0, $offre['prix_moyen'] - $offre['valeur_remise']);
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'offres' => $offres
        ]);
    } else {
        // Récupération de toutes les offres actives
        $query = "SELECT o.id, o.code, o.nom, o.description, o.categorie_id, c.nom as categorie_nom,
                o.date_debut, o.date_fin, o.type_remise, o.valeur_remise, o.actif, o.priorite,
                COUNT(oa.article_id) as nombre_articles,
                CASE 
                    WHEN o.type_remise = 'pourcentage' THEN CONCAT(o.valeur_remise, '%')
                    ELSE CONCAT(o.valeur_remise, ' €')
                END as remise_formatee
                FROM offres o
                LEFT JOIN categorie c ON o.categorie_id = c.id
                LEFT JOIN offres_articles oa ON o.id = oa.offre_id
                WHERE o.actif = 1
                AND (o.date_fin IS NULL OR o.date_fin >= CURDATE())
                GROUP BY o.id
                ORDER BY o.priorite DESC, o.date_debut DESC";
        
        $stmt = $db->query($query);
        $offres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'offres' => $offres
        ]);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
}
?>
