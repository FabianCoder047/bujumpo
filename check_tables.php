<?php
require_once 'config/database.php';

$db = getDB();

// Récupérer la liste des tables
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "Tables disponibles dans la base de données :\n";
print_r($tables);

// Pour chaque table, afficher la structure
foreach ($tables as $table) {
    echo "\nStructure de la table $table :\n";
    $columns = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
}
?>
