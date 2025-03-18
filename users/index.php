<?php
// Démarrer la session d'abord
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

// Récupérer les informations de l'utilisateur actuel
// Utilisateur temporaire pour éviter l'erreur
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Déterminer le type d'utilisateur actif (par défaut: technicien)
$user_type = isset($_GET['type']) ? $_GET['type'] : 'technicien';

// Configuration de la pagination
$items_per_page = 6;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Traitement de la recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';

if ($user_type === 'technicien') {
    if (!empty($search)) {
        $search_condition = " WHERE (
            nom LIKE :search OR 
            prenom LIKE :search OR 
            specialite LIKE :search
        )";
    }

    // Récupérer le nombre total de techniciens
    $count_query = "SELECT COUNT(*) as total FROM technicien" . $search_condition;

    $count_stmt = $db->prepare($count_query);
    if (!empty($search)) {
        $search_param = "%$search%";
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_result['total'];
    $total_pages = ceil($total_items / $items_per_page);

    // Récupérer les techniciens pour la page courante
    $query = "SELECT id, prenom, nom, date_naissance, specialite
              FROM technicien" . $search_condition . "
              ORDER BY nom, prenom
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques pour les techniciens
    $stats = [
        'total' => $total_items,
        'specialites' => [],
        'age_moyen' => 0,
        'plus_jeune' => 0,
        'plus_age' => 0
    ];

    // Récupérer les statistiques
    try {
        // Compter les spécialités
        $spec_query = "SELECT specialite, COUNT(*) as count FROM technicien GROUP BY specialite ORDER BY count DESC LIMIT 4";
        $spec_stmt = $db->prepare($spec_query);
        $spec_stmt->execute();
        $stats['specialites'] = $spec_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer l'âge moyen
        $age_query = "SELECT AVG(TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())) as age_moyen FROM technicien";
        $age_stmt = $db->prepare($age_query);
        $age_stmt->execute();
        $age_result = $age_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['age_moyen'] = round($age_result['age_moyen']);
        
        // Trouver le plus jeune et le plus âgé
        $min_max_query = "SELECT 
                            MIN(TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())) as plus_jeune,
                            MAX(TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())) as plus_age 
                          FROM technicien";
        $min_max_stmt = $db->prepare($min_max_query);
        $min_max_stmt->execute();
        $min_max_result = $min_max_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['plus_jeune'] = $min_max_result['plus_jeune'];
        $stats['plus_age'] = $min_max_result['plus_age'];
    } catch (PDOException $e) {
        // Gérer l'erreur silencieusement
    }
} else if ($user_type === 'client') {
    // Logique pour les clients
    // Exemple similaire à celui des techniciens
    if (!empty($search)) {
        $search_condition = " WHERE (
            nom LIKE :search OR 
            prenom LIKE :search OR 
            email LIKE :search
        )";
    }

    // Récupérer le nombre total de clients (exemple fictif)
    $count_query = "SELECT COUNT(*) as total FROM clients" . $search_condition;
    
    $count_stmt = $db->prepare($count_query);
    if (!empty($search)) {
        $search_param = "%$search%";
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_result['total'] ?? 0;
    $total_pages = ceil($total_items / $items_per_page);
    
    // Ici vous récupéreriez les clients depuis votre base de données
    // Pour l'exemple, nous utilisons un tableau vide
    $items = [];
    $stats = [
        'total' => $total_items,
        'nouveaux' => 0,
        'actifs' => 0
    ];
}

// Inclure l'en-tête
include '../includes/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
        <!-- Top header -->
        <div class="bg-white shadow-sm">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <?php if ($user_type === 'technicien'): ?>
                        Gestion des Techniciens
                    <?php elseif ($user_type === 'client'): ?>
                        Gestion des Clients
                    <?php else: ?>
                        Gestion des Utilisateurs
                    <?php endif; ?>
                </h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white border-b border-gray-200">
            <div class="container mx-auto px-6">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <a href="?type=technicien" class="<?php echo $user_type === 'technicien' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Techniciens
                    </a>
                    <a href="?type=client" class="<?php echo $user_type === 'client' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Clients
                    </a>
                    <a href="?type=admin" class="<?php echo $user_type === 'admin' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Administrateurs
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container mx-auto px-6 py-8">
            <?php if ($user_type === 'technicien'): ?>
                <!-- Statistics Cards pour Techniciens -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Techniciens Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Total Techniciens</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?php echo $stats['total']; ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-blue-500 hover:text-blue-700 text-sm font-semibold">Voir tous les techniciens →</a>
                        </div>
                    </div>

                    <!-- Spécialités Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Spécialités</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?php echo count($stats['specialites']); ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-yellow-500 hover:text-yellow-700 text-sm font-semibold">Voir les détails →</a>
                        </div>
                    </div>

                    <!-- Âge moyen Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Âge moyen</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['age_moyen']; ?> ans</p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-red-500 hover:text-red-700 text-sm font-semibold">Voir les statistiques →</a>
                        </div>
                    </div>

                    <!-- Expérience Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Tranches d'âge</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['plus_jeune']; ?>-<?php echo $stats['plus_age']; ?> ans</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-green-500 hover:text-green-700 text-sm font-semibold">Voir les statistiques →</a>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Quick Actions Card -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Actions rapides</h3>
                        <div class="space-y-4">
                            <a href="#" onclick="openModal('addTechnicienModal'); return false;" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                                <div class="bg-blue-100 p-2 rounded-full mr-4">
                                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Ajouter un technicien</h4>
                                    <p class="text-sm text-gray-600">Enregistrer un nouveau technicien</p>
                                </div>
                            </a>
                            <a href="#" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                                <div class="bg-green-100 p-2 rounded-full mr-4">
                                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Exporter les données</h4>
                                    <p class="text-sm text-gray-600">Exporter en CSV ou Excel</p>
                                </div>
                            </a>
                            <a href="#" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                                <div class="bg-purple-100 p-2 rounded-full mr-4">
                                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Imprimer la liste</h4>
                                    <p class="text-sm text-gray-600">Générer un rapport imprimable</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Spécialités Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 col-span-2">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Répartition des spécialités</h3>
                        <div class="space-y-4">
                            <?php foreach ($stats['specialites'] as $specialite): ?>
                                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                    <div class="bg-indigo-100 p-2 rounded-full mr-4">
                                        <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($specialite['specialite']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo $specialite['count']; ?> technicien(s)</p>
                                    </div>
                                    <a href="?type=technicien&specialite=<?php echo urlencode($specialite['specialite']); ?>" class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-md text-sm font-medium hover:bg-indigo-200 transition duration-200">Voir</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Techniciens List -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-800">Liste des techniciens</h3>
                        <a href="#" onclick="openModal('addTechnicienModal'); return false;" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Ajouter un technicien
                        </a>
                    </div>

                    <!-- Search and Filter -->
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="relative flex-1">
                            <form action="" method="GET">
                                <input type="hidden" name="type" value="technicien">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher un technicien...">
                            </form>
                        </div>
                        <div class="flex gap-4">
                            <select class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="if(this.value) window.location.href='?type=technicien&specialite='+this.value;">
                                <option value="">Toutes les spécialités</option>
                                <?php foreach ($stats['specialites'] as $specialite): ?>
                                    <option value="<?php echo htmlspecialchars($specialite['specialite']); ?>" <?php echo (isset($_GET['specialite']) && $_GET['specialite'] === $specialite['specialite']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($specialite['specialite']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technicien</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date de naissance</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Âge</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spécialité</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            Aucun technicien trouvé
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $technicien): 
                                        // Calculer l'âge à partir de la date de naissance
                                        $dateNaissance = new DateTime($technicien['date_naissance']);
                                        $aujourdhui = new DateTime();
                                        $age = $dateNaissance->diff($aujourdhui)->y;
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold">
                                                            <?php echo substr($technicien['prenom'], 0, 1) . substr($technicien['nom'], 0, 1); ?>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($technicien['prenom'] . ' ' . $technicien['nom']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($technicien['date_naissance'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $age; ?> ans
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    <?php echo htmlspecialchars($technicien['specialite']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex justify-end space-x-2">
                                                    <button onclick="viewTechnicien(<?php echo $technicien['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </button>
                                                    <button onclick="editTechnicien(<?php echo $technicien['id']; ?>)" class="text-indigo-600 hover:text-indigo-900">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
                                                    <button onclick="deleteTechnicien(<?php echo $technicien['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex justify-between items-center mt-6">
                            <div class="text-sm text-gray-500">
                                Affichage de <span class="font-medium">
                                    <?php                                 
                                    $start_count = $total_items > 0 ? $offset + 1 : 0;
                                    $end_count = min($total_items, $offset + count($items));
                                    echo $start_count . '-' . $end_count;
                                    ?>
                                </span> 
                                sur <span class="font-medium"><?php echo $total_items; ?></span> résultats
                            </div>
                            <div class="flex space-x-1">
                                <?php if ($current_page > 1): ?>
                                    <a href="?type=technicien&page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Précédent
                                    </a>
                                <?php else: ?>
                                    <button class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-white cursor-not-allowed" disabled>
                                        Précédent
                                    </button>
                                <?php endif; ?>
                                
                                <?php
                                    // Afficher les numéros de page
                                    $max_visible_pages = 3; // Nombre maximum de boutons de page à afficher
                                    
                                    // Calculer les pages à afficher
                                    if ($total_pages <= $max_visible_pages) {
                                        $start_page = 1;
                                        $end_page = $total_pages;
                                    } else {
                                        $start_page = max(1, min($current_page - 1, $total_pages - $max_visible_pages + 1));
                                        $end_page = min($start_page + $max_visible_pages - 1, $total_pages);
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <?php if ($i == $current_page): ?>
                                        <button class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-white bg-blue-500 hover:bg-blue-600">
                                            <?php echo $i; ?>
                                        </button>
                                    <?php else: ?>
                                        <a href="?type=technicien&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?type=technicien&page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Suivant
                                    </a>
                                <?php else: ?>
                                    <button class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-white cursor-not-allowed" disabled>
                                        Suivant
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($user_type === 'client'): ?>
                <!-- Content pour Clients -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-800">Liste des clients</h3>
                        <a href="#" onclick="openModal('addClientModal'); return false;" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Ajouter un client
                        </a>
                    </div>

                    <!-- Search and Filter -->
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="relative flex-1">
                            <form action="" method="GET">
                                <input type="hidden" name="type" value="client">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher un client...">
                            </form>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        Aucun client trouvé
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($user_type === 'admin'): ?>
                <!-- Content pour Administrateurs -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-800">Liste des administrateurs</h3>
                        <a href="#" onclick="openModal('addAdminModal'); return false;" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Ajouter un administrateur
                        </a>
                    </div>

                    <!-- Search -->
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="relative flex-1">
                            <form action="" method="GET">
                                <input type="hidden" name="type" value="admin">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher un administrateur...">
                            </form>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Administrateur</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        Aucun administrateur trouvé
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Technicien Modal -->
<div id="addTechnicienModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-indigo-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Ajouter un technicien</h3>
            <button onclick="closeModal('addTechnicienModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="add_technicien.php" method="POST" class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="nom" name="nom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" id="prenom" name="prenom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="date_naissance" class="block text-sm font-medium text-gray-700 mb-1">Date de naissance <span class="text-red-500">*</span></label>
                    <input type="date" id="date_naissance" name="date_naissance" max="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="specialite" class="block text-sm font-medium text-gray-700 mb-1">Spécialité <span class="text-red-500">*</span></label>
                    <input type="text" id="specialite" name="specialite" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addTechnicienModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Technicien Modal -->
<div id="viewTechnicienModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-semibold text-gray-900">Détails du technicien</h3>
            <button onclick="closeModal('viewTechnicienModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-6">
            <div class="flex flex-col md:flex-row">
                <!-- Informations du technicien -->
                <div class="md:w-1/2 p-4">
                    <h2 class="text-xl font-semibold mb-6 text-gray-800">Informations personnelles</h2>
                    
                    <div class="mb-10 flex items-center">
                        <div class="h-20 w-20 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-2xl font-bold" id="view_initials">
                            <!-- Initiales du technicien -->
                        </div>
                        <div class="ml-6">
                            <h3 class="text-lg font-semibold" id="view_fullname"><!-- Nom complet --></h3>
                            <p class="text-gray-600" id="view_specialite"><!-- Spécialité --></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Nom</p>
                            <p class="font-medium" id="view_nom"><!-- Nom --></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Prénom</p>
                            <p class="font-medium" id="view_prenom"><!-- Prénom --></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Date de naissance</p>
                            <p class="font-medium" id="view_date_naissance"><!-- Date de naissance --></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Âge</p>
                            <p class="font-medium" id="view_age"><!-- Âge --></p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-500">Spécialité</p>
                            <p class="font-medium" id="view_specialite_detail"><!-- Spécialité --></p>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques et informations complémentaires -->
                <div class="md:w-1/2 p-4 md:border-l border-gray-200">
                    <h2 class="text-xl font-semibold mb-6 text-gray-800">Statistiques</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Interventions réalisées</p>
                            <p class="text-2xl font-bold text-indigo-600">0</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Interventions en cours</p>
                            <p class="text-2xl font-bold text-yellow-600">0</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Taux de satisfaction</p>
                            <p class="text-2xl font-bold text-green-600">-</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Temps moyen d'intervention</p>
                            <p class="text-2xl font-bold text-blue-600">-</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <h3 class="font-semibold mb-2">Compétences</h3>
                        <div class="flex flex-wrap gap-2" id="view_competences">
                            <!-- Compétences du technicien -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="mt-6 pt-6 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('viewTechnicienModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Fermer
                </button>
                <button type="button" onclick="closeModal('viewTechnicienModal'); editTechnicien(currentTechnicienId);" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Modifier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Technicien Modal -->
<div id="editTechnicienModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-indigo-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Modifier le technicien</h3>
            <button onclick="closeModal('editTechnicienModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="update_technicien.php" method="POST" class="p-5">
            <input type="hidden" id="edit_id" name="id" value="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="edit_nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_nom" name="nom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="edit_prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_prenom" name="prenom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="edit_date_naissance" class="block text-sm font-medium text-gray-700 mb-1">Date de naissance <span class="text-red-500">*</span></label>
                    <input type="date" id="edit_date_naissance" name="date_naissance" max="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="edit_specialite" class="block text-sm font-medium text-gray-700 mb-1">Spécialité <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_specialite" name="specialite" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('editTechnicienModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Technicien Modal -->
<div id="deleteTechnicienModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center mb-4">
                <div class="bg-red-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-semibold text-center text-gray-900 mb-4">Confirmer la suppression</h3>
            <p class="text-gray-700 text-center mb-6">Êtes-vous sûr de vouloir supprimer ce technicien ? Cette action est irréversible.</p>
            <div class="flex justify-center space-x-4">
                <button type="button" onclick="closeModal('deleteTechnicienModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <form id="deleteTechnicienForm" action="delete_technicien.php" method="POST">
                    <input type="hidden" id="delete_id" name="id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Client Modal -->
<div id="addClientModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-indigo-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Ajouter un client</h3>
            <button onclick="closeModal('addClientModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="add_client.php" method="POST" class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="client_nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="client_nom" name="nom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="client_prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" id="client_prenom" name="prenom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="client_email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="client_email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="client_telephone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone <span class="text-red-500">*</span></label>
                    <input type="tel" id="client_telephone" name="telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div class="md:col-span-2">
                    <label for="client_adresse" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <textarea id="client_adresse" name="adresse" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addClientModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Admin Modal -->
<div id="addAdminModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-indigo-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Ajouter un administrateur</h3>
            <button onclick="closeModal('addAdminModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="add_admin.php" method="POST" class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="admin_nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="admin_nom" name="nom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="admin_prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" id="admin_prenom" name="prenom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="admin_email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="admin_role" class="block text-sm font-medium text-gray-700 mb-1">Rôle <span class="text-red-500">*</span></label>
                    <select id="admin_role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">Sélectionner un rôle</option>
                        <option value="admin">Administrateur</option>
                        <option value="super_admin">Super Administrateur</option>
                    </select>
                </div>
                <div>
                    <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="admin_password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label for="admin_password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="admin_password_confirm" name="password_confirm" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addAdminModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Variable pour stocker l'ID du technicien actuellement sélectionné
    let currentTechnicienId = null;

    // Fonction pour ouvrir un modal
    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    // Fonction pour fermer un modal
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    // Fonction pour afficher les détails d'un technicien
    function viewTechnicien(id) {
        currentTechnicienId = id;
        
        // Faire une requête AJAX pour récupérer les détails du technicien
        fetch(`get_technicien.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const technicien = data.technicien;
                    
                    // Remplir les champs du modal avec les données récupérées
                    document.getElementById('view_initials').textContent = technicien.prenom.charAt(0) + technicien.nom.charAt(0);
                    document.getElementById('view_fullname').textContent = technicien.prenom + ' ' + technicien.nom;
                    document.getElementById('view_specialite').textContent = technicien.specialite;
                    document.getElementById('view_nom').textContent = technicien.nom;
                    document.getElementById('view_prenom').textContent = technicien.prenom;
                    
                    // Formater la date de naissance
                    const dateNaissance = new Date(technicien.date_naissance);
                    const formattedDate = dateNaissance.toLocaleDateString('fr-FR');
                    document.getElementById('view_date_naissance').textContent = formattedDate;
                    
                    // Calculer l'âge
                    const today = new Date();
                    let age = today.getFullYear() - dateNaissance.getFullYear();
                    const m = today.getMonth() - dateNaissance.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < dateNaissance.getDate())) {
                        age--;
                    }
                    document.getElementById('view_age').textContent = age + ' ans';
                    
                    document.getElementById('view_specialite_detail').textContent = technicien.specialite;
                    
                    // Afficher les compétences (basées sur la spécialité)
                    const competencesContainer = document.getElementById('view_competences');
                    competencesContainer.innerHTML = '';
                    
                    const competences = technicien.specialite.split(',');
                    competences.forEach(competence => {
                        const span = document.createElement('span');
                        span.className = 'px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm';
                        span.textContent = competence.trim();
                        competencesContainer.appendChild(span);
                    });
                    
                    // Afficher le modal
                    openModal('viewTechnicienModal');
                } else {
                    alert('Erreur lors de la récupération des données du technicien');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la récupération des données');
            });
    }

    // Fonction pour éditer un technicien
    function editTechnicien(id) {
        currentTechnicienId = id;
        
        // Faire une requête AJAX pour récupérer les détails du technicien
        fetch(`get_technicien.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const technicien = data.technicien;
                    
                    // Remplir les champs du formulaire avec les données récupérées
                    document.getElementById('edit_id').value = technicien.id;
                    document.getElementById('edit_nom').value = technicien.nom;
                    document.getElementById('edit_prenom').value = technicien.prenom;
                    document.getElementById('edit_date_naissance').value = technicien.date_naissance;
                    document.getElementById('edit_specialite').value = technicien.specialite;
                    
                    // Afficher le modal
                    openModal('editTechnicienModal');
                } else {
                    alert('Erreur lors de la récupération des données du technicien');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la récupération des données');
            });
    }

    // Fonction pour supprimer un technicien
    function deleteTechnicien(id) {
        document.getElementById('delete_id').value = id;
        openModal('deleteTechnicienModal');
    }

    // Fermer les modals en cliquant à l'extérieur
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.fixed.inset-0');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
    }
</script>

<?php include '../includes/footer.php'; ?>


