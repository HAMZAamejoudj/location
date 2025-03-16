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

// Récupérer la liste des véhicules et techniciens pour le formulaire
$database = new Database();
$db = $database->getConnection();
// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get form data
        $numero_commande = $_POST['numero_commande'];
        $date_commande = $_POST['date_commande'];
        $date_livraison_prevue = $_POST['date_livraison_prevue'];
        $id_fournisseur = $_POST['id_fournisseur'];
        $statut = $_POST['statut'];
        $montant_total_ht = $_POST['montant_total_ht'];
       
        $montant_total_ttc = $_POST['montant_total_ttc'];
        $notes = $_POST['notes'] ?? '';
        
        // Insert command into database
        $query = "INSERT INTO commandes (
                    Numero_Commande, 
                    Date_Commande, 
                    ID_Fournisseur, 
                    Date_Livraison_Prevue, 
                    Statut_Commande, 
                    Montant_Total_HT, 
                    
                    Montant_Total_TTC, 
                    Notes
                  ) VALUES (
                    :numero_commande, 
                    :date_commande, 
                    :id_fournisseur, 
                    :date_livraison_prevue, 
                    :statut, 
                    :montant_total_ht, 
                  
                    :montant_total_ttc, 
                    :notes
                  )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':numero_commande', $numero_commande);
        $stmt->bindParam(':date_commande', $date_commande);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur);
        $stmt->bindParam(':date_livraison_prevue', $date_livraison_prevue);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':montant_total_ht', $montant_total_ht);
      
        $stmt->bindParam(':montant_total_ttc', $montant_total_ttc);
        $stmt->bindParam(':notes', $notes);
        
        $stmt->execute();
        $id_commande = $db->lastInsertId();
        
        // Process articles
        if (isset($_POST['articles']) && is_array($_POST['articles'])) {
            foreach ($_POST['articles'] as $article) {
                // Skip empty rows
                if (empty($article['id_article'])) {
                    continue;
                }
                
                $id_article = $article['id_article'];
                $quantite = $article['quantite'];
                $prix_unitaire = $article['prix_unitaire'];
                $taux_tva = $article['taux_tva'];
                
                // Calculate line totals
                $total_ht = $quantite * $prix_unitaire;
                $total_tva = $total_ht * ($taux_tva / 100);
                $total_ttc = $total_ht + $total_tva;
                
                // Insert command line
                $query = "INSERT INTO commande_details (
                            ID_Commande, 
                            ID_Article, 
                            Quantite, 
                            Prix_Unitaire_HT,
                            Taux_TVA,
                            Total_HT,
                            Total_TVA,
                            Total_TTC
                          ) VALUES (
                            :id_commande,
                            :id_article,
                            :quantite,
                            :prix_unitaire,
                            :taux_tva,
                            :total_ht,
                            :total_tva,
                            :total_ttc
                          )";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_commande', $id_commande);
                $stmt->bindParam(':id_article', $id_article);
                $stmt->bindParam(':quantite', $quantite);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire);
                $stmt->bindParam(':taux_tva', $taux_tva);
                $stmt->bindParam(':total_ht', $total_ht);
                $stmt->bindParam(':total_tva', $total_tva);
                $stmt->bindParam(':total_ttc', $total_ttc);
                
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Set success message
        $success_message = "La commande a été créée avec succès!";
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->rollBack();
        $error_message = "Erreur: " . $e->getMessage();
    }
}

// Generate new command number
$newCommandeNumber = 'CMD-' . date('Ymd') . '-' . rand(1000, 9999);

// Fetch fournisseurs list
$fournisseurs = [];
try {
    $query = "SELECT * FROM fournisseurs ORDER BY ID_Fournisseur";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Fetch articles list
$articles = [];
try {
    $query = "SELECT * FROM articles ORDER BY designation";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une nouvelle commande</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">Créer une nouvelle commande</h1>
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Retour aux commandes
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (isset($error_message)): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Erreur!</strong>
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Succès!</strong>
                <span class="block sm:inline"><?php echo $success_message; ?></span>
                <div class="mt-2">
                    <a href="commandes.php" class="text-green-700 underline">Retour à la liste des commandes</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Command Form -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <form id="commandeForm" method="POST" action="">
                    <!-- Command Details Section -->
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Informations de la commande</h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">Remplissez les détails pour cette commande.</p>
                    </div>
                    
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <!-- Command Number -->
                            <div class="sm:col-span-2">
                                <label for="numero_commande" class="block text-sm font-medium text-gray-700">Numéro de commande</label>
                                <div class="mt-1">
                                    <input type="text" name="numero_commande" id="numero_commande" 
                                        value="<?php echo $newCommandeNumber; ?>" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" >
                                </div>
                            </div>

                            <!-- Command Date -->
                            <div class="sm:col-span-2">
                                <label for="date_commande" class="block text-sm font-medium text-gray-700">Date de commande</label>
                                <div class="mt-1">
                                    <input type="date" name="date_commande" id="date_commande" 
                                        value="<?php echo date('Y-m-d'); ?>" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>

                            <!-- Expected Delivery Date -->
                            <div class="sm:col-span-2">
                                <label for="date_livraison_prevue" class="block text-sm font-medium text-gray-700">Date de livraison prévue</label>
                                <div class="mt-1">
                                    <input type="date" name="date_livraison_prevue" id="date_livraison_prevue" 
                                        value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>

                            <!-- Fournisseur Selection -->
                            <div class="sm:col-span-3">
                                <label for="id_fournisseur" class="block text-sm font-medium text-gray-700">Fournisseur</label>
                                <div class="mt-1">
                                    <select id="id_fournisseur" name="id_fournisseur" required
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        <option value="">Sélectionner un fournisseur</option>
                                        <?php foreach ($fournisseurs as $fournisseur): ?>
                                            <option value="<?php echo $fournisseur['ID_Fournisseur']; ?>"><?php echo htmlspecialchars($fournisseur['Code_Fournisseur']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Status Selection -->
                            <div class="sm:col-span-3">
                                <label for="statut" class="block text-sm font-medium text-gray-700">Statut</label>
                                <div class="mt-1">
                                    <select id="statut" name="statut" required
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        <option value="En attente" selected>En attente</option>
                                        <option value="En cours">En cours</option>
                                        <option value="Livrée">Livrée</option>
                                        <option value="Annulée">Annulée</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Articles Section -->
                    <div class="px-4 py-5 sm:px-6 border-t border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Articles commandés</h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">Ajoutez les articles à cette commande.</p>
                    </div>

                    <div class="px-4 py-5 sm:p-6">
                        <div id="articles-container">
                            <!-- Article Template Row -->
                            <div class="article-row grid grid-cols-1 gap-y-4 gap-x-4 sm:grid-cols-12 mb-6 pb-6 border-b border-gray-200">
                                <div class="sm:col-span-5">
                                    <label class="block text-sm font-medium text-gray-700">Article</label>
                                    <select name="articles[0][id_article]" class="article-select mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="updateArticleInfo(this, 0)">
                                        <option value="">Sélectionner un article</option>
                                        <?php foreach ($articles as $article): ?>
                                            <option value="<?php echo $article['id']; ?>" 
                                                    data-reference="<?php echo htmlspecialchars($article['reference']); ?>"
                                                    data-designation="<?php echo htmlspecialchars($article['designation']); ?>"
                                                    data-prix="<?php echo $article['prix_achat']; ?>"
                                                    data-tva="<?php echo $article['tva']; ?>">
                                                <?php echo htmlspecialchars($article['designation']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Référence</label>
                                    <input type="text" name="articles[0][reference]" class="mt-1 shadow-sm block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50" readonly>
                                </div>
                                
                                <div class="sm:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700">Quantité</label>
                                    <input type="number" name="articles[0][quantite]" min="1" value="1" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(0)">
                                </div>
                                
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Prix unitaire HT</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number" step="0.01" name="articles[0][prix_unitaire]" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(0)">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">€</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="sm:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700">TVA (%)</label>
                                    <input type="number" step="0.1" name="articles[0][taux_tva]" value="20" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" required onchange="calculateRowTotal(0)">
                                </div>
                                
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Total HT</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="text" name="articles[0][total_ht]" class="bg-gray-50 block w-full pr-12 sm:text-sm border-gray-300 rounded-md" readonly>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">€</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="sm:col-span-1 flex items-end justify-center">
                                    <button type="button" class="text-red-600 hover:text-red-900" onclick="removeArticleRow(this)">
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="button" id="add-article-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Ajouter un article
                            </button>
                        </div>

                        <!-- Totals Section -->
                        <div class="mt-8 border-t border-gray-200 pt-8">
                            <div class="flex flex-col sm:flex-row sm:justify-end">
                                <div class="w-full sm:w-1/3 bg-gray-50 p-4 rounded-md">
                                    <div class="flex justify-between py-2 text-sm">
                                        <span class="font-medium text-gray-500">Total HT:</span>
                                        <span id="total_ht_display" class="font-medium">0,00 €</span>
                                        <input type="hidden" name="montant_total_ht" id="montant_total_ht" value="0">
                                    </div>
                                    <div class="flex justify-between py-2 text-sm">
                                        <span class="font-medium text-gray-500">Total TVA:</span>
                                        <span id="total_tva_display" class="font-medium">0,00 €</span>
                                        <input type="hidden" name="montant_total_tva" id="montant_total_tva" value="0">
                                    </div>
                                    <div class="flex justify-between py-2 text-base font-medium">
                                        <span class="text-gray-900">Total TTC:</span>
                                        <span id="total_ttc_display" class="text-indigo-600">0,00 €</span>
                                        <input type="hidden" name="montant_total_ttc" id="montant_total_ttc" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="px-4 py-5 sm:px-6 border-t border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Notes et commentaires</h3>
                        <div class="mt-2">
                            <textarea name="notes" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Ajoutez des notes ou instructions spéciales pour cette commande..."></textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 flex justify-end space-x-3">
                        <a href="commandes.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Annuler
                        </a>
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Enregistrer la commande
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Counter for article rows
        let articleCounter = 1;

        // Add new article row
        document.getElementById('add-article-btn').addEventListener('click', function() {
            const container = document.getElementById('articles-container');
            const template = container.querySelector('.article-row').cloneNode(true);
            
            // Update names and indices
            const inputs = template.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/\[\d+\]/, '[' + articleCounter + ']'));
                }
                if (input.classList.contains('article-select')) {
                    input.setAttribute('onchange', 'updateArticleInfo(this, ' + articleCounter + ')');
                }
                if (input.getAttribute('onchange') && input.getAttribute('onchange').includes('calculateRowTotal')) {
                    input.setAttribute('onchange', 'calculateRowTotal(' + articleCounter + ')');
                }
                // Reset values
                if (!input.readOnly && input.type !== 'hidden') {
                    if (input.type === 'number' && input.name.includes('quantite')) {
                        input.value = 1;
                    } else if (input.type === 'number' && input.name.includes('taux_tva')) {
                        input.value = 20;
                    } else {
                        input.value = '';
                    }
                }
            });
            
            container.appendChild(template);
            articleCounter++;
        });

        // Remove article row
        function removeArticleRow(button) {
            const rows = document.querySelectorAll('.article-row');
            if (rows.length > 1) {
                button.closest('.article-row').remove();
                calculateTotals();
            }
        }

        // Update article information when selected
        function updateArticleInfo(select, index) {
            const selectedOption = select.options[select.selectedIndex];
            const row = select.closest('.article-row');
            
            if (selectedOption.value) {
                const reference = selectedOption.getAttribute('data-reference');
                const prix = selectedOption.getAttribute('data-prix');
                const tva = selectedOption.getAttribute('data-tva');
                
                row.querySelector('input[name="articles[' + index + '][reference]"]').value = reference;
                row.querySelector('input[name="articles[' + index + '][prix_unitaire]"]').value = prix;
                row.querySelector('input[name="articles[' + index + '][taux_tva]"]').value = tva || 20;
                
                calculateRowTotal(index);
            }
        }

        // Calculate row total
        function calculateRowTotal(index) {
            const row = document.querySelector('select[name="articles[' + index + '][id_article]"]').closest('.article-row');
            const quantite = parseFloat(row.querySelector('input[name="articles[' + index + '][quantite]"]').value) || 0;
            const prixUnitaire = parseFloat(row.querySelector('input[name="articles[' + index + '][prix_unitaire]"]').value) || 0;
            
            const totalHT = quantite * prixUnitaire;
            row.querySelector('input[name="articles[' + index + '][total_ht]"]').value = totalHT.toFixed(2) + ' €';
            
            calculateTotals();
        }

        // Calculate order totals
        function calculateTotals() {
            let totalHT = 0;
            let totalTVA = 0;
            
            document.querySelectorAll('.article-row').forEach(row => {
                const quantite = parseFloat(row.querySelector('input[name*="[quantite]"]').value) || 0;
                const prixUnitaire = parseFloat(row.querySelector('input[name*="[prix_unitaire]"]').value) || 0;
                const tauxTVA = parseFloat(row.querySelector('input[name*="[taux_tva]"]').value) || 0;
                
                const ligneHT = quantite * prixUnitaire;
                const ligneTVA = ligneHT * (tauxTVA / 100);
                
                totalHT += ligneHT;
                totalTVA += ligneTVA;
            });
            
            const totalTTC = totalHT + totalTVA;
            
            // Update displayed totals
            document.getElementById('total_ht_display').textContent = formatCurrency(totalHT);
            document.getElementById('total_tva_display').textContent = formatCurrency(totalTVA);
            document.getElementById('total_ttc_display').textContent = formatCurrency(totalTTC);
            
            // Update hidden inputs for form submission
            document.getElementById('montant_total_ht').value = totalHT.toFixed(2);
          
            document.getElementById('montant_total_ttc').value = totalTTC.toFixed(2);
        }

        // Format currency with Euro symbol
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(amount);
        }
 // Validate form before submission
 document.getElementById('commandeForm').addEventListener('submit', function(event) {
            let isValid = true;
            let hasArticles = false;
            
            // Check if supplier is selected
            const fournisseur = document.getElementById('id_fournisseur');
            if (!fournisseur.value) {
                isValid = false;
                fournisseur.classList.add('border-red-500');
                const errorMsg = document.createElement('p');
                errorMsg.className = 'mt-1 text-sm text-red-600';
                errorMsg.textContent = 'Veuillez sélectionner un fournisseur';
                
                // Only add error message if it doesn't exist already
                if (!fournisseur.parentNode.querySelector('.text-red-600')) {
                    fournisseur.parentNode.appendChild(errorMsg);
                }
            } else {
                fournisseur.classList.remove('border-red-500');
                const errorMsg = fournisseur.parentNode.querySelector('.text-red-600');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
            
            // Check if at least one article is selected
            document.querySelectorAll('.article-select').forEach(select => {
                if (select.value) {
                    hasArticles = true;
                }
            });
            
            if (!hasArticles) {
                isValid = false;
                const container = document.getElementById('articles-container');
                
                // Only add error message if it doesn't exist already
                if (!container.nextElementSibling.classList || !container.nextElementSibling.classList.contains('text-red-600')) {
                    const errorMsg = document.createElement('p');
                    errorMsg.className = 'mt-2 text-sm text-red-600';
                    errorMsg.textContent = 'Veuillez sélectionner au moins un article';
                    container.parentNode.insertBefore(errorMsg, container.nextElementSibling);
                }
            } else {
                const errorMsg = document.querySelector('#articles-container + .text-red-600');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
            
            // Validate each article row
            document.querySelectorAll('.article-row').forEach(row => {
                const articleSelect = row.querySelector('.article-select');
                
                if (articleSelect.value) {
                    // Check if quantity is valid
                    const quantityInput = row.querySelector('input[name*="[quantite]"]');
                    if (!quantityInput.value || parseInt(quantityInput.value) < 1) {
                        isValid = false;
                        quantityInput.classList.add('border-red-500');
                        
                        // Only add error message if it doesn't exist already
                        if (!quantityInput.parentNode.querySelector('.text-red-600')) {
                            const errorMsg = document.createElement('p');
                            errorMsg.className = 'mt-1 text-sm text-red-600';
                            errorMsg.textContent = 'Quantité invalide';
                            quantityInput.parentNode.appendChild(errorMsg);
                        }
                    } else {
                        quantityInput.classList.remove('border-red-500');
                        const errorMsg = quantityInput.parentNode.querySelector('.text-red-600');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    }
                    
                    // Check if price is valid
                    const priceInput = row.querySelector('input[name*="[prix_unitaire]"]');
                    if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
                        isValid = false;
                        priceInput.classList.add('border-red-500');
                        
                        // Only add error message if it doesn't exist already
                        if (!priceInput.parentNode.querySelector('.text-red-600')) {
                            const errorMsg = document.createElement('p');
                            errorMsg.className = 'mt-1 text-sm text-red-600';
                            errorMsg.textContent = 'Prix invalide';
                            priceInput.parentNode.appendChild(errorMsg);
                        }
                    } else {
                        priceInput.classList.remove('border-red-500');
                        const errorMsg = priceInput.parentNode.querySelector('.text-red-600');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    }
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                
                // Scroll to the first error
                const firstError = document.querySelector('.border-red-500');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</body>
</html>