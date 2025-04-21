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

// Vérifier que l'ID de la catégorie est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID de la catégorie non spécifié";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Initialiser les variables
$nom = "";
$description = "";
$errors = [];

// Si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valider le nom
    if (empty($_POST["nom"])) {
        $errors[] = "Le nom de la catégorie est requis";
    } else {
        $nom = clean_input($_POST["nom"]);
        
        // Vérifier si le nom existe déjà (mais pas pour l'ID actuel)
        $check_sql = "SELECT COUNT(*) FROM categorie WHERE nom = :nom AND id != :id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "Une autre catégorie avec ce nom existe déjà";
        }
    }
    
    // Récupérer la description
    $description = !empty($_POST["description"]) ? clean_input($_POST["description"]) : null;
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        $sql = "UPDATE categorie SET nom = :nom, description = :description WHERE id = :id";
        $stmt = $db->prepare($sql);
        
        // Binder les paramètres
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Rediriger vers la page de visualisation
            $_SESSION['success_message'] = "La catégorie a été mise à jour avec succès";
            header("Location: view.php?id=" . $id);
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            $errors[] = "Erreur lors de la mise à jour de la catégorie: " . $errorInfo[2];
        }
    }
} else {
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
    
    // Remplir les variables avec les données de la catégorie
    $nom = $categorie['nom'];
    $description = $categorie['description'];
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une catégorie</title>
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
        
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier la catégorie</h2>
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
                                <label for="nom" class="form-label">Nom de la catégorie <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" value="<?php echo $nom; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo $description; ?></textarea>
                                <div class="form-text">Une description détaillée de la catégorie (optionnel).</div>
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
    
    <!-- Bootstrap Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>