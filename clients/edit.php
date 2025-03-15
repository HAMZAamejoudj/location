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

// Vérifier si l'ID du client est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$client_id = intval($_GET['id']);

// Initialiser les variables
$errors = [];
$success = false;
$client = [];

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Récupérer les données du client, y compris les notes
try {
    $query = "SELECT id, nom, prenom, email, telephone, adresse, code_postal, ville, date_creation, notes FROM clients WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $client_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Client non trouvé, rediriger vers la liste
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération du client: ' . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    if (empty($_POST['nom'])) {
        $errors['nom'] = 'Le nom est requis';
    }
    
    if (empty($_POST['prenom'])) {
        $errors['prenom'] = 'Le prénom est requis';
    }
    
    if (empty($_POST['email'])) {
        $errors['email'] = 'L\'email est requis';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'L\'email n\'est pas valide';
    }
    
    if (empty($_POST['telephone'])) {
        $errors['telephone'] = 'Le téléphone est requis';
    }
    
    // Si aucune erreur, mettre à jour le client
    if (empty($errors)) {
        try {
            // Préparer la requête de mise à jour
            $query = "UPDATE clients SET 
                     nom = :nom, 
                     prenom = :prenom, 
                     email = :email, 
                     telephone = :telephone, 
                     adresse = :adresse, 
                     code_postal = :code_postal, 
                     ville = :ville,
                     notes = :notes
                     WHERE id = :id";
            
            $stmt = $db->prepare($query);
            
            // Binder les paramètres
            $stmt->bindParam(':id', $client_id);
            $stmt->bindParam(':nom', $_POST['nom']);
            $stmt->bindParam(':prenom', $_POST['prenom']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':telephone', $_POST['telephone']);
            $stmt->bindParam(':adresse', $_POST['adresse']);
            $stmt->bindParam(':code_postal', $_POST['code_postal']);
            $stmt->bindParam(':ville', $_POST['ville']);
            $stmt->bindParam(':notes', $_POST['notes']);
            
            // Exécuter la requête
            $stmt->execute();
            
            // Mettre à jour les données du client pour l'affichage
            $client = [
                'id' => $client_id,
                'nom' => $_POST['nom'],
                'prenom' => $_POST['prenom'],
                'email' => $_POST['email'],
                'telephone' => $_POST['telephone'],
                'adresse' => $_POST['adresse'] ?? '',
                'code_postal' => $_POST['code_postal'] ?? '',
                'ville' => $_POST['ville'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'date_creation' => $client['date_creation']
            ];
            
            $success = true;
            
        } catch (PDOException $e) {
            // Gérer les erreurs de base de données
            $errors['database'] = 'Erreur lors de la mise à jour du client: ' . $e->getMessage();
        }
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
                <h1 class="text-2xl font-semibold text-gray-800">Modifier un client</h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Client Form -->
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
                    <li class="text-gray-800 font-medium">Modifier</li>
                </ol>
            </nav>
            
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                    <p class="font-bold">Succès!</p>
                    <p>Le client a été mis à jour avec succès.</p>
                    <div class="mt-2">
                        <a href="index.php" class="text-green-600 hover:underline font-medium">Retourner à la liste des clients</a>
                        ou
                        <a href="view.php?id=<?php echo $client_id; ?>" class="text-green-600 hover:underline font-medium">Voir le profil du client</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if (isset($errors['database'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    <p class="font-bold">Erreur!</p>
                    <p><?php echo $errors['database']; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Form Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-6">Informations du client</h2>
                    
                    <form action="edit.php?id=<?php echo $client_id; ?>" method="POST" data-validate="true" id="edit-client-form">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nom -->
                            <div>
                                <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($client['nom']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['nom']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                <?php if (isset($errors['nom'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['nom']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Prénom -->
                            <div>
                                <label for="prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($client['prenom']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['prenom']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                <?php if (isset($errors['prenom'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['prenom']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                <?php if (isset($errors['email'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['email']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Téléphone -->
                            <div>
                                <label for="telephone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone <span class="text-red-500">*</span></label>
                                <input type="text" id="telephone" name="telephone" value="<?php echo htmlspecialchars($client['telephone']); ?>" class="w-full px-4 py-2 border <?php echo isset($errors['telephone']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                <?php if (isset($errors['telephone'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['telephone']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Adresse -->
                            <div class="md:col-span-2">
                                <label for="adresse" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                                <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($client['adresse']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Code Postal -->
                            <div>
                                <label for="code_postal" class="block text-sm font-medium text-gray-700 mb-1">Code Postal</label>
                                <input type="text" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($client['code_postal']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Ville -->
                            <div>
                                <label for="ville" class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                                <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($client['ville']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Notes -->
                            <div class="md:col-span-2">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea id="notes" name="notes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Date de création (en lecture seule) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date de création</label>
                                <input type="text" value="<?php echo date('d/m/Y', strtotime($client['date_creation'])); ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg" readonly>
                            </div>
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
                        <h3 class="text-sm font-medium text-blue-800">Conseils pour la modification des clients</h3>
                        <ul class="mt-2 text-sm text-blue-700 list-disc list-inside">
                            <li>Vérifiez que les informations de contact sont à jour</li>
                            <li>L'adresse email sera utilisée pour les communications automatiques</li>
                            <li>Utilisez le champ notes pour ajouter des informations importantes sur le client</li>
                            <li>Vous pouvez gérer les véhicules associés à ce client depuis son profil</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $root_path . '/includes/footer.php'; ?>
