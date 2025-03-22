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
                        $stmt->bindParam(':intervention_id', $intervention_id);
                        $stmt->bindParam(':article_id', $article['id']);
                        $stmt->bindParam(':quantite', $article['quantite']);
                        $stmt->bindParam(':prix_unitaire', $article['prix_unitaire']);
                        $stmt->bindParam(':remise', $article['remise']);
                        $stmt->execute();
                    }
                }
                
                // Insérer les offres sélectionnées
                if (!empty($selected_offres)) {
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
                
                // Créer une commande si demandé
                $commande_id = null;
                if ($create_commande) {
                    // Récupérer les informations du client à partir du véhicule
                    $query = "SELECT v.client_id FROM vehicules v WHERE v.id = :vehicule_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':vehicule_id', $vehicule_id);
                    $stmt->execute();
                    $client_id = $stmt->fetchColumn();
                    
                    if ($client_id) {
                        // Créer la commande
                        $query = "INSERT INTO commandes (ID_client , vehicule_id, date_creation, Statut_Commande, intervention_id) 
                                  VALUES (:client_id, :vehicule_id, NOW(), 'En attente', :intervention_id)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':client_id', $client_id);
                        $stmt->bindParam(':vehicule_id', $vehicule_id);
                     //   $stmt->bindParam(':user_id', $_SESSION['user_id']);
                        $stmt->bindParam(':intervention_id', $intervention_id);
                        
                        if ($stmt->execute()) {
                            $commande_id = $db->lastInsertId();
                            
                            // Mettre à jour l'intervention avec l'ID de la commande
                            $query = "UPDATE interventions SET commande_id = :commande_id WHERE id = :intervention_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':commande_id', $commande_id);
                            $stmt->bindParam(':intervention_id', $intervention_id);
                            $stmt->execute();
                            
                            // Ajouter les articles à la commande
                            if (!empty($selected_articles)) {
                                $query = "INSERT INTO commande_articles (commande_id, article_id, quantite, prix_unitaire, remise) 
                                          VALUES (:commande_id, :article_id, :quantite, :prix_unitaire, :remise)";
                                $stmt = $db->prepare($query);
                                
                                foreach ($selected_articles as $article) {
                                    $stmt->bindParam(':commande_id', $commande_id);
                                    $stmt->bindParam(':article_id', $article['id']);
                                    $stmt->bindParam(':quantite', $article['quantite']);
                                    $stmt->bindParam(':prix_unitaire', $article['prix_unitaire']);
                                    $stmt->bindParam(':remise', $article['remise']);
                                    $stmt->execute();
                                }
                            }
                            
                            // Ajouter les offres à la commande
                            if (!empty($selected_offres)) {
                                $query = "INSERT INTO commande_offres (commande_id, offre_id, quantite, prix_unitaire, remise) 
                                          VALUES (:commande_id, :offre_id, :quantite, :prix_unitaire, :remise)";
                                $stmt = $db->prepare($query);
                                
                                foreach ($selected_offres as $offre) {
                                    $stmt->bindParam(':commande_id', $commande_id);
                                    $stmt->bindParam(':offre_id', $offre['id']);
                                    $stmt->bindParam(':quantite', $offre['quantite']);
                                    $stmt->bindParam(':prix_unitaire', $offre['prix_unitaire']);
                                    $stmt->bindParam(':remise', $offre['remise']);
                                    $stmt->execute();
                                }
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