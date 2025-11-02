<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer tous les ports
    $query = "SELECT id, nom, pays FROM ports WHERE nom != 'Port de Bujumbura' ORDER BY nom ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $ports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($ports);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
