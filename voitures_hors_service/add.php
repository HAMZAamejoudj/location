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

// Récupérer la liste des voitures pour le sélecteur
$sql = "SELECT id, numero_immatriculation, marque, modele, couleur FROM voitures ORDER BY numero_immatriculation";
$stmt = $db->prepare($sql);
$stmt->execute();
$voitures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des garages pour le sélecteur
$sql = "SELECT id, nom, adresse FROM garage ORDER BY nom";
$stmt = $db->prepare($sql);
$stmt->execute();
$garages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pré-sélection du garage si spécifié dans l'URL
$garage_preselect = isset($_GET['garage_id']) ? intval($_GET['garage_id']) : 0;

// Initialiser les variables
$id_voiture = "";
$id_garage = $garage_preselect;
$date_debut = date('Y-m-d');
$date_fin = "";
$raison = "";
$description = "";
$cout_reparation = "";
$statut = "En cours";
$notes = "";
$errors = [];

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valider la voiture
    if (empty($_POST["id_voiture"])) {
        $errors[] = "La sélection d'une voiture est requise";
    } else {
        $id_voiture = intval($_POST["id_voiture"]);
    }
    
    // Récupérer et valider les autres champs
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
    
    // Vérifier la cohérence des dates
    if (!empty($date_debut) && !empty($date_fin)) {
        if (strtotime($date_fin) < strtotime($date_debut)) {
            $errors[] = "La date de fin ne peut pas être antérieure à la date de début";
        }
    }
    
    // Si aucune erreur, procéder à l'insertion
    if (empty($errors)) {
        $sql = "INSERT INTO voitures_hors_service (id_voiture, id_garage, date_debut, date_fin, raison, description, cout_reparation, statut, notes) 
                VALUES (:id_voiture, :id_garage, :date_debut, :date_fin, :raison, :description, :cout_reparation, :statut, :notes)";
        $stmt = $db->prepare($sql);
        
        // Binder les paramètres
        $stmt->bindParam(':id_voiture', $id_voiture, PDO::PARAM_INT);
        $stmt->bindParam(':id_garage', $id_garage, PDO::PARAM_INT);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':raison', $raison);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':cout_reparation', $cout_reparation);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':notes', $notes);
        
        if ($stmt->execute()) {
            // Mettre à jour le statut de la voiture si nécessaire
            if ($statut == 'En cours') {
                $update_sql = "UPDATE voitures SET statut = 'Indisponible' WHERE id = :id_voiture";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->bindParam(':id_voiture', $id_voiture, PDO::PARAM_INT);
                $update_stmt->execute();
            }
            
            // Rediriger vers la page d'index avec un message de succès
            $_SESSION['success_message'] = "La voiture a été mise en réparation avec succès";
            header("Location: index.php");
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            $errors[] = "Erreur lors de l'ajout: " . $errorInfo[2];
        }
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
    <title>Ajouter une voiture en réparation</title>
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
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="fas fa-tools me-2"></i>Ajouter une voiture en réparation</h2>
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
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="id_voiture" class="form-label">Voiture <span class="text-danger">*</span></label>
                                    <select class="form-select select2" id="id_voiture" name="id_voiture" required>
                                        <option value="">Sélectionner une voiture</option>
                                        <?php foreach ($voitures as $voiture): ?>
                                            <option value="<?php echo $voiture['id']; ?>" <?php echo ($id_voiture == $voiture['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($voiture['numero_immatriculation'] . ' - ' . $voiture['marque'] . ' ' . $voiture['modele'] . ' (' . $voiture['couleur'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
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
                                    <label for="statut" class="form-label">Statut <span class="text-danger">*</span></label>
                                    <select class="form-select" id="statut" name="statut" required>
                                        <option value="En cours" <?php echo ($statut == 'En cours') ? 'selected' : ''; ?>>En cours</option>
                                        <option value="Terminé" <?php echo ($statut == 'Terminé') ? 'selected' : ''; ?>>Terminé</option>
                                        <option value="Annulé" <?php echo ($statut == 'Annulé') ? 'selected' : ''; ?>>Annulé</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="cout_reparation" class="form-label">Coût de la réparation (€)</label>
                                <input type="text" class="form-control" id="cout_reparation" name="cout_reparation" value="<?php echo $cout_reparation; ?>" placeholder="0.00">
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo $notes; ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">
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