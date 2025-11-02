<?php
require_once '../../includes/auth_check.php';
checkRole(['douanier']);
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = isset($input['type']) ? strtolower(trim($input['type'])) : '';
    $ref_id = isset($input['ref_id']) ? (int)$input['ref_id'] : 0;
    $mouvement = isset($input['mouvement']) ? strtolower(trim($input['mouvement'])) : 'entree';
    $thc = array_key_exists('thc', $input) && $input['thc'] !== '' ? (float)$input['thc'] : null;
    $magasinage = array_key_exists('magasinage', $input) && $input['magasinage'] !== '' ? (float)$input['magasinage'] : null;
    $droits = array_key_exists('droits_douane', $input) && $input['droits_douane'] !== '' ? (float)$input['droits_douane'] : null;
    $surestaries = array_key_exists('surestaries', $input) && $input['surestaries'] !== '' ? (float)$input['surestaries'] : null;

    if (!in_array($type, ['camion','bateau'], true) || $ref_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }
    if (!in_array($mouvement, ['entree','sortie'], true)) { $mouvement = 'entree'; }

    $db = getDB();

    // Validate existence and date for mouvement
    if ($type === 'camion') {
        $stmt = $db->prepare("SELECT date_entree, date_sortie FROM camions WHERE id = ?");
        $stmt->execute([$ref_id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Camion introuvable']); exit; }
        if ($mouvement === 'entree' && empty($row['date_entree'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>"Ce camion n'a pas d'entrée"]); exit; }
        if ($mouvement === 'sortie' && empty($row['date_sortie'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>"Ce camion n'a pas de sortie"]); exit; }
    } else {
        $stmt = $db->prepare("SELECT date_entree, date_sortie FROM bateaux WHERE id = ?");
        $stmt->execute([$ref_id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Bateau introuvable']); exit; }
        if ($mouvement === 'entree' && empty($row['date_entree'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>"Ce bateau n'a pas d'entrée"]); exit; }
        if ($mouvement === 'sortie' && empty($row['date_sortie'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>"Ce bateau n'a pas de sortie"]); exit; }
    }

    // Upsert
    $existsStmt = $db->prepare("SELECT id FROM frais_transit WHERE type=? AND ref_id=? AND mouvement=?");
    $existsStmt->execute([$type, $ref_id, $mouvement]);
    $id = $existsStmt->fetchColumn();

    if ($id) {
        $upd = $db->prepare("UPDATE frais_transit SET thc = :thc, magasinage = :magasinage, droits_douane = :droits, surestaries = :surestaries WHERE id = :id");
        $upd->execute([
            ':thc' => $thc, ':magasinage' => $magasinage, ':droits' => $droits, ':surestaries' => $surestaries, ':id' => $id
        ]);
    } else {
        $ins = $db->prepare("INSERT INTO frais_transit (type, ref_id, mouvement, thc, magasinage, droits_douane, surestaries, created_by) VALUES (:type, :ref_id, :mouvement, :thc, :magasinage, :droits, :surestaries, :created_by)");
        $user = getCurrentUser();
        $ins->execute([
            ':type' => $type,
            ':ref_id' => $ref_id,
            ':mouvement' => $mouvement,
            ':thc' => $thc,
            ':magasinage' => $magasinage,
            ':droits' => $droits,
            ':surestaries' => $surestaries,
            ':created_by' => $user['id']
        ]);
        $id = (int)$db->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => (int)$id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
