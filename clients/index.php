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

// Pour la démonstration, créons un tableau de clients fictifs
$clients = [
    [
        'id' => 1,
        'nom' => 'Dupont',
        'prenom' => 'Jean',
        'email' => 'jean.dupont@example.com',
        'telephone' => '06 12 34 56 78',
        'adresse' => '123 Rue de Paris',
        'code_postal' => '75001',
        'ville' => 'Paris',
        'date_creation' => '2022-01-15',
        'nb_vehicules' => 2
    ],
    [
        'id' => 2,
        'nom' => 'Martin',
        'prenom' => 'Sophie',
        'email' => 'sophie.martin@example.com',
        'telephone' => '07 23 45 67 89',
        'adresse' => '45 Avenue des Champs',
        'code_postal' => '69002',
        'ville' => 'Lyon',
        'date_creation' => '2022-03-22',
        'nb_vehicules' => 1
    ],
    [
        'id' => 3,
        'nom' => 'Leroy',
        'prenom' => 'Michel',
        'email' => 'michel.leroy@example.com',
        'telephone' => '06 34 56 78 90',
        'adresse' => '8 Boulevard de la Liberté',
        'code_postal' => '33000',
        'ville' => 'Bordeaux',
        'date_creation' => '2022-05-10',
        'nb_vehicules' => 3
    ],
    [
        'id' => 4,
        'nom' => 'Petit',
        'prenom' => 'Marie',
        'email' => 'marie.petit@example.com',
        'telephone' => '07 45 67 89 01',
        'adresse' => '56 Rue du Commerce',
        'code_postal' => '44000',
        'ville' => 'Nantes',
        'date_creation' => '2022-07-05',
        'nb_vehicules' => 1
    ],
    [
        'id' => 5,
        'nom' => 'Roux',
        'prenom' => 'Pierre',
        'email' => 'pierre.roux@example.com',
        'telephone' => '06 56 78 90 12',
        'adresse' => '12 Avenue Jean Jaurès',
        'code_postal' => '13001',
        'ville' => 'Marseille',
        'date_creation' => '2022-09-18',
        'nb_vehicules' => 2
    ],
    [
        'id' => 6,
        'nom' => 'Moreau',
        'prenom' => 'Isabelle',
        'email' => 'isabelle.moreau@example.com',
        'telephone' => '07 67 89 01 23',
        'adresse' => '78 Rue Victor Hugo',
        'code_postal' => '59000',
        'ville' => 'Lille',
        'date_creation' => '2022-11-30',
        'nb_vehicules' => 1
    ]
];

// Fonction pour rechercher dans le tableau des clients
function searchClients($clients, $search) {
    if (empty($search)) {
        return $clients;
    }
    
    $search = strtolower($search);
    $results = [];
    
    foreach ($clients as $client) {
        if (
            strpos(strtolower($client['nom']), $search) !== false ||
            strpos(strtolower($client['prenom']), $search) !== false ||
            strpos(strtolower($client['email']), $search) !== false ||
            strpos(strtolower($client['telephone']), $search) !== false ||
            strpos(strtolower($client['ville']), $search) !== false
        ) {
            $results[] = $client;
        }
    }
    
    return $results;
}

// Traitement de la recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filteredClients = searchClients($clients, $search);

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
                <h1 class="text-2xl font-semibold text-gray-800">Gestion des Clients</h1>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-gray-700"><?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Utilisateur'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clients Content -->
        <div class="container mx-auto px-6 py-8">
            <!-- Search and Add Button -->
            <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
                <div class="flex-1">
                    <form action="" method="GET" class="mb-4 md:mb-0">
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher un client..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="submit" class="absolute right-0 top-0 h-full px-4 text-gray-600 hover:text-indigo-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="flex space-x-3">
                    <a href="create.php" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Ajouter un client
                    </a>
                    <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filtrer
                    </button>
                </div>
            </div>

        <!-- Clients Table -->
<?php
    $database = new Database();
    $db = $database->getConnection();
    
    // Configuration de la pagination
    $clients_per_page = 6;
    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($current_page - 1) * $clients_per_page;
    
    // Récupérer le nombre total de clients
    $count_query = "SELECT COUNT(*) as total FROM clients";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute();
    $total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_clients = $total_result['total'];
    $total_pages = ceil($total_clients / $clients_per_page);
    
    // Récupérer les clients pour la page courante
    $query = "SELECT id, nom, prenom, adresse, code_postal, ville, telephone, email, date_creation, notes 
              FROM clients 
              ORDER BY date_creation DESC
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $clients_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Client
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Contact
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Adresse
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date de création
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Véhicules
                    </th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            Aucun client trouvé
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold">
                                            <?php echo substr($client['prenom'], 0, 1) . substr($client['nom'], 0, 1); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: <?php echo htmlspecialchars($client['id']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($client['email']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($client['telephone']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($client['adresse']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($client['code_postal'] . ' ' . $client['ville']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d/m/Y', strtotime($client['date_creation'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    0 véhicule
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <a href="view.php?id=<?php echo $client['id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Voir">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="edit.php?id=<?php echo $client['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Modifier">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <a href="delete.php?id=<?php echo $client['id']; ?>"  class="text-red-600 hover:text-red-900" title="Supprimer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="mt-6 flex justify-between items-center">
    <div class="text-sm text-gray-500">
        Affichage de <span class="font-medium">
            <?php 
                $start_count = $total_clients > 0 ? $offset + 1 : 0;
                $end_count = min($total_clients, $offset + count($clients));
                echo $start_count . '-' . $end_count;
            ?>
        </span> 
        client(s) sur <span class="font-medium"><?php echo $total_clients; ?></span> au total
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="flex space-x-2">
            <!-- Bouton Précédent -->
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Précédent
                </a>
            <?php else: ?>
                <button class="px-3 py-1 rounded border border-gray-300 text-gray-400 cursor-not-allowed opacity-50" disabled>
                    Précédent
                </button>
            <?php endif; ?>

            <!-- Pages numérotées -->
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
                    <button class="px-3 py-1 rounded bg-indigo-600 text-white">
                        <?php echo $i; ?>
                    </button>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="px-3 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Bouton Suivant -->
            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Suivant
                </a>
            <?php else: ?>
                <button class="px-3 py-1 rounded border border-gray-300 text-gray-400 cursor-not-allowed opacity-50" disabled>
                    Suivant
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>


<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Confirmation de suppression</h3>
        </div>
        <div class="px-6 py-4">
            <p class="text-gray-700">Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible.</p>
        </div>
        <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg">
            <button type="button" onclick="cancelDelete()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition">
                Annuler
            </button>
            <a href="#" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                Supprimer
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for delete confirmation -->
<script>
    function Delete(id) {
        const modal = document.getElementById('deleteModal');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        
        modal.classList.remove('hidden');
        confirmBtn.href = 'delete.php?id=' + id;
    }
    
    function cancelDelete() {
        const modal = document.getElementById('deleteModal');
        modal.classList.add('hidden');
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            cancelDelete();
        }
    });
</script>

<?php include $root_path . '/includes/footer.php'; ?>