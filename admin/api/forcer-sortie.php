<?php
require_once '../../includes/auth_check.php';
checkRole(['admin']);
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du bateau invalide']);
    exit();
}

$bateau_id = (int)$_POST['id'];
$db = getDB();

try {
    // Vérifier si le bateau existe et n'est pas déjà sorti
    $stmt = $db->prepare("SELECT * FROM bateaux WHERE id = ? AND date_sortie IS NULL");
    $stmt->execute([$bateau_id]);
    $bateau = $stmt->fetch();

    if (!$bateau) {
        echo json_encode(['success' => false, 'message' => 'Bateau non trouvé ou déjà sorti']);
        exit();
    }

    // Mettre à jour la date de sortie
    $stmt = $db->prepare("UPDATE bateaux SET date_sortie = NOW(), statut = 'sortie' WHERE id = ?");
    $stmt->execute([$bateau_id]);

    // Enregistrer le mouvement
    $stmt = $db->prepare("
        INSERT INTO mouvements (bateau_id, type_mouvement, date_mouvement, utilisateur_id, notes)
        VALUES (?, 'sortie', NOW(), ?, 'Sortie forcée par l\'administrateur')
    ");
    $stmt->execute([$bateau_id, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Sortie enregistrée avec succès']);

} catch (Exception $e) {
    error_log('Erreur lors de la sortie forcée du bateau : ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la sortie du bateau']);
}
?>
