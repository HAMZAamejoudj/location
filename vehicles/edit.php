<?php
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions

if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;

    // Vérification et nettoyage des données
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $immatriculation = trim($_POST['immatriculation']);
    $client_id = intval($_POST['client_id']);
    $marque = trim($_POST['marque']);
    $modele = trim($_POST['modele']);
    $annee = intval($_POST['annee']);
    $kilometrage = intval($_POST['kilometrage']);
    $couleur = trim($_POST['couleur']);
    $carburant = trim($_POST['carburant']);
    $puissance = intval($_POST['puissance']);
    $date_mise_circulation = date('Y-m-d', strtotime($_POST['date_mise_circulation']));
    $date_derniere_revision = date('Y-m-d', strtotime($_POST['date_derniere_revision']));
    $date_prochain_ct = date('Y-m-d', strtotime($_POST['date_prochain_ct']));
    $statut = trim($_POST['statut']);
    $notes = trim($_POST['notes']);

    if ($id > 0) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "UPDATE vehicules SET immatriculation = :immatriculation, client_id = :client_id, marque = :marque, 
                      modele = :modele, annee = :annee, kilometrage = :kilometrage, couleur = :couleur, carburant = :carburant, 
                      puissance = :puissance, date_mise_circulation = :date_mise_circulation, date_derniere_revision = :date_derniere_revision, 
                      date_prochain_ct = :date_prochain_ct, statut = :statut, notes = :notes WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':immatriculation', $immatriculation);
            $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt->bindParam(':marque', $marque);
            $stmt->bindParam(':modele', $modele);
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->bindParam(':kilometrage', $kilometrage, PDO::PARAM_INT);
            $stmt->bindParam(':couleur', $couleur);
            $stmt->bindParam(':carburant', $carburant);
            $stmt->bindParam(':puissance', $puissance, PDO::PARAM_INT);
            $stmt->bindParam(':date_mise_circulation', $date_mise_circulation);
            $stmt->bindParam(':date_derniere_revision', $date_derniere_revision);
            $stmt->bindParam(':date_prochain_ct', $date_prochain_ct);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':notes', $notes);
            
            if ($stmt->execute()) {
                $success = true;
                header("Location: view.php");
                exit;
            } else {
                $errors['database'] = "Erreur lors de la mise à jour du véhicule.";
            }
        } catch (PDOException $e) {
            $errors['database'] = "Erreur: " . $e->getMessage();
        }
    } else {
        $errors['id'] = "ID de véhicule invalide.";
    }
}

header("Location: edit.php?id=$id&error=true");
exit;
?>
