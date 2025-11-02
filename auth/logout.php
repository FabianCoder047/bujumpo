<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();
        $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([$_SESSION['user_id'], 'Déconnexion', 'Déconnexion du système']);
    } catch (Exception $e) {
        // Ignorer les erreurs de log lors de la déconnexion
    }
}

session_destroy();
header('Location: ../index.php');
exit();
?>
