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
if ($debug) $debugInfo[] = "Database connection established (PDO)";

// Fonction pour nettoyer les données entrées par l'utilisateur
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Traitement de la suppression si confirmée
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Vérifier d'abord si le garage n'est pas associé à des voitures hors service
    $stmt = $db->prepare("SELECT COUNT(*) FROM voitures_hors_service WHERE id_garage = :id AND statut = 'En cours'");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $delete_error = "Ce garage ne peut pas être supprimé car il est associé à " . $count . " réparation(s) en cours.";
    } else {
        // Supprimer le garage
        $stmt = $db->prepare("DELETE FROM garage WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $delete_success = "Garage supprimé avec succès.";
        } else {
            $errorInfo = $stmt->errorInfo();
            $delete_error = "Erreur lors de la suppression du garage: " . $errorInfo[2];
        }
    }
}

// Traitement de la recherche
$search = "";
if (isset($_GET['search'])) {
    $search = clean_input($_GET['search']);
}

// Requête pour récupérer les garages (avec recherche si applicable)
$sql = "SELECT g.*, 
        (SELECT COUNT(*) FROM voitures_hors_service WHERE id_garage = g.id AND statut = 'En cours') as voitures_en_reparation 
        FROM garage g";

if (!empty($search)) {
    $sql .= " WHERE g.nom LIKE :search OR g.adresse LIKE :search OR g.responsable LIKE :search";
}

$sql .= " ORDER BY g.nom ASC";

$stmt = $db->prepare($sql);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
}

$stmt->execute();
$garages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Garages</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .table-responsive {
            margin-top: 20px;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

    <div class="container mt-4">
        <!-- Affichage du mode debug si activé -->
        <?php if ($debug): ?>
            <div class="alert alert-info">
                <h5>Mode Debug</h5>
                <ul>
                    <?php foreach ($debugInfo as $info): ?>
                        <li><?php echo $info; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Gestion des Garages</h1>
            <a href="garage_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Ajouter un garage
            </a>
        </div>
        
        <?php if (isset($delete_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $delete_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($delete_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $delete_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire de recherche -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Rechercher par nom, adresse ou responsable" value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tableau des garages -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Adresse</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Responsable</th>
                                <th>Voitures en réparation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($garages)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Aucun garage trouvé</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($garages as $garage): ?>
                                    <tr>
                                        <td><?php echo $garage['id']; ?></td>
                                        <td><?php echo htmlspecialchars($garage['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($garage['adresse']); ?></td>
                                        <td><?php echo htmlspecialchars($garage['telephone']); ?></td>
                                        <td><?php echo htmlspecialchars($garage['email']); ?></td>
                                        <td><?php echo htmlspecialchars($garage['responsable']); ?></td>
                                        <td>
                                            <?php if (isset($garage['voitures_en_reparation']) && $garage['voitures_en_reparation'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $garage['voitures_en_reparation']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="garage_view.php?id=<?php echo $garage['id']; ?>" class="btn btn-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="garage_edit.php?id=<?php echo $garage['id']; ?>" class="btn btn-warning" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $garage['id']; ?>" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Modal de confirmation de suppression -->
                                            <div class="modal fade" id="deleteModal<?php echo $garage['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $garage['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $garage['id']; ?>">Confirmer la suppression</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Êtes-vous sûr de vouloir supprimer le garage "<?php echo htmlspecialchars($garage['nom']); ?>" ?
                                                            <?php if (isset($garage['voitures_en_reparation']) && $garage['voitures_en_reparation'] > 0): ?>
                                                                <div class="alert alert-warning mt-2">
                                                                    <i class="fas fa-exclamation-triangle"></i> Ce garage a actuellement <?php echo $garage['voitures_en_reparation']; ?> voiture(s) en réparation.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="id" value="<?php echo $garage['id']; ?>">
                                                                <button type="submit" name="delete" class="btn btn-danger">Supprimer</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include $root_path . '/includes/footer.php'; ?>
    
    <!-- Bootstrap Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>