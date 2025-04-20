<?php
// Démarrer la session
session_start();

// Mode debug
$debug = true;
$debugInfo = [];

// Chemin racine de l'application
$root_path = dirname(__DIR__);
if ($debug) $debugInfo[] = "Root path: " . $root_path;

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
    if ($debug) $debugInfo[] = "Database config loaded";
} else {
    if ($debug) $debugInfo[] = "WARNING: Database config file not found!";
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
    if ($debug) $debugInfo[] = "Functions file loaded";
} else {
    if ($debug) $debugInfo[] = "WARNING: Functions file not found!";
}

// Vérifier si l'utilisateur est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
    if ($debug) $debugInfo[] = "Created test user with ID: 1";
}

// Utilisateur temporaire pour éviter l'erreur
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];
if ($debug) $debugInfo[] = "Current user: " . $currentUser['name'] . " (" . $currentUser['role'] . ")";

// Créer une connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $perPage;

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$station = isset($_GET['station']) ? $_GET['station'] : '';

// Requête SQL de base
$query = "SELECT v.*, c.nom as categorie_nom 
          FROM voitures v 
          LEFT JOIN categorie c ON v.id_categorie = c.id
          WHERE 1=1";

// Ajouter les conditions de recherche et filtrage si nécessaire
$params = [];

if (!empty($search)) {
    $query .= " AND (v.numero_immatriculation LIKE :search OR v.marque LIKE :search OR v.modele LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND v.id_categorie = :category";
    $params[':category'] = $category;
}

if (!empty($status)) {
    $query .= " AND v.statut = :status";
    $params[':status'] = $status;
}

if (!empty($brand)) {
    $query .= " AND v.marque = :brand";
    $params[':brand'] = $brand;
}

if (!empty($station)) {
    $query .= " AND v.station = :station";
    $params[':station'] = $station;
}

// Requête pour le nombre total de résultats (pour la pagination)
$countQuery = str_replace("SELECT v.*, c.nom as categorie_nom", "SELECT COUNT(*) as total", $query);
$stmtCount = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value);
}
$stmtCount->execute();
$totalRows = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $perPage);

// Ajouter l'ordre et la pagination à la requête principale
$query .= " ORDER BY v.date_creation DESC LIMIT :offset, :perPage";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$voitures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories pour le filtre
$queryCategories = "SELECT id, nom FROM categorie ORDER BY nom";
$stmtCategories = $db->prepare($queryCategories);
$stmtCategories->execute();
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les marques pour le filtre
$queryBrands = "SELECT DISTINCT marque FROM voitures ORDER BY marque";
$stmtBrands = $db->prepare($queryBrands);
$stmtBrands->execute();
$brands = $stmtBrands->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les stations pour le filtre
$queryStations = "SELECT DISTINCT station FROM voitures ORDER BY station";
$stmtStations = $db->prepare($queryStations);
$stmtStations->execute();
$stations = $stmtStations->fetchAll(PDO::FETCH_COLUMN);

// Afficher les messages de débogage si activé
if ($debug) {
    echo '<div style="position: fixed; bottom: 0; right: 0; z-index: 9999; background: rgba(0,0,0,0.8); color: lime; font-family: monospace; font-size: 12px; padding: 10px; max-width: 50%; max-height: 50%; overflow: auto;">';
    echo '<h3>Debug Info:</h3>';
    echo '<ul>';
    foreach ($debugInfo as $info) {
        echo '<li>' . $info . '</li>';
    }
    echo '</ul>';
    echo '<p>SQL Query: ' . $query . '</p>';
    echo '<p>Total rows: ' . $totalRows . '</p>';
    echo '</div>';
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Liste des Véhicules</h2>
                    <p class="mt-1 text-sm text-gray-600">Gérez votre flotte de véhicules</p>
                </div>
                <div>
                    <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none">
                        <i class="fas fa-plus mr-2"></i>Ajouter un véhicule
                    </a>
                </div>
            </div>

            <!-- Affichage des messages flash s'il y en a -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['flash_message']['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $_SESSION['flash_message']['message']; ?>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <!-- Filtres et recherche -->
            <div class="bg-white shadow rounded-lg mb-6 p-4">
                <form action="index.php" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500" placeholder="Immatriculation, marque, modèle...">
                        </div>
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                            <select id="category" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                            <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Tous les statuts</option>
                                <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Disponible</option>
                                <option value="unavailable" <?php echo $status == 'unavailable' ? 'selected' : ''; ?>>Indisponible</option>
                                <option value="sold" <?php echo $status == 'sold' ? 'selected' : ''; ?>>Vendu</option>
                            </select>
                        </div>
                        <div>
                            <label for="brand" class="block text-sm font-medium text-gray-700 mb-1">Marque</label>
                            <select id="brand" name="brand" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Toutes les marques</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?php echo $b; ?>" <?php echo $brand == $b ? 'selected' : ''; ?>>
                                        <?php echo strtoupper(htmlspecialchars($b)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <a href="index.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 mr-2">
                            Réinitialiser
                        </a>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                            Filtrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tableau des véhicules -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Immatriculation
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Marque / Modèle
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Catégorie
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Statut
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Station
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kilométrage
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date d'ajout
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($voitures) > 0): ?>
                                <?php foreach ($voitures as $voiture): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($voiture['numero_immatriculation']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo strtoupper(htmlspecialchars($voiture['marque'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($voiture['modele']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($voiture['categorie_nom'] ?? 'Non défini'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $statusText = 'Inconnu';
                                            
                                            switch ($voiture['statut']) {
                                                case 'available':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    $statusText = 'Disponible';
                                                    break;
                                                case 'unavailable':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $statusText = 'Indisponible';
                                                    break;
                                                case 'sold':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    $statusText = 'Vendu';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php 
                                                $stationText = 'Non défini';
                                                switch ($voiture['station']) {
                                                    case 'casablanca_airport':
                                                        $stationText = 'Casablanca Aéroport';
                                                        break;
                                                    case 'casablanca_downtown':
                                                        $stationText = 'Casablanca Centre-ville';
                                                        break;
                                                    case 'rabat':
                                                        $stationText = 'Rabat Centre';
                                                        break;
                                                    case 'marrakech':
                                                        $stationText = 'Marrakech Aéroport';
                                                        break;
                                                }
                                                echo htmlspecialchars($stationText);
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo number_format($voiture['kilometres'], 0, ',', ' '); ?> km
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('d/m/Y', strtotime($voiture['date_creation'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $voiture['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $voiture['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="confirmDelete(<?php echo $voiture['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                        Aucun véhicule trouvé. <a href="create.php" class="text-primary-600 hover:underline">Ajouter un véhicule</a>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>&brand=<?php echo urlencode($brand); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Précédent
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>&brand=<?php echo urlencode($brand); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Suivant
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à <span class="font-medium"><?php echo min($offset + $perPage, $totalRows); ?></span> sur <span class="font-medium"><?php echo $totalRows; ?></span> résultats
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>&brand=<?php echo urlencode($brand); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Précédent</span>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($startPage + 4, $totalPages);
                                    
                                    if ($startPage > 1) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isActive = $i == $page;
                                        $class = $isActive 
                                            ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' 
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
                                        
                                        echo '<a href="?page=' . $i . '&per_page=' . $perPage . '&search=' . urlencode($search) . '&category=' . urlencode($category) . '&status=' . urlencode($status) . '&brand=' . urlencode($brand) . '" class="relative inline-flex items-center px-4 py-2 border ' . $class . ' text-sm font-medium">' . $i . '</a>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>&brand=<?php echo urlencode($brand); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Suivant</span>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Supprimer le véhicule</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Êtes-vous sûr de vouloir supprimer ce véhicule ? Cette action est irréversible.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a href="#" id="confirmDelete" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Supprimer
                </a>
                <button type="button" onclick="hideModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('confirmDelete').href = 'delete.php?id=' + id;
    }
    
    function hideModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
</script>

<?php include $root_path . '/includes/footer.php'; ?>