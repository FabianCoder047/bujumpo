<?php
session_start();
require_once 'config/database.php';

// Redirection basée sur le rôle si l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'autorite':
            header('Location: autorite/dashboard.php');
            break;
        case 'vigileEntree':
            header('Location: vigile-entree/dashboard.php');
            break;
        case 'vigileSortie':
            header('Location: vigile-sortie/dashboard.php');
            break;
        case 'peseur':
            header('Location: peseur/dashboard.php');
            break;
        case 'vigileMaritime':
            header('Location: vigile-maritime/dashboard.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Port de Bujumbura - Accueil</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-blue-100 flex items-center justify-center min-h-screen">

  <!-- Carte principale -->
  <div class="bg-white shadow-xl rounded-2xl p-10 max-w-6xl w-full flex flex-col md:flex-row items-center space-y-8 md:space-y-0 md:space-x-10">
    
    <!-- Texte à gauche -->
    <div class="md:w-1/2 space-y-4">
      <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900">
        PORT DE BUJUMBURA
      </h1>
      <h2 class="text-2xl font-semibold text-blue-600 flex items-center gap-2">
        Système de Gestion Portuaire 
        <i class="fa-solid fa-anchor text-blue-400"></i>
      </h2>
      <p class="text-gray-600 text-lg">
        Connectez-vous pour accéder au système de gestion portuaire.
      </p>
      <a href="login.php" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md font-medium transition-all duration-200">
        <i class="fa-solid fa-right-to-bracket mr-2"></i> Veuillez vous connecter
      </a>
    </div>

    <!-- Image à droite -->
    <div class="md:w-1/2">
      <img src="images/home-img.png" 
           alt="Conteneur au port" 
           class="rounded-xl shadow-md w-full object-cover">
    </div>
  </div>

</body>
</html>
