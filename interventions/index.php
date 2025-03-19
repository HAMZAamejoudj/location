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

// Récupération des catégories pour le formulaire d'articles
function getCategories($db) {
    try {
        $query = "SELECT id, nom FROM categorie ORDER BY nom ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des catégories: ' . $e->getMessage());
        return [];
    }
}

$categories = getCategories($db);

// Récupération des techniciens
function getTechniciens($db) {
    try {
        $query = "SELECT t.id, CONCAT(t.prenom, ' ', t.nom) AS nom_complet, t.specialite 
                  FROM technicien t 
                  ORDER BY t.nom ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des techniciens: ' . $e->getMessage());
        return [];
    }
}

$techniciens = getTechniciens($db);

// Récupération des véhicules
$vehicules = [];
try {
    $query = "SELECT v.*, CONCAT(c.nom, ' ', c.prenom) AS client_nom
              FROM vehicules v
              INNER JOIN clients c ON v.client_id = c.id
              ORDER BY v.marque, v.modele";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération des véhicules: ' . $e->getMessage();
}

// Données pour les interventions
$interventions = [];
try {
    // Paramètres de filtrage et pagination
    $statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $interventionsParPage = 10; // Nombre d'interventions par page
    $pageCourante = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $debut = ($pageCourante - 1) * $interventionsParPage;

    // Construction de la requête avec filtres
    $where_clauses = [];
    $params = [];
    
    if (!empty($statut_filter)) {
        $where_clauses[] = "i.statut = :statut";
        $params[':statut'] = $statut_filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(v.immatriculation LIKE :search OR c.nom LIKE :search OR c.prenom LIKE :search OR i.description LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Récupérer le nombre total d'interventions avec filtres
    $query_count = "SELECT COUNT(*) 
                    FROM interventions i
                    INNER JOIN vehicules v ON i.vehicule_id = v.id
                    INNER JOIN clients c ON v.client_id = c.id
                    LEFT JOIN technicien t ON i.technicien_id = t.id
                    $where_sql";
    
    $stmt_count = $db->prepare($query_count);
    foreach ($params as $key => $value) {
        $stmt_count->bindValue($key, $value);
    }
    $stmt_count->execute();
    $totalInterventions = $stmt_count->fetchColumn();
    $totalPages = ceil($totalInterventions / $interventionsParPage);

    // Requête paginée avec filtres
    $query = "SELECT i.id, i.date_creation, i.date_prevue, i.date_debut, i.date_fin, 
                    i.description, i.diagnostique, i.kilometrage, i.statut, i.commentaire,
                    v.immatriculation, CONCAT(v.marque, ' ', v.modele) AS vehicule_info,
                    CONCAT(c.nom, ' ', c.prenom) AS client, 
                    CONCAT(t.prenom, ' ', t.nom) AS technicien,
                    t.specialite AS technicien_specialite
              FROM interventions i
              INNER JOIN vehicules v ON i.vehicule_id = v.id
              INNER JOIN clients c ON v.client_id = c.id
              LEFT JOIN technicien t ON i.technicien_id = t.id
              $where_sql
              ORDER BY i.date_creation DESC
              LIMIT :debut, :interventionsParPage";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':debut', $debut, PDO::PARAM_INT);
    $stmt->bindValue(':interventionsParPage', $interventionsParPage, PDO::PARAM_INT);
    $stmt->execute();
    $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Comptage par statut pour les statistiques
    $query_stats = "SELECT statut, COUNT(*) as count FROM interventions GROUP BY statut";
    $stmt_stats = $db->query($query_stats);
    $stats = [];
    while ($row = $stmt_stats->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['statut']] = $row['count'];
    }
    
    // Valeurs par défaut si certains statuts n'existent pas encore
    $statuts = ['En attente', 'En cours', 'Terminée', 'Facturée', 'Annulée'];
    foreach ($statuts as $statut) {
        if (!isset($stats[$statut])) {
            $stats[$statut] = 0;
        }
    }
    
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération des interventions : ' . $e->getMessage();
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
                <h1 class="text-2xl font-semibold text-gray-800">Gestion des Interventions</h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container mx-auto px-6 py-8">
            <!-- Notifications -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                    <p><?php echo $_SESSION['success']; ?></p>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    <p><?php echo $_SESSION['error']; ?></p>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <!-- Total Interventions Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Total</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalInterventions; ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?statut=" class="text-blue-500 hover:text-blue-700 text-sm font-semibold">Voir toutes →</a>
                    </div>
                </div>

                <!-- En attente Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">En attente</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['En attente']; ?></p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?statut=En+attente" class="text-yellow-500 hover:text-yellow-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>

                <!-- En cours Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">En cours</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['En cours']; ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?statut=En+cours" class="text-blue-500 hover:text-blue-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>

                <!-- Terminée Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Terminée</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['Terminée']; ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?statut=Terminée" class="text-green-500 hover:text-green-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>

                <!-- Facturée Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Facturée</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['Facturée']; ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?statut=Facturée" class="text-purple-500 hover:text-purple-700 text-sm font-semibold">Voir les détails →</a>
                    </div>
                </div>
            </div>

            <!-- Interventions List -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Liste des interventions</h3>
                    <button onclick="openModal('addInterventionModal')" class="px-4 py-2 bg-green-600 text-white rounded-md flex items-center hover:bg-green-700 transition duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Ajouter une intervention
                    </button>
                </div>

                <!-- Search and Filter -->
                <div class="flex flex-col md:flex-row gap-4 mb-6">
                    <form action="" method="GET" class="flex flex-col md:flex-row gap-4 w-full">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher une intervention...">
                        </div>
                        <div class="flex gap-4">
                            <select name="statut" class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tous les statuts</option>
                                <option value="En attente" <?php echo $statut_filter === 'En attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="En cours" <?php echo $statut_filter === 'En cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="Terminée" <?php echo $statut_filter === 'Terminée' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="Facturée" <?php echo $statut_filter === 'Facturée' ? 'selected' : ''; ?>>Facturée</option>
                                <option value="Annulée" <?php echo $statut_filter === 'Annulée' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Filtrer
                            </button>
                            <?php if (!empty($search) || !empty($statut_filter)): ?>
                                <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                    Réinitialiser
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date création</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date prévue</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client/Véhicule</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technicien</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($interventions)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">Aucune intervention trouvée</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($interventions as $intervention): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $intervention['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($intervention['date_creation'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $intervention['date_prevue'] ? date('d/m/Y', strtotime($intervention['date_prevue'])) : '-'; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                            <?php echo htmlspecialchars(substr($intervention['description'], 0, 50)) . (strlen($intervention['description']) > 50 ? '...' : ''); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div><?php echo htmlspecialchars($intervention['client']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($intervention['immatriculation']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($intervention['technicien']): ?>
                                                <div class="text-gray-700"><?php echo htmlspecialchars($intervention['technicien']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($intervention['technicien_specialite']); ?></div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Non assigné</span>
                                                <button onclick="assignTechnicien(<?php echo $intervention['id']; ?>)" class="ml-2 text-xs text-blue-500 hover:text-blue-700">
                                                    Assigner
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = '';
                                            switch ($intervention['statut']) {
                                                case 'En attente':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'En cours':
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'Terminée':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'Facturée':
                                                    $statusClass = 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'Annulée':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $intervention['statut']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="viewIntervention(<?php echo $intervention['id']; ?>)" class="text-blue-600 hover:text-blue-900">Voir</button>
                                                <button onclick="editIntervention(<?php echo $intervention['id']; ?>)" class="text-indigo-600 hover:text-indigo-900">Modifier</button>
                                                <button onclick="deleteIntervention(<?php echo $intervention['id']; ?>)" class="text-red-600 hover:text-red-900">Supprimer</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-between items-center mt-6">
                        <div class="text-sm text-gray-500">
                            Affichage de <span class="font-medium"><?= $debut + 1 ?></span> à 
                            <span class="font-medium"><?= min($debut + $interventionsParPage, $totalInterventions) ?></span> 
                            sur <span class="font-medium"><?= $totalInterventions ?></span> résultats
                        </div>
                        <div class="flex space-x-1">
                        <?php 
                            // Construire l'URL de base pour la pagination en conservant les filtres
                            $url_params = [];
                            if (!empty($search)) $url_params[] = "search=" . urlencode($search);
                            if (!empty($statut_filter)) $url_params[] = "statut=" . urlencode($statut_filter);
                            $url_base = "?" . implode("&", $url_params);
                            $url_base = !empty($url_base) ? $url_base . "&" : "?";
                            ?>
                            
                            <?php if ($pageCourante > 1): ?>
                                <a href="<?= $url_base ?>page=<?= $pageCourante - 1 ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Précédent</a>
                            <?php else: ?>
                                <span class="px-3 py-1 border border-gray-200 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">Précédent</span>
                            <?php endif; ?>

                            <?php 
                            // Afficher un nombre limité de pages avec ellipses
                            $range = 2; // Nombre de pages à afficher de chaque côté de la page courante
                            
                            for ($i = 1; $i <= $totalPages; $i++): 
                                // Afficher la première page, la dernière page, et les pages autour de la page courante
                                if ($i == 1 || $i == $totalPages || ($i >= $pageCourante - $range && $i <= $pageCourante + $range)):
                            ?>
                                <?php if ($pageCourante == $i): ?>
                                    <span class="px-3 py-1 border border-blue-500 rounded-md text-sm font-medium text-white bg-blue-500"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= $url_base ?>page=<?= $i ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><?= $i ?></a>
                                <?php endif; ?>
                            <?php 
                                // Ajouter des ellipses
                                elseif (($i == 2 && $pageCourante - $range > 2) || ($i == $totalPages - 1 && $pageCourante + $range < $totalPages - 1)): 
                            ?>
                                <span class="px-2 py-1 text-gray-500">...</span>
                            <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($pageCourante < $totalPages): ?>
                                <a href="<?= $url_base ?>page=<?= $pageCourante + 1 ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Suivant</a>
                            <?php else: ?>
                                <span class="px-3 py-1 border border-gray-200 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">Suivant</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Intervention Modal -->
<div id="addInterventionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Ajouter une intervention</h3>
            <button onclick="closeModal('addInterventionModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="addInterventionForm" action="create.php" method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="vehicule_id" class="block text-sm font-medium text-gray-700 mb-1">Véhicule <span class="text-red-500">*</span></label>
                    <select id="vehicule_id" name="vehicule_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                        <option value="">Sélectionner un véhicule</option>
                        <?php foreach ($vehicules as $vehicule): ?>
                            <option value="<?php echo $vehicule['id']; ?>">
                                <?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele'] . ' - ' . $vehicule['immatriculation'] . ' (' . $vehicule['client_nom'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="technicien_id" class="block text-sm font-medium text-gray-700 mb-1">Technicien</label>
                    <select id="technicien_id" name="technicien_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                        <option value="">Sélectionner un technicien</option>
                        <?php foreach ($techniciens as $technicien): ?>
                            <option value="<?php echo $technicien['id']; ?>" data-specialite="<?php echo htmlspecialchars($technicien['specialite']); ?>">
                                <?php echo htmlspecialchars($technicien['nom_complet']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 technicien-specialite hidden">Spécialité: <span id="specialite_technicien"></span></p>
                </div>
                <div>
                    <label for="date_prevue" class="block text-sm font-medium text-gray-700 mb-1">Date prévue</label>
                    <input type="date" id="date_prevue" name="date_prevue" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                    <input type="date" id="date_debut" name="date_debut" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="date_fin" class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                    <input type="date" id="date_fin" name="date_fin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="kilometrage" class="block text-sm font-medium text-gray-700 mb-1">Kilométrage</label>
                    <input type="number" id="kilometrage" name="kilometrage" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Kilométrage actuel">
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                    <textarea id="description" name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Description du problème ou de l'intervention à réaliser"></textarea>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="diagnostique" class="block text-sm font-medium text-gray-700 mb-1">Diagnostique</label>
                    <textarea id="diagnostique" name="diagnostique" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Diagnostique technique"></textarea>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="commentaire" class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                    <textarea id="commentaire" name="commentaire" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Commentaires additionnels"></textarea>
                </div>
                <div>
                    <label for="statut" class="block text-sm font-medium text-gray-700 mb-1">Statut <span class="text-red-500">*</span></label>
                    <select id="statut" name="statut" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                        <option value="En attente">En attente</option>
                        <option value="En cours">En cours</option>
                        <option value="Terminée">Terminée</option>
                        <option value="Facturée">Facturée</option>
                        <option value="Annulée">Annulée</option>
                    </select>
                </div>
            </div>
            
            <!-- Section des articles -->
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Articles à utiliser</h4>
                
                <!-- Sélection de catégorie -->
                <div class="mb-4">
                    <label for="categorie_filter" class="block text-sm font-medium text-gray-700 mb-1">Filtrer par catégorie</label>
                    <select id="categorie_filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?php echo $categorie['id']; ?>"><?php echo htmlspecialchars($categorie['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Recherche d'articles -->
                <div class="mb-4">
                    <label for="article_search" class="block text-sm font-medium text-gray-700 mb-1">Rechercher un article</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" id="article_search" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Rechercher par référence, désignation...">
                    </div>
                </div>
                
                <!-- Liste des articles -->
                <div class="border border-gray-300 rounded-md overflow-hidden">
                    <div class="max-h-64 overflow-y-auto" id="articles_container">
                        <div class="p-4 text-center text-gray-500">
                            Sélectionnez une catégorie ou recherchez un article
                        </div>
                    </div>
                </div>
                
                <!-- Articles sélectionnés -->
                <div class="mt-6">
                    <h5 class="text-md font-medium text-gray-900 mb-2">Articles sélectionnés</h5>
                    <div class="border border-gray-300 rounded-md overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200" id="selected_articles_table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="selected_articles_body">
                                <tr>
                                    <td colspan="7" class="px-4 py-3 text-center text-gray-500">Aucun article sélectionné</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-right font-medium text-gray-700">Total HT:</td>
                                    <td class="px-4 py-3 font-medium text-gray-900" id="total_ht">0.00 €</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addInterventionModal')" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Enregistrer
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-4">Les champs marqués d'un <span class="text-red-500">*</span> sont obligatoires.</p>
            
            <!-- Champ caché pour stocker les articles sélectionnés -->
            <input type="hidden" id="selected_articles_json" name="selected_articles" value="[]">
        </form>
    </div>
</div>

<!-- View Intervention Modal -->
<div id="viewInterventionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white z-10 flex justify-between items-center p-5 border-b border-gray-200">
            <div class="flex items-center">
                <h3 class="text-xl font-semibold text-gray-800">Détails de l'intervention</h3>
            </div>
            <button onclick="closeModal('viewInterventionModal')" class="text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-200 rounded-full p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div id="interventionDetails" class="p-6">
            <!-- En-tête avec statut et dates principales -->
            <div class="mb-6">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span id="view_statut" class="px-3 py-1 rounded-full text-sm font-medium"></span>
                    <div class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Créée le <span id="view_date_creation" class="ml-1 font-medium"></span>
                    </div>
                </div>
            </div>

            <!-- Informations sur le véhicule et le client -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h4 class="font-medium text-gray-700 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Véhicule et client
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Client</p>
                        <p id="view_client" class="font-medium"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Véhicule</p>
                        <p id="view_vehicule" class="font-medium"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Kilométrage</p>
                        <p id="view_kilometrage" class="font-medium"></p>
                    </div>
                </div>
            </div>

            <!-- Informations sur l'intervention -->
            <div class="mb-6">
                <h4 class="font-medium text-gray-700 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Détails de l'intervention
                </h4>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Description</p>
                        <p id="view_description" class="text-gray-800 whitespace-pre-line bg-gray-50 p-3 rounded"></p>
                    </div>
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Diagnostique</p>
                        <p id="view_diagnostique" class="text-gray-800 whitespace-pre-line bg-gray-50 p-3 rounded"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Commentaire</p>
                        <p id="view_commentaire" class="text-gray-800 whitespace-pre-line bg-gray-50 p-3 rounded"></p>
                    </div>
                </div>
            </div>

            <!-- Planning et technicien -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Planning -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Planning
                    </h4>
                    <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Date prévue:</span>
                            <span id="view_date_prevue" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Date de début:</span>
                            <span id="view_date_debut" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Date de fin:</span>
                            <span id="view_date_fin" class="font-medium"></span>
                        </div>
                    </div>
                </div>

                <!-- Technicien -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Technicien
                    </h4>
                    <div id="view_technicien_container" class="bg-white border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 flex-shrink-0 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p id="view_technicien" class="font-medium"></p>
                                <p id="view_technicien_specialite" class="text-sm text-gray-500"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Articles utilisés -->
            <div class="mb-6">
                <h4 class="font-medium text-gray-700 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Articles utilisés
                </h4>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200" id="view_articles_table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="view_articles_body">
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-center text-gray-500">Aucun article utilisé</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right font-medium text-gray-700">Total HT:</td>
                                <td class="px-4 py-3 font-medium text-gray-900" id="view_total_ht">0.00 €</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('viewInterventionModal')" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    Fermer
                </button>
                <button type="button" id="editFromViewBtn" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Intervention Modal -->
<div id="editInterventionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Modifier l'intervention</h3>
            <button onclick="closeModal('editInterventionModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editInterventionForm" action="edit.php" method="POST" class="p-6">
            <input type="hidden" id="edit_id" name="id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="edit_vehicule_id" class="block text-sm font-medium text-gray-700 mb-1">Véhicule <span class="text-red-500">*</span></label>
                    <select id="edit_vehicule_id" name="vehicule_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Sélectionner un véhicule</option>
                        <?php foreach ($vehicules as $vehicule): ?>
                            <option value="<?php echo $vehicule['id']; ?>">
                                <?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele'] . ' - ' . $vehicule['immatriculation'] . ' (' . $vehicule['client_nom'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit_technicien_id" class="block text-sm font-medium text-gray-700 mb-1">Technicien</label>
                    <select id="edit_technicien_id" name="technicien_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Sélectionner un technicien</option>
                        <?php foreach ($techniciens as $technicien): ?>
                            <option value="<?php echo $technicien['id']; ?>" data-specialite="<?php echo htmlspecialchars($technicien['specialite']); ?>">
                                <?php echo htmlspecialchars($technicien['nom_complet']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 edit-technicien-specialite hidden">Spécialité: <span id="edit_specialite_technicien"></span></p>
                </div>
                <div>
                    <label for="edit_date_prevue" class="block text-sm font-medium text-gray-700 mb-1">Date prévue</label>
                    <input type="date" id="edit_date_prevue" name="date_prevue" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="edit_date_debut" class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                    <input type="date" id="edit_date_debut" name="date_debut" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="edit_date_fin" class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                    <input type="date" id="edit_date_fin" name="date_fin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="edit_kilometrage" class="block text-sm font-medium text-gray-700 mb-1">Kilométrage</label>
                    <input type="number" id="edit_kilometrage" name="kilometrage" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Kilométrage actuel">
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                    <textarea id="edit_description" name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Description du problème ou de l'intervention à réaliser"></textarea>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="edit_diagnostique" class="block text-sm font-medium text-gray-700 mb-1">Diagnostique</label>
                    <textarea id="edit_diagnostique" name="diagnostique" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Diagnostique technique"></textarea>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="edit_commentaire" class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                    <textarea id="edit_commentaire" name="commentaire" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Commentaires additionnels"></textarea>
                </div>
                <div>
                    <label for="edit_statut" class="block text-sm font-medium text-gray-700 mb-1">Statut <span class="text-red-500">*</span></label>
                    <select id="edit_statut" name="statut" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="En attente">En attente</option>
                        <option value="En cours">En cours</option>
                        <option value="Terminée">Terminée</option>
                        <option value="Facturée">Facturée</option>
                        <option value="Annulée">Annulée</option>
                    </select>
                </div>
            </div>
            
            <!-- Section des articles -->
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Articles à utiliser</h4>
                
                <!-- Sélection de catégorie -->
                <div class="mb-4">
                    <label for="edit_categorie_filter" class="block text-sm font-medium text-gray-700 mb-1">Filtrer par catégorie</label>
                    <select id="edit_categorie_filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?php echo $categorie['id']; ?>"><?php echo htmlspecialchars($categorie['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Recherche d'articles -->
                <div class="mb-4">
                    <label for="edit_article_search" class="block text-sm font-medium text-gray-700 mb-1">Rechercher un article</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" id="edit_article_search" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher par référence, désignation...">
                    </div>
                </div>
                
                <!-- Liste des articles -->
                <div class="border border-gray-300 rounded-md overflow-hidden">
                    <div class="max-h-64 overflow-y-auto" id="edit_articles_container">
                        <div class="p-4 text-center text-gray-500">
                            Sélectionnez une catégorie ou recherchez un article
                        </div>
                    </div>
                </div>
                
                <!-- Articles sélectionnés -->
                <div class="mt-6">
                    <h5 class="text-md font-medium text-gray-900 mb-2">Articles sélectionnés</h5>
                    <div class="border border-gray-300 rounded-md overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200" id="edit_selected_articles_table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="edit_selected_articles_body">
                                <tr>
                                    <td colspan="7" class="px-4 py-3 text-center text-gray-500">Aucun article sélectionné</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-right font-medium text-gray-700">Total HT:</td>
                                    <td class="px-4 py-3 font-medium text-gray-900" id="edit_total_ht">0.00 €</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('editInterventionModal')" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Enregistrer les modifications
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-4">Les champs marqués d'un <span class="text-red-500">*</span> sont obligatoires.</p>
            
            <!-- Champ caché pour stocker les articles sélectionnés -->
            <input type="hidden" id="edit_selected_articles_json" name="selected_articles" value="[]">
        </form>
    </div>
</div>

<!-- Assign Technicien Modal -->
<div id="assignTechnicienModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Assigner un technicien</h3>
            <button onclick="closeModal('assignTechnicienModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="assignTechnicienForm" action="../actions/assigner_technicien.php" method="POST" class="p-6">
            <input type="hidden" id="assign_intervention_id" name="intervention_id">
            <div class="mb-4">
                <label for="assign_technicien_id" class="block text-sm font-medium text-gray-700 mb-1">Technicien</label>
                <select id="assign_technicien_id" name="technicien_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Sélectionner un technicien</option>
                    <?php foreach ($techniciens as $technicien): ?>
                        <option value="<?php echo $technicien['id']; ?>" data-specialite="<?php echo htmlspecialchars($technicien['specialite']); ?>">
                            <?php echo htmlspecialchars($technicien['nom_complet']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500 assign-technicien-specialite hidden">Spécialité: <span id="assign_specialite_technicien"></span></p>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('assignTechnicienModal')" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Assigner
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Confirmer la suppression</h3>
            <p class="text-gray-600 mb-6">Êtes-vous sûr de vouloir supprimer cette intervention ? Cette action est irréversible.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('deleteConfirmationModal')" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Annuler
                </button>
                <form id="deleteInterventionForm" action="../actions/delete_intervention.php" method="POST">
                    <input type="hidden" id="delete_id" name="id">
                    <button type="submit" class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonctions pour les modales
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.getElementById(modalId).classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

// Variables globales pour stocker les articles
let allArticles = [];
let selectedArticles = [];
let editSelectedArticles = [];

// Fonction pour charger les articles par catégorie
function loadArticlesByCategory(categoryId, mode = 'add') {
    const containerSelector = mode === 'add' ? '#articles_container' : '#edit_articles_container';
    const container = document.querySelector(containerSelector);
    
    container.innerHTML = '<div class="p-4 text-center text-gray-500">Chargement des articles...</div>';
    
    // Requête AJAX pour récupérer les articles
    fetch(`get_articles.php?categorie_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allArticles = data.articles;
                displayArticles(allArticles, mode);
            } else {
                container.innerHTML = `<div class="p-4 text-center text-red-500">${data.error}</div>`;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            container.innerHTML = '<div class="p-4 text-center text-red-500">Erreur lors du chargement des articles</div>';
        });
}

// Fonction pour rechercher des articles
function searchArticles(searchTerm, mode = 'add') {
    if (searchTerm.length < 2) return;
    
    const containerSelector = mode === 'add' ? '#articles_container' : '#edit_articles_container';
    const container = document.querySelector(containerSelector);
    
    container.innerHTML = '<div class="p-4 text-center text-gray-500">Recherche en cours...</div>';
    
    // Requête AJAX pour rechercher les articles
    fetch(`search_articles.php?search=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allArticles = data.articles;
                displayArticles(allArticles, mode);
            } else {
                container.innerHTML = `<div class="p-4 text-center text-red-500">${data.error}</div>`;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            container.innerHTML = '<div class="p-4 text-center text-red-500">Erreur lors de la recherche</div>';
        });
}

// Fonction pour afficher les articles dans le conteneur
function displayArticles(articles, mode = 'add') {
    const containerSelector = mode === 'add' ? '#articles_container' : '#edit_articles_container';
    const container = document.querySelector(containerSelector);
    
    if (articles.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-gray-500">Aucun article trouvé</div>';
        return;
    }
    
    let html = '<div class="divide-y divide-gray-200">';
    
    articles.forEach(article => {
        const isSelected = mode === 'add' 
            ? selectedArticles.some(a => a.id === article.id)
            : editSelectedArticles.some(a => a.id === article.id);
        
        html += `
            <div class="p-4 hover:bg-gray-50 flex justify-between items-center">
                <div>
                    <div class="font-medium text-gray-800">${article.reference} - ${article.designation}</div>
                    <div class="text-sm text-gray-500">Prix: ${parseFloat(article.prix_vente_ht).toFixed(2)} € | Stock: ${article.stock}</div>
                </div>
                <button type="button" 
                    class="${isSelected ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'} px-3 py-1 rounded-md text-sm"
                    onclick="${isSelected ? `removeArticle(${article.id}, '${mode}')` : `addArticle(${JSON.stringify(article).replace(/"/g, '&quot;')}, '${mode}')`}">
                    ${isSelected ? 'Ajouté ✓' : 'Ajouter'}
                </button>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Fonction pour ajouter un article à la liste des sélectionnés
function addArticle(article, mode = 'add') {
    // Déterminer quelle liste et quels éléments DOM utiliser
    const articlesList = mode === 'add' ? selectedArticles : editSelectedArticles;
    const tableBodyId = mode === 'add' ? 'selected_articles_body' : 'edit_selected_articles_body';
    const totalHtId = mode === 'add' ? 'total_ht' : 'edit_total_ht';
    const hiddenInputId = mode === 'add' ? 'selected_articles_json' : 'edit_selected_articles_json';
    
    // Vérifier si l'article est déjà dans la liste
    if (articlesList.some(a => a.id === article.id)) {
        return;
    }
    
    // Ajouter l'article avec une quantité par défaut de 1 et sans remise
    const newArticle = {
        ...article,
        quantite: 1,
        remise: 0,
        total: parseFloat(article.prix_vente_ht)
    };
    
    articlesList.push(newArticle);
    
    // Mettre à jour l'affichage
    updateSelectedArticlesDisplay(mode);
    
    // Mettre à jour la liste des articles disponibles pour marquer celui-ci comme sélectionné
    displayArticles(allArticles, mode);
}

// Fonction pour supprimer un article de la liste des sélectionnés
function removeArticle(articleId, mode = 'add') {
    // Déterminer quelle liste utiliser
    const articlesList = mode === 'add' ? selectedArticles : editSelectedArticles;
    
    // Trouver l'index de l'article à supprimer
    const index = articlesList.findIndex(a => a.id === articleId);
    
    if (index !== -1) {
        // Supprimer l'article de la liste
        articlesList.splice(index, 1);
        
        // Mettre à jour l'affichage
        updateSelectedArticlesDisplay(mode);
        
        // Mettre à jour la liste des articles disponibles
        displayArticles(allArticles, mode);
    }
}

// Fonction pour mettre à jour la quantité d'un article
function updateQuantity(articleId, newQuantity, mode = 'add') {
    // Déterminer quelle liste utiliser
    const articlesList = mode === 'add' ? selectedArticles : editSelectedArticles;
    
    // Trouver l'article
    const article = articlesList.find(a => a.id === articleId);
    
    if (article) {
        // Mettre à jour la quantité
        article.quantite = parseInt(newQuantity);
        
        // Recalculer le total
        article.total = (article.prix_vente_ht * article.quantite) * (1 - article.remise / 100);
        
        // Mettre à jour l'affichage
        updateSelectedArticlesDisplay(mode);
    }
}
// Fonction pour mettre à jour la remise d'un article
function updateRemise(articleId, newRemise, mode = 'add') {
    // Déterminer quelle liste utiliser
    const articlesList = mode === 'add' ? selectedArticles : editSelectedArticles;
    
    // Trouver l'article
    const article = articlesList.find(a => a.id === articleId);
    
    if (article) {
        // Mettre à jour la remise
        article.remise = parseFloat(newRemise);
        
        // Recalculer le total
        article.total = (article.prix_vente_ht * article.quantite) * (1 - article.remise / 100);
        
        // Mettre à jour l'affichage
        updateSelectedArticlesDisplay(mode);
    }
}

// Fonction pour mettre à jour l'affichage des articles sélectionnés
function updateSelectedArticlesDisplay(mode = 'add') {
    // Déterminer quels éléments DOM utiliser
    const articlesList = mode === 'add' ? selectedArticles : editSelectedArticles;
    const tableBodyId = mode === 'add' ? 'selected_articles_body' : 'edit_selected_articles_body';
    const totalHtId = mode === 'add' ? 'total_ht' : 'edit_total_ht';
    const hiddenInputId = mode === 'add' ? 'selected_articles_json' : 'edit_selected_articles_json';
    
    const tableBody = document.getElementById(tableBodyId);
    const totalHtElement = document.getElementById(totalHtId);
    const hiddenInput = document.getElementById(hiddenInputId);
    
    // Si la liste est vide, afficher un message
    if (articlesList.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-3 text-center text-gray-500">Aucun article sélectionné</td>
            </tr>
        `;
        totalHtElement.textContent = '0.00 €';
        hiddenInput.value = JSON.stringify([]);
        return;
    }
    
    // Calculer le total HT
    let totalHt = 0;
    
    // Générer les lignes du tableau
    let html = '';
    articlesList.forEach(article => {
        const total = article.total;
        totalHt += total;
        
        html += `
            <tr>
                <td class="px-4 py-3 text-sm text-gray-800">${article.reference}</td>
                <td class="px-4 py-3 text-sm text-gray-800">${article.designation}</td>
                <td class="px-4 py-3 text-sm">
                    <input type="number" min="1" value="${article.quantite}" 
                           class="w-16 px-2 py-1 border border-gray-300 rounded-md"
                           onchange="updateQuantity(${article.id}, this.value, '${mode}')">
                </td>
                <td class="px-4 py-3 text-sm text-gray-800">${parseFloat(article.prix_vente_ht).toFixed(2)} €</td>
                <td class="px-4 py-3 text-sm">
                    <input type="number" min="0" max="100" value="${article.remise}" 
                           class="w-16 px-2 py-1 border border-gray-300 rounded-md"
                           onchange="updateRemise(${article.id}, this.value, '${mode}')"> %
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-800">${total.toFixed(2)} €</td>
                <td class="px-4 py-3 text-sm">
                    <button type="button" class="text-red-600 hover:text-red-800" 
                            onclick="removeArticle(${article.id}, '${mode}')">
                        Supprimer
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    totalHtElement.textContent = totalHt.toFixed(2) + ' €';
    
    // Mettre à jour le champ caché avec les données JSON
    hiddenInput.value = JSON.stringify(articlesList);
}

// Afficher la spécialité du technicien sélectionné
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des filtres de catégories et recherche d'articles
    const categorieFilter = document.getElementById('categorie_filter');
    const articleSearch = document.getElementById('article_search');
    const editCategorieFilter = document.getElementById('edit_categorie_filter');
    const editArticleSearch = document.getElementById('edit_article_search');
    
    if (categorieFilter) {
        categorieFilter.addEventListener('change', function() {
            loadArticlesByCategory(this.value, 'add');
        });
    }
    
    if (articleSearch) {
        articleSearch.addEventListener('input', function() {
            if (this.value.length >= 2) {
                searchArticles(this.value, 'add');
            }
        });
    }
    
    if (editCategorieFilter) {
        editCategorieFilter.addEventListener('change', function() {
            loadArticlesByCategory(this.value, 'edit');
        });
    }
    
    if (editArticleSearch) {
        editArticleSearch.addEventListener('input', function() {
            if (this.value.length >= 2) {
                searchArticles(this.value, 'edit');
            }
        });
    }

    const technicienSelect = document.getElementById('technicien_id');
    const specialiteInfo = document.querySelector('.technicien-specialite');
    const specialiteSpan = document.getElementById('specialite_technicien');
    
    if (technicienSelect) {
        technicienSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (this.value) {
                const specialite = selectedOption.getAttribute('data-specialite');
                specialiteSpan.textContent = specialite;
                specialiteInfo.classList.remove('hidden');
            } else {
                specialiteInfo.classList.add('hidden');
            }
        });
    }
    
    // Même logique pour le formulaire d'édition
    const editTechnicienSelect = document.getElementById('edit_technicien_id');
    const editSpecialiteInfo = document.querySelector('.edit-technicien-specialite');
    const editSpecialiteSpan = document.getElementById('edit_specialite_technicien');
    
    if (editTechnicienSelect) {
        editTechnicienSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (this.value) {
                const specialite = selectedOption.getAttribute('data-specialite');
                editSpecialiteSpan.textContent = specialite;
                editSpecialiteInfo.classList.remove('hidden');
            } else {
                editSpecialiteInfo.classList.add('hidden');
            }
        });
    }
    
    // Pour le formulaire d'assignation
    const assignTechnicienSelect = document.getElementById('assign_technicien_id');
    const assignSpecialiteInfo = document.querySelector('.assign-technicien-specialite');
    const assignSpecialiteSpan = document.getElementById('assign_specialite_technicien');
    
    if (assignTechnicienSelect) {
        assignTechnicienSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (this.value) {
                const specialite = selectedOption.getAttribute('data-specialite');
                assignSpecialiteSpan.textContent = specialite;
                assignSpecialiteInfo.classList.remove('hidden');
            } else {
                assignSpecialiteInfo.classList.add('hidden');
            }
        });
    }
});

// Fonction pour voir les détails d'une intervention
function viewIntervention(id) {
    fetch(`recuperation_inter.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const intervention = data.intervention;
                
                // Remplir les informations client et véhicule
                document.getElementById('view_client').textContent = intervention.client_nom || 'Non spécifié';
                document.getElementById('view_vehicule').textContent = intervention.vehicule_complet || 
                    `${intervention.marque} ${intervention.modele} (${intervention.immatriculation})`;
                
                // Kilométrage
                document.getElementById('view_kilometrage').textContent = intervention.kilometrage ? 
                    `${intervention.kilometrage.toLocaleString()} km` : 'Non renseigné';
                
                // Dates
                document.getElementById('view_date_creation').textContent = 
                    intervention.date_creation_formatted || formatDate(intervention.date_creation) || 'Non spécifiée';
                document.getElementById('view_date_prevue').textContent = 
                    intervention.date_prevue_formatted || (intervention.date_prevue ? formatDate(intervention.date_prevue) : 'Non définie');
                document.getElementById('view_date_debut').textContent = 
                    intervention.date_debut_formatted || (intervention.date_debut ? formatDate(intervention.date_debut) : 'Non démarrée');
                document.getElementById('view_date_fin').textContent = 
                    intervention.date_fin_formatted || (intervention.date_fin ? formatDate(intervention.date_fin) : 'Non terminée');
                
                // Description, diagnostique et commentaire
                document.getElementById('view_description').textContent = intervention.description || 'Aucune description';
                document.getElementById('view_diagnostique').textContent = intervention.diagnostique || 'Aucun diagnostique effectué';
                document.getElementById('view_commentaire').textContent = intervention.commentaire || 'Aucun commentaire';
                
                // Afficher le statut avec la bonne classe
                const statusElement = document.getElementById('view_statut');
                statusElement.textContent = intervention.statut;
                
                // Appliquer la couleur appropriée au statut
                statusElement.className = 'px-3 py-1 rounded-full text-sm font-medium';
                switch (intervention.statut) {
                    case 'En attente':
                        statusElement.classList.add('bg-yellow-100', 'text-yellow-800');
                        break;
                    case 'En cours':
                        statusElement.classList.add('bg-blue-100', 'text-blue-800');
                        break;
                    case 'Terminée':
                        statusElement.classList.add('bg-green-100', 'text-green-800');
                        break;
                    case 'Facturée':
                        statusElement.classList.add('bg-purple-100', 'text-purple-800');
                        break;
                    case 'Annulée':
                        statusElement.classList.add('bg-red-100', 'text-red-800');
                        break;
                    default:
                        statusElement.classList.add('bg-gray-100', 'text-gray-800');
                }
                
                // Afficher les informations du technicien
                const technicienElement = document.getElementById('view_technicien');
                const technicienSpecialiteElement = document.getElementById('view_technicien_specialite');
                
                if (intervention.technicien_nom) {
                    technicienElement.textContent = intervention.technicien_nom;
                    technicienSpecialiteElement.textContent = `Spécialité: ${intervention.technicien_specialite || 'Non spécifiée'}`;
                } else {
                    technicienElement.textContent = 'Non assigné';
                    technicienSpecialiteElement.textContent = 'Aucun technicien n\'est assigné à cette intervention';
                }
                
                // Charger et afficher les articles associés à l'intervention
                loadInterventionArticles(intervention.id);
                
                // Configurer le bouton d'édition
                document.getElementById('editFromViewBtn').onclick = function() {
                    closeModal('viewInterventionModal');
                    editIntervention(intervention.id);
                };
                
                // Ouvrir le modal
                openModal('viewInterventionModal');
            } else {
                showToast('error', 'Erreur lors de la récupération des détails de l\'intervention');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('error', 'Une erreur est survenue lors de la récupération des données');
        });
}

// Fonction pour charger les articles associés à une intervention
function loadInterventionArticles(interventionId) {
    fetch(`get_intervention_articles.php?intervention_id=${interventionId}`)
        .then(response => response.json())
        .then(data => {
            const tableBody = document.getElementById('view_articles_body');
            const totalHtElement = document.getElementById('view_total_ht');
            
            if (data.success && data.articles.length > 0) {
                let html = '';
                let totalHt = 0;
                
                data.articles.forEach(article => {
                    const total = article.prix_unitaire * article.quantite * (1 - article.remise / 100);
                    totalHt += total;
                    
                    html += `
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-800">${article.reference}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">${article.designation}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">${article.quantite}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">${parseFloat(article.prix_unitaire).toFixed(2)} €</td>
                            <td class="px-4 py-3 text-sm text-gray-800">${article.remise} %</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-800">${total.toFixed(2)} €</td>
                        </tr>
                    `;
                });
                
                tableBody.innerHTML = html;
                totalHtElement.textContent = totalHt.toFixed(2) + ' €';
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center text-gray-500">Aucun article utilisé</td>
                    </tr>
                `;
                totalHtElement.textContent = '0.00 €';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('view_articles_body').innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-3 text-center text-red-500">Erreur lors du chargement des articles</td>
                </tr>
            `;
        });
}

// Fonction pour éditer une intervention
function editIntervention(id) {
    fetch(`recuperation_inter.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const intervention = data.intervention;
                
                // Remplir les champs du formulaire d'édition
                document.getElementById('edit_id').value = intervention.id;
                document.getElementById('edit_vehicule_id').value = intervention.vehicule_id;
                document.getElementById('edit_technicien_id').value = intervention.technicien_id || '';
                document.getElementById('edit_date_prevue').value = intervention.date_prevue ? formatDateForInput(intervention.date_prevue) : '';
                document.getElementById('edit_date_debut').value = intervention.date_debut ? formatDateForInput(intervention.date_debut) : '';
                document.getElementById('edit_date_fin').value = intervention.date_fin ? formatDateForInput(intervention.date_fin) : '';
                document.getElementById('edit_kilometrage').value = intervention.kilometrage || '';
                document.getElementById('edit_description').value = intervention.description;
                document.getElementById('edit_diagnostique').value = intervention.diagnostique || '';
                document.getElementById('edit_commentaire').value = intervention.commentaire || '';
                document.getElementById('edit_statut').value = intervention.statut;
                
                // Afficher la spécialité du technicien si un technicien est sélectionné
                const editTechnicienSelect = document.getElementById('edit_technicien_id');
                const editSpecialiteInfo = document.querySelector('.edit-technicien-specialite');
                const editSpecialiteSpan = document.getElementById('edit_specialite_technicien');
                
                if (intervention.technicien_id) {
                    const selectedOption = editTechnicienSelect.options[editTechnicienSelect.selectedIndex];
                    const specialite = selectedOption.getAttribute('data-specialite');
                    editSpecialiteSpan.textContent = specialite;
                    editSpecialiteInfo.classList.remove('hidden');
                } else {
                    editSpecialiteInfo.classList.add('hidden');
                }
                
                // Charger les articles associés à l'intervention pour l'édition
                loadInterventionArticlesForEdit(intervention.id);
                
                // Ouvrir le modal
                openModal('editInterventionModal');
            } else {
                alert('Erreur lors de la récupération des détails de l\'intervention');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors de la récupération des données');
        });
}

// Fonction pour charger les articles associés à une intervention pour l'édition
function loadInterventionArticlesForEdit(interventionId) {
    fetch(`get_intervention_articles.php?intervention_id=${interventionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Réinitialiser la liste des articles sélectionnés
                editSelectedArticles = [];
                
                if (data.articles.length > 0) {
                    // Convertir les articles reçus au format attendu
                    data.articles.forEach(article => {
                        editSelectedArticles.push({
                            id: article.article_id,
                            reference: article.reference,
                            designation: article.designation,
                            prix_vente_ht: article.prix_unitaire,
                            quantite: article.quantite,
                            remise: article.remise,
                            total: article.prix_unitaire * article.quantite * (1 - article.remise / 100)
                        });
                    });
                }
                
                // Mettre à jour l'affichage
                updateSelectedArticlesDisplay('edit');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

// Fonction pour assigner un technicien
function assignTechnicien(id) {
    document.getElementById('assign_intervention_id').value = id;
    openModal('assignTechnicienModal');
}

// Fonction pour supprimer une intervention
function deleteIntervention(id) {
    document.getElementById('delete_id').value = id;
    openModal('deleteConfirmationModal');
}

// Fonctions utilitaires pour le formatage des dates
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}

function formatDateForInput(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
}

// Fonction pour afficher des notifications toast
function showToast(type, message) {
    // Implémentation simple d'alerte, à remplacer par une solution plus élégante
    if (type === 'error') {
        alert(message);
    } else {
        alert(message);
    }
}

// Fermer les modales si on clique en dehors
document.addEventListener('click', function(event) {
    const modals = [
        'addInterventionModal', 
        'viewInterventionModal', 
        'editInterventionModal', 
        'deleteConfirmationModal',
        'assignTechnicienModal'
    ];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && !modal.classList.contains('hidden')) {
            const modalContent = modal.querySelector('div > div');
            if (modalContent && !modalContent.contains(event.target) && modal.contains(event.target)) {
                closeModal(modalId);
            }
        }
    });
});

// Fermer les modales avec la touche Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = [
            'addInterventionModal', 
            'viewInterventionModal', 
            'editInterventionModal', 
            'deleteConfirmationModal',
            'assignTechnicienModal'
        ];
        
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && !modal.classList.contains('hidden')) {
                closeModal(modalId);
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

