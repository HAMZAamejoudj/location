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
    //header('Location: login.php');
    //exit;
    $_SESSION['user_id'] = 1; // Utilisateur temporaire pour le développement
}

// Récupérer les informations de l'utilisateur actuel
$currentUser = [
    'name' => 'Utilisateur Test',
    'role' => 'Administrateur'
];

// Vérifier si l'ID de la commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_commande = intval($_GET['id']);

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la commande
$commande = null;
$articles = [];
$error_message = null;

try {
    // Récupérer les informations de la commande
    $query = "SELECT c.*, f.Code_Fournisseur, f.Raison_Sociale AS nom_fournisseur, f.Adresse, f.Telephone, f.Email 
    FROM commandes c
    INNER JOIN fournisseurs f ON c.ID_Fournisseur = f.ID_Fournisseur
    WHERE c.ID_Commande = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_commande);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les articles de la commande
        $query = "SELECT cd.*, a.reference, a.designation
                  FROM commande_details cd
                  INNER JOIN article a ON cd.article_id = a.id
                  WHERE cd.ID_Commande = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id_commande);
        $stmt->execute();
        
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "La commande demandée n'existe pas.";
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Inclure l'en-tête
include $root_path . '/includes/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include $root_path . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
        <div class="container mx-auto px-6 py-8">
            <!-- Header with back button -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center">
                    <a href="index.php" class="mr-4 text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-semibold text-gray-800">Détails de la Commande</h1>
                </div>
                <div class="flex space-x-3">
                    <button id="print-btn" class="px-4 py-2 bg-green-600 text-white rounded-md flex items-center hover:bg-green-700 transition duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Imprimer
                    </button>
                    <a href="edit.php?id=<?php echo $id_commande; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-md flex items-center hover:bg-blue-700 transition duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Modifier
                    </a>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php elseif ($commande): ?>
                <!-- Command Details -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex justify-between items-center">
                            <h2 class="text-lg font-semibold text-gray-800">Bon de Commande N° <?php echo htmlspecialchars($commande['Numero_Commande']); ?></h2>
                            <span class="<?php 
                                switch ($commande['Statut_Commande']) {
                                    case 'En attente':
                                        echo 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'En cours':
                                        echo 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'Livrée':
                                        echo 'bg-green-100 text-green-800';
                                        break;
                                    case 'Annulée':
                                        echo 'bg-red-100 text-red-800';
                                        break;
                                    default:
                                        echo 'bg-gray-100 text-gray-800';
                                }
                            ?> px-3 py-1 rounded-full text-sm font-semibold">
                                <?php echo htmlspecialchars($commande['Statut_Commande']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-2">Informations de la commande</h3>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Date de commande:</span> <?php echo date('d/m/Y', strtotime($commande['Date_Commande'])); ?></p>
                                    <p><span class="font-medium">Date de livraison prévue:</span> <?php echo date('d/m/Y', strtotime($commande['Date_Livraison_Prevue'])); ?></p>
                                    <p><span class="font-medium">Conditions de paiement:</span> <?php echo htmlspecialchars($commande['Conditions_Paiement'] ?? 'Non spécifié'); ?></p>
                                    <p><span class="font-medium">Créé par:</span> <?php echo htmlspecialchars($commande['Cree_Par'] ?? 'Non spécifié'); ?></p>
                                    <p><span class="font-medium">Date de création:</span> <?php echo isset($commande['Date_Creation']) ? date('d/m/Y H:i', strtotime($commande['Date_Creation'])) : 'Non spécifié'; ?></p>
                                    <?php if (isset($commande['Modifie_Par']) && $commande['Modifie_Par']): ?>
                                        <p><span class="font-medium">Modifié par:</span> <?php echo htmlspecialchars($commande['Modifie_Par']); ?></p>
                                        <p><span class="font-medium">Date de modification:</span> <?php echo date('d/m/Y H:i', strtotime($commande['Date_Modification'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-2">Informations du fournisseur</h3>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Code:</span> <?php echo htmlspecialchars($commande['Code_Fournisseur']); ?></p>
                                    <p><span class="font-medium">Nom:</span> <?php echo htmlspecialchars($commande['nom_fournisseur']); ?></p>
                                    <p><span class="font-medium">Adresse:</span> <?php echo htmlspecialchars($commande['Adresse'] ?? 'Non spécifié'); ?></p>
                                    <p><span class="font-medium">Téléphone:</span> <?php echo htmlspecialchars($commande['Telephone'] ?? 'Non spécifié'); ?></p>
                                    <p><span class="font-medium">Email:</span> <?php echo htmlspecialchars($commande['Email'] ?? 'Non spécifié'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (isset($commande['Notes']) && $commande['Notes']): ?>
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-500 mb-2">Notes</h3>
                                <div class="bg-gray-50 p-4 rounded-md">
                                    <p><?php echo nl2br(htmlspecialchars($commande['Notes'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Articles commandés</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unité</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total HT</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (count($articles) > 0): ?>
                                        <?php foreach ($articles as $article): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($article['reference']); ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($article['designation']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo $article['quantite']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo number_format($article['prix_unitaire'], 2, ',', ' ') . ' DH'; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo number_format($article['montant_ht'], 2, ',', ' ') . ' DH'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Aucun article trouvé pour cette commande.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-right text-sm font-medium text-gray-500">Total HT:</td>
                                        <td class="px-6 py-4 text-right text-sm font-medium text-gray-900"><?php echo number_format($commande['Montant_Total_HT'], 2, ',', ' ') . ' DH'; ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Print Template -->
<div id="print-template" class="hidden">
    <div class="print-content p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">Bon de Commande</h1>
            <p class="text-lg">N° <?php echo isset($commande) ? htmlspecialchars($commande['Numero_Commande']) : ''; ?></p>
        </div>
        
        <div class="flex justify-between mb-6">
            <div>
                <h2 class="font-bold">Fournisseur:</h2>
                <p><?php echo isset($commande) ? htmlspecialchars($commande['Code_Fournisseur'] . ' - ' . $commande['nom_fournisseur']) : ''; ?></p>
                <p><?php echo isset($commande) ? htmlspecialchars($commande['Adresse'] ?? '') : ''; ?></p>
                <p><?php echo isset($commande) ? htmlspecialchars($commande['Telephone'] ?? '') : ''; ?></p>
                <p><?php echo isset($commande) ? htmlspecialchars($commande['Email'] ?? '') : ''; ?></p>
            </div>
            <div>
                <p><strong>Date de commande:</strong> <?php echo isset($commande) ? date('d/m/Y', strtotime($commande['Date_Commande'])) : ''; ?></p>
                <p><strong>Date de livraison prévue:</strong> <?php echo isset($commande) ? date('d/m/Y', strtotime($commande['Date_Livraison_Prevue'])) : ''; ?></p>
                <p><strong>Statut:</strong> <?php echo isset($commande) ? htmlspecialchars($commande['Statut_Commande']) : ''; ?></p>
                <p><strong>Conditions de paiement:</strong> <?php echo isset($commande) ? htmlspecialchars($commande['Conditions_Paiement'] ?? 'Non spécifié') : ''; ?></p>
            </div>
        </div>
        
        <table class="w-full mb-6 border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2 text-left">Référence</th>
                    <th class="border p-2 text-left">Désignation</th>
                    <th class="border p-2 text-left">Unité</th>
                    <th class="border p-2 text-right">Quantité</th>
                    <th class="border p-2 text-right">Prix unitaire</th>
                    <th class="border p-2 text-right">Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($articles) && count($articles) > 0): ?>
                    <?php foreach ($articles as $article): ?>
                        <tr>
                            <td class="border p-2"><?php echo htmlspecialchars($article['reference']); ?></td>
                            <td class="border p-2"><?php echo htmlspecialchars($article['designation']); ?></td>
                            <td class="border p-2 text-right"><?php echo $article['quantite']; ?></td>
                            <td class="border p-2 text-right"><?php echo number_format($article['prix_unitaire'], 2, ',', ' ') . ' DH'; ?></td>
                            <td class="border p-2 text-right"><?php echo number_format($article['montant_ht'], 2, ',', ' ') . ' DH'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="border p-2 text-center">Aucun article trouvé pour cette commande.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="border p-2 text-right font-bold">Total HT:</td>
                    <td class="border p-2 text-right"><?php echo isset($commande) ? number_format($commande['Montant_Total_HT'], 2, ',', ' ') . ' DH' : ''; ?></td>
                </tr>
            </tfoot>
        </table>
        
        <?php if (isset($commande) && isset($commande['Notes']) && $commande['Notes']): ?>
            <div class="mt-8">
                <h3 class="font-bold mb-2">Notes:</h3>
                <p class="border p-2"><?php echo nl2br(htmlspecialchars($commande['Notes'])); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="mt-8 flex justify-between">
            <div>
                <p class="font-bold">Signature du responsable:</p>
                <div class="h-16 w-32 border-b mt-12"></div>
            </div>
            <div>
                <p class="font-bold">Cachet de l'entreprise:</p>
                <div class="h-16 w-32 border mt-4"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to print the command
    document.getElementById('print-btn').addEventListener('click', function() {
        const printContent = document.getElementById('print-template').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Bon de Commande - <?php echo isset($commande) ? htmlspecialchars($commande['Numero_Commande']) : ''; ?></title>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; }
                    th { background-color: #f2f2f2; text-align: left; }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                    .font-bold { font-weight: bold; }
                    @media print {
                        body { margin: 0; padding: 15px; }
                        button { display: none; }
                    }
                </style>
            </head>
            <body>
                ${printContent}
                <div class="text-center" style="margin-top: 20px;">
                    <button onclick="window.print(); setTimeout(function() { window.close(); }, 500);">Imprimer</button>
                </div>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    });
</script>

<?php include $root_path . '/includes/footer.php'; ?>
