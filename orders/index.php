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
    //header('Location: login.php');
    //exit;
    $_SESSION['user_id'] = 1; // Utilisateur temporaire pour le développement
}

// Récupérer les informations de l'utilisateur actuel
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Inclure l'en-tête
include '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer la liste des fournisseurs
$fournisseurs = [];
try {
    $query = "SELECT * FROM fournisseurs ORDER BY ID_Fournisseur";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération des fournisseurs: ' . $e->getMessage();
}

// Récupérer la liste des articles
$articles = [];
try {
    $query = "SELECT * FROM articles ORDER BY designation";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération des articles: ' . $e->getMessage();
}

// Données pour les commandes
$commandes = [];
try {
    $commandesParPage = 10; // Nombre de commandes par page
    $pageCourante = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $debut = ($pageCourante - 1) * $commandesParPage;

    // Récupérer le nombre total de commandes
    $stmtTotal = $db->query("SELECT COUNT(*) FROM commandes");
    $totalCommandes = $stmtTotal->fetchColumn();
    $totalPages = ceil($totalCommandes / $commandesParPage);

    // Requête paginée
    $query = "SELECT c.ID_Commande, c.Numero_Commande, c.Date_Commande, c.Date_Livraison_Prevue, 
                     f.Nom AS fournisseur, c.Montant_Total_HT, c.Montant_Total_TTC, c.Statut
              FROM commandes c
              INNER JOIN fournisseurs f ON c.ID_Fournisseur = f.ID_Fournisseur
              ORDER BY c.Date_Commande DESC
              LIMIT :debut, :commandesParPage";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':debut', $debut, PDO::PARAM_INT);
    $stmt->bindValue(':commandesParPage', $commandesParPage, PDO::PARAM_INT);
    $stmt->execute();
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération des commandes : ' . $e->getMessage();
}
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
        <!-- Top header -->
        <div class="bg-white shadow-sm">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">Gestion des Commandes</h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container mx-auto px-6 py-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Commandes Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Total Commandes</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalCommandes; ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="#" class="text-blue-500 hover:text-blue-700 text-sm font-semibold">Voir toutes les commandes →</a>
                    </div>
                </div>

                <!-- En attente Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">En attente</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php 
                                    $enAttenteCount = 0;
                                    foreach ($commandes as $commande) {
                                        if ($commande['Statut'] === 'En attente') {
                                            $enAttenteCount++;
                                        }
                                    }
                                    echo $enAttenteCount;
                                ?>
                            </p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="#" class="text-yellow-500 hover:text-yellow-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>
                
                <!-- Livrées Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Livrées</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php 
                                    $livreeCount = 0;
                                    foreach ($commandes as $commande) {
                                        if ($commande['Statut'] === 'Livrée') {
                                            $livreeCount++;
                                        }
                                    }
                                    echo $livreeCount;
                                ?>
                            </p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="#" class="text-green-500 hover:text-green-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>
                
                <!-- Montant total Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Montant Total</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php 
                                    $montantTotal = 0;
                                    foreach ($commandes as $commande) {
                                        $montantTotal += $commande['Montant_Total_TTC'];
                                    }
                                    echo number_format($montantTotal, 2, ',', ' ') . ' €';
                                ?>
                            </p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="#" class="text-purple-500 hover:text-purple-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>
            </div>

            <!-- Commandes List -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Liste des commandes</h3>
                    <a href="create.php" class="px-4 py-2 bg-green-600 text-white rounded-md flex items-center hover:bg-green-700 transition duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Nouvelle commande
    </a>
                </div>

                <!-- Search and Filter -->
                <div class="flex flex-col md:flex-row gap-4 mb-6">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher une commande...">
                    </div>
                    <div class="flex gap-4">
                        <select class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option>Tous les statuts</option>
                            <option>En attente</option>
                            <option>En cours</option>
                            <option>Livrée</option>
                            <option>Annulée</option>
                        </select>
                        <select class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option>Tous les fournisseurs</option>
                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                <option value="<?php echo $fournisseur['ID_Fournisseur']; ?>"><?php echo htmlspecialchars($fournisseur['ID_Fournisseur']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Commande</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant HT</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant TTC</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison prévue</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($commandes as $commande): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($commande['Numero_Commande']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($commande['Date_Commande'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($commande['fournisseur']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($commande['Montant_Total_HT'], 2, ',', ' ') . ' €'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($commande['Montant_Total_TTC'], 2, ',', ' ') . ' €'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($commande['Date_Livraison_Prevue'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = '';
                                        switch ($commande['Statut']) {
                                            case 'En attente':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'En cours':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'Livrée':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'Annulée':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($commande['Statut']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewCommande(<?php echo $commande['ID_Commande']; ?>)" class="text-blue-600 hover:text-blue-900">Voir</button>
                                            <button onclick="editCommande(<?php echo $commande['ID_Commande']; ?>)" class="text-indigo-600 hover:text-indigo-900">Modifier</button>
                                            <button onclick="deleteCommande(<?php echo $commande['ID_Commande']; ?>)" class="text-red-600 hover:text-red-900">Supprimer</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex justify-between items-center mt-6">
                    <div class="text-sm text-gray-500">
                        Affichage de <span class="font-medium"><?= $debut + 1 ?></span> à 
                        <span class="font-medium"><?= min($debut + $commandesParPage, $totalCommandes) ?></span> 
                        sur <span class="font-medium"><?= $totalCommandes ?></span> résultats
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($pageCourante > 1): ?>
                            <a href="?page=<?= $pageCourante - 1 ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Précédent</a>
                        <?php else: ?>
                            <span class="px-3 py-1 border border-gray-200 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">Précédent</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($pageCourante == $i): ?>
                                <span class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-white bg-blue-500"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pageCourante < $totalPages): ?>
                            <a href="?page=<?= $pageCourante + 1 ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Suivant</a>
                        <?php else: ?>
                            <span class="px-3 py-1 border border-gray-200 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">Suivant</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nouvelle Commande -->
<div id="addCommandeModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-green-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Nouvelle commande</h3>
            <button onclick="closeModal('addCommandeModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="" method="POST" class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label for="fournisseur_id" class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                    <select id="fournisseur_id" name="fournisseur_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" required>
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach ($fournisseurs as $fournisseur): ?>
                            <option value="<?php echo $fournisseur['ID_Fournisseur']; ?>"><?php echo htmlspecialchars($fournisseur['Nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date_livraison" class="block text-sm font-medium text-gray-700 mb-1">Date de livraison prévue</label>
                    <input type="date" id="date_livraison" name="date_livraison" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" required>
                </div>
            </div>

            <h4 class="text-lg font-semibold text-gray-700 mb-3">Articles</h4>
            
            <div id="articles-container">
                <div class="article-row grid grid-cols-1 md:grid-cols-6 gap-3 mb-3 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Article</label>
                        <select name="articles[0][id]" class="article-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" required onchange="updateArticleInfo(this, 0)">
                            <option value="">Sélectionner un article</option>
                            <?php foreach ($articles as $article): ?>
                                <option value="<?php echo $article['ID_Article']; ?>" 
                                        data-reference="<?php echo htmlspecialchars($article['Reference']); ?>"
                                        data-designation="<?php echo htmlspecialchars($article['Designation']); ?>"
                                        data-prix="<?php echo $article['Prix_Achat_HT']; ?>"
                                        data-tva="<?php echo $article['Taux_TVA']; ?>">
                                    <?php echo htmlspecialchars($article['Designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Référence</label>
                        <input type="text" name="articles[0][reference]" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantité</label>
                        <input type="number" name="articles[0][quantite]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" value="1" min="1" required onchange="calculateRowTotal(0)">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prix unitaire HT</label>
                        <input type="number" name="articles[0][prix]" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" required onchange="calculateRowTotal(0)">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total HT</label>
                        <input type="text" name="articles[0][total]" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <div class="flex items-center justify-center">
                        <button type="button" class="text-red-500 hover:text-red-700" onclick="removeArticleRow(this)">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-3 mb-5">
                <button type="button" id="add-article" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    + Ajouter un article
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div></div>
                <div class="space-y-3 bg-
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
    <h3 class="text-xl font-semibold text-gray-800">Liste des commandes</h3>
    <a href="create.php" class="px-4 py-2 bg-green-600 text-white rounded-md flex items-center hover:bg-green-700 transition duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Nouvelle commande
    </a>
</div>

                    <!-- Search and Filter -->
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher une commande...">
                        </div>
                        <div class="flex gap-4">
                            <select class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option>Tous les statuts</option>
                                <option>En attente</option>
                                <option>En cours</option>
                                <option>Livrée</option>
                                <option>Annulée</option>
                            </select>
                            <select class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option>Tous les fournisseurs</option>
                                <?php foreach ($fournisseurs as $fournisseur): ?>
                                    <option value="<?php echo $fournisseur['ID_Fournisseur']; ?>"><?php echo htmlspecialchars($fournisseur['Nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Commande</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant HT</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant TTC</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison prévue</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($commandes as $commande): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($commande['Numero_Commande']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($commande['Date_Commande'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($commande['fournisseur']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($commande['Montant_Total_HT'], 2, ',', ' ') . ' €'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($commande['Montant_Total_TTC'], 2, ',', ' ') . ' €'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($commande['Date_Livraison_Prevue'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = '';
                                            switch ($commande['Statut']) {
                                                case 'En attente':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'En cours':
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'Livrée':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'Annulée':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($commande['Statut']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="viewCommande(<?php echo $commande['ID_Commande']; ?>)" class="text-blue-600 hover:text-blue-900">Voir</button>
                                                <button onclick="editCommande(<?php echo $commande['ID_Commande']; ?>)" class="text-indigo-600 hover:text-indigo-900">Modifier</button>
                                                <button onclick="deleteCommande(<?php echo $commande['ID_Commande']; ?>)" class="text-red-600 hover:text-red-900">Supprimer</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
