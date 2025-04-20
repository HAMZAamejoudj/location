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

// Créer une connexion à la base de données pour récupérer les catégories
$database = new Database();
$db = $database->getConnection();

// Afficher les messages de débogage si activé
if ($debug) {
    echo '<div style="position: fixed; bottom: 0; right: 0; z-index: 9999; background: rgba(0,0,0,0.8); color: lime; font-family: monospace; font-size: 12px; padding: 10px; max-width: 50%; max-height: 50%; overflow: auto;">';
    echo '<h3>Debug Info:</h3>';
    echo '<ul>';
    foreach ($debugInfo as $info) {
        echo '<li>' . $info . '</li>';
    }
    echo '</ul>';
    
    // Afficher les données de session
    echo '<h3>Session Data:</h3>';
    echo '<pre>';
    print_r($_SESSION);
    echo '</pre>';
    
    echo '</div>';
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            900: '#0c4a6e',
                        },
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                    }
                }
            }
        }
    </script>
<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

     <!-- Main Content -->
     <main class="flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Informations du Véhicule</h2>
                        <p class="mt-1 text-sm text-gray-600">Ajoutez un nouveau véhicule à votre flotte.</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="./index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </a>
                        <button type="reset" form="vehicleForm" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                            <i class="fas fa-redo mr-2"></i>Réinitialiser
                        </button>
                        <button type="submit" form="vehicleForm" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none">
                            <i class="fas fa-plus mr-2"></i>Ajouter
                        </button>
                    </div>
                </div>

                <!-- Affichage des messages flash s'il y en a -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['flash_message']['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $_SESSION['flash_message']['message']; ?>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>

                <!-- Form Content -->
                <div class="bg-white shadow rounded-lg" x-data="{ activeTab: 'basic' }">
                    <!-- Tabs -->
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button @click="activeTab = 'basic'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'basic', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'basic' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                                <i class="fas fa-car mr-2"></i>Infos de Base
                            </button>
                            <button @click="activeTab = 'details'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'details', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'details' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                                <i class="fas fa-info-circle mr-2"></i>Détails
                            </button>
                            <button @click="activeTab = 'offre'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'offre', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'offre' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                                <i class="fas fa-tag mr-2"></i>Offre
                            </button>
                            <button @click="activeTab = 'history'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'history', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'history' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                                <i class="fas fa-history mr-2"></i>Historique
                            </button>
                        </nav>
                    </div>

                    <!-- Form wrapper -->
                    <form id="vehicleForm" action="process_voiture.php" method="POST" enctype="multipart/form-data">
                        <!-- Basic Info Tab -->
                        <div x-show="activeTab === 'basic'" class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                
                                    <div>
                                        <label for="registration" class="block text-sm font-medium text-gray-700">Numéro d'Immatriculation <span class="text-red-500">*</span></label>
                                        <input type="text" id="registration" name="immatriculation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required value="<?php echo isset($_SESSION['form_data']['immatriculation']) ? htmlspecialchars($_SESSION['form_data']['immatriculation']) : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="brand" class="block text-sm font-medium text-gray-700">Marque <span class="text-red-500">*</span></label>
                                        <select id="brand" name="marque" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none" required>
                                            <option value="">:: MARQUE ::</option>
                                            <?php 
                                            $brands = ['audi' => 'AUDI', 'bmw' => 'BMW', 'citroen' => 'CITROEN', 'dacia' => 'DACIA', 'fiat' => 'FIAT', 'ford' => 'FORD', 'honda' => 'HONDA', 'hyundai' => 'HYUNDAI', 'kia' => 'KIA', 'mercedes' => 'MERCEDES', 'nissan' => 'NISSAN', 'opel' => 'OPEL', 'peugeot' => 'PEUGEOT', 'renault' => 'RENAULT', 'seat' => 'SEAT', 'toyota' => 'TOYOTA', 'volkswagen' => 'VOLKSWAGEN', 'autre' => 'Autre'];
                                            foreach ($brands as $value => $label): 
                                                $selected = isset($_SESSION['form_data']['marque']) && $_SESSION['form_data']['marque'] === $value ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="model" class="block text-sm font-medium text-gray-700">Modèle <span class="text-red-500">*</span></label>
                                        <input type="text" id="model" name="modele" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required value="<?php echo isset($_SESSION['form_data']['modele']) ? htmlspecialchars($_SESSION['form_data']['modele']) : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="color" class="block text-sm font-medium text-gray-700">Couleur <span class="text-red-500">*</span></label>
                                        <input type="text" id="color" name="couleur" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required value="<?php echo isset($_SESSION['form_data']['couleur']) ? htmlspecialchars($_SESSION['form_data']['couleur']) : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700">Statut <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <select id="status" name="statut" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none" required>
                                                <option value="available" <?php echo (isset($_SESSION['form_data']['statut']) && $_SESSION['form_data']['statut'] === 'available') ? 'selected' : ''; ?>>Disponible</option>
                                                <option value="unavailable" <?php echo (isset($_SESSION['form_data']['statut']) && $_SESSION['form_data']['statut'] === 'unavailable') ? 'selected' : ''; ?>>Indisponible</option>
                                                <option value="sold" <?php echo (isset($_SESSION['form_data']['statut']) && $_SESSION['form_data']['statut'] === 'sold') ? 'selected' : ''; ?>>Vendu</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="categorie" class="block text-sm font-medium text-gray-700">Catégorie <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <select id="categorie" name="id_categorie" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none" required>
                                                <option value="">Sélectionner une catégorie</option>
                                                <?php
                                                // Récupérer les catégories depuis la base de données
                                                try {
                                                    $query = "SELECT id, nom FROM categorie ORDER BY nom";
                                                    $stmt = $db->prepare($query);
                                                    $stmt->execute();
                                                    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                    foreach ($categories as $category): 
                                                        $selected = isset($_SESSION['form_data']['id_categorie']) && $_SESSION['form_data']['id_categorie'] == $category['id'] ? 'selected' : '';
                                                    ?>
                                                        <option value="<?php echo $category['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($category['nom']); ?></option>
                                                    <?php endforeach;
                                                } catch (PDOException $e) {
                                                    echo '<option value="">Erreur: Impossible de charger les catégories</option>';
                                                    if($debug) error_log("Erreur de récupération des catégories: " . $e->getMessage());
                                                }
                                                ?>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="station" class="block text-sm font-medium text-gray-700">Station <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <select id="station" name="station" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none" required>
                                                <option value="casablanca_airport" <?php echo (isset($_SESSION['form_data']['station']) && $_SESSION['form_data']['station'] === 'casablanca_airport') ? 'selected' : ''; ?>>Casablanca Aéroport</option>
                                                <option value="casablanca_downtown" <?php echo (isset($_SESSION['form_data']['station']) && $_SESSION['form_data']['station'] === 'casablanca_downtown') ? 'selected' : ''; ?>>Casablanca Centre-ville</option>
                                                <option value="rabat" <?php echo (isset($_SESSION['form_data']['station']) && $_SESSION['form_data']['station'] === 'rabat') ? 'selected' : ''; ?>>Rabat Centre</option>
                                                <option value="marrakech" <?php echo (isset($_SESSION['form_data']['station']) && $_SESSION['form_data']['station'] === 'marrakech') ? 'selected' : ''; ?>>Marrakech Aéroport</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="owner" class="block text-sm font-medium text-gray-700">Propriétaire <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <select id="owner" name="proprietaire" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none" required>
                                                <option value="">Sélectionner un propriétaire</option>
                                                <option value="1" <?php echo (isset($_SESSION['form_data']['proprietaire']) && $_SESSION['form_data']['proprietaire'] == '1') ? 'selected' : ''; ?>>Entreprise XYZ</option>
                                                <option value="2" <?php echo (isset($_SESSION['form_data']['proprietaire']) && $_SESSION['form_data']['proprietaire'] == '2') ? 'selected' : ''; ?>>Mohammed Alami</option>
                                                <option value="3" <?php echo (isset($_SESSION['form_data']['proprietaire']) && $_SESSION['form_data']['proprietaire'] == '3') ? 'selected' : ''; ?>>Sara Bennani</option>
                                                <option value="4" <?php echo (isset($_SESSION['form_data']['proprietaire']) && $_SESSION['form_data']['proprietaire'] == '4') ? 'selected' : ''; ?>>Société ABC</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="year" class="block text-sm font-medium text-gray-700">Année <span class="text-red-500">*</span></label>
                                        <input type="number" id="year" name="annee" value="<?php echo isset($_SESSION['form_data']['annee']) ? htmlspecialchars($_SESSION['form_data']['annee']) : date('Y'); ?>" min="1900" max="<?php echo date('Y') + 1; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"><?php echo isset($_SESSION['form_data']['notes']) ? htmlspecialchars($_SESSION['form_data']['notes']) : ''; ?></textarea>
                                </div>

                                <div class="mt-6 flex items-center">
                                    <input id="backToBase" name="retour_base" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" <?php echo (isset($_SESSION['form_data']['retour_base']) && $_SESSION['form_data']['retour_base']) ? 'checked' : ''; ?>>
                                    <label for="backToBase" class="ml-2 block text-sm text-gray-700">Retour à la Base</label>
                                </div>
                        </div>

                        <!-- Details Tab -->
                        <div x-show="activeTab === 'details'" class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label for="fuelType" class="block text-sm font-medium text-gray-700">Type de Carburant</label>
                                        <div class="relative">
                                            <select id="fuelType" name="type_carburant" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none">
                                                <option value="">Sélectionner...</option>
                                                <option value="essence" <?php echo (isset($_SESSION['form_data']['type_carburant']) && $_SESSION['form_data']['type_carburant'] === 'essence') ? 'selected' : ''; ?>>Essence</option>
                                                <option value="diesel" <?php echo (isset($_SESSION['form_data']['type_carburant']) && $_SESSION['form_data']['type_carburant'] === 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                                <option value="electrique" <?php echo (isset($_SESSION['form_data']['type_carburant']) && $_SESSION['form_data']['type_carburant'] === 'electrique') ? 'selected' : ''; ?>>Électrique</option>
                                                <option value="hybride" <?php echo (isset($_SESSION['form_data']['type_carburant']) && $_SESSION['form_data']['type_carburant'] === 'hybride') ? 'selected' : ''; ?>>Hybride</option>
                                                <option value="gpl" <?php echo (isset($_SESSION['form_data']['type_carburant']) && $_SESSION['form_data']['type_carburant'] === 'gpl') ? 'selected' : ''; ?>>GPL</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="odometer" class="block text-sm font-medium text-gray-700">Kilométrage (Km)</label>
                                        <input type="number" id="odometer" name="kilometres" min="0" value="<?php echo isset($_SESSION['form_data']['kilometres']) ? htmlspecialchars($_SESSION['form_data']['kilometres']) : '0'; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    </div>
                                    <div>
                                        <label for="seats" class="block text-sm font-medium text-gray-700">Nombre de Places</label>
                                        <input type="number" id="seats" name="nombre_places" min="1" value="<?php echo isset($_SESSION['form_data']['nombre_places']) ? htmlspecialchars($_SESSION['form_data']['nombre_places']) : '5'; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    </div>
                                    <div>
                                        <label for="doors" class="block text-sm font-medium text-gray-700">Nombre de Portes</label>
                                        <input type="number" id="doors" name="nombre_portes" min="2" value="<?php echo isset($_SESSION['form_data']['nombre_portes']) ? htmlspecialchars($_SESSION['form_data']['nombre_portes']) : '4'; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    </div>
                                    <div>
                                        <label for="engine" class="block text-sm font-medium text-gray-700">Moteur (cc)</label>
                                        <input type="number" id="engine" name="cylindree_moteur" min="0" placeholder="1600" value="<?php echo isset($_SESSION['form_data']['cylindree_moteur']) ? htmlspecialchars($_SESSION['form_data']['cylindree_moteur']) : ''; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    </div>
                                    <div>
                                        <label for="puissance" class="block text-sm font-medium text-gray-700">Puissance (CV)</label>
                                        <input type="number" id="puissance" name="puissance" min="0" placeholder="90" value="<?php echo isset($_SESSION['form_data']['puissance']) ? htmlspecialchars($_SESSION['form_data']['puissance']) : ''; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-6 mb-6">
                                    <div class="flex items-center">
                                        <input id="automatic" name="est_automatique" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" <?php echo (isset($_SESSION['form_data']['est_automatique']) && $_SESSION['form_data']['est_automatique']) ? 'checked' : ''; ?>>
                                        <label for="automatic" class="ml-2 block text-sm text-gray-700">Automatique</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="airCondition" name="a_climatisation" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" <?php echo (isset($_SESSION['form_data']['a_climatisation']) && $_SESSION['form_data']['a_climatisation']) ? 'checked' : ''; ?>>
                                        <label for="airCondition" class="ml-2 block text-sm text-gray-700">Climatisation</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="radio" name="a_radio" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" <?php echo (isset($_SESSION['form_data']['a_radio']) && $_SESSION['form_data']['a_radio']) ? 'checked' : ''; ?>>
                                        <label for="radio" class="ml-2 block text-sm text-gray-700">Radio/CD</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="gps" name="a_gps" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" <?php echo (isset($_SESSION['form_data']['a_gps']) && $_SESSION['form_data']['a_gps']) ? 'checked' : ''; ?>>
                                        <label for="gps" class="ml-2 block text-sm text-gray-700">GPS</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="bluetooth" name="a_bluetooth" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" <?php echo (isset($_SESSION['form_data']['a_bluetooth']) && $_SESSION['form_data']['a_bluetooth']) ? 'checked' : ''; ?>>
                                        <label for="bluetooth" class="ml-2 block text-sm text-gray-700">Bluetooth</label>
                                        </div>
                                </div>

                                <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">Assurance</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label for="insurance" class="block text-sm font-medium text-gray-700">Compagnie d'Assurance</label>
                                        <div class="relative">
                                            <select id="insurance" name="assureur" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 appearance-none">
                                                <option value="">Sélectionner...</option>
                                                <option value="axa" <?php echo (isset($_SESSION['form_data']['assureur']) && $_SESSION['form_data']['assureur'] === 'axa') ? 'selected' : ''; ?>>AXA Assurance</option>
                                                <option value="allianz" <?php echo (isset($_SESSION['form_data']['assureur']) && $_SESSION['form_data']['assureur'] === 'allianz') ? 'selected' : ''; ?>>Allianz</option>
                                                <option value="wafa" <?php echo (isset($_SESSION['form_data']['assureur']) && $_SESSION['form_data']['assureur'] === 'wafa') ? 'selected' : ''; ?>>Wafa Assurance</option>
                                                <option value="saham" <?php echo (isset($_SESSION['form_data']['assureur']) && $_SESSION['form_data']['assureur'] === 'saham') ? 'selected' : ''; ?>>Saham Assurance</option>
                                                <option value="rma" <?php echo (isset($_SESSION['form_data']['assureur']) && $_SESSION['form_data']['assureur'] === 'rma') ? 'selected' : ''; ?>>RMA Assurance</option>
                                                <option value="autre" <?php echo (isset($_SESSION['form_data']['assureur']) && $_SESSION['form_data']['assureur'] === 'autre') ? 'selected' : ''; ?>>Autre</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="insuranceStart" class="block text-sm font-medium text-gray-700">Date de Début</label>
                                        <div class="relative mt-1">
                                            <input type="text" id="insuranceStart" name="date_debut_assurance" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pl-10" placeholder="JJ/MM/AAAA" value="<?php echo isset($_SESSION['form_data']['date_debut_assurance']) ? htmlspecialchars($_SESSION['form_data']['date_debut_assurance']) : ''; ?>">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="far fa-calendar-alt text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="insuranceEnd" class="block text-sm font-medium text-gray-700">Date de Fin</label>
                                        <div class="relative mt-1">
                                            <input type="text" id="insuranceEnd" name="date_fin_assurance" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pl-10" placeholder="JJ/MM/AAAA" value="<?php echo isset($_SESSION['form_data']['date_fin_assurance']) ? htmlspecialchars($_SESSION['form_data']['date_fin_assurance']) : ''; ?>">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="far fa-calendar-alt text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">KTEO (Contrôle Technique)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="kteoStart" class="block text-sm font-medium text-gray-700">Date de Début</label>
                                        <div class="relative mt-1">
                                            <input type="text" id="kteoStart" name="date_debut_kteo" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pl-10" placeholder="JJ/MM/AAAA" value="<?php echo isset($_SESSION['form_data']['date_debut_kteo']) ? htmlspecialchars($_SESSION['form_data']['date_debut_kteo']) : ''; ?>">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="far fa-calendar-alt text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="kteoEnd" class="block text-sm font-medium text-gray-700">Date de Fin</label>
                                        <div class="relative mt-1">
                                            <input type="text" id="kteoEnd" name="date_fin_kteo" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pl-10" placeholder="JJ/MM/AAAA" value="<?php echo isset($_SESSION['form_data']['date_fin_kteo']) ? htmlspecialchars($_SESSION['form_data']['date_fin_kteo']) : ''; ?>">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="far fa-calendar-alt text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">EOT (Vignette)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="eotStart" class="block text-sm font-medium text-gray-700">Date de Début</label>
                                        <div class="relative mt-1">
                                            <input type="text" id="eotStart" name="date_debut_eot" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pl-10" placeholder="JJ/MM/AAAA" value="<?php echo isset($_SESSION['form_data']['date_debut_eot']) ? htmlspecialchars($_SESSION['form_data']['date_debut_eot']) : ''; ?>">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="far fa-calendar-alt text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="eotEnd" class="block text-sm font-medium text-gray-700">Date de Fin</label>
                                        <div class="relative mt-1">
                                            <input type="text" id="eotEnd" name="date_fin_eot" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pl-10" placeholder="JJ/MM/AAAA" value="<?php echo isset($_SESSION['form_data']['date_fin_eot']) ? htmlspecialchars($_SESSION['form_data']['date_fin_eot']) : ''; ?>">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="far fa-calendar-alt text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>

                        <!-- Offre Tab -->
                        <div x-show="activeTab === 'offre'" class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Détails de l'Offre</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="prix" class="block text-sm font-medium text-gray-700">Prix (DH) <span class="text-red-500">*</span></label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number" name="prix" id="prix" min="0" step="0.01" class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-3 pr-12 sm:text-sm border-gray-300 rounded-md" placeholder="0.00" value="<?php echo isset($_SESSION['form_data']['prix']) ? htmlspecialchars($_SESSION['form_data']['prix']) : ''; ?>" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">DH</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="prix_solde" class="block text-sm font-medium text-gray-700">Prix Soldé (DH)</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number" name="prix_solde" id="prix_solde" min="0" step="0.01" class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-3 pr-12 sm:text-sm border-gray-300 rounded-md" placeholder="0.00" value="<?php echo isset($_SESSION['form_data']['prix_solde']) ? htmlspecialchars($_SESSION['form_data']['prix_solde']) : ''; ?>">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">DH</span>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Laissez vide s'il n'y a pas de solde.</p>
                                </div>
                            </div>

                            <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">Images du Véhicule</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Image Principale</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="image_principale" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none">
                                                    <span>Télécharger un fichier</span>
                                                    <input id="image_principale" name="image_principale" type="file" class="sr-only" accept="image/*">
                                                </label>
                                                <p class="pl-1">ou glisser-déposer</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF jusqu'à 2MB</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Image Secondaire 1</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="image_secondaire_1" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none">
                                                    <span>Télécharger un fichier</span>
                                                    <input id="image_secondaire_1" name="image_secondaire_1" type="file" class="sr-only" accept="image/*">
                                                </label>
                                                <p class="pl-1">ou glisser-déposer</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF jusqu'à 2MB</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Image Secondaire 2</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="image_secondaire_2" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none">
                                                    <span>Télécharger un fichier</span>
                                                    <input id="image_secondaire_2" name="image_secondaire_2" type="file" class="sr-only" accept="image/*">
                                                </label>
                                                <p class="pl-1">ou glisser-déposer</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF jusqu'à 2MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 text-sm text-gray-500">
                                <p>Les images seront redimensionnées automatiquement. Pour de meilleurs résultats, utilisez des images de haute qualité au format paysage.</p>
                            </div>
                        </div>

                        <!-- History Tab -->
                        <div x-show="activeTab === 'history'" class="p-6">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            L'historique sera disponible après l'ajout du véhicule.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-center items-center h-64">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Pas d'historique</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Ajoutez d'abord le véhicule pour voir son historique.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons (visible on all tabs) -->
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                            <a href="./index.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                Annuler
                            </a>
                            <button type="reset" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                <i class="fas fa-redo mr-2"></i>Réinitialiser
                            </button>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none">
                                <i class="fas fa-save mr-2"></i>Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Tips Card -->
                <div class="mt-6 bg-blue-50 rounded-lg p-4 border border-blue-100">
                    <div class="flex items-start">
                        <div class="mr-3">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-blue-800">Conseils pour l'ajout de véhicules</h3>
                            <ul class="mt-2 text-sm text-blue-700 list-disc list-inside">
                                <li>Vérifiez que l'immatriculation est correctement formatée</li>
                                <li>Assurez-vous que toutes les dates d'assurance et de contrôle technique sont à jour</li>
                                <li>Le kilométrage doit être vérifié et mis à jour régulièrement</li>
                                <li>Ajoutez des notes détaillées pour les caractéristiques spécifiques du véhicule</li>
                                <li>Sélectionnez la catégorie appropriée pour faciliter la recherche et le filtrage</li>
                                <li>Pour l'offre, assurez-vous d'ajouter des images de haute qualité et de format paysage</li>
                                <li>Le prix doit être indiqué en dirhams (DH) et être compétitif sur le marché</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialiser Flatpickr pour les sélecteurs de date
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#insuranceStart, #insuranceEnd, #kteoStart, #kteoEnd, #eotStart, #eotEnd", {
                dateFormat: "d/m/Y",
                locale: "fr",
                allowInput: true
            });
            
            // Prévisualisation des images
            const setupImagePreview = function(inputId) {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            const container = this.closest('div.flex.justify-center');
                            
                            reader.onload = function(event) {
                                // Supprimer la prévisualisation précédente si elle existe
                                const existingPreview = container.querySelector('.image-preview');
                                if (existingPreview) {
                                    existingPreview.remove();
                                }
                                
                                // Masquer le contenu SVG et les instructions
                                const svgContainer = container.querySelector('.space-y-1');
                                svgContainer.style.display = 'none';
                                
                                // Créer l'élément de prévisualisation
                                const preview = document.createElement('div');
                                preview.className = 'image-preview relative w-full h-32';
                                preview.style.backgroundImage = `url(${event.target.result})`;
                                preview.style.backgroundSize = 'cover';
                                preview.style.backgroundPosition = 'center';
                                
                                // Ajouter un bouton de suppression
                                const removeBtn = document.createElement('button');
                                removeBtn.className = 'absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center';
                                removeBtn.innerHTML = '×';
                                removeBtn.type = 'button';
                                removeBtn.addEventListener('click', function() {
                                    input.value = '';
                                    preview.remove();
                                    svgContainer.style.display = 'block';
                                });
                                
                                preview.appendChild(removeBtn);
                                container.appendChild(preview);
                            };
                            
                            reader.readAsDataURL(file);
                        }
                    });
                }
            };
            
            // Configurer la prévisualisation pour chaque champ d'image
            setupImagePreview('image_principale');
            setupImagePreview('image_secondaire_1');
            setupImagePreview('image_secondaire_2');
            
            // Créer un événement pour soumettre tous les formulaires ensemble
            document.getElementById('vehicleForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validation supplémentaire si nécessaire
                if (!document.getElementById('registration').value) {
                    alert('Veuillez entrer un numéro d\'immatriculation');
                    return;
                }
                
                if (!document.getElementById('categorie').value) {
                    alert('Veuillez sélectionner une catégorie');
                    return;
                }
                
                // Vérifier si l'onglet Offre est actif et le prix est requis
                // On accède directement à la valeur de l'onglet actif via l'attribut x-data
                const activeTabElement = document.querySelector('[x-data]');
                let activeTab = 'basic'; // Par défaut
                
                if (activeTabElement && activeTabElement.__x) {
                    activeTab = activeTabElement.__x.$data.activeTab;
                }
                
                // Vérifier si l'onglet Offre est actif et si le prix est bien renseigné
                if (activeTab === 'offre' && !document.getElementById('prix').value) {
                    alert('Veuillez entrer un prix pour le véhicule');
                    return;
                }
                
                // Soumettre le formulaire si tout est valide
                this.submit();
            });
        });
    </script>
<?php 
// Nettoyer les données de formulaire après l'affichage
unset($_SESSION['form_data']); 
include $root_path . '/includes/footer.php'; 
?>