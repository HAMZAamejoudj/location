<?php
// Démarrer la session
session_start();

// Chemin racine de l'application
$root_path = dirname(__DIR__);

// Inclure les fichiers de configuration et de fonctions
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
}

if (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

// Vérifier si l'utilisateur est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    // Pour le développement, créer un utilisateur factice
    $_SESSION['user_id'] = 1;
}

// Utilisateur temporaire pour éviter l'erreur
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Vérifier si l'ID de la facture est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$facture_id = $_GET['id'];

$database = new Database();
$db = $database->getConnection();

// Récupérer les informations de la facture avec le numéro de commande
$query = "SELECT f.*, c.nom as client_nom, c.prenom as client_prenom, c.email as client_email, 
          c.adresse as client_adresse, c.telephone as client_telephone,
          cmd.Numero_Commande
          FROM factures f
          LEFT JOIN clients c ON f.ID_Client = c.id
          LEFT JOIN commandes cmd ON f.ID_Commande = cmd.ID_Commande
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $facture_id);
$stmt->execute();
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si la facture existe
if (!$facture) {
    header('Location: index.php');
    exit;
}

// Récupérer les détails de la facture (articles)
$query_details = "SELECT fd.*, a.designation, a.reference
                  FROM facture_details fd
                  LEFT JOIN article a ON fd.article_id = a.id
                  WHERE fd.ID_Facture = :id";
$stmt_details = $db->prepare($query_details);
$stmt_details->bindParam(':id', $facture_id);
$stmt_details->execute();
$facture_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
$success = false;

// Traiter le changement de statut
if (isset($_POST['update_status'])) {
    try {
        $updateQuery = "UPDATE factures SET Statut_Facture = :statut, Updated_At = :updated_at";
        
        // Si le statut est "Payée", mettre à jour la date de paiement
        if ($_POST['statut'] === 'Payée') {
            $updateQuery .= ", Date_Paiement = :date_paiement, Mode_Paiement = :mode_paiement, Reference_Paiement = :reference_paiement";
        }
        
        $updateQuery .= " WHERE id = :id";
        
        $updateStmt = $db->prepare($updateQuery);
        
        $currentDate = date('Y-m-d H:i:s');
        
        $updateStmt->bindParam(':statut', $_POST['statut']);
        $updateStmt->bindParam(':updated_at', $currentDate);
        $updateStmt->bindParam(':id', $facture_id);
        
        if ($_POST['statut'] === 'Payée') {
            $updateStmt->bindParam(':date_paiement', $currentDate);
            $updateStmt->bindParam(':mode_paiement', $_POST['mode_paiement']);
            $updateStmt->bindParam(':reference_paiement', $_POST['reference_paiement']);
        }
        
        if ($updateStmt->execute()) {
            $success = true;
            $message = "Le statut de la facture a été mis à jour.";
            
            // Mettre à jour les données de la facture
            $facture['Statut_Facture'] = $_POST['statut'];
            $facture['Updated_At'] = $currentDate;
            
            if ($_POST['statut'] === 'Payée') {
                $facture['Date_Paiement'] = $currentDate;
                $facture['Mode_Paiement'] = $_POST['mode_paiement'];
                $facture['Reference_Paiement'] = $_POST['reference_paiement'];
            }
        } else {
            $message = "Erreur lors de la mise à jour du statut de la facture.";
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
    }
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-auto p-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Détails de la Facture</h1>
                <div class="flex space-x-2">
                    <a href="index.php" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Retour à la liste
                    </a>
                    <a href="edit_facture.php?id=<?php echo $facture_id; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Modifier
                    </a>
                    <a href="print_facture.php?id=<?php echo $facture_id; ?>" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500" target="_blank">
                        Imprimer
                    </a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="<?php echo $success ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> px-4 py-3 rounded mb-4 border">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Informations de la facture -->
            <div class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 bg-gray-50 rounded-md">
                    <h3 class="text-lg font-medium text-gray-700 mb-3">Informations de la Facture</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Numéro de facture:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['Numero_Facture']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Date de facture:</span>
                            <span class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($facture['Date_Facture'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Statut:</span>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                switch($facture['Statut_Facture']) {
                                    case 'Payée':
                                        echo 'bg-green-100 text-green-800';
                                        break;
                                    case 'En attente':
                                        echo 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'En retard':
                                        echo 'bg-red-100 text-red-800';
                                        break;
                                    case 'Annulée':
                                        echo 'bg-gray-100 text-gray-800';
                                        break;
                                    default:
                                        echo 'bg-blue-100 text-blue-800';
                                }
                                ?>">
                                <?php echo htmlspecialchars($facture['Statut_Facture']); ?>
                            </span>
                        </div>
                        <?php if ($facture['Statut_Facture'] === 'Payée' && !empty($facture['Date_Paiement'])): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Date de paiement:</span>
                                <span class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($facture['Date_Paiement'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Mode de paiement:</span>
                                <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['Mode_Paiement'] ?? 'Non spécifié'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Référence du paiement:</span>
                                <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['Reference_Paiement'] ?? 'Non spécifié'); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Montant total HT:</span>
                            <span class="text-sm font-bold text-gray-900"><?php echo number_format($facture['Montant_Total_HT'], 2, ',', ' '); ?> Dhs</span>
                        </div>
                        <?php if (!empty($facture['Numero_Commande'])): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Commande associée:</span>
                                <a href="../orders/view.php?id=<?php echo $facture['ID_Commande']; ?>" class="text-sm text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($facture['Numero_Commande']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 rounded-md">
                    <h3 class="text-lg font-medium text-gray-700 mb-3">Informations du Client</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Nom:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['client_nom'] . ' ' . $facture['client_prenom']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Email:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['client_email'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Téléphone:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['client_telephone'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Adresse:</span>
                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($facture['client_adresse'] ?? 'Non spécifié'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Détails de la facture (articles) -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-700 mb-3">Articles de la Facture</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire HT</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant HT</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($facture_details as $detail): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($detail['reference']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($detail['designation']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?php echo htmlspecialchars($detail['quantite']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?php echo number_format($detail['prix_unitaire'], 2, ',', ' '); ?> Dhs
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?php echo number_format($detail['montant_ht'], 2, ',', ' '); ?> Dhs
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                    Total HT:
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                    <?php echo number_format($facture['Montant_Total_HT'], 2, ',', ' '); ?> Dhs
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Notes -->
            <?php if (!empty($facture['Notes'])): ?>
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-700 mb-3">Notes</h3>
                    <div class="p-4 bg-gray-50 rounded-md">
                        <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($facture['Notes'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Mise à jour du statut -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-700 mb-3">Mise à jour du statut</h3>
                <div class="p-4 bg-gray-50 rounded-md">
                    <form method="POST" action="" id="update-status-form">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="statut" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                                <select id="statut" name="statut" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <?php 
                                    $statuts = ['En attente', 'Payée', 'Annulée', 'En retard'];
                                    foreach ($statuts as $statut):
                                        $selected = ($facture['Statut_Facture'] === $statut) ? 'selected' : '';
                                        echo "<option value=\"$statut\" $selected>$statut</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                            
                            <div id="payment-details" class="<?php echo $facture['Statut_Facture'] !== 'Payée' ? 'hidden' : ''; ?>">
                                <label for="mode_paiement" class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                                <select id="mode_paiement" name="mode_paiement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <?php 
                                    $modes = ['Carte bancaire', 'Espèces', 'Chèque', 'Virement bancaire', 'PayPal'];
                                    foreach ($modes as $mode):
                                        $selected = (isset($facture['Mode_Paiement']) && $facture['Mode_Paiement'] === $mode) ? 'selected' : '';
                                        echo "<option value=\"$mode\" $selected>$mode</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                            
                            <div id="reference-details" class="<?php echo $facture['Statut_Facture'] !== 'Payée' ? 'hidden' : ''; ?>">
                                <label for="reference_paiement" class="block text-sm font-medium text-gray-700 mb-1">Référence du paiement</label>
                                <input type="text" id="reference_paiement" name="reference_paiement" placeholder="Numéro de transaction, chèque, etc." 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo isset($facture['Reference_Paiement']) ? htmlspecialchars($facture['Reference_Paiement']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="update_status" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Mettre à jour le statut
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const statutSelect = document.getElementById('statut');
        const paymentDetails = document.getElementById('payment-details');
        const referenceDetails = document.getElementById('reference-details');
        
        // Afficher/masquer les détails de paiement en fonction du statut sélectionné
        statutSelect.addEventListener('change', function() {
            if (this.value === 'Payée') {
                paymentDetails.classList.remove('hidden');
                referenceDetails.classList.remove('hidden');
            } else {
                paymentDetails.classList.add('hidden');
                referenceDetails.classList.add('hidden');
            }
        });
    });
</script>

<?php
// Inclure le pied de page
include $root_path . '/includes/footer.php';
?>
