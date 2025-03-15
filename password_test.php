<?php
$stored_hash = '$2y$10$RHCt1BeGxFXwyzRZgiMT1.KQxivfdV1mj6R6e5iuJZqk6.9ebSOJa';
$test_password = 'admin123';

echo "<h3>Test de vérification du mot de passe</h3>";
echo "Hash stocké : " . $stored_hash . "<br>";
echo "Mot de passe à tester : " . $test_password . "<br>";

$result = password_verify($test_password, $stored_hash);
echo "Résultat de password_verify() : " . ($result ? "Succès" : "Échec") . "<br>";

if (!$result) {
    // Créer un nouveau hash pour tester
    $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "<hr>";
    echo "Nouveau hash généré : " . $new_hash . "<br>";
    echo "Vous pouvez utiliser cette requête SQL pour mettre à jour le mot de passe :<br>";
    echo "<code>UPDATE users SET password = '$new_hash' WHERE username = 'admin';</code>";
}
?>