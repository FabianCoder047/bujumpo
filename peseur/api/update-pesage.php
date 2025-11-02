<?php
require_once '../../includes/auth_check.php';
checkRole(['peseur']);
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) { $input = []; }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pesage invalide']);
        exit;
    }

    $db = getDB();

    // Récupérer le pesage courant
    $stmt = $db->prepare('SELECT * FROM pesages WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pesage introuvable']);
        exit;
    }

    $fields = [
        'ptav' => isset($input['ptav']) ? (float)$input['ptav'] : (float)$current['ptav'],
        'ptac' => isset($input['ptac']) ? (float)$input['ptac'] : (float)$current['ptac'],
        'ptra' => isset($input['ptra']) ? (float)$input['ptra'] : (float)$current['ptra'],
        'charge_essieu' => isset($input['charge_essieu']) ? (float)$input['charge_essieu'] : (float)$current['charge_essieu'],
        'total_poids_marchandises' => array_key_exists('total_poids_marchandises', $input) ? (float)$input['total_poids_marchandises'] : (float)($current['total_poids_marchandises'] ?? 0),
    ];

    // Surcharge: si fournie, utiliser, sinon recalculer (poids marchandises > ptac)
    if (isset($input['surcharge'])) {
        $fields['surcharge'] = (int)$input['surcharge'] ? 1 : 0;
    } else {
        $fields['surcharge'] = ($fields['total_poids_marchandises'] > $fields['ptac']) ? 1 : 0;
    }

    // Déterminer les changements
    $before = [
        'ptav' => (float)$current['ptav'],
        'ptac' => (float)$current['ptac'],
        'ptra' => (float)$current['ptra'],
        'charge_essieu' => (float)$current['charge_essieu'],
        'total_poids_marchandises' => (float)($current['total_poids_marchandises'] ?? 0),
        'surcharge' => (int)($current['surcharge'] ?? 0),
    ];

    $changes = [];
    foreach ($fields as $k => $v) {
        $prev = $before[$k];
        if ((string)$prev !== (string)$v) {
            $changes[$k] = ['from' => $prev, 'to' => $v];
        }
    }

    if (empty($changes)) {
        echo json_encode(['success' => true, 'message' => 'Aucun changement']);
        exit;
    }

    // Mise à jour
    $upd = $db->prepare('UPDATE pesages SET ptav = ?, ptac = ?, ptra = ?, charge_essieu = ?, total_poids_marchandises = ?, surcharge = ? WHERE id = ?');
    $upd->execute([
        $fields['ptav'],
        $fields['ptac'],
        $fields['ptra'],
        $fields['charge_essieu'],
        $fields['total_poids_marchandises'],
        $fields['surcharge'],
        $id,
    ]);

    // Log
    $user = getCurrentUser();
    $details = 'Modification pesage ID '.$id.'; changements: '.json_encode($changes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $log = $db->prepare('INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)');
    $log->execute([$user['id'], 'Modification Pesage', $details]);

    echo json_encode(['success' => true, 'message' => 'Pesage mis à jour', 'changes' => $changes]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: '.$e->getMessage()]);
}
