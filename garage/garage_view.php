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

// Vérifier que l'ID du garage est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID du garage non spécifié";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Récupérer les informations du garage
$sql = "SELECT * FROM garage WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $_SESSION['error_message'] = "Garage non trouvé";
    header("Location: index.php");
    exit;
}

$garage = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les voitures en réparation dans ce garage
$sql = "SELECT vhs.*, v.numero_immatriculation, v.marque, v.modele, v.couleur 
        FROM voitures_hors_service vhs 
        JOIN voitures v ON vhs.id_voiture = v.id 
        WHERE vhs.id_garage = :id_garage 
        ORDER BY vhs.date_debut DESC";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id_garage', $id, PDO::PARAM_INT);
$stmt->execute();
$reparations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du garage</title>
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
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-warehouse me-2"></i><?php echo htmlspecialchars($garage['nom']); ?></h1>
            <div>
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Retour
                </a>
                <a href="garage_edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i> Modifier
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Informations du garage</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <th style="width: 30%">ID:</th>
                                    <td><?php echo $garage['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Nom:</th>
                                    <td><?php echo htmlspecialchars($garage['nom']); ?></td>
                                </tr>
                                <tr>
                                    <th>Adresse:</th>
                                    <td><?php echo nl2br(htmlspecialchars($garage['adresse'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Téléphone:</th>
                                    <td>
                                        <?php if (!empty($garage['telephone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($garage['telephone']); ?>"><?php echo htmlspecialchars($garage['telephone']); ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>
                                        <?php if (!empty($garage['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($garage['email']); ?>"><?php echo htmlspecialchars($garage['email']); ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Responsable:</th>
                                    <td>
                                        <?php if (!empty($garage['responsable'])): ?>
                                            <?php echo htmlspecialchars($garage['responsable']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Date de création:</th>
                                    <td><?php echo date('d/m/Y H:i', strtotime($garage['date_creation'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Dernière modification:</th>
                                    <td>
                                        <?php if (isset($garage['date_modification'])): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($garage['date_modification'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($garage['notes'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h3 class="mb-0">Notes</h3>
                    </div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($garage['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Voitures en réparation</h3>
                        <a href="../voitures_hors_service/add.php?garage_id=<?php echo $id; ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-plus"></i> Ajouter
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reparations)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Aucune voiture en réparation dans ce garage actuellement.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Voiture</th>
                                            <th>Raison</th>
                                            <th>Date début</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reparations as $reparation): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($reparation['numero_immatriculation']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($reparation['marque'] . ' ' . $reparation['modele']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($reparation['raison']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($reparation['date_debut'])); ?></td>
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
                                                    <a href="../voitures_hors_service/view.php?id=<?php echo $reparation['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
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
    
    <!-- Footer -->
    <?php include $root_path . '/includes/footer.php'; ?>
    
    <!-- Bootstrap Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>