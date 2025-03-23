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
    $_SESSION['error'] = 'Vous devez être connecté pour effectuer cette action.';
    header('Location: index.php');
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer l'ID de l'intervention
    $intervention_id = isset($_POST['intervention_id']) ? intval($_POST['intervention_id']) : 0;
    
    if (empty($intervention_id)) {
        $_SESSION['error'] = 'ID d\'intervention invalide.';
        header('Location: index.php');
        exit;
    }
    
    try {
        // Connexion à la base de données
        $database = new Database();
        $db = $database->getConnection();
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Vérifier si l'intervention existe et récupérer ses informations
        $query = "SELECT i.*, v.client_id FROM interventions i
                  JOIN vehicules v ON i.vehicule_id = v.id
                  WHERE i.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $intervention_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier si l'intervention n'est pas déjà liée à une commande
            if ($intervention['commande_id']) {
                $db->rollBack();
                $_SESSION['error'] = 'Cette intervention est déjà liée à une commande.';
                header('Location: index.php');
                exit;
            }
            $numero_commande = 'CMD-' . date('YmdHis') . '-' . rand(1000, 9999);
            // Créer la commande
            $query = "INSERT INTO commandes (ID_client,Numero_Commande, vehicule_id, date_creation, Statut_Commande, user_id, intervention_id) 
                      VALUES (:client_id,:numero_commande ,:vehicule_id, NOW(), 'En attente', :user_id, :intervention_id)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':client_id', $intervention['client_id']);
            $stmt->bindParam(':numero_commande', $numero_commande);
            $stmt->bindParam(':vehicule_id', $intervention['vehicule_id']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':intervention_id', $intervention_id);
            
            if ($stmt->execute()) {
                $commande_id = $db->lastInsertId();
                
                // Mettre à jour l'intervention avec l'ID de la commande
                $query = "UPDATE interventions SET commande_id = :commande_id WHERE id = :intervention_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':commande_id', $commande_id);
                $stmt->bindParam(':intervention_id', $intervention_id);
                $stmt->execute();
                
                // Récupérer les articles de l'intervention
                $query = "SELECT ia.article_id, ia.quantite, ia.prix_unitaire, ia.remise 
                          FROM interventions_articles ia 
                          WHERE ia.intervention_id = :intervention_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':intervention_id', $intervention_id);
                $stmt->execute();
                
                $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ajouter les articles à la commande
                if (!empty($articles)) {
                    $query = "INSERT INTO commande_articles (commande_id, article_id, quantite, prix_unitaire, remise) 
                              VALUES (:commande_id, :article_id, :quantite, :prix_unitaire, :remise)";
                    $stmt = $db->prepare($query);
                    
                    foreach ($articles as $article) {
                        $stmt->bindParam(':commande_id', $commande_id);
                        $stmt->bindParam(':article_id', $article['article_id']);
                        $stmt->bindParam(':quantite', $article['quantite']);
                        $stmt->bindParam(':prix_unitaire', $article['prix_unitaire']);
                        $stmt->bindParam(':remise', $article['remise']);
                        $stmt->execute();
                    }
                }
                
                // Récupérer les offres de l'intervention
                $query = "SELECT io.offre_id, io.quantite, io.prix_unitaire, io.remise 
                          FROM interventions_offres io 
                          WHERE io.intervention_id = :intervention_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':intervention_id', $intervention_id);
                $stmt->execute();
                
                $offres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ajouter les offres à la commande
                if (!empty($offres)) {
                    $query = "INSERT INTO commande_offres (commande_id, offre_id, quantite, prix_unitaire, remise) 
                              VALUES (:commande_id, :offre_id, :quantite, :prix_unitaire, :remise)";
                    $stmt = $db->prepare($query);
                    
                    foreach ($offres as $offre) {
                        $stmt->bindParam(':commande_id', $commande_id);
                        $stmt->bindParam(':offre_id', $offre['offre_id']);
                        $stmt->bindParam(':quantite', $offre['quantite']);
                        $stmt->bindParam(':prix_unitaire', $offre['prix_unitaire']);
                        $stmt->bindParam(':remise', $offre['remise']);
                        $stmt->execute();
                    }
                }
                
                // Valider la transaction
                $db->commit();
                
                $_SESSION['success'] = 'La commande a été créée avec succès à partir de l\'intervention.';
                header('Location: ../commandes/view.php?id=' . $commande_id);
                exit;
            } else {
                $db->rollBack();
                $_SESSION['error'] = 'Une erreur est survenue lors de la création de la commande.';
                header('Location: index.php');
                exit;
            }
        } else {
            $db->rollBack();
            $_SESSION['error'] = 'Intervention non trouvée.';
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $_SESSION['error'] = 'Erreur de base de données: ' . $e->getMessage();
        header('Location: index.php');
        exit;
    }
} else {
    // Redirection si accès direct
    header('Location: index.php');
    exit;
}
?>