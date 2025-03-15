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


$vehicules = [];
try {
    $query = "SELECT *
              FROM  vehicules v ";
$stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
} catch (PDOException $e) {
    $errors['database'] = 'Erreur lors de la récupération du client: ' . $e->getMessage();
}
// Données pour les interventions
$interventions = [];
try {
    $interventionsParPage = 5; // Nombre d'interventions par page
    $pageCourante = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $debut = ($pageCourante - 1) * $interventionsParPage;

    // Récupérer le nombre total d'interventions
    $stmtTotal = $db->query("SELECT COUNT(*) FROM interventions");
    $totalInterventions = $stmtTotal->fetchColumn();
    $totalPages = ceil($totalInterventions / $interventionsParPage);

    // Requête paginée
    $query = "SELECT i.id, i.date_creation, i.description, i.statut, v.immatriculation, 
                     CONCAT(c.nom, ' ', c.prenom) AS client, u.username AS technicien
              FROM interventions i
              INNER JOIN vehicules v ON i.vehicule_id = v.id
              INNER JOIN clients c ON v.client_id = c.id
              LEFT JOIN users u ON i.technicien_id = u.id
              LIMIT :debut, :interventionsParPage";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':debut', $debut, PDO::PARAM_INT);
    $stmt->bindValue(':interventionsParPage', $interventionsParPage, PDO::PARAM_INT);
    $stmt->execute();
    $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Interventions Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Total Interventions</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalInterventions; ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="#" class="text-blue-500 hover:text-blue-700 text-sm font-semibold">Voir toutes les interventions →</a>
                    </div>
                </div>

                <!-- En cours Card -->
                <div class="bg-white p-6 rounded-lg shadow-md transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">En cours</h3>
                            <p class="text-3xl font-bold text-gray-800 mt-2">
                                <?php 
                                    $enCoursCount = 0;
                                    foreach ($interventions as $intervention) {
                                        if ($intervention['statut'] === 'En cours') {
                                            $enCoursCount++;
                                        }
                                    }
                                    echo $enCoursCount;
                                ?>
                            </p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="#" class="text-yellow-500 hover:text-yellow-700 text-sm font-semibold">Voir les détails →</a>
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
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher une intervention...">
                    </div>
                    <div class="flex gap-4">
                        <select class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option>Tous les statuts</option>
                            <option>En attente</option>
                            <option>En cours</option>
                            <option>Terminée</option>
                            <option>Facturée</option>
                            <option>Annulée</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technicien</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($interventions as $intervention): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $intervention['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $intervention['date_creation']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $intervention['description']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $intervention['client']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $intervention['technicien'] ?? 'Non assigné'; ?></td>
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
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex justify-between items-center mt-6">
                    <div class="text-sm text-gray-500">
                        Affichage de <span class="font-medium"><?= $debut + 1 ?></span> à 
                        <span class="font-medium"><?= min($debut + $interventionsParPage, $totalInterventions) ?></span> 
                        sur <span class="font-medium"><?= $totalInterventions ?></span> résultats
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
<div id="addInterventionModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-green-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Ajouter une intervention</h3>
            <button onclick="closeModal('addInterventionModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="create.php" method="POST" class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="vehicule_id" class="block text-sm font-medium text-gray-700 mb-1">Véhicule</label>
                    <select id="vehicule_id" name="vehicule_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" required>
                        <option value="">Sélectionner un véhicule</option>
                        <!-- Remplir dynamiquement les options -->
                        <?php foreach ($vehicules as $vehicule): ?>
                            <option value="<?php echo $vehicule['id']; ?>"><?php echo $vehicule['marque'].' '.$vehicule['modele'].' '.$vehicule['immatriculation']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="technicien_id" class="block text-sm font-medium text-gray-700 mb-1">Technicien</label>
                    <select id="technicien_id" name="technicien_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                        <option value="">Sélectionner un technicien</option>
                        <?php foreach ($techniciens as $technicien): ?>
                            <option value="<?php echo $technicien['id']; ?>"><?php echo $technicien['nom_complet']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date_prevue" class="block text-sm font-medium text-gray-700 mb-1">Date prévue</label>
                    <input type="date" id="date_prevue" name="date_prevue" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500" required></textarea>
                </div>
                <div>
                    <label for="diagnostique" class="block text-sm font-medium text-gray-700 mb-1">Diagnostique</label>
                    <textarea id="diagnostique" name="diagnostique" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                </div>
                <div>
                    <label for="kilometrage" class="block text-sm font-medium text-gray-700 mb-1">Kilométrage</label>
                    <input type="number" id="kilometrage" name="kilometrage" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addInterventionModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
<div id="viewInterventionModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
<span id="view_id" style="display: none;"></span>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-semibold text-gray-900">Détails de l'intervention</h3>
            <button onclick="closeModal('viewInterventionModal')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-lg font-semibold text-blue-600 mb-4">Informations générales</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Véhicule:</span>
                            <span id="view_vehicule" class="text-sm text-gray-900">AB-123-CD</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Technicien:</span>
                            <span id="view_technicien" class="text-sm text-gray-900">Jean Dupont</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Date prévue:</span>
                            <span id="view_date_prevue" class="text-sm text-gray-900">2025-03-20</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Kilométrage:</span>
                            <span id="view_kilometrage" class="text-sm text-gray-900">45 000 km</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Statut:</span>
                            <span id="view_statut" class="text-sm text-gray-900">Statut</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-blue-600 mb-4">Description</h4>
                    <p id="view_description" class="text-sm text-gray-700">
                        Problème de frein détecté.
                    </p>

                    <h4 class="text-lg font-semibold text-blue-600 mt-6 mb-4">Diagnostique</h4>
                    <p id="view_diagnostique" class="text-sm text-gray-700">
                        Freins avant usés, remplacement nécessaire.
                    </p>
                </div>
                
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('viewInterventionModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Fermer
                </button>
                <button type="button" onclick="closeModal('viewInterventionModal');  editIntervention( document.getElementById('view_id').textContent);" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Modifier
                </button>
            </div>
        </div>
    </div>
</div>

<div id="deleteInterventionModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center mb-4">
                <div class="bg-red-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-semibold text-center text-gray-900 mb-4">Confirmer la suppression</h3>
            <p class="text-gray-700 text-center mb-6">Êtes-vous sûr de vouloir supprimer cette intervention ? Cette action est irréversible.</p>
            <div class="flex justify-center space-x-4">
                <button type="button" onclick="closeModal('deleteInterventionModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <form id="deleteInterventionForm" action="delete.php" method="POST">
                    <input type="hidden" id="delete_intervention_id" name="id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<div id="editInterventionModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center p-4 border-b bg-blue-600 rounded-t-lg">
            <h3 class="text-xl font-semibold text-white">Modifier une intervention</h3>
            <button onclick="closeModal('editInterventionModal')" class="text-white hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="edit.php" method="POST" class="p-5">
            <!-- Champ caché pour l'ID de l'intervention -->
            <input type="hidden" id="edit_intervention_id" name="id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="edit_vehicule_id" class="block text-sm font-medium text-gray-700 mb-1">Véhicule</label>
                    <select id="edit_vehicule_id" name="vehicule_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Sélectionner un véhicule</option>
                        <!-- Remplir dynamiquement les options -->
                        <?php foreach ($vehicules as $vehicule): ?>
                            <option value="<?php echo $vehicule['id']; ?>"><?php echo $vehicule['marque'].' '.$vehicule['modele'].' '.$vehicule['immatriculation']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit_technicien_id" class="block text-sm font-medium text-gray-700 mb-1">Technicien</label>
                    <select id="edit_technicien_id" name="technicien_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Sélectionner un technicien</option>
                        <?php foreach ($techniciens as $technicien): ?>
                            <option value="<?php echo $technicien['id']; ?>"><?php echo $technicien['nom_complet']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit_date_prevue" class="block text-sm font-medium text-gray-700 mb-1">Date prévue</label>
                    <input type="date" id="edit_date_prevue" name="date_prevue" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="edit_description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required></textarea>
                </div>
                <div>
                    <label for="edit_diagnostique" class="block text-sm font-medium text-gray-700 mb-1">Diagnostique</label>
                    <textarea id="edit_diagnostique" name="diagnostique" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label for="edit_kilometrage" class="block text-sm font-medium text-gray-700 mb-1">Kilométrage</label>
                    <input type="number" id="edit_kilometrage" name="kilometrage" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('editInterventionModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    // Fonctions pour gérer les modals (ajout, modification, suppression)
    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function viewIntervention(id) {
    // Faire une requête pour récupérer les détails de l'intervention depuis la base de données
    console.log('Récupération des données de l\'intervention avec l\'ID:', id);
    fetch(`recuperation_inter.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            console.log('Données de l\'intervention:', data);
            document.getElementById('view_id').textContent = id;
            // Remplir les champs du modal avec les données récupérées
            document.getElementById('view_vehicule').textContent = data.vehicule; // Exemple : immatriculation du véhicule
            document.getElementById('view_technicien').textContent = data.technicien; // Exemple : nom du technicien
            document.getElementById('view_date_prevue').textContent = data.date_prevue || 'Non spécifiée';
           // document.getElementById('view_date_creation').textContent = data.date_creation;
            document.getElementById('view_description').textContent = data.description;
            document.getElementById('view_diagnostique').textContent = data.diagnostique || 'Non spécifié';
            document.getElementById('view_kilometrage').textContent = data.kilometrage + ' km';
            document.getElementById('view_statut').textContent = data.statut;


            // Afficher le modal
            openModal('viewInterventionModal');
        })
        .catch(error => console.error('Erreur lors de la récupération des données de l\'intervention:', error));
}


function editIntervention(id) {
    console.log('Modifier l\'intervention avec l\'ID:', id);
    
    // Faire une requête pour récupérer les détails de l'intervention
    fetch(`recuperation_inter.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Pré-remplir les champs du modal avec les données récupérées
            document.getElementById('edit_intervention_id').value = data.id;
            document.getElementById('edit_vehicule_id').value = data.vehicule_id;
            document.getElementById('edit_technicien_id').value = data.technicien_id;
            document.getElementById('edit_date_prevue').value = data.date_prevue;
            document.getElementById('edit_description').value = data.description;
            document.getElementById('edit_diagnostique').value = data.diagnostique;
            document.getElementById('edit_kilometrage').value = data.kilometrage;

            // Ouvrir le modal
            openModal('editInterventionModal');
        })
        .catch(error => console.error('Erreur lors de la récupération des données de l\'intervention:', error));
}


    function deleteIntervention(id) {
        // Logique pour supprimer une intervention
        
        document.getElementById('delete_intervention_id').value = id;
        openModal('deleteInterventionModal');
    }
</script>

<?php include '../includes/footer.php'; ?>
