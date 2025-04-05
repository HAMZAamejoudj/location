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

// Vérifier si un ID de véhicule est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$vehicule_id = $_GET['id'];

// Récupérer les informations du véhicule
$database = new Database();
$db = $database->getConnection();
$query = "SELECT * FROM vehicules WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $vehicule_id);
$stmt->execute();
$vehicule = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si le véhicule existe
if (!$vehicule) {
    header('Location: index.php');
    exit;
}

// Récupérer la liste des clients pour le formulaire
$query = "SELECT  cl.id as client_id,
                 CASE 
                    WHEN cl.type_client_id = 1 THEN CONCAT(cl.prenom, ' ', cl.nom)
                    ELSE CONCAT(cl.raison_sociale)
                 END AS Nom_Client
                 from clients cl;";
$stmt = $db->prepare($query);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification et nettoyage des données
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $immatriculation = trim($_POST['immatriculation']);
    $client_id = intval($_POST['client_id']);
    $marque = trim($_POST['marque']);
    $modele = trim($_POST['modele']);
    $annee = intval($_POST['annee']);
    $kilometrage = intval($_POST['kilometrage']);
    $couleur = trim($_POST['couleur']);
    $carburant = trim($_POST['carburant']);
    $puissance = intval($_POST['puissance']);
    $date_mise_circulation = !empty($_POST['date_mise_circulation']) ? date('Y-m-d', strtotime($_POST['date_mise_circulation'])) : null;
    $date_derniere_revision = !empty($_POST['date_derniere_revision']) ? date('Y-m-d', strtotime($_POST['date_derniere_revision'])) : null;
    $date_prochain_ct = !empty($_POST['date_prochain_ct']) ? date('Y-m-d', strtotime($_POST['date_prochain_ct'])) : null;
    $statut = trim($_POST['statut']);
    $notes = trim($_POST['notes']);

    if ($id > 0) {
        try {
            $query = "UPDATE vehicules SET immatriculation = :immatriculation, client_id = :client_id, marque = :marque, 
                      modele = :modele, annee = :annee, kilometrage = :kilometrage, couleur = :couleur, carburant = :carburant, 
                      puissance = :puissance, date_mise_circulation = :date_mise_circulation, date_derniere_revision = :date_derniere_revision, 
                      date_prochain_ct = :date_prochain_ct, statut = :statut, notes = :notes WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':immatriculation', $immatriculation);
            $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt->bindParam(':marque', $marque);
            $stmt->bindParam(':modele', $modele);
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->bindParam(':kilometrage', $kilometrage, PDO::PARAM_INT);
            $stmt->bindParam(':couleur', $couleur);
            $stmt->bindParam(':carburant', $carburant);
            $stmt->bindParam(':puissance', $puissance, PDO::PARAM_INT);
            $stmt->bindParam(':date_mise_circulation', $date_mise_circulation);
            $stmt->bindParam(':date_derniere_revision', $date_derniere_revision);
            $stmt->bindParam(':date_prochain_ct', $date_prochain_ct);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':notes', $notes);
            
            if ($stmt->execute()) {
                $success = true;
                header("Location: view.php?id=" . $id);
                exit;
            } else {
                $errors['database'] = "Erreur lors de la mise à jour du véhicule.";
            }
        } catch (PDOException $e) {
            $errors['database'] = "Erreur: " . $e->getMessage();
        }
    } else {
        $errors['id'] = "ID de véhicule invalide.";
    }
}

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
                <h1 class="text-2xl font-semibold text-gray-800">Modifier un véhicule</h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Vehicle Form -->
        <div class="container mx-auto px-6 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-6" aria-label="Breadcrumb">
                <ol class="flex text-sm text-gray-600">
                    <li>
                        <a href="<?php echo $root_path; ?>/dashboard.php" class="hover:text-indigo-600">Tableau de bord</a>
                    </li>
                    <li class="mx-2">/</li>
                    <li>
                        <a href="index.php" class="hover:text-indigo-600">Véhicules</a>
                    </li>
                    <li class="mx-2">/</li>
                    <li class="text-gray-800 font-medium">Modifier</li>
                </ol>
            </nav>
            
            <!-- Error Message -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'true'): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    <p class="font-bold">Erreur</p>
                    <p>Une erreur s'est produite lors de la mise à jour du véhicule.</p>
                </div>
            <?php endif; ?>
            
            <!-- Form Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-6">Informations du véhicule</h2>
                    
                    <form action="edit.php" method="POST" data-validate="true" id="edit-vehicle-form">
                        <input type="hidden" name="id" value="<?php echo $vehicule_id; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Immatriculation -->
                            <div>
                                <label for="immatriculation" class="block text-sm font-medium text-gray-700 mb-1">Immatriculation <span class="text-red-500">*</span></label>
                                <input type="text" id="immatriculation" name="immatriculation" value="<?php echo htmlspecialchars($vehicule['immatriculation']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['immatriculation']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="AB-123-CD" required>
                                <?php if (isset($errors['immatriculation'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['immatriculation']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Client -->
                            <div>
                                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                                <select id="client_id" name="client_id" class="w-full px-4 py-2 border <?php echo isset($errors['client_id']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                    <option value="">Sélectionner un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['client_id']; ?>" <?php echo ($vehicule['client_id'] == $client['client_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['Nom_Client']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['client_id'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['client_id']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Marque -->
                            <div>
                                <label for="marque" class="block text-sm font-medium text-gray-700 mb-1">Marque <span class="text-red-500">*</span></label>
                                <select id="marque" name="marque" class="w-full px-4 py-2 border <?php echo isset($errors['marque']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                    <option value="">:: MARQUE ::</option>
                                    <option value="abarth" <?php echo ($vehicule['marque'] == 'abarth') ? 'selected' : ''; ?>>ABARTH</option>
                                    <option value="alfa_romeo" <?php echo ($vehicule['marque'] == 'alfa_romeo') ? 'selected' : ''; ?>>ALFA ROMEO</option>
                                    <option value="audi" <?php echo ($vehicule['marque'] == 'audi') ? 'selected' : ''; ?>>AUDI</option>
                                    <option value="bmw" <?php echo ($vehicule['marque'] == 'bmw') ? 'selected' : ''; ?>>BMW</option>
                                    <option value="byd" <?php echo ($vehicule['marque'] == 'byd') ? 'selected' : ''; ?>>BYD</option>
                                    <option value="changan" <?php echo ($vehicule['marque'] == 'changan') ? 'selected' : ''; ?>>CHANGAN</option>
                                    <option value="chery" <?php echo ($vehicule['marque'] == 'chery') ? 'selected' : ''; ?>>CHERY</option>
                                    <option value="citroen" <?php echo ($vehicule['marque'] == 'citroen') ? 'selected' : ''; ?>>CITROEN</option>
                                    <option value="cupra" <?php echo ($vehicule['marque'] == 'cupra') ? 'selected' : ''; ?>>CUPRA</option>
                                    <option value="dacia" <?php echo ($vehicule['marque'] == 'dacia') ? 'selected' : ''; ?>>DACIA</option>
                                    <option value="dfsk" <?php echo ($vehicule['marque'] == 'dfsk') ? 'selected' : ''; ?>>DFSK</option>
                                    <option value="ds" <?php echo ($vehicule['marque'] == 'ds') ? 'selected' : ''; ?>>DS</option>
                                    <option value="fiat" <?php echo ($vehicule['marque'] == 'fiat') ? 'selected' : ''; ?>>FIAT</option>
                                    <option value="ford" <?php echo ($vehicule['marque'] == 'ford') ? 'selected' : ''; ?>>FORD</option>
                                    <option value="geely" <?php echo ($vehicule['marque'] == 'geely') ? 'selected' : ''; ?>>GEELY</option>
                                    <option value="gwm" <?php echo ($vehicule['marque'] == 'gwm') ? 'selected' : ''; ?>>GWM</option>
                                    <option value="honda" <?php echo ($vehicule['marque'] == 'honda') ? 'selected' : ''; ?>>HONDA</option>
                                    <option value="hyundai" <?php echo ($vehicule['marque'] == 'hyundai') ? 'selected' : ''; ?>>HYUNDAI</option>
                                    <option value="jaecoo" <?php echo ($vehicule['marque'] == 'jaecoo') ? 'selected' : ''; ?>>JAECOO</option>
                                    <option value="jaguar" <?php echo ($vehicule['marque'] == 'jaguar') ? 'selected' : ''; ?>>JAGUAR</option>
                                    <option value="jeep" <?php echo ($vehicule['marque'] == 'jeep') ? 'selected' : ''; ?>>JEEP</option>
                                    <option value="kia" <?php echo ($vehicule['marque'] == 'kia') ? 'selected' : ''; ?>>KIA</option>
                                    <option value="land_rover" <?php echo ($vehicule['marque'] == 'land_rover') ? 'selected' : ''; ?>>LAND ROVER</option>
                                    <option value="lexus" <?php echo ($vehicule['marque'] == 'lexus') ? 'selected' : ''; ?>>LEXUS</option>
                                    <option value="mahindra" <?php echo ($vehicule['marque'] == 'mahindra') ? 'selected' : ''; ?>>MAHINDRA</option>
                                    <option value="maserati" <?php echo ($vehicule['marque'] == 'maserati') ? 'selected' : ''; ?>>MASERATI</option>
                                    <option value="mazda" <?php echo ($vehicule['marque'] == 'mazda') ? 'selected' : ''; ?>>MAZDA</option>
                                    <option value="mercedes" <?php echo ($vehicule['marque'] == 'mercedes') ? 'selected' : ''; ?>>MERCEDES</option>
                                    <option value="mg" <?php echo ($vehicule['marque'] == 'mg') ? 'selected' : ''; ?>>MG</option>
                                    <option value="mini" <?php echo ($vehicule['marque'] == 'mini') ? 'selected' : ''; ?>>MINI</option>
                                    <option value="mitsubishi" <?php echo ($vehicule['marque'] == 'mitsubishi') ? 'selected' : ''; ?>>MITSUBISHI</option>
                                    <option value="nissan" <?php echo ($vehicule['marque'] == 'nissan') ? 'selected' : ''; ?>>NISSAN</option>
                                    <option value="omoda" <?php echo ($vehicule['marque'] == 'omoda') ? 'selected' : ''; ?>>OMODA</option>
                                    <option value="opel" <?php echo ($vehicule['marque'] == 'opel') ? 'selected' : ''; ?>>OPEL</option>
                                    <option value="peugeot" <?php echo ($vehicule['marque'] == 'peugeot') ? 'selected' : ''; ?>>PEUGEOT</option>
                                    <option value="porsche" <?php echo ($vehicule['marque'] == 'porsche') ? 'selected' : ''; ?>>PORSCHE</option>
                                    <option value="renault" <?php echo ($vehicule['marque'] == 'renault') ? 'selected' : ''; ?>>RENAULT</option>
                                    <option value="seat" <?php echo ($vehicule['marque'] == 'seat') ? 'selected' : ''; ?>>SEAT</option>
                                    <option value="seres" <?php echo ($vehicule['marque'] == 'seres') ? 'selected' : ''; ?>>SERES</option>
                                    <option value="skoda" <?php echo ($vehicule['marque'] == 'skoda') ? 'selected' : ''; ?>>SKODA</option>
                                    <option value="suzuki" <?php echo ($vehicule['marque'] == 'suzuki') ? 'selected' : ''; ?>>SUZUKI</option>
                                    <option value="toyota" <?php echo ($vehicule['marque'] == 'toyota') ? 'selected' : ''; ?>>TOYOTA</option>
                                    <option value="volkswagen" <?php echo ($vehicule['marque'] == 'volkswagen') ? 'selected' : ''; ?>>VOLKSWAGEN</option>
                                    <option value="volvo" <?php echo ($vehicule['marque'] == 'volvo') ? 'selected' : ''; ?>>VOLVO</option>
                                    <option value="autre" <?php echo ($vehicule['marque'] == 'autre') ? 'selected' : ''; ?>>Autre</option>
                                </select>
                                <?php if (isset($errors['marque'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['marque']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Modèle -->
                            <div>
                                <label for="modele" class="block text-sm font-medium text-gray-700 mb-1">Modèle <span class="text-red-500">*</span></label>
                                <input type="text" id="modele" name="modele" value="<?php echo htmlspecialchars($vehicule['modele']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['modele']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="Clio, 308, etc." required>
                                <?php if (isset($errors['modele'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['modele']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Année -->
                            <div>
                                <label for="annee" class="block text-sm font-medium text-gray-700 mb-1">Année <span class="text-red-500">*</span></label>
                                <input type="number" id="annee" name="annee" value="<?php echo htmlspecialchars($vehicule['annee']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['annee']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                                <?php if (isset($errors['annee'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['annee']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Kilométrage -->
                            <div>
                                <label for="kilometrage" class="block text-sm font-medium text-gray-700 mb-1">Kilométrage <span class="text-red-500">*</span></label>
                                <input type="number" id="kilometrage" name="kilometrage" value="<?php echo htmlspecialchars($vehicule['kilometrage']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['kilometrage']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" min="0" required>
                                <?php if (isset($errors['kilometrage'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['kilometrage']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Couleur -->
                            <div>
                                <label for="couleur" class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                                <input type="text" id="couleur" name="couleur" value="<?php echo htmlspecialchars($vehicule['couleur'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="Noir, Blanc, etc.">
                            </div>
                            
                            <!-- Carburant -->
                            <div>
                                <label for="carburant" class="block text-sm font-medium text-gray-700 mb-1">Type de carburant</label>
                                <select id="carburant" name="carburant" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Sélectionner un type</option>
                                    <option value="essence" <?php echo ($vehicule['carburant'] == 'essence') ? 'selected' : ''; ?>>Essence</option>
                                    <option value="diesel" <?php echo ($vehicule['carburant'] == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                    <option value="hybride" <?php echo ($vehicule['carburant'] == 'hybride') ? 'selected' : ''; ?>>Hybride</option>
                                    <option value="electrique" <?php echo ($vehicule['carburant'] == 'electrique') ? 'selected' : ''; ?>>Électrique</option>
                                    <option value="gpl" <?php echo ($vehicule['carburant'] == 'gpl') ? 'selected' : ''; ?>>GPL</option>
                                </select>
                            </div>
                            
                            <!-- Puissance -->
                            <div>
                                <label for="puissance" class="block text-sm font-medium text-gray-700 mb-1">Puissance fiscale (CV)</label>
                                <input type="number" id="puissance" name="puissance" value="<?php echo htmlspecialchars($vehicule['puissance'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="5" min="1">
                            </div>
                            
                            <!-- Date d'achat -->
                            <div>
                                <label for="date_mise_circulation" class="block text-sm font-medium text-gray-700 mb-1">Date d'achat</label>
                                <input type="date" id="date_mise_circulation" name="date_mise_circulation" value="<?php echo !empty($vehicule['date_mise_circulation']) ? date('Y-m-d', strtotime($vehicule['date_mise_circulation'])) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Date dernière révision -->
                            <div>
                                <label for="date_derniere_revision" class="block text-sm font-medium text-gray-700 mb-1">Date dernière révision</label>
                                <input type="date" id="date_derniere_revision" name="date_derniere_revision" value="<?php echo !empty($vehicule['date_derniere_revision']) ? date('Y-m-d', strtotime($vehicule['date_derniere_revision'])) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Date prochain contrôle technique -->
                            <div>
                                <label for="date_prochain_ct" class="block text-sm font-medium text-gray-700 mb-1">Date prochain contrôle technique</label>
                                <input type="date" id="date_prochain_ct" name="date_prochain_ct" value="<?php echo !empty($vehicule['date_prochain_ct']) ? date('Y-m-d', strtotime($vehicule['date_prochain_ct'])) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Statut -->
                            <div>
                                <label for="statut" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                                <select id="statut" name="statut" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="actif" <?php echo ($vehicule['statut'] == 'actif' || empty($vehicule['statut'])) ? 'selected' : ''; ?>>Actif</option>
                                    <option value="maintenance" <?php echo ($vehicule['statut'] == 'maintenance') ? 'selected' : ''; ?>>En maintenance</option>
                                    <option value="inactif" <?php echo ($vehicule['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="mt-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea id="notes" name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="Informations complémentaires sur le véhicule..."><?php echo htmlspecialchars($vehicule['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="mt-8 flex justify-end space-x-3">
                            <a href="index.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                Annuler
                            </a>
                            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
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
                        <h3 class="text-sm font-medium text-blue-800">Conseils pour la modification de véhicules</h3>
                        <ul class="mt-2 text-sm text-blue-700 list-disc list-inside">
                            <li>Assurez-vous que toutes les informations sont correctes avant d'enregistrer</li>
                            <li>La mise à jour du kilométrage permet de suivre l'évolution de l'usure du véhicule</li>
                            <li>Les dates de révision et de contrôle technique sont importantes pour la planification des entretiens</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- History Card -->
            <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Historique des modifications</h2>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Créé le: <?php echo !empty($vehicule['date_creation']) ? date('d/m/Y', strtotime($vehicule['date_creation'])) : 'Non disponible'; ?></span>
                        </div>
                        
                        <?php if (!empty($vehicule['date_modification'])): ?>
                            <div class="flex items-center text-sm text-gray-600 mt-2">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                <span>Dernière modification le: <?php echo date('d/m/Y', strtotime($vehicule['date_modification'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $root_path . '/includes/footer.php'; ?>
