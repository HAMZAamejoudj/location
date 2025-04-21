<?php
// Démarrer la session
session_start();
define('BASE_URL', '../');


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

// Vérifier que l'ID de la réparation est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID de la réparation non spécifié";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Récupérer les informations de la réparation
$sql = "SELECT vhs.*, 
        v.numero_immatriculation, v.marque, v.modele, v.couleur, v.statut AS statut_voiture,
        g.nom AS nom_garage, g.adresse AS adresse_garage, g.telephone AS telephone_garage
        FROM voitures_hors_service vhs
        LEFT JOIN voitures v ON vhs.id_voiture = v.id
        LEFT JOIN garage g ON vhs.id_garage = g.id
        WHERE vhs.id = :id";

$stmt = $db->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $_SESSION['error_message'] = "Réparation non trouvée";
    header("Location: index.php");
    exit;
}

$reparation = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculer la durée de la réparation
$date_debut = new DateTime($reparation['date_debut']);
$duration_text = "";

if (!empty($reparation['date_fin'])) {
    $date_fin = new DateTime($reparation['date_fin']);
    $interval = $date_debut->diff($date_fin);
    
    if ($interval->days == 0) {
        $duration_text = "Même jour";
    } else if ($interval->days == 1) {
        $duration_text = "1 jour";
    } else {
        $duration_text = $interval->days . " jours";
    }
} else if ($reparation['statut'] == 'En cours') {
    $today = new DateTime();
    $interval = $date_debut->diff($today);
    
    if ($interval->days == 0) {
        $duration_text = "Commencé aujourd'hui";
    } else if ($interval->days == 1) {
        $duration_text = "Depuis 1 jour";
    } else {
        $duration_text = "Depuis " . $interval->days . " jours";
    }
}

// Traitement si on change le statut
if (isset($_POST['action']) && $_POST['action'] == 'terminer') {
    // Mettre à jour le statut de la réparation
    $update_sql = "UPDATE voitures_hors_service 
                  SET statut = 'Terminé', date_fin = CURRENT_DATE 
                  WHERE id = :id AND statut = 'En cours'";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($update_stmt->execute() && $update_stmt->rowCount() > 0) {
        // Mettre à jour le statut de la voiture
        $update_voiture_sql = "UPDATE voitures SET statut = 'Disponible' 
                             WHERE id = :id_voiture";
        $update_voiture_stmt = $db->prepare($update_voiture_sql);
        $update_voiture_stmt->bindParam(':id_voiture', $reparation['id_voiture'], PDO::PARAM_INT);
        $update_voiture_stmt->execute();
        
        // Rediriger pour rafraîchir les données
        $_SESSION['success_message'] = "La réparation a été marquée comme terminée";
        header("Location: view.php?id=" . $id);
        exit;
    }
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la réparation</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #ddd;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        .timeline-badge {
            position: absolute;
            left: -30px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid;
        }
        .timeline-badge.primary {
            border-color: #0d6efd;
        }
        .timeline-badge.success {
            border-color: #198754;
        }
        .timeline-badge.warning {
            border-color: #ffc107;
        }
        .timeline-badge.danger {
            border-color: #dc3545;
        }
        .timeline-content {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
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
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- En-tête avec informations principales -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="mb-0">
                        <i class="fas fa-car me-2"></i>
                        <?php echo htmlspecialchars($reparation['numero_immatriculation']); ?>
                    </h1>
                    <div>
                        <a href="index.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <a href="/garage/garage_view.php ?php echo $id; ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <?php if ($reparation['statut'] == 'En cours'): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#terminerModal">
                                <i class="fas fa-check-circle"></i> Terminer
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="mb-2">
                            <strong>Véhicule:</strong> 
                            <?php echo htmlspecialchars($reparation['marque'] . ' ' . $reparation['modele'] . ' (' . $reparation['couleur'] . ')'); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Raison:</strong> 
                            <?php echo htmlspecialchars($reparation['raison']); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-2">
                            <strong>Date de début:</strong> 
                            <?php echo date('d/m/Y', strtotime($reparation['date_debut'])); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Date de fin:</strong> 
                            <?php echo !empty($reparation['date_fin']) ? date('d/m/Y', strtotime($reparation['date_fin'])) : '<span class="text-muted">Non définie</span>'; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Durée:</strong> 
                            <?php echo $duration_text; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php
                            $status_class = 'bg-secondary';
                            if ($reparation['statut'] == 'En cours') $status_class = 'bg-warning';
                            if ($reparation['statut'] == 'Terminé') $status_class = 'bg-success';
                        ?>
                        <span class="badge status-badge <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($reparation['statut']); ?>
                        </span>
                        <?php if (!empty($reparation['cout_reparation'])): ?>
                            <div class="mt-2">
                                <h4><?php echo number_format($reparation['cout_reparation'], 2, ',', ' '); ?> €</h4>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Détails de la réparation -->
            <div class="col-md-8">
                <?php if (!empty($reparation['description'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Description</h5>
                    </div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($reparation['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reparation['notes'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
                    </div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($reparation['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Chronologie de la réparation -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Chronologie</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-badge primary"></div>
                                <div class="timeline-content">
                                    <h6><?php echo date('d/m/Y', strtotime($reparation['date_debut'])); ?> - Début</h6>
                                    <p>La voiture a été mise en réparation pour raison de <strong><?php echo htmlspecialchars($reparation['raison']); ?></strong>.</p>
                                </div>
                            </div>
                            
                            <?php if (!empty($reparation['date_fin'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge success"></div>
                                <div class="timeline-content">
                                    <h6><?php echo date('d/m/Y', strtotime($reparation['date_fin'])); ?> - Fin</h6>
                                    <p>La réparation a été terminée.</p>
                                    <?php if (!empty($reparation['cout_reparation'])): ?>
                                        <p>Coût total: <strong><?php echo number_format($reparation['cout_reparation'], 2, ',', ' '); ?> €</strong></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php elseif ($reparation['statut'] == 'Annulé'): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge danger"></div>
                                <div class="timeline-content">
                                    <h6>Annulation</h6>
                                    <p>La réparation a été annulée.</p>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="timeline-item">
                                <div class="timeline-badge warning"></div>
                                <div class="timeline-content">
                                    <h6>En cours</h6>
                                    <p>La réparation est toujours en cours depuis <?php echo $duration_text; ?>.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations additionnelles -->
            <div class="col-md-4">
                <!-- Garage -->
                <?php if (!empty($reparation['id_garage'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Garage</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($reparation['nom_garage']); ?></h6>
                        <p><?php echo nl2br(htmlspecialchars($reparation['adresse_garage'])); ?></p>
                        <?php if (!empty($reparation['telephone_garage'])): ?>
                            <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($reparation['telephone_garage']); ?></p>
                        <?php endif; ?>
                        <a href="../garage/view.php?id=<?php echo $reparation['id_garage']; ?>" class="btn btn-sm btn-outline-secondary">
                            Voir le garage
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Informations sur la voiture -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-car me-2"></i>Informations véhicule</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Immatriculation:</strong> 
                            <?php echo htmlspecialchars($reparation['numero_immatriculation']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Marque:</strong> 
                            <?php echo htmlspecialchars($reparation['marque']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Modèle:</strong> 
                            <?php echo htmlspecialchars($reparation['modele']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Couleur:</strong> 
                            <?php echo htmlspecialchars($reparation['couleur']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Statut actuel:</strong> 
                            <span class="badge bg-<?php echo ($reparation['statut_voiture'] == 'Disponible') ? 'success' : 'danger'; ?>">
                                <?php echo htmlspecialchars($reparation['statut_voiture']); ?>
                            </span>
                        </div>
                        <a href="../voitures/view.php?id=<?php echo $reparation['id_voiture']; ?>" class="btn btn-sm btn-outline-primary">
                            Voir la fiche véhicule
                        </a>
                    </div>
                </div>
                
                <!-- Actions disponibles -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                            <?php if ($reparation['statut'] == 'En cours'): ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#terminerModal">
                                    <i class="fas fa-check-circle me-2"></i>Marquer comme terminé
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash me-2"></i>Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal pour terminer la réparation -->
    <?php if ($reparation['statut'] == 'En cours'): ?>
    <div class="modal fade" id="terminerModal" tabindex="-1" aria-labelledby="terminerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="terminerModalLabel">Terminer la réparation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir marquer cette réparation comme terminée ?</p>
                    <p>Cela va également remettre la voiture en statut "Disponible".</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="terminer">
                        <button type="submit" class="btn btn-success">Confirmer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Modal pour supprimer la réparation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cet enregistrement de réparation ?</p>
                    <?php if ($reparation['statut'] == 'En cours'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Attention, cette réparation est toujours en cours. La suppression ne remettra pas automatiquement la voiture en statut "Disponible".
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" action="delete.php">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
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