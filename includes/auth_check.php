<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Fonction pour vérifier si l'utilisateur a le bon rôle
function checkRole($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header('Location: ../index.php');
        exit();
    }
}

// Fonction pour obtenir les informations de l'utilisateur connecté
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'],
        'nom' => $_SESSION['nom'],
        'prenom' => $_SESSION['prenom'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}
?>
