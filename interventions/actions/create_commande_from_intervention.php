<?php
// Démarrer la session
session_start();

// Chemin racine de l'application
$root_path = dirname(dirname(__DIR__));

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    redirectWithError('Vous devez être connecté pour effectuer cette action.', 'index.php');
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer l'ID de l'intervention
    $intervention_id = isset($_POST['intervention_id']) ? intval($_POST['intervention_id']) : 0;

    if (empty($intervention_id)) {
        redirectWithError('ID d\'intervention invalide.', 'index.php');
    }

    try {
        // Connexion à la base de données
        $database = new Database();
        $db = $database->getConnection();

        // Démarrer une transaction
        $db->beginTransaction();

        // Vérifier si l'intervention existe et récupérer ses informations
        $intervention = getInterventionById($db, $intervention_id);

        if (!$intervention) {
            $db->rollBack();
            redirectWithError('Intervention non trouvée.', 'index.php');
        }

        // Vérifier si l'intervention n'est pas déjà liée à une commande
        if ($intervention['commande_id']) {
            $db->rollBack();
            redirectWithError('Cette intervention est déjà liée à une commande.', 'index.php');
        }

        // Créer la commande
        $commande_id = createCommande($db, $intervention, $_SESSION['user_id']);

        // Mettre à jour l'intervention avec l'ID de la commande
        updateInterventionCommande($db, $intervention_id, $commande_id);

        // Ajouter les articles et les offres à la commande
        $articles = getInterventionArticles($db, $intervention_id);
        $offres = getInterventionOffres($db, $intervention_id);

        if (!empty($articles)) {
            insertCommandeArticles($db, $commande_id, $articles);
        }

        if (!empty($offres)) {
            insertCommandeOffres($db, $commande_id, $offres);
        }

        // Valider la transaction
        $db->commit();

        redirectWithSuccess('La commande a été créée avec succès à partir de l\'intervention.', '../../orders/view.php?id=' . $commande_id);

    } catch (PDOException $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        redirectWithError('Erreur de base de données: ' . $e->getMessage(), 'index.php');
    }
} else {
    // Redirection si accès direct
    header('Location: index.php');
    exit;
}

// Fonction pour rediriger avec un message d'erreur
function redirectWithError($message, $location) {
    $_SESSION['error'] = $message;
    header('Location: ' . $location);
    exit;
}

// Fonction pour rediriger avec un message de succès
function redirectWithSuccess($message, $location) {
    $_SESSION['success'] = $message;
    header('Location: ' . $location);
    exit;
}

// Fonction pour récupérer une intervention par ID
function getInterventionById($db, $intervention_id) {
    $query = "SELECT i.*, v.client_id 
              FROM interventions i
              JOIN vehicules v ON i.vehicule_id = v.id
              WHERE i.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $intervention_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fonction pour créer une commande
function createCommande($db, $intervention, $user_id) {
    $numero_commande = 'CMD-' . date('YmdHis') . '-' . rand(1000, 9999);
    $query = "INSERT INTO commandes (ID_client, Numero_Commande, vehicule_id, date_creation, Statut_Commande, user_id, intervention_id) 
              VALUES (:client_id, :numero_commande, :vehicule_id, NOW(), 'En attente', :user_id, :intervention_id)";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':client_id', $intervention['client_id'], PDO::PARAM_INT);
    $stmt->bindValue(':numero_commande', $numero_commande);
    $stmt->bindValue(':vehicule_id', $intervention['vehicule_id'], PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':intervention_id', $intervention['id'], PDO::PARAM_INT);
    $stmt->execute();
    return $db->lastInsertId();
}

// Fonction pour mettre à jour l'intervention avec l'ID de commande
function updateInterventionCommande($db, $intervention_id, $commande_id) {
    $query = "UPDATE interventions SET commande_id = :commande_id WHERE id = :intervention_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':commande_id', $commande_id, PDO::PARAM_INT);
    $stmt->bindValue(':intervention_id', $intervention_id, PDO::PARAM_INT);
    $stmt->execute();
}

// Fonction pour récupérer les articles d'une intervention
function getInterventionArticles($db, $intervention_id) {
    $query = "SELECT ia.article_id, ia.quantite, ia.prix_unitaire, ia.remise 
              FROM interventions_articles ia 
              WHERE ia.intervention_id = :intervention_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':intervention_id', $intervention_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les offres d'une intervention
function getInterventionOffres($db, $intervention_id) {
    $query = "SELECT io.offre_id, io.quantite, io.prix_unitaire, io.remise 
              FROM interventions_offres io 
              WHERE io.intervention_id = :intervention_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':intervention_id', $intervention_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour insérer les articles dans une commande
function insertCommandeArticles($db, $commande_id, $articles) {
    $query = "INSERT INTO commande_articles (commande_id, article_id, quantite, prix_unitaire, remise) 
              VALUES (:commande_id, :article_id, :quantite, :prix_unitaire, :remise)";
    $stmt = $db->prepare($query);

    foreach ($articles as $article) {
        $stmt->bindValue(':commande_id', $commande_id, PDO::PARAM_INT);
        $stmt->bindValue(':article_id', $article['article_id'], PDO::PARAM_INT);
        $stmt->bindValue(':quantite', $article['quantite'], PDO::PARAM_INT);
        $stmt->bindValue(':prix_unitaire', $article['prix_unitaire']);
        $stmt->bindValue(':remise', $article['remise']);
        $stmt->execute();
    }
}

// Fonction pour insérer les offres dans une commande
function insertCommandeOffres($db, $commande_id, $offres) {
    $query = "INSERT INTO commande_offres (commande_id, offre_id, quantite, prix_unitaire, remise) 
              VALUES (:commande_id, :offre_id, :quantite, :prix_unitaire, :remise)";
    $stmt = $db->prepare($query);

    foreach ($offres as $offre) {
        $stmt->bindValue(':commande_id', $commande_id, PDO::PARAM_INT);
        $stmt->bindValue(':offre_id', $offre['offre_id'], PDO::PARAM_INT);
        $stmt->bindValue(':quantite', $offre['quantite'], PDO::PARAM_INT);
        $stmt->bindValue(':prix_unitaire', $offre['prix_unitaire']);
        $stmt->bindValue(':remise', $offre['remise']);
        $stmt->execute();
    }
}
?>