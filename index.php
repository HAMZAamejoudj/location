<?php
// Initialisation de la session
session_start();

// Redirection vers la page de connexion ou le tableau de bord en fonction de l'état de connexion
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>