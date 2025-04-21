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

// Vérifier que l'ID du garage est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID du garage non spécifié";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Initialiser les variables
$nom = $adresse = $telephone = $email = $responsable = $notes = "";
$errors = [];

// Si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valider le nom
    if (empty($_POST["nom"])) {
        $errors[] = "Le nom du garage est requis";
    } else {
        $nom = clean_input($_POST["nom"]);
    }
    
    // Valider l'adresse
    if (empty($_POST["adresse"])) {
        $errors[] = "L'adresse du garage est requise";
    } else {
        $adresse = clean_input($_POST["adresse"]);
    }
    
    // Récupérer et nettoyer les autres champs
    $telephone = !empty($_POST["telephone"]) ? clean_input($_POST["telephone"]) : null;
    $email = !empty($_POST["email"]) ? clean_input($_POST["email"]) : null;
    $responsable = !empty($_POST["responsable"]) ? clean_input($_POST["responsable"]) : null;
    $notes = !empty($_POST["notes"]) ? clean_input($_POST["notes"]) : null;
    
    // Valider l'email s'il est fourni
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        $sql = "UPDATE garage SET nom = :nom, adresse = :adresse, telephone = :telephone, 
                email = :email, responsable = :responsable, notes = :notes WHERE id = :id";
        $stmt = $db->prepare($sql);
        
        // Binder les paramètres
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':responsable', $responsable, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Rediriger vers la page d'index avec un message de succès
            $_SESSION['success_message'] = "Le garage a été mis à jour avec succès";
            header("Location: index.php");
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            $errors[] = "Erreur lors de la mise à jour du garage: " . $errorInfo[2];
        }
    }
} else {
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
    
    // Remplir les variables avec les données du garage
    $nom = $garage['nom'];
    $adresse = $garage['adresse'];
    $telephone = $garage['telephone'];
    $email = $garage['email'];
    $responsable = $garage['responsable'];
    $notes = $garage['notes'];
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un garage</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier le garage</h2>
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
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $id; ?>">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom du garage <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" value="<?php echo $nom; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="adresse" class="form-label">Adresse <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3" required><?php echo $adresse; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo $telephone; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="responsable" class="form-label">Responsable</label>
                                <input type="text" class="form-control" id="responsable" name="responsable" value="<?php echo $responsable; ?>">
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
    
    <!-- Bootstrap Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>