<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
    exit();
}

$email = trim($input['email']);
$password = $input['password'];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nom, prenom, email, password, role, first_login FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        exit();
    }
    
    // Créer la session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nom'] = $user['nom'];
    $_SESSION['prenom'] = $user['prenom'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_login'] = $user['first_login'];
    
    // Déterminer la redirection
    $redirect = '';
    switch ($user['role']) {
        case 'admin':
            $redirect = 'admin/dashboard.php';
            break;
        case 'autorite':
            $redirect = 'autorite/dashboard.php';
            break;
        case 'EnregistreurEntreeRoute':
            $redirect = 'vigile-entree/dashboard.php';
            break;
        case 'EnregistreurSortieRoute':
            $redirect = 'vigile-sortie/dashboard.php';
            break;
        case 'peseur':
            $redirect = 'peseur/dashboard.php';
            break;
        case 'EnregistreurBateaux':
            $redirect = 'vigile-maritime/dashboard.php';
            break;
        case 'douanier':
            $redirect = 'douane/dashboard.php';
            break;
    }
    
    // Logger la connexion
    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
    $logStmt->execute([$user['id'], 'Connexion', 'Connexion au système']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'redirect' => $redirect,
        'first_login' => $user['first_login']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
