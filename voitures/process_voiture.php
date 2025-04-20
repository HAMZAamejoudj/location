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
    die("Erreur critique: Fichier de configuration de base de données non trouvé.");
}

// Vérifier si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Créer une connexion à la base de données
        $database = new Database();
        $db = $database->getConnection();
        
        // Collecter les données du formulaire
        $immatriculation = $_POST['immatriculation'] ?? '';
        $marque = $_POST['marque'] ?? '';
        $modele = $_POST['modele'] ?? '';
        $couleur = $_POST['couleur'] ?? '';
        $statut = $_POST['statut'] ?? 'available';
        $id_categorie = $_POST['id_categorie'] ?? '';
        $station = $_POST['station'] ?? '';
        $proprietaire = $_POST['proprietaire'] ?? '';
        $annee = $_POST['annee'] ?? date('Y');
        $notes = $_POST['notes'] ?? '';
        $retour_base = isset($_POST['retour_base']) ? 1 : 0;
        
        // Détails techniques
        $type_carburant = $_POST['type_carburant'] ?? null;
        $kilometres = $_POST['kilometres'] ?? 0;
        $nombre_places = $_POST['nombre_places'] ?? 5;
        $nombre_portes = $_POST['nombre_portes'] ?? 4;
        $cylindree_moteur = $_POST['cylindree_moteur'] ?? null;
        $puissance = $_POST['puissance'] ?? null;
        $est_automatique = isset($_POST['est_automatique']) ? 1 : 0;
        $a_climatisation = isset($_POST['a_climatisation']) ? 1 : 0;
        $a_radio = isset($_POST['a_radio']) ? 1 : 0;
        $a_gps = isset($_POST['a_gps']) ? 1 : 0;
        $a_bluetooth = isset($_POST['a_bluetooth']) ? 1 : 0;
        
        // Assurance
        $assureur = $_POST['assureur'] ?? null;
        $date_debut_assurance = $_POST['date_debut_assurance'] ?? null;
        $date_fin_assurance = $_POST['date_fin_assurance'] ?? null;
        
        // KTEO (Contrôle Technique)
        $date_debut_kteo = $_POST['date_debut_kteo'] ?? null;
        $date_fin_kteo = $_POST['date_fin_kteo'] ?? null;
        
        // EOT (Vignette)
        $date_debut_eot = $_POST['date_debut_eot'] ?? null;
        $date_fin_eot = $_POST['date_fin_eot'] ?? null;
        
        // Informations d'offre
        $prix = $_POST['prix'] ?? null;
        $prix_solde = $_POST['prix_solde'] ?? null;
        
        // Gérer les uploads d'images
        $image_principale = null;
        $image_secondaire_1 = null;
        $image_secondaire_2 = null;
        
        // Définir le dossier de stockage des images
        $upload_dir = $root_path . '/uploads/vehicules/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Fonction pour traiter l'upload d'une image
        function handleImageUpload($file, $upload_dir, $immatriculation, $suffix = '') {
            global $debug, $debugInfo;
            
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                if ($debug && isset($file)) $debugInfo[] = "Upload error for image: " . $file['error'];
                return null;
            }
            
            // Obtenir l'extension du fichier
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Vérifier si l'extension est autorisée
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_extension, $allowed_extensions)) {
                if ($debug) $debugInfo[] = "Invalid file extension: " . $file_extension;
                return null;
            }
            
            // Générer un nom de fichier unique
            $new_file_name = $immatriculation . '_' . uniqid() . ($suffix ? '_' . $suffix : '') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_file_name;
            
            // Déplacer le fichier téléchargé
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                if ($debug) $debugInfo[] = "File uploaded successfully: " . $new_file_name;
                return $new_file_name;
            } else {
                if ($debug) $debugInfo[] = "Failed to move uploaded file";
                return null;
            }
        }
        
        // Traiter les uploads d'images
        if (isset($_FILES['image_principale']) && $_FILES['image_principale']['error'] === UPLOAD_ERR_OK) {
            $image_principale = handleImageUpload($_FILES['image_principale'], $upload_dir, $immatriculation, 'main');
        }
        
        if (isset($_FILES['image_secondaire_1']) && $_FILES['image_secondaire_1']['error'] === UPLOAD_ERR_OK) {
            $image_secondaire_1 = handleImageUpload($_FILES['image_secondaire_1'], $upload_dir, $immatriculation, 'sec1');
        }
        
        if (isset($_FILES['image_secondaire_2']) && $_FILES['image_secondaire_2']['error'] === UPLOAD_ERR_OK) {
            $image_secondaire_2 = handleImageUpload($_FILES['image_secondaire_2'], $upload_dir, $immatriculation, 'sec2');
        }
        
        // Préparer la requête SQL pour insérer dans la table 'voiture'
        $sql = "INSERT INTO voitures (
                    numero_immatriculation , marque, modele, couleur, statut, id_categorie, station,
                    proprietaire, annee, notes, retour_base, type_carburant, kilometres,
                    nombre_places, nombre_portes, cylindree_moteur, puissance, est_automatique,
                    a_climatisation, a_radio, a_gps, a_bluetooth, assureur, date_debut_assurance,
                    date_fin_assurance, date_debut_kteo, date_fin_kteo, date_debut_eot, date_fin_eot,
                    prix, prix_solde, image_principale, image_secondaire_1, image_secondaire_2
                ) VALUES (
                    :immatriculation, :marque, :modele, :couleur, :statut, :id_categorie, :station,
                    :proprietaire, :annee, :notes, :retour_base, :type_carburant, :kilometres,
                    :nombre_places, :nombre_portes, :cylindree_moteur, :puissance, :est_automatique,
                    :a_climatisation, :a_radio, :a_gps, :a_bluetooth, :assureur, STR_TO_DATE(:date_debut_assurance, '%d/%m/%Y'),
                    STR_TO_DATE(:date_fin_assurance, '%d/%m/%Y'), STR_TO_DATE(:date_debut_kteo, '%d/%m/%Y'), 
                    STR_TO_DATE(:date_fin_kteo, '%d/%m/%Y'), STR_TO_DATE(:date_debut_eot, '%d/%m/%Y'), 
                    STR_TO_DATE(:date_fin_eot, '%d/%m/%Y'), :prix, :prix_solde, :image_principale, :image_secondaire_1, :image_secondaire_2
                )";
        
        $stmt = $db->prepare($sql);
        
        // Associer les valeurs aux paramètres
        $stmt->bindParam(':immatriculation', $immatriculation);
        $stmt->bindParam(':marque', $marque);
        $stmt->bindParam(':modele', $modele);
        $stmt->bindParam(':couleur', $couleur);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':id_categorie', $id_categorie);
        $stmt->bindParam(':station', $station);
        $stmt->bindParam(':proprietaire', $proprietaire);
        $stmt->bindParam(':annee', $annee);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':retour_base', $retour_base);
        $stmt->bindParam(':type_carburant', $type_carburant);
        $stmt->bindParam(':kilometres', $kilometres);
        $stmt->bindParam(':nombre_places', $nombre_places);
        $stmt->bindParam(':nombre_portes', $nombre_portes);
        $stmt->bindParam(':cylindree_moteur', $cylindree_moteur);
        $stmt->bindParam(':puissance', $puissance);
        $stmt->bindParam(':est_automatique', $est_automatique);
        $stmt->bindParam(':a_climatisation', $a_climatisation);
        $stmt->bindParam(':a_radio', $a_radio);
        $stmt->bindParam(':a_gps', $a_gps);
        $stmt->bindParam(':a_bluetooth', $a_bluetooth);
        $stmt->bindParam(':assureur', $assureur);
        $stmt->bindParam(':date_debut_assurance', $date_debut_assurance);
        $stmt->bindParam(':date_fin_assurance', $date_fin_assurance);
        $stmt->bindParam(':date_debut_kteo', $date_debut_kteo);
        $stmt->bindParam(':date_fin_kteo', $date_fin_kteo);
        $stmt->bindParam(':date_debut_eot', $date_debut_eot);
        $stmt->bindParam(':date_fin_eot', $date_fin_eot);
        $stmt->bindParam(':prix', $prix);
        $stmt->bindParam(':prix_solde', $prix_solde);
        $stmt->bindParam(':image_principale', $image_principale);
        $stmt->bindParam(':image_secondaire_1', $image_secondaire_1);
        $stmt->bindParam(':image_secondaire_2', $image_secondaire_2);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Définir un message de succès
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Le véhicule a été ajouté avec succès!'
            ];
            
            // Rediriger vers la page de liste
            header('Location: index.php');
            exit;
        } else {
            throw new Exception("Erreur lors de l'ajout du véhicule");
        }
    } catch (Exception $e) {
        // En cas d'erreur, stocker les données du formulaire dans la session
        $_SESSION['form_data'] = $_POST;
        
        // Définir un message d'erreur
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Erreur: ' . $e->getMessage()
        ];
        
        if ($debug) {
            error_log("Erreur: " . $e->getMessage());
            $debugInfo[] = "Error: " . $e->getMessage();
        }
        
        // Rediriger vers le formulaire
        header('Location: create.php');
        exit;
    }
} else {
    // Rediriger si accès direct au script sans formulaire
    header('Location: index.php');
    exit;
}

// Afficher les messages de débogage si activé (ne devrait pas être atteint normalement)
if ($debug) {
    echo '<div style="position: fixed; bottom: 0; right: 0; z-index: 9999; background: rgba(0,0,0,0.8); color: lime; font-family: monospace; font-size: 12px; padding: 10px; max-width: 50%; max-height: 50%; overflow: auto;">';
    echo '<h3>Debug Info:</h3>';
    echo '<ul>';
    foreach ($debugInfo as $info) {
        echo '<li>' . $info . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}
?>