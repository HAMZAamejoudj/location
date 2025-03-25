<?php
// Démarrer la session d'abord
session_start();

// Chemin racine de l'application
$root_path = __DIR__;

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
} else {
    // Si le fichier n'existe pas, on crée un $currentUser par défaut
    // pour éviter les erreurs
    $currentUser = [
        'name' => 'Utilisateur',
        'role' => 'Admin'
    ];
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

// Vérifier si l'utilisateur est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    //header('Location: login.php');
    //exit;
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
}

// Récupérer les informations de l'utilisateur actuel
// Utilisateur temporaire pour éviter l'erreur
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Inclure l'en-tête
include 'includes/header.php';
$database = new Database();
$db = $database->getConnection();
$count_query = "SELECT COUNT(*) as total FROM clients";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total_clients = $total_result['total'];

// Récupérer les alertes pour interventions à échéance proche (dans les 7 prochains jours)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

// Requête pour les interventions qui se terminent bientôt
$interventions_query = "SELECT i.id, i.description, i.date_fin, v.immatriculation, c.nom, c.prenom 
                       FROM interventions i 
                       LEFT JOIN vehicules v ON i.vehicule_id = v.id 
                       LEFT JOIN clients c ON i.client_id = c.id OR v.client_id = c.id
                       WHERE i.date_fin BETWEEN :today AND :next_week 
                       AND i.statut NOT IN ('Terminée', 'Facturée', 'Annulée')
                       ORDER BY i.date_fin ASC
                       LIMIT 5";
$interventions_stmt = $db->prepare($interventions_query);
$interventions_stmt->bindParam(':today', $today);
$interventions_stmt->bindParam(':next_week', $next_week);
$interventions_stmt->execute();
$interventions_alerts = $interventions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Requête pour les factures non payées
$factures_query = "SELECT f.id, f.Numero_Facture, f.Date_Facture, f.Montant_Total_HT, c.nom, c.prenom 
                  FROM factures f 
                  JOIN clients c ON f.ID_Client = c.id 
                  WHERE f.Statut_Facture = 'Émise' 
                  ORDER BY f.Date_Facture ASC
                  LIMIT 5";
$factures_stmt = $db->prepare($factures_query);
$factures_stmt->execute();
$factures_alerts = $factures_stmt->fetchAll(PDO::FETCH_ASSOC);

// Requête pour les offres qui expirent bientôt
$offres_query = "SELECT id, nom, code, date_fin 
                FROM offres 
                WHERE date_fin BETWEEN :today AND :next_week 
                AND actif = 1
                ORDER BY date_fin ASC
                LIMIT 5";
$offres_stmt = $db->prepare($offres_query);
$offres_stmt->bindParam(':today', $today);
$offres_stmt->bindParam(':next_week', $next_week);
$offres_stmt->execute();
$offres_alerts = $offres_stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre total d'alertes
$total_alerts = count($interventions_alerts) + count($factures_alerts) + count($offres_alerts);
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
        <!-- Top header -->
        <div class="bg-white shadow-sm">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">Tableau de bord</h1>
                <div class="flex items-center space-x-4">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notificationButton" class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php if ($total_alerts > 0): ?>
                                <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"><?php echo $total_alerts; ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Popup de notifications -->
                        <div id="notificationPopup" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-lg shadow-lg z-50 overflow-hidden">
                            <!-- Tabs -->
                            <div class="flex border-b">
                                <button id="tab-all" class="flex-1 py-3 px-4 text-sm font-medium text-center text-blue-600 border-b-2 border-blue-600 focus:outline-none">
                                    Toutes (<?php echo $total_alerts; ?>)
                                </button>
                                <button id="tab-interventions" class="flex-1 py-3 px-4 text-sm font-medium text-center text-gray-500 hover:text-gray-700 focus:outline-none">
                                    Interventions (<?php echo count($interventions_alerts); ?>)
                                </button>
                                <button id="tab-factures" class="flex-1 py-3 px-4 text-sm font-medium text-center text-gray-500 hover:text-gray-700 focus:outline-none">
                                    Factures (<?php echo count($factures_alerts); ?>)
                                </button>
                            </div>
                            
                            <!-- Contenu des tabs -->
                            <div class="max-h-96 overflow-y-auto">
                                <!-- Tab All -->
                                <div id="content-all" class="p-3 space-y-3">
                                    <?php if ($total_alerts === 0): ?>
                                        <p class="text-center text-gray-500 py-4">Aucune notification</p>
                                    <?php else: ?>
                                        <!-- Interventions -->
                                        <?php foreach ($interventions_alerts as $alert): ?>
                                            <div class="flex items-center p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded-lg">
                                                <div class="mr-3 text-yellow-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-800">
                                                        Intervention #<?php echo $alert['id']; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        Échéance: <?php echo date('d/m/Y', strtotime($alert['date_fin'])); ?>
                                                    </p>
                                                </div>
                                                <a href="interventions/edit.php?id=<?php echo $alert['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                    Voir
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <!-- Factures -->
                                        <?php foreach ($factures_alerts as $alert): ?>
                                            <div class="flex items-center p-3 bg-red-50 border-l-4 border-red-400 rounded-lg">
                                                <div class="mr-3 text-red-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-800">
                                                        Facture <?php echo htmlspecialchars($alert['Numero_Facture']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        Date: <?php echo date('d/m/Y', strtotime($alert['Date_Facture'])); ?>
                                                    </p>
                                                </div>
                                                <a href="invoices/view.php?id=<?php echo $alert['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                    Voir
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <!-- Offres -->
                                        <?php foreach ($offres_alerts as $alert): ?>
                                            <div class="flex items-center p-3 bg-blue-50 border-l-4 border-blue-400 rounded-lg">
                                                <div class="mr-3 text-blue-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-800">
                                                        Offre <?php echo htmlspecialchars($alert['code']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        Expire le: <?php echo date('d/m/Y', strtotime($alert['date_fin'])); ?>
                                                    </p>
                                                </div>
                                                <a href="offres/edit.php?id=<?php echo $alert['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                    Voir
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Tab Interventions -->
                                <div id="content-interventions" class="hidden p-3 space-y-3">
                                    <?php if (count($interventions_alerts) === 0): ?>
                                        <p class="text-center text-gray-500 py-4">Aucune intervention à signaler</p>
                                    <?php else: ?>
                                        <?php foreach ($interventions_alerts as $alert): ?>
                                            <div class="flex items-center p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded-lg">
                                                <div class="mr-3 text-yellow-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-800">
                                                        Intervention #<?php echo $alert['id']; ?> - <?php echo htmlspecialchars($alert['description']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        Client: <?php echo htmlspecialchars($alert['nom'] . ' ' . $alert['prenom']); ?><br>
                                                        Véhicule: <?php echo htmlspecialchars($alert['immatriculation']); ?><br>
                                                        Échéance: <?php echo date('d/m/Y', strtotime($alert['date_fin'])); ?>
                                                    </p>
                                                </div>
                                                <a href="interventions/edit.php?id=<?php echo $alert['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                    Voir
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Tab Factures -->
                                <div id="content-factures" class="hidden p-3 space-y-3">
                                    <?php if (count($factures_alerts) === 0): ?>
                                        <p class="text-center text-gray-500 py-4">Aucune facture en attente</p>
                                    <?php else: ?>
                                        <?php foreach ($factures_alerts as $alert): ?>
                                            <div class="flex items-center p-3 bg-red-50 border-l-4 border-red-400 rounded-lg">
                                                <div class="mr-3 text-red-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-800">
                                                        Facture <?php echo htmlspecialchars($alert['Numero_Facture']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        Client: <?php echo htmlspecialchars($alert['nom'] . ' ' . $alert['prenom']); ?><br>
                                                        Date: <?php echo date('d/m/Y', strtotime($alert['Date_Facture'])); ?><br>
                                                        Montant: <?php echo number_format($alert['Montant_Total_HT'], 2, ',', ' '); ?> DH
                                                    </p>
                                                </div>
                                                <a href="invoices/view.php?id=<?php echo $alert['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                    Voir
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div class="border-t p-3 text-center">
                                <a href="#" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Voir toutes les notifications
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="container mx-auto px-6 py-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Clients Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Clients</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                            <?php echo $total_clients; ?>
                            </p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="clients/index.php" class="text-blue-500 hover:text-blue-700 text-sm font-semibold">Voir tous les clients →</a>
                    </div>
                </div>

                <!-- Vehicles Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Véhicules</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php echo "42"; ?>
                            </p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="vehicles/" class="text-green-500 hover:text-green-700 text-sm font-semibold">Voir tous les véhicules →</a>
                    </div>
                </div>

                <!-- Interventions Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Interventions</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php echo "17"; ?>
                            </p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="interventions/" class="text-yellow-500 hover:text-yellow-700 text-sm font-semibold">Voir toutes les interventions →</a>
                    </div>
                </div>

                <!-- Invoices Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Factures</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php echo "36"; ?>
                            </p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="invoices/" class="text-purple-500 hover:text-purple-700 text-sm font-semibold">Voir toutes les factures →</a>
                    </div>
                </div>
            </div>

            <!-- Quick Access Sections -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Stock Management -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Gestion du stock</h3>
                    <div class="space-y-2">
                        <a href="stock/" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                            <div class="bg-blue-100 p-2 rounded-full mr-4">
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800">Liste des articles</h4>
                                <p class="text-sm text-gray-600">Consulter et gérer le stock de pièces et fournitures</p>
                            </div>
                        </a>
                        <a href="stock/create.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                            <div class="bg-green-100 p-2 rounded-full mr-4">
                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800">Ajouter un article</h4>
                                <p class="text-sm text-gray-600">Créer un nouvel article dans l'inventaire</p>
                            </div>
                        </a>
                    </div>
                    </div>

<!-- Orders Management -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Bons de commande & livraison</h3>
    <div class="space-y-2">
        <a href="orders/" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
            <div class="bg-yellow-100 p-2 rounded-full mr-4">
                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div>
                <h4 class="font-medium text-gray-800">Bons de commande</h4>
                <p class="text-sm text-gray-600">Gérer les commandes fournisseurs</p>
            </div>
        </a>
        <a href="deliveries/" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
            <div class="bg-purple-100 p-2 rounded-full mr-4">
                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path>
                </svg>
            </div>
            <div>
                <h4 class="font-medium text-gray-800">Bons de livraison</h4>
                <p class="text-sm text-gray-600">Gérer les livraisons clients</p>
            </div>
        </a>
    </div>
</div>
</div>

<!-- Main Actions Grid -->
<h3 class="text-xl font-semibold text-gray-800 mb-4">Accès rapide</h3>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

<!-- Clients Management -->
<a href="clients/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-blue-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Clients</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer les informations clients</p>
</a>

<!-- Vehicles Management -->
<a href="vehicles/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-green-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Véhicules</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer le parc automobile</p>
</a>

<!-- Interventions Management -->
<a href="interventions/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-yellow-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Interventions</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer les réparations et services</p>
</a>

<!-- Stock Management -->
<a href="stock/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-indigo-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Stock</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer l'inventaire des pièces</p>
</a>

<!-- Invoices Management -->
<a href="invoices/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-purple-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Factures</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer la facturation</p>
</a>

<!-- Orders Management -->
<a href="orders/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-red-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Commandes</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer les bons de commande</p>
</a>

<!-- Deliveries Management -->
<a href="deliveries/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-pink-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Livraisons</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer les bons de livraison</p>
</a>

<!-- Users Management -->
<a href="users/" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 flex flex-col items-center justify-center text-center">
    <div class="bg-gray-100 p-4 rounded-full mb-4">
        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
        </svg>
    </div>
    <h3 class="text-lg font-medium text-gray-800">Utilisateurs</h3>
    <p class="text-sm text-gray-600 mt-2">Gérer les comptes utilisateurs</p>
</a>
</div>
</div>
</div>
</div>

<!-- Ajoutez ce script JavaScript avant la fin de votre fichier -->
<script>
document.addEventListener('DOMContentLoaded', function() {
const notificationButton = document.getElementById('notificationButton');
const notificationPopup = document.getElementById('notificationPopup');

// Tabs
const tabAll = document.getElementById('tab-all');
const tabInterventions = document.getElementById('tab-interventions');
const tabFactures = document.getElementById('tab-factures');

// Contents
const contentAll = document.getElementById('content-all');
const contentInterventions = document.getElementById('content-interventions');
const contentFactures = document.getElementById('content-factures');

// Fonction pour afficher/masquer la popup
notificationButton.addEventListener('click', function(event) {
event.stopPropagation();
notificationPopup.classList.toggle('hidden');
});

// Fermer la popup si on clique ailleurs
document.addEventListener('click', function(event) {
if (!notificationPopup.contains(event.target) && event.target !== notificationButton) {
notificationPopup.classList.add('hidden');
}
});

// Empêcher la propagation des clics dans la popup
notificationPopup.addEventListener('click', function(event) {
event.stopPropagation();
});

// Gestion des tabs
tabAll.addEventListener('click', function() {
// Activer ce tab
tabAll.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
tabInterventions.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
tabFactures.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');

tabInterventions.classList.add('text-gray-500');
tabFactures.classList.add('text-gray-500');

// Afficher le contenu correspondant
contentAll.classList.remove('hidden');
contentInterventions.classList.add('hidden');
contentFactures.classList.add('hidden');
});

tabInterventions.addEventListener('click', function() {
// Activer ce tab
tabAll.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
tabInterventions.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
tabFactures.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');

tabAll.classList.add('text-gray-500');
tabFactures.classList.add('text-gray-500');

// Afficher le contenu correspondant
contentAll.classList.add('hidden');
contentInterventions.classList.remove('hidden');
contentFactures.classList.add('hidden');
});

tabFactures.addEventListener('click', function() {
// Activer ce tab
tabAll.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
tabInterventions.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
tabFactures.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');

tabAll.classList.add('text-gray-500');
tabInterventions.classList.add('text-gray-500');

// Afficher le contenu correspondant
contentAll.classList.add('hidden');
contentInterventions.classList.add('hidden');
contentFactures.classList.remove('hidden');
});
});
</script>

<?php include 'includes/footer.php'; ?>
