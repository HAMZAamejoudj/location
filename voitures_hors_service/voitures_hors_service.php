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
    
    // Supprimer la réparation
    $stmt = $db->prepare("DELETE FROM voitures_hors_service WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $delete_success = "Entrée supprimée avec succès.";
    } else {
        $errorInfo = $stmt->errorInfo();
        $delete_error = "Erreur lors de la suppression: " . $errorInfo[2];
    }
}

// Traitement de la recherche et des filtres
$search = "";
$statut_filter = "";
$raison_filter = "";

if (isset($_GET['search'])) {
    $search = clean_input($_GET['search']);
}

if (isset($_GET['statut']) && in_array($_GET['statut'], ['En cours', 'Terminé', 'Annulé'])) {
    $statut_filter = $_GET['statut'];
}

if (isset($_GET['raison']) && in_array($_GET['raison'], ['Maintenance', 'Réparation', 'Accident', 'Autre'])) {
    $raison_filter = $_GET['raison'];
}

// Requête pour récupérer les voitures hors service
$sql = "SELECT vhs.*, 
        v.numero_immatriculation, v.marque, v.modele, v.couleur,
        g.nom as nom_garage
        FROM voitures_hors_service vhs
        LEFT JOIN voitures v ON vhs.id_voiture = v.id
        LEFT JOIN garage g ON vhs.id_garage = g.id
        WHERE 1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (v.numero_immatriculation LIKE :search OR v.marque LIKE :search OR v.modele LIKE :search OR g.nom LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($statut_filter)) {
    $sql .= " AND vhs.statut = :statut";
    $params[':statut'] = $statut_filter;
}

if (!empty($raison_filter)) {
    $sql .= " AND vhs.raison = :raison";
    $params[':raison'] = $raison_filter;
}

$sql .= " ORDER BY vhs.date_debut DESC";

$stmt = $db->prepare($sql);

// Binder les paramètres si nécessaire
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$reparations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => count($reparations),
    'en_cours' => 0,
    'termine' => 0,
    'annule' => 0
];

foreach ($reparations as $rep) {
    if ($rep['statut'] == 'En cours') $stats['en_cours']++;
    if ($rep['statut'] == 'Terminé') $stats['termine']++;
    if ($rep['statut'] == 'Annulé') $stats['annule']++;
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voitures en réparation</title>
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
        .stats-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .stats-card.primary {
            border-left-color: #0d6efd;
        }
        .stats-card.warning {
            border-left-color: #ffc107;
        }
        .stats-card.success {
            border-left-color: #198754;
        }
        .stats-card.secondary {
            border-left-color: #6c757d;
        }
    </style>
</head>
<body>
    
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
            <h1><i class="fas fa-tools me-2"></i>Voitures en réparation</h1>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Ajouter une voiture en réparation
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
        
        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal mt-0">Total</h6>
                                <h3><?php echo $stats['total']; ?></h3>
                            </div>
                            <div class="avatar-sm">
                                <i class="fas fa-car fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal mt-0">En cours</h6>
                                <h3><?php echo $stats['en_cours']; ?></h3>
                            </div>
                            <div class="avatar-sm">
                                <i class="fas fa-wrench fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal mt-0">Terminées</h6>
                                <h3><?php echo $stats['termine']; ?></h3>
                            </div>
                            <div class="avatar-sm">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card secondary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal mt-0">Annulées</h6>
                                <h3><?php echo $stats['annule']; ?></h3>
                            </div>
                            <div class="avatar-sm">
                                <i class="fas fa-ban fa-2x text-secondary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formulaire de recherche et filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Rechercher par immatriculation, marque, modèle ou garage" value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statut" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="En cours" <?php echo ($statut_filter == 'En cours') ? 'selected' : ''; ?>>En cours</option>
                            <option value="Terminé" <?php echo ($statut_filter == 'Terminé') ? 'selected' : ''; ?>>Terminé</option>
                            <option value="Annulé" <?php echo ($statut_filter == 'Annulé') ? 'selected' : ''; ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="raison" name="raison">
                            <option value="">Toutes les raisons</option>
                            <option value="Maintenance" <?php echo ($raison_filter == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="Réparation" <?php echo ($raison_filter == 'Réparation') ? 'selected' : ''; ?>>Réparation</option>
                            <option value="Accident" <?php echo ($raison_filter == 'Accident') ? 'selected' : ''; ?>>Accident</option>
                            <option value="Autre" <?php echo ($raison_filter == 'Autre') ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tableau des voitures en réparation -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Voiture</th>
                                <th>Garage</th>
                                <th>Raison</th>
                                <th>Dates</th>
                                <th>Coût</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reparations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucune voiture en réparation trouvée</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reparations as $reparation): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($reparation['numero_immatriculation']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($reparation['marque'] . ' ' . $reparation['modele']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($reparation['nom_garage'])): ?>
                                                <a href="../garage/view.php?id=<?php echo $reparation['id_garage']; ?>">
                                                    <?php echo htmlspecialchars($reparation['nom_garage']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifié</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $badge_class = 'bg-secondary';
                                                if ($reparation['raison'] == 'Maintenance') $badge_class = 'bg-info';
                                                if ($reparation['raison'] == 'Réparation') $badge_class = 'bg-primary';
                                                if ($reparation['raison'] == 'Accident') $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($reparation['raison']); ?></span>
                                            <?php if (!empty($reparation['description'])): ?>
                                                <br><small><?php echo substr(htmlspecialchars($reparation['description']), 0, 50); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            Début: <?php echo date('d/m/Y', strtotime($reparation['date_debut'])); ?><br>
                                            <?php if (!empty($reparation['date_fin'])): ?>
                                                Fin: <?php echo date('d/m/Y', strtotime($reparation['date_fin'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">En cours</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($reparation['cout_reparation'])): ?>
                                                <?php echo number_format($reparation['cout_reparation'], 2, ',', ' '); ?> €
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($reparation['statut'] == 'En cours'): ?>
                                                <span class="badge bg-warning">En cours</span>
                                            <?php elseif ($reparation['statut'] == 'Terminé'): ?>
                                                <span class="badge bg-success">Terminé</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Annulé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $reparation['id']; ?>" class="btn btn-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $reparation['id']; ?>" class="btn btn-warning" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $reparation['id']; ?>" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Modal de confirmation de suppression -->
                                            <div class="modal fade" id="deleteModal<?php echo $reparation['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $reparation['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $reparation['id']; ?>">Confirmer la suppression</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Êtes-vous sûr de vouloir supprimer cette entrée pour la voiture <strong><?php echo htmlspecialchars($reparation['numero_immatriculation']); ?></strong> ?</p>
                                                            
                                                            <?php if ($reparation['statut'] == 'En cours'): ?>
                                                                <div class="alert alert-warning">
                                                                    <i class="fas fa-exclamation-triangle me-1"></i> 
                                                                    Attention, cette réparation est toujours en cours.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="id" value="<?php echo $reparation['id']; ?>">
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