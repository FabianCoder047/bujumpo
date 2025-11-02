<?php
require_once '../../includes/auth_check.php';
checkRole(['douanier']);
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
    $ref_id = isset($_GET['ref_id']) ? (int)$_GET['ref_id'] : 0;
    $mouvement = isset($_GET['mouvement']) ? strtolower(trim($_GET['mouvement'])) : 'entree';
    if (!in_array($type, ['camion','bateau'], true) || $ref_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }
    if (!in_array($mouvement, ['entree','sortie'], true)) { $mouvement = 'entree'; }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, thc, magasinage, droits_douane, surestaries, total FROM frais_transit WHERE type=? AND ref_id=? AND mouvement=?");
    $stmt->execute([$type, $ref_id, $mouvement]);
    $row = $stmt->fetch();
    echo json_encode(['success' => true, 'item' => $row ?: null]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
