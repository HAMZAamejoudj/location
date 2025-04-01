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
    // Démarrer une transaction
    $db->beginTransaction();

    // Mettre à jour le stock de l'article
    $updateQuery = "UPDATE articles SET quantite_stock = :nouveau_stock, derniere_mise_a_jour = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':nouveau_stock', $nouveau_stock);
    $updateStmt->bindParam(':id', $article_id);
    $updateStmt->execute();

    // Enregistrer le mouvement de stock dans la table historique_stock
    $historique_query = "INSERT INTO historique_stock (article_id, type_mouvement, quantite, stock_avant, stock_apres, 
                        motif, commentaire, utilisateur_id, date_mouvement) 
                        VALUES (:article_id, :type_mouvement, :quantite, :stock_avant, :stock_apres, 
                        :motif, :commentaire, :utilisateur_id, NOW())";
    
    $historique_stmt = $db->prepare($historique_query);
    $historique_stmt->bindParam(':article_id', $article_id);
    $historique_stmt->bindParam(':type_mouvement', $type_mouvement);
    $historique_stmt->bindParam(':quantite', $quantite);
    $historique_stmt->bindParam(':stock_avant', $stock_actuel);
    $historique_stmt->bindParam(':stock_apres', $nouveau_stock);
    $historique_stmt->bindParam(':motif', $motif);
    $historique_stmt->bindParam(':commentaire', $commentaire);
    $historique_stmt->bindParam(':utilisateur_id', $_SESSION['user_id']);
    $historique_stmt->execute();

    // Valider la transaction
    $db->commit();

    // Message de succès
    $_SESSION['success'] = "Le stock a été mis à jour avec succès. Nouveau stock: " . $nouveau_stock;
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $db->rollBack();
    $_SESSION['error'] = "Une erreur s'est produite lors de la mise à jour du stock: " . $e->getMessage();
}

// Rediriger vers la page d'index
header('Location: index.php');
exit;
