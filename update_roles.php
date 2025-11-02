<?php
// Script pour mettre à jour les références aux rôles dans les fichiers
$filesToUpdate = [
    'vigile-entree' => 'EnregistreurEntreeRoute',
    'vigile-sortie' => 'EnregistreurSortieRoute',
    'vigile-maritime' => 'EnregistreurBateaux'
];

// Fonction pour mettre à jour les références dans un fichier
function updateFileReferences($filePath, $oldRole, $newRole) {
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $newContent = str_replace("'$oldRole'", "'$newRole'", $content);
        $newContent = str_replace('"'.$oldRole.'"', '"'.$newRole.'"', $newContent);
        
        if ($content !== $newContent) {
            file_put_contents($filePath, $newContent);
            echo "Updated: $filePath\n";
        }
    }
}

// Mettre à jour les fichiers dans les dossiers concernés
foreach ($filesToUpdate as $oldDir => $newRole) {
    $oldRole = str_replace('-', '', $oldDir);
    
    // Mettre à jour les fichiers dans le dossier
    if (is_dir($oldDir)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($oldDir));
        foreach ($files as $file) {
            if ($file->isFile()) {
                updateFileReferences($file->getPathname(), $oldRole, $newRole);
            }
        }
    }
    
    // Mettre à jour les fichiers qui font référence aux rôles
    $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.'));
    foreach ($allFiles as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'html'])) {
            updateFileReferences($file->getPathname(), $oldRole, $newRole);
        }
    }
}

echo "Mise à jour des rôles terminée.\n";
?>
