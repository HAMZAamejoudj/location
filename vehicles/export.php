<?php
// Démarrer la session d'abord
session_start();

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Récupérer le format d'export (par défaut CSV)
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';

// Récupérer les filtres éventuels
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$marque = isset($_GET['marque']) ? $_GET['marque'] : '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Construction de la requête avec filtres
    $whereClause = [];
    $params = [];

    if (!empty($search)) {
        $whereClause[] = "(v.immatriculation LIKE :search OR v.marque LIKE :search OR v.modele LIKE :search OR CONCAT(c.nom, ' ', c.prenom) LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($status !== '') {
        $whereClause[] = "v.statut = :status";
        $params[':status'] = $status;
    }

    if ($marque !== '') {
        $whereClause[] = "v.marque = :marque";
        $params[':marque'] = $marque;
    }

    $whereString = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';
    
    // Requête pour récupérer les véhicules
    $query = "SELECT v.id, v.immatriculation, v.marque, v.modele, v.annee, 
              CONCAT(c.nom, ' ', c.prenom) AS client, c.telephone, c.email,
              v.kilometrage, v.statut, v.couleur, v.carburant, v.puissance,
              v.date_achat, v.date_derniere_revision, v.date_prochain_ct, v.notes,
              v.date_creation
              FROM vehicules v 
              LEFT JOIN clients c ON v.client_id = c.id 
              $whereString
              ORDER BY v.date_creation DESC";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enregistrer l'action dans les logs
    $logQuery = "INSERT INTO logs (user_id, action, entite, entite_id, details, date_action, adresse_ip) 
                VALUES (:user_id, 'Export', 'vehicules', NULL, :details, NOW(), :adresse_ip)";
    
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindParam(':user_id', $_SESSION['user_id']);
    
    $logDetails = "Export des véhicules au format " . strtoupper($format);
    $logStmt->bindParam(':details', $logDetails);
    
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $logStmt->bindParam(':adresse_ip', $ipAddress);
    
    $logStmt->execute();
    
    // Générer le fichier selon le format demandé
    if ($format === 'csv') {
        // Définir les en-têtes pour le téléchargement CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=vehicules_' . date('Y-m-d') . '.csv');
        
        // Créer le flux de sortie
        $output = fopen('php://output', 'w');
        
        // Ajouter le BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Écrire les en-têtes
        fputcsv($output, [
            'ID', 'Immatriculation', 'Marque', 'Modèle', 'Année', 'Client', 
            'Téléphone', 'Email', 'Kilométrage', 'Statut', 'Couleur', 
            'Carburant', 'Puissance (CV)', 'Date d\'achat', 'Dernière révision', 
            'Prochain CT', 'Notes', 'Date de création'
        ], ';');
        
        // Écrire les données
        foreach ($vehicles as $vehicle) {
            fputcsv($output, [
                $vehicle['id'],
                $vehicle['immatriculation'],
                $vehicle['marque'],
                $vehicle['modele'],
                $vehicle['annee'],
                $vehicle['client'],
                $vehicle['telephone'],
                $vehicle['email'],
                $vehicle['kilometrage'],
                $vehicle['statut'],
                $vehicle['couleur'],
                $vehicle['carburant'],
                $vehicle['puissance'],
                $vehicle['date_achat'],
                $vehicle['date_derniere_revision'],
                $vehicle['date_prochain_ct'],
                $vehicle['notes'],
                $vehicle['date_creation']
            ], ';');
        }
        
        fclose($output);
    } elseif ($format === 'excel') {
        // Pour Excel, nous utiliserons la bibliothèque PhpSpreadsheet
        // Mais ici, nous allons simplement renvoyer un CSV avec une extension .xlsx
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=vehicules_' . date('Y-m-d') . '.xlsx');
        
        // Créer le flux de sortie
        $output = fopen('php://output', 'w');
        
        // Ajouter le BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Écrire les en-têtes
        fputcsv($output, [
            'ID', 'Immatriculation', 'Marque', 'Modèle', 'Année', 'Client', 
            'Téléphone', 'Email', 'Kilométrage', 'Statut', 'Couleur', 
            'Carburant', 'Puissance (CV)', 'Date d\'achat', 'Dernière révision', 
            'Prochain CT', 'Notes', 'Date de création'
        ], ';');
        
        // Écrire les données
        foreach ($vehicles as $vehicle) {
            fputcsv($output, [
                $vehicle['id'],
                $vehicle['immatriculation'],
                $vehicle['marque'],
                $vehicle['modele'],
                $vehicle['annee'],
                $vehicle['client'],
                $vehicle['telephone'],
                $vehicle['email'],
                $vehicle['kilometrage'],
                $vehicle['statut'],
                $vehicle['couleur'],
                $vehicle['carburant'],
                $vehicle['puissance'],
                $vehicle['date_achat'],
                $vehicle['date_derniere_revision'],
                $vehicle['date_prochain_ct'],
                $vehicle['notes'],
                $vehicle['date_creation']
            ], ';');
        }
        
        fclose($output);
    } else {
        // Format non supporté
        $_SESSION['error_messages'] = ["Format d'export non supporté"];
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_messages'] = ["Erreur lors de l'export: " . $e->getMessage()];
    header('Location: index.php');
    exit;
}
?>