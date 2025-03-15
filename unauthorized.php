<?php
// Initialisation de la session
session_start();

// Titre de la page
$page_title = "Accès non autorisé";

// Inclusion de l'en-tête
include "includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <!-- Inclusion de la barre latérale -->
        <?php include "includes/sidebar.php"; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Accès non autorisé</h1>
            </div>
            
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Vous n'avez pas les permissions nécessaires!</h4>
                <p>Vous n'êtes pas autorisé à accéder à cette page ou à effectuer cette action.</p>
                <hr>
                <p class="mb-0">Si vous pensez qu'il s'agit d'une erreur, veuillez contacter l'administrateur du système.</p>
            </div>
            
            <div class="text-center mt-4">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>
        </main>
    </div>
</div>

<?php
// Inclusion du pied de page
include "includes/footer.php";
?>