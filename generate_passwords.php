<?php
// Script pour générer les nouveaux mots de passe hachés
$passwords = [
    'EnregistreurEntreeRoute' => 'EnregistreurEntreeRoute123',
    'EnregistreurSortieRoute' => 'EnregistreurSortieRoute123',
    'EnregistreurBateaux' => 'EnregistreurBateaux123'
];

foreach ($passwords as $role => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "UPDATE users SET password = '$hash' WHERE role = '$role';<br>";
}
?>
