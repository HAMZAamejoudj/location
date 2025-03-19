<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour accéder à cette page.";
    header("Location: ../login.php");
    exit;
}

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $vehicule_id = isset($_POST['vehicule_id']) ? filter_var($_POST['vehicule_id'], FILTER_VALIDATE_INT) : null;
    $technicien_id = isset($_POST['technicien_id']) && !empty($_POST['technicien_id']) ? filter_var($_POST['technicien_id'], FILTER_VALIDATE_INT) : null;
    $date_prevue = isset($_POST['date_prevue']) && !empty($_POST['date_prevue']) ? $_POST['date_prevue'] : null;
    $date_debut = isset($_POST['date_debut']) && !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = isset($_POST['date_fin']) && !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $kilometrage = isset($_POST['kilometrage']) && !empty($_POST['kilometrage']) ? filter_var($_POST['kilometrage'], FILTER_VALIDATE_INT) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $diagnostique = isset($_POST['diagnostique']) ? trim($_POST['diagnostique']) : null;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : null;
    $statut = isset($_POST['statut']) ? trim($_POST['statut']) : 'En attente';

    // Validation des données
    $errors = [];
    if (!$vehicule_id) {
        $errors[] = "Veuillez sélectionner un véhicule.";
    }
    if (empty($description)) {
        $errors[] = "La description est obligatoire.";
    }

    // Si pas d'erreurs, procéder à l'insertion
    if (empty($errors)) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Préparer la requête d'insertion
            $query = "INSERT INTO interventions (
                        vehicule_id, technicien_id, date_creation, date_prevue, date_debut, date_fin, 
                        kilometrage, description, diagnostique, commentaire, statut
                      ) VALUES (
                        :vehicule_id, :technicien_id, NOW(), :date_prevue, :date_debut, :date_fin, 
                        :kilometrage, :description, :diagnostique, :commentaire, :statut
                      )";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':vehicule_id', $vehicule_id, PDO::PARAM_INT);
            $stmt->bindParam(':technicien_id', $technicien_id, PDO::PARAM_INT);
            $stmt->bindParam(':date_prevue', $date_prevue);
            $stmt->bindParam(':date_debut', $date_debut);
            $stmt->bindParam(':date_fin', $date_fin);
            $stmt->bindParam(':kilometrage', $kilometrage, PDO::PARAM_INT);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':diagnostique', $diagnostique);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':statut', $statut);

            $success = $stmt->execute();

            if ($success) {
                $intervention_id = $db->lastInsertId();
                
                // Traitement des articles sélectionnés
                if (isset($_POST['selected_articles']) && !empty($_POST['selected_articles'])) {
                    try {
                        $articles = json_decode($_POST['selected_articles'], true);
                        
                        if (is_array($articles) && !empty($articles)) {
                            // Préparer la requête d'insertion pour les articles
                            $query_articles = "INSERT INTO interventions_articles 
                                            (intervention_id, article_id, quantite, prix_unitaire, remise) 
                                            VALUES 
                                            (:intervention_id, :article_id, :quantite, :prix_unitaire, :remise)";
                            
                            $stmt_articles = $db->prepare($query_articles);
                            
                            foreach ($articles as $article) {
                                $stmt_articles->bindParam(':intervention_id', $intervention_id, PDO::PARAM_INT);
                                $stmt_articles->bindParam(':article_id', $article['id'], PDO::PARAM_INT);
                                $stmt_articles->bindParam(':quantite', $article['quantite'], PDO::PARAM_INT);
                                $stmt_articles->bindParam(':prix_unitaire', $article['prix_vente_ht'], PDO::PARAM_STR);
                                $stmt_articles->bindParam(':remise', $article['remise'], PDO::PARAM_STR);
                                $stmt_articles->execute();
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Erreur lors de l\'ajout des articles à l\'intervention: ' . $e->getMessage());
                        // Ne pas annuler l'intervention même si l'ajout des articles échoue
                        $_SESSION['warning'] = 'L\'intervention a été créée mais certains articles n\'ont pas pu être ajoutés.';
                    }
                }
                
                $_SESSION['success'] = "L'intervention a été créée avec succès.";
                header("Location: index.php");
                exit;
            } else {
                $_SESSION['error'] = "Une erreur est survenue lors de la création de l'intervention.";
                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            error_log('Erreur lors de la création de l\'intervention: ' . $e->getMessage());
            $_SESSION['error'] = "Une erreur de base de données est survenue.";
            header("Location: index.php");
            exit;
        }
    } else {
        // Stocker les erreurs dans la session
        $_SESSION['error'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST; // Stocker les données du formulaire pour les réafficher
        header("Location: index.php");
        exit;
    }
} else {
    // Si accès direct à ce fichier sans soumission de formulaire
    header("Location: index.php");
    exit;
}
?>
