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
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Récupérer les données du formulaire
$article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
$type_mouvement = isset($_POST['type_mouvement']) ? $_POST['type_mouvement'] : '';
$quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 0;
$motif = isset($_POST['motif']) ? $_POST['motif'] : '';
$commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';

// Validation des données
if ($article_id <= 0 || empty($type_mouvement) || $quantite <= 0 || empty($motif)) {
    $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis correctement.";
    header('Location: index.php');
    exit;
}

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Récupérer les informations de l'article
$query = "SELECT reference, designation, quantite_stock FROM articles WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $article_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $_SESSION['error'] = "Article introuvable.";
    header('Location: index.php');
    exit;
}

$article = $stmt->fetch(PDO::FETCH_ASSOC);
$stock_actuel = $article['quantite_stock'];

// Calculer le nouveau stock en fonction du type de mouvement
$nouveau_stock = $stock_actuel;
switch ($type_mouvement) {
    case 'entree':
        $nouveau_stock = $stock_actuel + $quantite;
        break;
    case 'sortie':
        $nouveau_stock = $stock_actuel - $quantite;
        // Vérifier si le stock devient négatif
        if ($nouveau_stock < 0) {
            $_SESSION['error'] = "Stock insuffisant pour effectuer cette opération.";
            header('Location: index.php');
            exit;
        }
        break;
    case 'ajustement':
        $nouveau_stock = $quantite; // Le stock est directement défini à la quantité spécifiée
        break;
    default:
        $_SESSION['error'] = "Type de mouvement non valide.";
        header('Location: index.php');
        exit;
}

try {
    // Code précédent jusqu'au calcul du nouveau stock...

    // Déterminer le Type_Operation selon l'enum de la base de données
    $type_operation_db = 'Autre'; // Valeur par défaut
    switch ($type_mouvement) {
        case 'entree':
            $type_operation_db = 'Réception'; // Correspond à l'enum dans la DB
            break;
        case 'sortie':
            $type_operation_db = 'Modification'; // Correspond à l'enum dans la DB
            break;
        case 'ajustement':
            $type_operation_db = 'Modification'; // Correspond à l'enum dans la DB
            break;
    }
    error_log("Type d'opération mappé: $type_operation_db");

    // Démarrer une transaction
    $db->beginTransaction();
    error_log("Transaction démarrée");

    // Mettre à jour le stock de l'article
    $updateQuery = "UPDATE articles SET quantite_stock = :nouveau_stock, derniere_mise_a_jour = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':nouveau_stock', $nouveau_stock, PDO::PARAM_INT);
    $updateStmt->bindValue(':id', $article_id, PDO::PARAM_INT);
    $result = $updateStmt->execute();
    
    if (!$result) {
        error_log("Échec de la mise à jour du stock: " . print_r($updateStmt->errorInfo(), true));
        throw new Exception("Échec de la mise à jour du stock");
    }
    
    error_log("Stock mis à jour avec succès");

    // Récupérer le prix d'achat actuel de l'article
    $prixQuery = "SELECT prix_achat FROM articles WHERE id = :id";
    $prixStmt = $db->prepare($prixQuery);
    $prixStmt->bindValue(':id', $article_id, PDO::PARAM_INT);
    $prixStmt->execute();
    $prix_achat = $prixStmt->fetchColumn();
    error_log("Prix d'achat récupéré: $prix_achat");

    // Générer un ID_Commande et ID_Ligne_Commande factice (puisqu'ils ne peuvent pas être NULL)
    $id_commande_factice = 0; // Utilisez 0 ou une autre valeur par défaut acceptable
    $id_ligne_commande_factice = 0; // Utilisez 0 ou NULL si autorisé

    // Enregistrer le mouvement de stock dans la table historique_articles
    $historique_query = "INSERT INTO historique_articles (ID_Article, ID_Commande, ID_Ligne_Commande, 
                        Type_Operation, Date_Operation, Quantite, Prix_Unitaire, Utilisateur, Commentaire) 
                        VALUES (:article_id, :id_commande, :id_ligne_commande, :type_operation, NOW(), :quantite, 
                        :prix_unitaire, :utilisateur, :commentaire)";

    $historique_stmt = $db->prepare($historique_query);
    $historique_stmt->bindValue(':article_id', $article_id, PDO::PARAM_INT);
    $historique_stmt->bindValue(':id_commande', $id_commande_factice, PDO::PARAM_INT); // Valeur factice au lieu de NULL
    $historique_stmt->bindValue(':id_ligne_commande', $id_ligne_commande_factice, PDO::PARAM_INT); // Valeur factice ou NULL si autorisé
    $historique_stmt->bindValue(':type_operation', $type_operation_db, PDO::PARAM_STR); // Valeur de l'enum correcte
    $historique_stmt->bindValue(':quantite', $quantite, PDO::PARAM_STR); // Decimal doit être bindé comme STR
    $historique_stmt->bindValue(':prix_unitaire', $prix_achat, PDO::PARAM_STR); // Decimal doit être bindé comme STR
    $historique_stmt->bindValue(':utilisateur', (string)$_SESSION['user_id'], PDO::PARAM_STR); // Convertir en string pour varchar
    $historique_stmt->bindValue(':commentaire', $commentaire, PDO::PARAM_STR);
    
    // Déboguer la requête avant exécution
    error_log("Requête historique préparée avec les valeurs:");
    error_log("ID_Article: $article_id");
    error_log("ID_Commande: $id_commande_factice");
    error_log("ID_Ligne_Commande: $id_ligne_commande_factice");
    error_log("Type_Operation: $type_operation_db");
    error_log("Quantite: $quantite");
    error_log("Prix_Unitaire: $prix_achat");
    error_log("Utilisateur: " . $_SESSION['user_id']);
    error_log("Commentaire: $commentaire");
    
    $result = $historique_stmt->execute();
    
    if (!$result) {
        error_log("Échec de l'enregistrement dans l'historique: " . print_r($historique_stmt->errorInfo(), true));
        throw new Exception("Échec de l'enregistrement dans l'historique: " . implode(", ", $historique_stmt->errorInfo()));
    }
    
    error_log("Historique enregistré avec succès");

    // Valider la transaction
    $db->commit();
    error_log("Transaction validée");

    // Message de succès
    $_SESSION['success'] = "Le stock a été mis à jour avec succès. Nouveau stock: " . $nouveau_stock;
    error_log("Opération réussie: " . $_SESSION['success']);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        error_log("Transaction annulée");
    }
    
    $_SESSION['error'] = "Une erreur s'est produite lors de la mise à jour du stock: " . $e->getMessage();
    error_log("Exception: " . $e->getMessage());
}


// Rediriger vers la page d'index
header('Location: index.php');
exit;
