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
    // Récupérer les données du formulaire
    $vehicule_id = isset($_POST['vehicule_id']) ? intval($_POST['vehicule_id']) : 0;
    $technicien_id = isset($_POST['technicien_id']) && !empty($_POST['technicien_id']) ? intval($_POST['technicien_id']) : null;
    $date_prevue = isset($_POST['date_prevue']) && !empty($_POST['date_prevue']) ? $_POST['date_prevue'] : null;
    $date_debut = isset($_POST['date_debut']) && !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = isset($_POST['date_fin']) && !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $kilometrage = isset($_POST['kilometrage']) && !empty($_POST['kilometrage']) ? intval($_POST['kilometrage']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $diagnostique = isset($_POST['diagnostique']) ? trim($_POST['diagnostique']) : '';
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'En attente';
    $create_commande = isset($_POST['create_commande']) ? true : false;
    
    // Articles et offres sélectionnés
    $selected_articles = isset($_POST['selected_articles']) ? json_decode($_POST['selected_articles'], true) : [];
    $selected_offres = isset($_POST['selected_offres']) ? json_decode($_POST['selected_offres'], true) : [];
    
    // Validation des données
    $errors = [];
    
    if (empty($vehicule_id)) {
        $errors[] = 'Veuillez sélectionner un véhicule.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    // Si aucune erreur, procéder à l'enregistrement
    if (empty($errors)) {
        try {
            // Connexion à la base de données
            $database = new Database();
            $db = $database->getConnection();
            
            // Démarrer une transaction
            $db->beginTransaction();
            
            // Insérer l'intervention
            $query = "INSERT INTO interventions (vehicule_id, technicien_id, date_creation, date_prevue, date_debut, date_fin, 
                      kilometrage, description, diagnostique, commentaire, statut) 
                      VALUES (:vehicule_id, :technicien_id, NOW(), :date_prevue, :date_debut, :date_fin, 
                      :kilometrage, :description, :diagnostique, :commentaire, :statut)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':vehicule_id', $vehicule_id);
            $stmt->bindParam(':technicien_id', $technicien_id);
            $stmt->bindParam(':date_prevue', $date_prevue);
            $stmt->bindParam(':date_debut', $date_debut);
            $stmt->bindParam(':date_fin', $date_fin);
            $stmt->bindParam(':kilometrage', $kilometrage);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':diagnostique', $diagnostique);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':statut', $statut);
         //   $stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $intervention_id = $db->lastInsertId();
                
                // Insérer les articles sélectionnés
                if (!empty($selected_articles)) {
                    $query = "INSERT INTO interventions_articles (intervention_id, article_id, quantite, prix_unitaire, remise) 
                              VALUES (:intervention_id, :article_id, :quantite, :prix_unitaire, :remise)";
                    $stmt = $db->prepare($query);
                    
                    foreach ($selected_articles as $article) {
                        $stmt->bindValue(':intervention_id', $intervention_id);
                        $stmt->bindValue(':article_id', $article['id']);
                        $stmt->bindValue(':quantite', $article['quantite']);
                        $stmt->bindValue(':prix_unitaire', $article['prix_unitaire']);
                        $stmt->bindValue(':remise', $article['remise']);
                        $stmt->execute();
                    }
                }
                
                // Insérer les offres sélectionnées
               /*  if (!empty($selected_offres)) {
                    $query = "INSERT INTO interventions_offres (intervention_id, offre_id, quantite, prix_unitaire, remise) 
                              VALUES (:intervention_id, :offre_id, :quantite, :prix_unitaire, :remise)";
                    $stmt = $db->prepare($query);
                    
                    foreach ($selected_offres as $offre) {
                        $stmt->bindParam(':intervention_id', $intervention_id);
                        $stmt->bindParam(':offre_id', $offre['id']);
                        $stmt->bindParam(':quantite', $offre['quantite']);
                        $stmt->bindParam(':prix_unitaire', $offre['prix_unitaire']);
                        $stmt->bindParam(':remise', $offre['remise']);
                        $stmt->execute();
                    }
                }
                 */
                // Créer une commande si demandé
                $commande_id = null;
if ($create_commande) {
    // Récupérer les informations du client à partir du véhicule
    $query = "SELECT v.client_id FROM vehicules v WHERE v.id = :vehicule_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':vehicule_id', $vehicule_id);
    $stmt->execute();
    $client_id = $stmt->fetchColumn();
    
    if ($client_id) {
        // Créer la commande
        $numero_commande = 'CMD-' . date('YmdHis') . '-' . rand(1000, 9999);
        $query = "INSERT INTO commandes (ID_client, Numero_Commande, vehicule_id, date_creation, Statut_Commande, user_id, intervention_id) 
                  VALUES (:client_id, :numero_commande, :vehicule_id, NOW(), 'En attente', :user_id, :intervention_id)";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':client_id', $client_id);
        $stmt->bindValue(':numero_commande', $numero_commande);
        $stmt->bindValue(':vehicule_id', $vehicule_id);
        $stmt->bindValue(':user_id', $_SESSION['user_id']);
        $stmt->bindValue(':intervention_id', $intervention_id);
        
        if ($stmt->execute()) {
            $commande_id = $db->lastInsertId();
            
            // Mettre à jour l'intervention
            $query = "UPDATE interventions SET commande_id = :commande_id WHERE id = :intervention_id";
            $stmtUpdate = $db->prepare($query);
            $stmtUpdate->bindValue(':commande_id', $commande_id);
            $stmtUpdate->bindValue(':intervention_id', $intervention_id);
            $stmtUpdate->execute();
            
            // Ajouter les articles à la commande
            if (!empty($selected_articles)) {
                $total_ht = 0;
                $query = "INSERT INTO commande_details (ID_Commande, article_id, quantite, prix_unitaire, remise, montant_ht) 
                          VALUES (:commande_id, :article_id, :quantite, :prix_unitaire, :remise, :montant_ht)";
                $stmtArticles = $db->prepare($query);
                
                foreach ($selected_articles as $article) {
                    $montant_ht = round(($article['prix_unitaire'] * $article['quantite']) * (1 - $article['remise'] / 100), 2);
                    $total_ht += $montant_ht;

                    $stmtArticles->bindValue(':commande_id', $commande_id);
                    $stmtArticles->bindValue(':article_id', $article['id']);
                    $stmtArticles->bindValue(':quantite', $article['quantite']);
                    $stmtArticles->bindValue(':prix_unitaire', $article['prix_unitaire']);
                    $stmtArticles->bindValue(':remise', $article['remise']);
                    $stmtArticles->bindValue(':montant_ht', $montant_ht);
                    $stmtArticles->execute();
                }
                
                // Mettre à jour le montant total HT de la commande
                $updateQuery = "UPDATE commandes SET Montant_Total_HT = :montant_total_ht WHERE ID_Commande = :commande_id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindValue(':montant_total_ht', $total_ht);
                $updateStmt->bindValue(':commande_id', $commande_id);
                $updateStmt->execute();
            }
        }
    }
}
                
                // Valider la transaction
                $db->commit();
                
                // Message de succès
                $_SESSION['success'] = 'L\'intervention a été créée avec succès.' . ($commande_id ? ' Une commande associée a également été créée.' : '');
                header('Location: index.php');
                exit;
            } else {
                $db->rollBack();
                $_SESSION['error'] = 'Une erreur est survenue lors de la création de l\'intervention.';
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Erreur de base de données: ' . $e->getMessage();
            header('Location: index.php');
            exit;
        }

    } else {
        // Afficher les erreurs
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: index.php');
        exit;
    }
} else {
    // Redirection si accès direct
    header('Location: index.php');
    exit;
}
?>