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

// Vérifier si l'utilisateur est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
}

// Utilisateur temporaire pour éviter l'erreur
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Vérifier si l'ID de la commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_commande = intval($_GET['id']);

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la commande
$commande = null;
$commande_details = [];
$error_message = null;

try {
    // Récupérer les informations de la commande
    $query = "SELECT c.* FROM commandes c WHERE c.ID_Commande = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_commande);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les articles de la commande
        $query = "SELECT cd.*, a.reference, a.designation
                  FROM commande_details cd
                  INNER JOIN article a ON cd.article_id = a.id
                  WHERE cd.ID_Commande = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id_commande);
        $stmt->execute();
        
        $commande_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "La commande demandée n'existe pas.";
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get form data
        $numero_commande = $_POST['numero_commande'];
        $date_commande = $_POST['date_commande'];
        $date_livraison_prevue = $_POST['date_livraison_prevue'];
        $id_fournisseur = $_POST['id_fournisseur'];
        $statut = $_POST['statut'];
        $montant_total_ht = $_POST['montant_total_ht'];
        $notes = $_POST['notes'] ?? '';
        
        // Update command in database
        $query = "UPDATE commandes SET 
                    Numero_Commande = :numero_commande, 
                    Date_Commande = :date_commande, 
                    ID_Fournisseur = :id_fournisseur, 
                    Date_Livraison_Prevue = :date_livraison_prevue, 
                    Statut_Commande = :statut, 
                    Montant_Total_HT = :montant_total_ht, 
                    Notes = :notes,
                    Modifie_Par = :modifie_par,
                    Date_Modification = NOW()
                  WHERE ID_Commande = :id_commande";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':numero_commande', $numero_commande);
        $stmt->bindParam(':date_commande', $date_commande);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur);
        $stmt->bindParam(':date_livraison_prevue', $date_livraison_prevue);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':montant_total_ht', $montant_total_ht);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':modifie_par', $currentUser['name']);
        $stmt->bindParam(':id_commande', $id_commande);
        $stmt->execute();
        
        // Delete existing command details
        $query = "DELETE FROM commande_details WHERE ID_Commande = :id_commande";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_commande', $id_commande);
        $stmt->execute();
        
        // Process articles
        if (isset($_POST['articles']) && is_array($_POST['articles'])) {
            foreach ($_POST['articles'] as $article) {
                // Skip empty rows
                if (empty($article['id_article'])) {
                    continue;
                }
                
                $id_article = $article['id_article'];
                $quantite = $article['quantite'];
                $prix_unitaire = $article['prix_unitaire'];
                
                // Calculate line totals
                $total_ht = $quantite * $prix_unitaire;
                
                // Insert command line
                $query = "INSERT INTO commande_details (
                            ID_Commande, 
                            article_id, 
                            quantite, 
                            prix_unitaire,
                            montant_ht
                          ) VALUES (
                            :id_commande,
                            :id_article,
                            :quantite,
                            :prix_unitaire,
                            :total_ht
                          )";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_commande', $id_commande);
                $stmt->bindParam(':id_article', $id_article);
                $stmt->bindParam(':quantite', $quantite);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire);
                $stmt->bindParam(':total_ht', $total_ht);
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Set success message
        $success_message = "La commande a été mise à jour avec succès!";
        
        // Refresh command data
        $query = "SELECT c.* FROM commandes c WHERE c.ID_Commande = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id_commande);
        $stmt->execute();
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Refresh command details
        $query = "SELECT cd.*, a.reference, a.designation
                  FROM commande_details cd
                  INNER JOIN article a ON cd.article_id = a.id
                  WHERE cd.ID_Commande = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id_commande);
        $stmt->execute();
        $commande_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->rollBack();
        $error_message = "Erreur: " . $e->getMessage();
    }
}

// Fetch fournisseurs list
$fournisseurs = [];
try {
    $query = "SELECT * FROM fournisseurs ORDER BY ID_Fournisseur";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Fetch articles list
$articles = [];
try {
    $query = "SELECT * FROM article ORDER BY reference";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include $root_path . '/includes/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-900">Modifier la commande</h1>
                <div class="flex space-x-3">
                    <button id="print-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimer
                    </button>
                    <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                        </svg>
                        Retour aux commandes
                    </a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <?php if (isset($error_message)): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Erreur!</strong>
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Succès!</strong>
                    <span class="block sm:inline"><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($commande): ?>
                <!-- Command Form -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <form id="commandeForm" method="POST" action="">
                        <!-- Command Details Section -->
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Informations de la commande</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">Modifiez les détails de cette commande.</p>
                        </div>
                        
                        <div class="px-4 py-5 sm:p-6">
                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                <!-- Command Number -->
                                <div class="sm:col-span-2">
                                    <label for="numero_commande" class="block text-sm font-medium text-gray-700">Numéro de commande</label>
                                    <div class="mt-1">
                                        <input type="text" name="numero_commande" id="numero_commande" 
                                            value="<?php echo htmlspecialchars($commande['Numero_Commande']); ?>" 
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" >
                                    </div>
                                </div>

                                <!-- Command Date -->
                                <div class="sm:col-span-2">
                                    <label for="date_commande" class="block text-sm font-medium text-gray-700">Date de commande</label>
                                    <div class="mt-1">
                                        <input type="date" name="date_commande" id="date_commande" 
                                            value="<?php echo date('Y-m-d', strtotime($commande['Date_Commande'])); ?>" 
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>

                                <!-- Expected Delivery Date -->
                                <div class="sm:col-span-2">
                                    <label for="date_livraison_prevue" class="block text-sm font-medium text-gray-700">Date de livraison prévue</label>
                                    <div class="mt-1">
                                        <input type="date" name="date_livraison_prevue" id="date_livraison_prevue" 
                                            value="<?php echo date('Y-m-d', strtotime($commande['Date_Livraison_Prevue'])); ?>" 
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>

                                <!-- Fournisseur Selection -->
                                <div class="sm:col-span-3">
                                    <label for="id_fournisseur" class="block text-sm font-medium text-gray-700">Fournisseur</label>
                                    <div class="mt-1">
                                        <select id="id_fournisseur" name="id_fournisseur" required
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Sélectionner un fournisseur</option>
                                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                                <option value="<?php echo $fournisseur['ID_Fournisseur']; ?>" <?php echo ($fournisseur['ID_Fournisseur'] == $commande['ID_Fournisseur']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($fournisseur['Code_Fournisseur']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Status Selection -->
                                <div class="sm:col-span-3">
                                    <label for="statut" class="block text-sm font-medium text-gray-700">Statut</label>
                                    <div class="mt-1">
                                        <select id="statut" name="statut" required
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            <option value="En attente" <?php echo ($commande['Statut_Commande'] == 'En attente') ? 'selected' : ''; ?>>En attente</option>
                                            <option value="En cours" <?php echo ($commande['Statut_Commande'] == 'En cours') ? 'selected' : ''; ?>>En cours</option>
                                            <option value="Livrée" <?php echo ($commande['Statut_Commande'] == 'Livrée') ? 'selected' : ''; ?>>Livrée</option>
                                            <option value="Annulée" <?php echo ($commande['Statut_Commande'] == 'Annulée') ? 'selected' : ''; ?>>Annulée</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Articles Section -->
                        <div class="px-4 py-5 sm:px-6 border-t border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Articles commandés</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">Modifiez les articles de cette commande.</p>
                        </div>

                        <div class="px-4 py-5 sm:p-6">
                            <div class="articles-container">
                                <?php if (count($commande_details) > 0): ?>
                                    <?php foreach ($commande_details as $index => $detail): ?>
                                        <div class="article-row grid grid-cols-1 gap-y-4 gap-x-4 sm:grid-cols-12 mb-6 pb-6 border-b border-gray-200">
                                            <div class="sm:col-span-5">
                                                <label class="block text-sm font-medium text-gray-700">Article</label>
                                                <select name="articles[<?php echo $index; ?>][id_article]" class="article-select mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="updateArticleInfo(this, <?php echo $index; ?>)">
                                                    <option value="">Sélectionner un article</option>
                                                    <?php foreach ($articles as $article): ?>
                                                        <option value="<?php echo $article['id']; ?>" 
                                                                data-reference="<?php echo htmlspecialchars($article['reference']); ?>"
                                                                data-designation="<?php echo htmlspecialchars($article['designation']); ?>"
                                                                data-prix="<?php echo $article['prix_achat']; ?>"
                                                                <?php echo ($article['id'] == $detail['article_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($article['designation']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="sm:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Référence</label>
                                                <input type="text" name="articles[<?php echo $index; ?>][reference]" value="<?php echo htmlspecialchars($detail['reference']); ?>" class="mt-1 shadow-sm block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" readonly>
                                            </div>
                                            
                                            <div class="sm:col-span-1">
                                                <label class="block text-sm font-medium text-gray-700">Quantité</label>
                                                <input type="number" name="articles[<?php echo $index; ?>][quantite]" min="1" value="<?php echo $detail['quantite']; ?>" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(<?php echo $index; ?>)">
                                            </div>
                                            
                                            <div class="sm:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Prix unitaire</label>
                                                <div class="mt-1 relative rounded-md shadow-sm">
                                                    <input type="number" step="0.01" name="articles[<?php echo $index; ?>][prix_unitaire]" value="<?php echo $detail['prix_unitaire']; ?>" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(<?php echo $index; ?>)">
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">DH</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="sm:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Total</label>
                                                <div class="mt-1 relative rounded-md shadow-sm">
                                                    <input type="text" name="articles[<?php echo $index; ?>][total_ht]" value="<?php echo number_format($detail['montant_ht'], 2, '.', '') . ' DH'; ?>" class="bg-gray-50 block w-full pr-12 sm:text-sm border-gray-300 rounded-md" readonly>
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">DH</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="sm:col-span-1 flex items-end justify-center">
                                                <button type="button" class="text-red-600 hover:text-red-900 focus:outline-none" onclick="removeArticleRow(this)">
                                                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Empty Article Template Row -->
                                    <div class="article-row grid grid-cols-1 gap-y-4 gap-x-4 sm:grid-cols-12 mb-6 pb-6 border-b border-gray-200">
                                        <div class="sm:col-span-5">
                                            <label class="block text-sm font-medium text-gray-700">Article</label>
                                            <select name="articles[0][id_article]" class="article-select mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="updateArticleInfo(this, 0)">
                                                <option value="">Sélectionner un article</option>
                                                <?php foreach ($articles as $article): ?>
                                                    <option value="<?php echo $article['id']; ?>" 
                                                            data-reference="<?php echo htmlspecialchars($article['reference']); ?>"
                                                            data-designation="<?php echo htmlspecialchars($article['designation']); ?>"
                                                            data-prix="<?php echo $article['prix_achat']; ?>"
                                                            >
                                                        <?php echo htmlspecialchars($article['designation']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Référence</label>
                                            <input type="text" name="articles[0][reference]" class="mt-1 shadow-sm block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" readonly>
                                        </div>
                                        
                                        <div class="sm:col-span-1">
                                            <label class="block text-sm font-medium text-gray-700">Quantité</label>
                                            <input type="number" name="articles[0][quantite]" min="1" value="1" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(0)">
                                        </div>
                                        
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Prix unitaire</label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <input type="number" step="0.01" name="articles[0][prix_unitaire]" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(0)">
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-sm">DH</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Total</label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <input type="text" name="articles[0][total_ht]" class="bg-gray-50 block w-full pr-12 sm:text-sm border-gray-300 rounded-md" readonly>
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-sm">DH</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="sm:col-span-1 flex items-end justify-center">
                                            <button type="button" class="text-red-600 hover:text-red-900 focus:outline-none" onclick="removeArticleRow(this)">
                                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4">
                                <button type="button" id="add-article-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Ajouter un article
                                </button>
                            </div>

                            <!-- Totals Section -->
                            <div class="mt-8 border-t border-gray-200 pt-8">
                                <div class="flex flex-col sm:flex-row sm:justify-end">
                                    <div class="w-full sm:w-1/3 bg-gray-50 p-4 rounded-md">
                                        <div class="flex justify-between py-2 text-sm">
                                            <span class="font-medium text-gray-500">Total HT:</span>
                                            <span id="total_ht_display" class="font-medium"><?php echo number_format($commande['Montant_Total_HT'], 2, ',', ' '); ?> DH</span>
                                            <input type="hidden" name="montant_total_ht" id="montant_total_ht" value="<?php echo $commande['Montant_Total_HT']; ?>">
                                        </div>
                                        <div class="flex justify-between py-2 text-sm">
                                            <span class="font-medium text-gray-500">Total TVA:</span>
                                            <span id="total_tva_display" class="font-medium">0,00 DH</span>
                                            <input type="hidden" name="montant_total_tva" id="montant_total_tva" value="0">
                                        </div>
                                        <div class="flex justify-between py-2 text-base font-medium">
                                            <span class="text-gray-900">Total TTC:</span>
                                            <span id="total_ttc_display" class="text-indigo-600"><?php echo number_format($commande['Montant_Total_HT'] * 1.2, 2, ',', ' '); ?> DH</span>
                                            <input type="hidden" name="montant_total_ttc" id="montant_total_ttc" value="<?php echo $commande['Montant_Total_HT'] * 1.2; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div class="px-4 py-5 sm:px-6 border-t border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Notes et commentaires</h3>
                            <div class="mt-2">
                                <textarea name="notes" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Ajoutez des notes ou instructions spéciales pour cette commande..."><?php echo htmlspecialchars($commande['Notes']); ?></textarea>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 flex justify-end space-x-3">
                            <a href="index.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Annuler
                            </a>
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Mettre à jour la commande
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg p-6">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Commande non trouvée</h3>
                        <p class="mt-1 text-sm text-gray-500">La commande que vous cherchez n'existe pas ou a été supprimée.</p>
                        <div class="mt-6">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                                </svg>
                                Retour à la liste des commandes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Print Template -->
<div id="print-template" class="hidden">
    <div class="print-content p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">Bon de Commande</h1>
            <p id="print-numero-commande" class="text-lg"></p>
        </div>
        
        <div class="flex justify-between mb-6">
            <div>
                <h2 class="font-bold">Fournisseur:</h2>
                <p id="print-fournisseur"></p>
            </div>
            <div>
                <p><strong>Date de commande:</strong> <span id="print-date-commande"></span></p>
                <p><strong>Date de livraison prévue:</strong> <span id="print-date-livraison"></span></p>
            </div>
        </div>
        
        <table class="w-full mb-6 border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2 text-left">Référence</th>
                    <th class="border p-2 text-left">Désignation</th>
                    <th class="border p-2 text-right">Quantité</th>
                    <th class="border p-2 text-right">Prix unitaire</th>
                    <th class="border p-2 text-right">Total HT</th>
                </tr>
            </thead>
            <tbody id="print-articles">
                <!-- Articles will be added here dynamically -->
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="border p-2 text-right font-bold">Total HT:</td>
                    <td id="print-total-ht" class="border p-2 text-right"></td>
                </tr>
                <tr>
                    <td colspan="4" class="border p-2 text-right font-bold">TVA (20%):</td>
                    <td id="print-total-tva" class="border p-2 text-right"></td>
                </tr>
                <tr>
                    <td colspan="4" class="border p-2 text-right font-bold">Total TTC:</td>
                    <td id="print-total-ttc" class="border p-2 text-right"></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="mt-8">
            <h3 class="font-bold mb-2">Notes:</h3>
            <p id="print-notes" class="border p-2"></p>
        </div>
        
        <div class="mt-8 flex justify-between">
            <div>
                <p class="font-bold">Signature du responsable:</p>
                <div class="h-16 w-32 border-b mt-12"></div>
            </div>
            <div>
                <p class="font-bold">Cachet de l'entreprise:</p>
                <div class="h-16 w-32 border mt-4"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to update article information when an article is selected
    function updateArticleInfo(selectElement, rowIndex) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const row = selectElement.closest('.article-row');
        
        // Get data from the selected option
        const reference = selectedOption.dataset.reference || '';
        const prix = selectedOption.dataset.prix || 0;
        
        // Update the reference field
        row.querySelector('input[name="articles[' + rowIndex + '][reference]"]').value = reference;
        
        // Update the price field
        row.querySelector('input[name="articles[' + rowIndex + '][prix_unitaire]"]').value = prix;
        
        // Calculate the row total
        calculateRowTotal(rowIndex);
    }

    // Function to calculate the total for a specific row
    function calculateRowTotal(rowIndex) {
        const row = document.querySelector('select[name="articles[' + rowIndex + '][id_article]"]').closest('.article-row');
        const quantityInput = row.querySelector('input[name="articles[' + rowIndex + '][quantite]"]');
        const priceInput = row.querySelector('input[name="articles[' + rowIndex + '][prix_unitaire]"]');
        const totalInput = row.querySelector('input[name="articles[' + rowIndex + '][total_ht]"]');
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;
        
        // Update the row total
        totalInput.value = total.toFixed(2) + ' DH';
        
        // Recalculate the grand total
        calculateGrandTotal();
    }

    // Function to calculate the grand total of all rows
    function calculateGrandTotal() {
        let grandTotal = 0;
        const totalInputs = document.querySelectorAll('input[name*="[total_ht]"]');
        
        totalInputs.forEach(input => {
            // Remove the DH suffix if present and convert to float
            const value = input.value.replace(' DH', '');
            grandTotal += parseFloat(value) || 0;
        });
        
        // Calculate TVA (20% by default)
        const tvaRate = 0.20;
        const totalTVA = grandTotal * tvaRate;
        const totalTTC = grandTotal + totalTVA;
        
        // Update the displayed totals with DH currency
        document.getElementById('total_ht_display').textContent = formatCurrency(grandTotal);
        document.getElementById('total_tva_display').textContent = formatCurrency(totalTVA);
        document.getElementById('total_ttc_display').textContent = formatCurrency(totalTTC);
        
        // Update hidden inputs for form submission
        document.getElementById('montant_total_ht').value = grandTotal.toFixed(2);
        document.getElementById('montant_total_tva').value = totalTVA.toFixed(2);
        document.getElementById('montant_total_ttc').value = totalTTC.toFixed(2);
    }

    // Function to add a new article row
    function addArticleRow() {
        const container = document.querySelector('.articles-container');
        const templateRow = container.querySelector('.article-row').cloneNode(true);
        const rowCount = container.querySelectorAll('.article-row').length;
        
        // Update names and indices
        const inputs = templateRow.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/, '[' + rowCount + ']'));
            }
            
            if (input.classList.contains('article-select')) {
                input.setAttribute('onchange', 'updateArticleInfo(this, ' + rowCount + ')');
            }
            
            if (input.getAttribute('onchange') && input.getAttribute('onchange').includes('calculateRowTotal')) {
                input.setAttribute('onchange', 'calculateRowTotal(' + rowCount + ')');
            }
            
            // Reset values
            if (!input.readOnly && input.type !== 'hidden') {
                if (input.type === 'number' && input.name.includes('quantite')) {
                    input.value = 1;
                } else {
                    input.value = '';
                }
            } else if (input.readOnly) {
                input.value = '';
            }
        });
        
        // Reset select
        const select = templateRow.querySelector('select');
        select.selectedIndex = 0;
        
        container.appendChild(templateRow);
    }

    // Function to remove an article row
    function removeArticleRow(button) {
        const rows = document.querySelectorAll('.article-row');
        if (rows.length > 1) {
            button.closest('.article-row').remove();
            calculateGrandTotal();
        }
    }

    // Format currency with DH symbol
    function formatCurrency(amount) {
        return amount.toFixed(2).replace('.', ',') + ' DH';
    }

    // Function to prepare and print the command
    function printCommand() {
        // Get form data
        const numeroCommande = document.getElementById('numero_commande').value;
        const dateCommande = document.getElementById('date_commande').value;
        const dateLivraison = document.getElementById('date_livraison_prevue').value;
        const fournisseurSelect = document.getElementById('id_fournisseur');
        const fournisseur = fournisseurSelect.options[fournisseurSelect.selectedIndex]?.text || '';
        const notes = document.querySelector('textarea[name="notes"]').value;
        
        // Format dates for display
        const formattedDateCommande = new Date(dateCommande).toLocaleDateString('fr-FR');
        const formattedDateLivraison = new Date(dateLivraison).toLocaleDateString('fr-FR');
        
        // Update print template with command info
        document.getElementById('print-numero-commande').textContent = 'N° ' + numeroCommande;
        document.getElementById('print-fournisseur').textContent = fournisseur;
        document.getElementById('print-date-commande').textContent = formattedDateCommande;
        document.getElementById('print-date-livraison').textContent = formattedDateLivraison;
        document.getElementById('print-notes').textContent = notes || 'Aucune note';
        
        // Get totals
        const totalHT = document.getElementById('total_ht_display').textContent;
        const totalTVA = document.getElementById('total_tva_display').textContent;
        const totalTTC = document.getElementById('total_ttc_display').textContent;
        
        // Update totals in print template
        document.getElementById('print-total-ht').textContent = totalHT;
        document.getElementById('print-total-tva').textContent = totalTVA;
        document.getElementById('print-total-ttc').textContent = totalTTC;
        
        // Clear and populate articles table
        const articlesContainer = document.getElementById('print-articles');
        articlesContainer.innerHTML = '';
        
        document.querySelectorAll('.article-row').forEach(row => {
            const articleSelect = row.querySelector('.article-select');
            if (articleSelect.value) {
                const articleName = articleSelect.options[articleSelect.selectedIndex].text;
                const reference = row.querySelector('input[name*="[reference]"]').value;
                const quantity = row.querySelector('input[name*="[quantite]"]').value;
                const price = row.querySelector('input[name*="[prix_unitaire]"]').value;
                const total = row.querySelector('input[name*="[total_ht]"]').value;
                
                // Create table row
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border p-2">${reference}</td>
                    <td class="border p-2">${articleName}</td>
                    <td class="border p-2 text-right">${quantity}</td>
                    <td class="border p-2 text-right">${price} DH</td>
                    <td class="border p-2 text-right">${total}</td>
                `;
                articlesContainer.appendChild(tr);
            }
        });
        
        // Open print dialog
        const printContent = document.getElementById('print-template').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Bon de Commande - ${numeroCommande}</title>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; }
                    th { background-color: #f2f2f2; text-align: left; }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                    .font-bold { font-weight: bold; }
                    @media print {
                        body { margin: 0; padding: 15px; }
                        button { display: none; }
                    }
                </style>
            </head>
            <body>
                ${printContent}
                <div class="text-center" style="margin-top: 20px;">
                    <button onclick="window.print(); setTimeout(function() { window.close(); }, 500);">Imprimer</button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Initialize event listeners when the DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Add click event for the add article button
        document.getElementById('add-article-btn').addEventListener('click', addArticleRow);
        
        // Add click event for the print button
        document.getElementById('print-btn').addEventListener('click', function(e) {
            e.preventDefault();
            printCommand();
        });
        
        // Calculate initial grand total
        calculateGrandTotal();
        
        // Initialize row totals for existing rows
        document.querySelectorAll('.article-row').forEach((row, index) => {
            calculateRowTotal(index);
        });
    });

    // Validate form before submission
    document.getElementById('commandeForm').addEventListener('submit', function(event) {
        let isValid = true;
        let hasArticles = false;
        
        // Check if supplier is selected
        const fournisseur = document.getElementById('id_fournisseur');
        if (!fournisseur.value) {
            isValid = false;
            fournisseur.classList.add('border-red-500');
            const errorMsg = document.createElement('p');
            errorMsg.className = 'mt-1 text-sm text-red-600';
            errorMsg.textContent = 'Veuillez sélectionner un fournisseur';
            
            // Only add error message if it doesn't exist already
            if (!fournisseur.parentNode.querySelector('.text-red-600')) {
                fournisseur.parentNode.appendChild(errorMsg);
            }
        } else {
            fournisseur.classList.remove('border-red-500');
            const errorMsg = fournisseur.parentNode.querySelector('.text-red-600');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
        
        // Check if at least one article is selected
        document.querySelectorAll('.article-select').forEach(select => {
            if (select.value) {
                hasArticles = true;
            }
        });
        
        if (!hasArticles) {
            isValid = false;
            const container = document.querySelector('.articles-container');
            
            // Only add error message if it doesn't exist already
            if (!container.nextElementSibling || !container.nextElementSibling.classList.contains('text-red-600')) {
                const errorMsg = document.createElement('p');
                errorMsg.className = 'mt-2 text-sm text-red-600';
                errorMsg.textContent = 'Veuillez sélectionner au moins un article';
                container.parentNode.insertBefore(errorMsg, container.nextElementSibling);
            }
        } else {
            const errorMsg = document.querySelector('.articles-container + .text-red-600');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
        
        // Validate each article row
        document.querySelectorAll('.article-row').forEach(row => {
            const articleSelect = row.querySelector('.article-select');
            
            if (articleSelect.value) {
                // Check if quantity is valid
                const quantityInput = row.querySelector('input[name*="[quantite]"]');
                if (!quantityInput.value || parseInt(quantityInput.value) < 1) {
                    isValid = false;
                    quantityInput.classList.add('border-red-500');
                    
                    // Only add error message if it doesn't exist already
                    if (!quantityInput.parentNode.querySelector('.text-red-600')) {
                        const errorMsg = document.createElement('p');
                        errorMsg.className = 'mt-1 text-sm text-red-600';
                        errorMsg.textContent = 'Quantité invalide';
                        quantityInput.parentNode.appendChild(errorMsg);
                    }
                } else {
                    quantityInput.classList.remove('border-red-500');
                    const errorMsg = quantityInput.parentNode.querySelector('.text-red-600');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
                
                // Check if price is valid
                const priceInput = row.querySelector('input[name*="[prix_unitaire]"]');
                if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
                    isValid = false;
                    priceInput.classList.add('border-red-500');
                    
                    // Only add error message if it doesn't exist already
                    if (!priceInput.parentNode.querySelector('.text-red-600')) {
                        const errorMsg = document.createElement('p');
                        errorMsg.className = 'mt-1 text-sm text-red-600';
                        errorMsg.textContent = 'Prix invalide';
                        priceInput.parentNode.appendChild(errorMsg);
                    }
                } else {
                    priceInput.classList.remove('border-red-500');
                    const errorMsg = priceInput.parentNode.querySelector('.text-red-600');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            }
        });
        
        if (!isValid) {
            event.preventDefault();
            
            // Scroll to the first error
            const firstError = document.querySelector('.border-red-500');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
</script>

<?php include $root_path . '/includes/footer.php'; ?>

