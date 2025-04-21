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
    
    // Vérifier d'abord si la catégorie n'est pas associée à des voitures
    $stmt = $db->prepare("SELECT COUNT(*) FROM voitures WHERE id_categorie = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $delete_error = "Cette catégorie ne peut pas être supprimée car elle est associée à " . $count . " voiture(s).";
    } else {
        // Supprimer la catégorie
        $stmt = $db->prepare("DELETE FROM categorie WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $delete_success = "Catégorie supprimée avec succès.";
        } else {
            $errorInfo = $stmt->errorInfo();
            $delete_error = "Erreur lors de la suppression de la catégorie: " . $errorInfo[2];
        }
    }
}

// Traitement de la recherche
$search = "";
if (isset($_GET['search'])) {
    $search = clean_input($_GET['search']);
}

// Requête pour récupérer les catégories (avec recherche si applicable)
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM voitures WHERE id_categorie = c.id) as nombre_voitures 
        FROM categorie c";

if (!empty($search)) {
    $sql .= " WHERE c.nom LIKE :search OR c.description LIKE :search";
}

$sql .= " ORDER BY c.nom ASC";

$stmt = $db->prepare($sql);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
}

$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories</title>
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
            <h1><i class="fas fa-tags me-2"></i>Gestion des Catégories</h1>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Ajouter une catégorie
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
                        <input type="text" class="form-control" id="search" name="search" placeholder="Rechercher par nom ou description" value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tableau des catégories -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Date de création</th>
                                <th>Nombre de voitures</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucune catégorie trouvée</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $categorie): ?>
                                    <tr>
                                        <td><?php echo $categorie['id']; ?></td>
                                        <td><?php echo htmlspecialchars($categorie['nom']); ?></td>
                                        <td>
                                            <?php 
                                                $description = htmlspecialchars($categorie['description']);
                                                echo (strlen($description) > 50) ? substr($description, 0, 50) . '...' : $description; 
                                            ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($categorie['date_creation'])); ?></td>
                                        <td>
                                            <?php if ($categorie['nombre_voitures'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $categorie['nombre_voitures']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $categorie['id']; ?>" class="btn btn-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $categorie['id']; ?>" class="btn btn-warning" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $categorie['id']; ?>" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Modal de confirmation de suppression -->
                                            <div class="modal fade" id="deleteModal<?php echo $categorie['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $categorie['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $categorie['id']; ?>">Confirmer la suppression</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Êtes-vous sûr de vouloir supprimer la catégorie "<?php echo htmlspecialchars($categorie['nom']); ?>" ?
                                                            <?php if ($categorie['nombre_voitures'] > 0): ?>
                                                                <div class="alert alert-warning mt-2">
                                                                    <i class="fas fa-exclamation-triangle"></i> Cette catégorie est associée à <?php echo $categorie['nombre_voitures']; ?> voiture(s).
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="id" value="<?php echo $categorie['id']; ?>">
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