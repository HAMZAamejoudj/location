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

// Vérifier que l'ID de la catégorie est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID de la catégorie non spécifié";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Récupérer les informations de la catégorie
$sql = "SELECT * FROM categorie WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $_SESSION['error_message'] = "Catégorie non trouvée";
    header("Location: index.php");
    exit;
}

$categorie = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les voitures associées à cette catégorie
$sql = "SELECT v.id, v.numero_immatriculation, v.marque, v.modele, v.couleur, v.statut 
        FROM voitures v 
        WHERE v.id_categorie = :id_categorie
        ORDER BY v.marque, v.modele";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id_categorie', $id, PDO::PARAM_INT);
$stmt->execute();
$voitures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la catégorie</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($categorie['nom']); ?></h1>
            <div>
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Modifier
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Informations de la catégorie</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th style="width: 30%">ID:</th>
                                <td><?php echo $categorie['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Nom:</th>
                                <td><?php echo htmlspecialchars($categorie['nom']); ?></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td>
                                    <?php if (!empty($categorie['description'])): ?>
                                        <?php echo nl2br(htmlspecialchars($categorie['description'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune description</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Date de création:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($categorie['date_creation'])); ?></td>
                            </tr>
                            <tr>
                                <th>Nombre de voitures:</th>
                                <td>
                                    <span class="badge bg-primary"><?php echo count($voitures); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Statistiques</h3>
                    </div>
                    <div class="card-body">
                        <?php
                            $stats = [
                                'total' => count($voitures),
                                'disponible' => 0,
                                'indisponible' => 0,
                                'vendu' => 0
                            ];
                            
                            foreach ($voitures as $voiture) {
                                if ($voiture['statut'] == 'Disponible') $stats['disponible']++;
                                if ($voiture['statut'] == 'Indisponible') $stats['indisponible']++;
                                if ($voiture['statut'] == 'Vendu') $stats['vendu']++;
                            }
                            
                            // Calculer les pourcentages
                            $pct_disponible = ($stats['total'] > 0) ? round(($stats['disponible'] / $stats['total']) * 100) : 0;
                            $pct_indisponible = ($stats['total'] > 0) ? round(($stats['indisponible'] / $stats['total']) * 100) : 0;
                            $pct_vendu = ($stats['total'] > 0) ? round(($stats['vendu'] / $stats['total']) * 100) : 0;
                        ?>
                        
                        <div class="mb-3">
                            <h5>Total des voitures: <?php echo $stats['total']; ?></h5>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Disponibles:</span>
                                <span class="badge bg-success"><?php echo $stats['disponible']; ?> (<?php echo $pct_disponible; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct_disponible; ?>%;" aria-valuenow="<?php echo $pct_disponible; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $pct_disponible; ?>%</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Indisponibles:</span>
                                <span class="badge bg-warning"><?php echo $stats['indisponible']; ?> (<?php echo $pct_indisponible; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $pct_indisponible; ?>%;" aria-valuenow="<?php echo $pct_indisponible; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $pct_indisponible; ?>%</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Vendues:</span>
                                <span class="badge bg-secondary"><?php echo $stats['vendu']; ?> (<?php echo $pct_vendu; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo $pct_vendu; ?>%;" aria-valuenow="<?php echo $pct_vendu; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $pct_vendu; ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des voitures de cette catégorie -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Voitures dans cette catégorie</h3>
                <a href="../voitures/add.php?categorie=<?php echo $id; ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Ajouter une voiture
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Immatriculation</th>
                                <th>Marque</th>
                                <th>Modèle</th>
                                <th>Couleur</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($voitures)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucune voiture dans cette catégorie</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($voitures as $voiture): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($voiture['numero_immatriculation']); ?></td>
                                        <td><?php echo htmlspecialchars($voiture['marque']); ?></td>
                                        <td><?php echo htmlspecialchars($voiture['modele']); ?></td>
                                        <td><?php echo htmlspecialchars($voiture['couleur']); ?></td>
                                        <td>
                                            <?php
                                                $status_class = 'bg-secondary';
                                                if ($voiture['statut'] == 'Disponible') $status_class = 'bg-success';
                                                if ($voiture['statut'] == 'Indisponible') $status_class = 'bg-warning';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($voiture['statut']); ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../voitures/view.php?id=<?php echo $voiture['id']; ?>" class="btn btn-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../voitures/edit.php?id=<?php echo $voiture['id']; ?>" class="btn btn-warning" title="Modifier">
                                                    <i class="fas fa-edit"></i>
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
        </div>
    </div>
    
    <!-- Footer -->
    <?php include $root_path . '/includes/footer.php'; ?>
    
    <!-- Bootstrap Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>