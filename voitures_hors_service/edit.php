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

// Vérifier que l'ID de la réparation est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID de la réparation non spécifié";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Récupérer la liste des garages pour le sélecteur
$sql = "SELECT id, nom, adresse FROM garage ORDER BY nom";
$stmt = $db->prepare($sql);
$stmt->execute();
$garages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialiser les variables
$id_voiture = "";
$id_garage = null;
$date_debut = "";
$date_fin = "";
$raison = "";
$description = "";
$cout_reparation = "";
$statut = "";
$notes = "";
$errors = [];
$old_statut = "";
$numero_immatriculation = "";
$marque = "";
$modele = "";

// Si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les informations du formulaire
    $id_garage = !empty($_POST["id_garage"]) ? intval($_POST["id_garage"]) : null;
    
    if (empty($_POST["date_debut"])) {
        $errors[] = "La date de début est requise";
    } else {
        $date_debut = clean_input($_POST["date_debut"]);
    }
    
    $date_fin = !empty($_POST["date_fin"]) ? clean_input($_POST["date_fin"]) : null;
    
    if (empty($_POST["raison"])) {
        $errors[] = "La raison est requise";
    } else {
        $raison = clean_input($_POST["raison"]);
        // Vérifier que la raison est valide
        if (!in_array($raison, ['Maintenance', 'Réparation', 'Accident', 'Autre'])) {
            $errors[] = "La raison sélectionnée n'est pas valide";
        }
    }
    
    $description = !empty($_POST["description"]) ? clean_input($_POST["description"]) : null;
    $cout_reparation = !empty($_POST["cout_reparation"]) ? floatval(str_replace(',', '.', $_POST["cout_reparation"])) : null;
    
    if (empty($_POST["statut"])) {
        $errors[] = "Le statut est requis";
    } else {
        $statut = clean_input($_POST["statut"]);
        // Vérifier que le statut est valide
        if (!in_array($statut, ['En cours', 'Terminé', 'Annulé'])) {
            $errors[] = "Le statut sélectionné n'est pas valide";
        }
    }
    
    $notes = !empty($_POST["notes"]) ? clean_input($_POST["notes"]) : null;
    $old_statut = clean_input($_POST["old_statut"]);
    $id_voiture = intval($_POST["id_voiture"]);
    
    // Vérifier la cohérence des dates
    if (!empty($date_debut) && !empty($date_fin)) {
        if (strtotime($date_fin) < strtotime($date_debut)) {
            $errors[] = "La date de fin ne peut pas être antérieure à la date de début";
        }
    }
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        $sql = "UPDATE voitures_hors_service 
                SET id_garage = :id_garage, date_debut = :date_debut, date_fin = :date_fin, 
                raison = :raison, description = :description, cout_reparation = :cout_reparation, 
                statut = :statut, notes = :notes
                WHERE id = :id";
                
        $stmt = $db->prepare($sql);
        
        // Binder les paramètres
        $stmt->bindParam(':id_garage', $id_garage, PDO::PARAM_INT);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':raison', $raison);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':cout_reparation', $cout_reparation);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Si le statut a changé, mettre à jour le statut de la voiture
            if ($old_statut != $statut) {
                if ($statut == 'Terminé' || $statut == 'Annulé') {
                    // Vérifier s'il y a d'autres réparations en cours pour cette voiture
                    $check_sql = "SELECT COUNT(*) FROM voitures_hors_service 
                                 WHERE id_voiture = :id_voiture AND id != :id AND statut = 'En cours'";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->bindParam(':id_voiture', $id_voiture, PDO::PARAM_INT);
                    $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $check_stmt->execute();
                    $other_repairs = $check_stmt->fetchColumn();
                    
                    if ($other_repairs == 0) {
                        // Aucune autre réparation en cours, remettre la voiture disponible
                        $update_sql = "UPDATE voitures SET statut = 'Disponible' WHERE id = :id_voiture";
                        $update_stmt = $db->prepare($update_sql);
                        $update_stmt->bindParam(':id_voiture', $id_voiture, PDO::PARAM_INT);
                        $update_stmt->execute();
                    }
                } else if ($statut == 'En cours' && ($old_statut == 'Terminé' || $old_statut == 'Annulé')) {
                    // Remettre la voiture en indisponible
                    $update_sql = "UPDATE voitures SET statut = 'Indisponible' WHERE id = :id_voiture";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->bindParam(':id_voiture', $id_voiture, PDO::PARAM_INT);
                    $update_stmt->execute();
                }
            }
            
            // Rediriger vers la page de visualisation
            $_SESSION['success_message'] = "La réparation a été mise à jour avec succès";
            header("Location: view.php?id=" . $id);
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            $errors[] = "Erreur lors de la mise à jour: " . $errorInfo[2];
        }
    }
} else {
    // Récupérer les informations de la réparation
    $sql = "SELECT vhs.*, 
            v.numero_immatriculation, v.marque, v.modele, v.id as id_voiture 
            FROM voitures_hors_service vhs
            LEFT JOIN voitures v ON vhs.id_voiture = v.id
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
    
    // Remplir les variables avec les données de la réparation
    $id_voiture = $reparation['id_voiture'];
    $id_garage = $reparation['id_garage'];
    $date_debut = $reparation['date_debut'];
    $date_fin = $reparation['date_fin'];
    $raison = $reparation['raison'];
    $description = $reparation['description'];
    $cout_reparation = $reparation['cout_reparation'];
    $statut = $reparation['statut'];
    $notes = $reparation['notes'];
    $old_statut = $statut;
    $numero_immatriculation = $reparation['numero_immatriculation'];
    $marque = $reparation['marque'];
    $modele = $reparation['modele'];
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une réparation</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            padding: 5px;
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
        
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier la réparation</h2>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Informations sur la voiture (non modifiables) -->
                        <div class="alert alert-info mb-4">
                            <h5><i class="fas fa-car me-2"></i>Véhicule: <?php echo htmlspecialchars($numero_immatriculation . ' - ' . $marque . ' ' . $modele); ?></h5>
                            <p class="mb-0">Vous modifiez les détails de réparation pour ce véhicule.</p>
                        </div>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $id; ?>">
                            <input type="hidden" name="id_voiture" value="<?php echo $id_voiture; ?>">
                            <input type="hidden" name="old_statut" value="<?php echo $old_statut; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="id_garage" class="form-label">Garage</label>
                                    <select class="form-select select2" id="id_garage" name="id_garage">
                                        <option value="">Sélectionner un garage (optionnel)</option>
                                        <?php foreach ($garages as $garage): ?>
                                            <option value="<?php echo $garage['id']; ?>" <?php echo ($id_garage == $garage['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($garage['nom'] . ' (' . substr($garage['adresse'], 0, 30) . '...)'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="statut" class="form-label">Statut <span class="text-danger">*</span></label>
                                    <select class="form-select" id="statut" name="statut" required>
                                        <option value="En cours" <?php echo ($statut == 'En cours') ? 'selected' : ''; ?>>En cours</option>
                                        <option value="Terminé" <?php echo ($statut == 'Terminé') ? 'selected' : ''; ?>>Terminé</option>
                                        <option value="Annulé" <?php echo ($statut == 'Annulé') ? 'selected' : ''; ?>>Annulé</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="date_fin" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="raison" class="form-label">Raison <span class="text-danger">*</span></label>
                                    <select class="form-select" id="raison" name="raison" required>
                                        <option value="">Sélectionner une raison</option>
                                        <option value="Maintenance" <?php echo ($raison == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="Réparation" <?php echo ($raison == 'Réparation') ? 'selected' : ''; ?>>Réparation</option>
                                        <option value="Accident" <?php echo ($raison == 'Accident') ? 'selected' : ''; ?>>Accident</option>
                                        <option value="Autre" <?php echo ($raison == 'Autre') ? 'selected' : ''; ?>>Autre</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="cout_reparation" class="form-label">Coût de la réparation (€)</label>
                                    <input type="text" class="form-control" id="cout_reparation" name="cout_reparation" value="<?php echo $cout_reparation; ?>" placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo $notes; ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times-circle me-1"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include $root_path . '/includes/footer.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialiser Select2
            $('.select2').select2({
                width: '100%'
            });
            
            // Logique pour les dates et le statut
            $('#statut').on('change', function() {
                if ($(this).val() == 'Terminé' && $('#date_fin').val() == '') {
                    // Si statut "Terminé" et pas de date de fin, ajouter la date d'aujourd'hui
                    var today = new Date().toISOString().substr(0, 10);
                    $('#date_fin').val(today);
                }
            });
            
            // Validation de la date de fin
            $('#date_fin').on('change', function() {
                var dateDebut = new Date($('#date_debut').val());
                var dateFin = new Date($(this).val());
                
                if (dateFin < dateDebut) {
                    alert('La date de fin ne peut pas être antérieure à la date de début');
                    $(this).val('');
                }
            });
        });
    </script>
</body>
</html>