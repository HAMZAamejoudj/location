<?php
// Initialisation de la session
session_start();

echo "<h1>Débogage de session</h1>";
echo "<h2>Variables de session</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Test de permission 'lecture_stats'</h2>";
require_once "includes/functions.php";
echo "Résultat: " . (hasPermission('lecture_stats') ? "Autorisé" : "Non autorisé");

echo "<h2>Actions</h2>";
echo "<ul>";
echo "<li><a href='logout.php'>Se déconnecter</a></li>";
echo "<li><a href='dashboard.php'>Aller au tableau de bord</a></li>";
echo "</ul>";
?>