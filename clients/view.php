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

// Vérifier si un ID client est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Rediriger vers la liste des clients si aucun ID n'est fourni
    header('Location: index.php');
    exit;
}

$client_id = intval($_GET['id']);

// Pour la démonstration, je crée un tableau de clients fictifs
// Dans une vraie application, vous récupéreriez les données du client depuis la base de données
$clients = [
    1 => [
        'id' => 1,
        'nom' => 'Dupont',
        'prenom' => 'Jean',
        'email' => 'jean.dupont@example.com',
        'telephone' => '06 12 34 56 78',
        'adresse' => '123 Rue de Paris',
        'code_postal' => '75001',
        'ville' => 'Paris',
        'date_creation' => '2022-01-15',
        'nb_vehicules' => 2,
        'derniere_visite' => '2023-05-20',
        'vehicules' => [
            [
                'id' => 101,
                'marque' => 'Renault',
                'modele' => 'Clio',
                'immatriculation' => 'AB-123-CD',
                'annee' => 2018,
                'kilometrage' => 45000,
                'derniere_intervention' => '2023-05-20'
            ],
            [
                'id' => 102,
                'marque' => 'Peugeot',
                'modele' => '308',
                'immatriculation' => 'EF-456-GH',
                'annee' => 2020,
                'kilometrage' => 25000,
                'derniere_intervention' => '2023-04-10'
            ]
        ],
        'interventions' => [
            [
                'id' => 201,
                'date' => '2023-05-20',
                'vehicule' => 'Renault Clio',
                'type' => 'Révision',
                'montant' => 250.50,
                'statut' => 'Terminé'
            ],
            [
                'id' => 202,
                'date' => '2023-04-10',
                'vehicule' => 'Peugeot 308',
                'type' => 'Changement de pneus',
                'montant' => 420.00,
                'statut' => 'Terminé'
            ],
            [
                'id' => 203,
                'date' => '2022-11-15',
                'vehicule' => 'Renault Clio',
                'type' => 'Réparation freins',
                'montant' => 350.00,
                'statut' => 'Terminé'
            ]
        ],
        'factures' => [
            [
                'id' => 301,
                'numero' => 'F-2023-056',
                'date' => '2023-05-20',
                'montant' => 250.50,
                'statut' => 'Payé'
            ],
            [
                'id' => 302,
                'numero' => 'F-2023-034',
                'date' => '2023-04-10',
                'montant' => 420.00,
                'statut' => 'Payé'
            ],
            [
                'id' => 303,
                'numero' => 'F-2022-122',
                'date' => '2022-11-15',
                'montant' => 350.00,
                'statut' => 'Payé'
            ]
        ]
    ],
    2 => [
        'id' => 2,
        'nom' => 'Martin',
        'prenom' => 'Sophie',
        'email' => 'sophie.martin@example.com',
        'telephone' => '07 23 45 67 89',
        'adresse' => '45 Avenue des Champs',
        'code_postal' => '69002',
        'ville' => 'Lyon',
        'date_creation' => '2022-03-22',
        'nb_vehicules' => 1,
        'derniere_visite' => '2023-03-15',
        'vehicules' => [
            [
                'id' => 103,
                'marque' => 'Volkswagen',
                'modele' => 'Golf',
                'immatriculation' => 'IJ-789-KL',
                'annee' => 2019,
                'kilometrage' => 35000,
                'derniere_intervention' => '2023-03-15'
            ]
        ],
        'interventions' => [
            [
                'id' => 204,
                'date' => '2023-03-15',
                'vehicule' => 'Volkswagen Golf',
                'type' => 'Vidange',
                'montant' => 180.00,
                'statut' => 'Terminé'
            ]
        ],
        'factures' => [
            [
                'id' => 304,
                'numero' => 'F-2023-021',
                'date' => '2023-03-15',
                'montant' => 180.00,
                'statut' => 'Payé'
            ]
        ]
    ],
    3 => [
        'id' => 3,
        'nom' => 'Leroy',
        'prenom' => 'Michel',
        'email' => 'michel.leroy@example.com',
        'telephone' => '06 34 56 78 90',
        'adresse' => '8 Boulevard de la Liberté',
        'code_postal' => '33000',
        'ville' => 'Bordeaux',
        'date_creation' => '2022-05-10',
        'nb_vehicules' => 3,
        'derniere_visite' => '2023-06-05',
        'vehicules' => [
            [
                'id' => 104,
                'marque' => 'Citroen',
                'modele' => 'C3',
                'immatriculation' => 'MN-012-OP',
                'annee' => 2017,
                'kilometrage' => 65000,
                'derniere_intervention' => '2023-06-05'
            ],
            [
                'id' => 105,
                'marque' => 'Audi',
                'modele' => 'A3',
                'immatriculation' => 'QR-345-ST',
                'annee' => 2021,
                'kilometrage' => 20000,
                'derniere_intervention' => '2023-02-18'
            ],
            [
                'id' => 106,
                'marque' => 'BMW',
                'modele' => 'Serie 1',
                'immatriculation' => 'UV-678-WX',
                'annee' => 2019,
                'kilometrage' => 40000,
                'derniere_intervention' => '2022-12-10'
            ]
        ],
        'interventions' => [
            [
                'id' => 205,
                'date' => '2023-06-05',
                'vehicule' => 'Citroen C3',
                'type' => 'Révision complète',
                'montant' => 320.00,
                'statut' => 'Terminé'
            ],
            [
                'id' => 206,
                'date' => '2023-02-18',
                'vehicule' => 'Audi A3',
                'type' => 'Changement batterie',
                'montant' => 180.00,
                'statut' => 'Terminé'
            ],
            [
                'id' => 207,
                'date' => '2022-12-10',
                'vehicule' => 'BMW Serie 1',
                'type' => 'Révision',
                'montant' => 280.00,
                'statut' => 'Terminé'
            ]
        ],
        'factures' => [
            [
                'id' => 305,
                'numero' => 'F-2023-068',
                'date' => '2023-06-05',
                'montant' => 320.00,
                'statut' => 'Payé'
            ],
            [
                'id' => 306,
                'numero' => 'F-2023-012',
                'date' => '2023-02-18',
                'montant' => 180.00,
                'statut' => 'Payé'
            ],
            [
                'id' => 307,
                'numero' => 'F-2022-135',
                'date' => '2022-12-10',
                'montant' => 280.00,
                'statut' => 'Payé'
            ]
        ]
    ]
];

// Vérifier si le client existe
if (!isset($clients[$client_id])) {
    // Rediriger vers la liste des clients si le client n'existe pas
    header('Location: index.php');
    exit;
}

$client = $clients[$client_id];

// Calculer le total des factures
$total_factures = array_reduce($client['factures'], function($carry, $facture) {
    return $carry + $facture['montant'];
}, 0);

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
        <!-- Top header -->
        <div class="bg-white shadow-sm">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">Détails du client</h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Details Content -->
        <div class="container mx-auto px-6 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-6" aria-label="Breadcrumb">
                <ol class="flex text-sm text-gray-600">
                    <li>
                        <a href="<?php echo $root_path; ?>/dashboard.php" class="hover:text-indigo-600">Tableau de bord</a>
                    </li>
                    <li class="mx-2">/</li>
                    <li>
                        <a href="index.php" class="hover:text-indigo-600">Clients</a>
                    </li>
                    <li class="mx-2">/</li>
                    <li class="text-gray-800 font-medium">Détails</li>
                </ol>
            </nav>
            
            <!-- Client Header -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center mb-4 md:mb-0">
                            <div class="h-16 w-16 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xl font-semibold mr-4">
                                <?php echo substr($client['prenom'], 0, 1) . substr($client['nom'], 0, 1); ?>
                            </div>
                            <div>
                                <h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></h2>
                                <p class="text-gray-600">Client depuis le <?php echo date('d/m/Y', strtotime($client['date_creation'])); ?></p>
                                <div class="flex items-center mt-1">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo strtotime($client['derniere_visite']) > strtotime('-3 months') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?> mr-2">
                                        <?php echo strtotime($client['derniere_visite']) > strtotime('-3 months') ? 'Client actif' : 'Inactif depuis ' . date('d/m/Y', strtotime($client['derniere_visite'])); ?>
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?php echo $client['nb_vehicules']; ?> véhicule<?php echo $client['nb_vehicules'] > 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <a href="edit.php?id=<?php echo $client['id']; ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Modifier
                            </a>
                            <button onclick="window.print()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                </svg>
                                Imprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Client Info and Tabs -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Left Column - Client Info -->
                <div class="md:col-span-1">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Informations de contact</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-gray-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="text-gray-800"><?php echo htmlspecialchars($client['email']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-gray-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-gray-500">Téléphone</p>
                                        <p class="text-gray-800"><?php echo htmlspecialchars($client['telephone']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-gray-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-gray-500">Adresse</p>
                                        <p class="text-gray-800"><?php echo htmlspecialchars($client['adresse']); ?></p>
                                        <p class="text-gray-800"><?php echo htmlspecialchars($client['code_postal'] . ' ' . $client['ville']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Actions rapides</h3>
                                
                                <div class="space-y-2">
                                    <a href="<?php echo $root_path; ?>/vehicles/create.php?client_id=<?php echo $client['id']; ?>" class="flex items-center text-indigo-600 hover:text-indigo-800 transition">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Ajouter un véhicule
                                    </a>
                                    
                                    <a href="<?php echo $root_path; ?>/interventions/create.php?client_id=<?php echo $client['id']; ?>" class="flex items-center text-indigo-600 hover:text-indigo-800 transition">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Créer une intervention
                                    </a>
                                    
                                    <a href="<?php echo $root_path; ?>/invoices/create.php?client_id=<?php echo $client['id']; ?>" class="flex items-center text-indigo-600 hover:text-indigo-800 transition">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Créer une facture
                                    </a>
                                    
                                    <a href="#" onclick="sendEmail()" class="flex items-center text-indigo-600 hover:text-indigo-800 transition">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        Envoyer un email
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Statistiques</h3>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-xs text-gray-500">Véhicules</div>
                                        <div class="text-xl font-semibold text-indigo-600"><?php echo $client['nb_vehicules']; ?></div>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-xs text-gray-500">Interventions</div>
                                        <div class="text-xl font-semibold text-indigo-600"><?php echo count($client['interventions']); ?></div>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-xs text-gray-500">Factures</div>
                                        <div class="text-xl font-semibold text-indigo-600"><?php echo count($client['factures']); ?></div>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-xs text-gray-500">Total facturé</div>
                                        <div class="text-xl font-semibold text-indigo-600"><?php echo number_format($total_factures, 2, ',', ' '); ?> €</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Tabs for Vehicles, Interventions, Invoices -->
                <div class="md:col-span-2">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Tabs Header -->
                        <div class="border-b border-gray-200">
                            <nav class="flex -mb-px">
                                <button id="tab-vehicles" class="tab-button active text-indigo-600 border-indigo-500 py-4 px-6 font-medium border-b-2">
                                    Véhicules (<?php echo count($client['vehicules']); ?>)
                                </button>
                                <button id="tab-interventions" class="tab-button text-gray-500 hover:text-gray-700 py-4 px-6 font-medium border-b-2 border-transparent hover:border-gray-300">
                                    Interventions (<?php echo count($client['interventions']); ?>)
                                </button>
                                <button id="tab-invoices" class="tab-button text-gray-500 hover:text-gray-700 py-4 px-6 font-medium border-b-2 border-transparent hover:border-gray-300">
                                    Factures (<?php echo count($client['factures']); ?>)
                                </button>
                            </nav>
                        </div>
                        
                        <!-- Tab Content -->
                        <div class="p-6">
                            <!-- Vehicles Tab -->
                            <div id="content-vehicles" class="tab-content block">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-800">Liste des véhicules</h3>
                                    <a href="<?php echo $root_path; ?>/vehicles/create.php?client_id=<?php echo $client['id']; ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Ajouter
                                    </a>
                                </div>
                                
                                <?php if (empty($client['vehicules'])): ?>
                                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                                        <p class="text-gray-600">Aucun véhicule enregistré pour ce client.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Véhicule
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Immatriculation
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Année / Kilométrage
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Dernière intervention
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($client['vehicules'] as $vehicle): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($vehicle['marque'] . ' ' . $vehicle['modele']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($vehicle['immatriculation']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo $vehicle['annee']; ?></div>
                                                            <div class="text-sm text-gray-500"><?php echo number_format($vehicle['kilometrage'], 0, ',', ' '); ?> km</div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($vehicle['derniere_intervention'])); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <div class="flex justify-end space-x-2">
                                                                <a href="<?php echo $root_path; ?>/vehicles/view.php?id=<?php echo $vehicle['id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Voir">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                    </svg>
                                                                </a>
                                                                <a href="<?php echo $root_path; ?>/vehicles/edit.php?id=<?php echo $vehicle['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Modifier">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                    </svg>
                                                                </a>
                                                                <a href="<?php echo $root_path; ?>/interventions/create.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="text-green-600 hover:text-green-900" title="Ajouter intervention">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                                    </svg>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Interventions Tab -->
                            <div id="content-interventions" class="tab-content hidden">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-800">Interventions</h3>
                                    <a href="<?php echo $root_path; ?>/interventions/create.php?client_id=<?php echo $client['id']; ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Ajouter
                                    </a>
                                </div>
                                
                                <?php if (empty($client['interventions'])): ?>
                                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                                        <p class="text-gray-600">Aucune intervention enregistrée pour ce client.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Date
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Véhicule
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Type
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Montant
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Statut
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($client['interventions'] as $intervention): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($intervention['date'])); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($intervention['vehicule']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($intervention['type']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo number_format($intervention['montant'], 2, ',', ' '); ?> €</div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                <?php 
                                                                switch ($intervention['statut']) {
                                                                    case 'Terminé':
                                                                        echo 'bg-green-100 text-green-800';
                                                                        break;
                                                                    case 'En cours':
                                                                        echo 'bg-yellow-100 text-yellow-800';
                                                                        break;
                                                                    case 'Planifié':
                                                                        echo 'bg-blue-100 text-blue-800';
                                                                        break;
                                                                    default:
                                                                        echo 'bg-gray-100 text-gray-800';
                                                                }
                                                                ?>">
                                                                <?php echo htmlspecialchars($intervention['statut']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <div class="flex justify-end space-x-2">
                                                                <a href="<?php echo $root_path; ?>/interventions/view.php?id=<?php echo $intervention['id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Voir">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                    </svg>
                                                                </a>
                                                                <a href="<?php echo $root_path; ?>/interventions/edit.php?id=<?php echo $intervention['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Modifier">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                    </svg>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Invoices Tab -->
                            <div id="content-invoices" class="tab-content hidden">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-800">Factures</h3>
                                    <a href="<?php echo $root_path; ?>/invoices/create.php?client_id=<?php echo $client['id']; ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Ajouter
                                    </a>
                                </div>
                                
                                <?php if (empty($client['factures'])): ?>
                                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                                        <p class="text-gray-600">Aucune facture enregistrée pour ce client.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Numéro
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Date
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Montant
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Statut
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($client['factures'] as $facture): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($facture['numero']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($facture['date'])); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900"><?php echo number_format($facture['montant'], 2, ',', ' '); ?> €</div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                <?php 
                                                                switch ($facture['statut']) {
                                                                    case 'Payé':
                                                                        echo 'bg-green-100 text-green-800';
                                                                        break;
                                                                    case 'En attente':
                                                                        echo 'bg-yellow-100 text-yellow-800';
                                                                        break;
                                                                    case 'Retard':
                                                                        echo 'bg-red-100 text-red-800';
                                                                        break;
                                                                    default:
                                                                        echo 'bg-gray-100 text-gray-800';
                                                                }
                                                                ?>">
                                                                <?php echo htmlspecialchars($facture['statut']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <div class="flex justify-end space-x-2">
                                                                <a href="<?php echo $root_path; ?>/invoices/view.php?id=<?php echo $facture['id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Voir">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                    </svg>
                                                                </a>
                                                                <a href="<?php echo $root_path; ?>/invoices/print.php?id=<?php echo $facture['id']; ?>" class="text-green-600 hover:text-green-900" title="Imprimer">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                                                    </svg>
                                                                </a>
                                                                <a href="#" onclick="sendInvoiceEmail(<?php echo $facture['id']; ?>)" class="text-blue-600 hover:text-blue-900" title="Envoyer par email">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                                    </svg>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for tabs functionality -->
<script>
    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Get the target content id
                const target = button.id.replace('tab-', 'content-');
                
                // Remove active class from all buttons and add to clicked button
                tabButtons.forEach(btn => btn.classList.remove('active', 'text-indigo-600', 'border-indigo-500'));
                tabButtons.forEach(btn => btn.classList.add('text-gray-500', 'border-transparent'));
                button.classList.remove('text-gray-500', 'border-transparent');
                button.classList.add('active', 'text-indigo-600', 'border-indigo-500');
                
                // Hide all tab contents and show the target content
                tabContents.forEach(content => content.classList.add('hidden'));
                document.getElementById(target).classList.remove('hidden');
            });
        });
    });

    // Email sending functionality (simulate)
    function sendEmail() {
        alert('Un email a été envoyé à <?php echo htmlspecialchars($client['email']); ?>');
    }
    
    // Invoice email sending functionality (simulate)
    function sendInvoiceEmail(invoiceId) {
        alert('La facture a été envoyée par email à <?php echo htmlspecialchars($client['email']); ?>');
    }
</script>

<?php include $root_path . '/includes/footer.php'; ?>