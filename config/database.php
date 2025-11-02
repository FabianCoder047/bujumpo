<?php
class Database {
    private $host = '127.0.0.1';
    private $db_name = 'port_bujumbura';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Erreur de connexion: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Fonction utilitaire pour obtenir une connexion
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// Fonction pour initialiser la base de données
function initDatabase() {
    $host = '127.0.0.1';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer la base de données si elle n'existe pas
        $pdo->exec("CREATE DATABASE IF NOT EXISTS port_bujumbura");
        $pdo->exec("USE port_bujumbura");
        
        // Créer les tables
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'autorite', 'EnregistreurEntreeRoute', 'EnregistreurSortieRoute', 'peseur', 'EnregistreurBateaux') NOT NULL,
            first_login BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS types_marchandises (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS types_camions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS types_bateaux (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS ports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            pays VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS camions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_camion_id INT,
            marque VARCHAR(100) NOT NULL,
            immatriculation VARCHAR(50) UNIQUE NOT NULL,
            chauffeur VARCHAR(100) NOT NULL,
            agence VARCHAR(100) NOT NULL,
            est_charge BOOLEAN DEFAULT FALSE,
            date_entree TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_sortie TIMESTAMP NULL,
            statut ENUM('entree', 'en_pesage', 'sortie') DEFAULT 'entree',
            FOREIGN KEY (type_camion_id) REFERENCES types_camions(id)
        );
        
        CREATE TABLE IF NOT EXISTS marchandises_camions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            camion_id INT,
            type_marchandise_id INT,
            poids DECIMAL(10,2),
            quantite INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (camion_id) REFERENCES camions(id) ON DELETE CASCADE,
            FOREIGN KEY (type_marchandise_id) REFERENCES types_marchandises(id)
        );
        
        CREATE TABLE IF NOT EXISTS pesages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            camion_id INT,
            ptav DECIMAL(10,2),
            ptac DECIMAL(10,2),
            ptra DECIMAL(10,2),
            charge_essieu DECIMAL(10,2),
            total_poids_marchandises DECIMAL(10,2) NULL,
            surcharge BOOLEAN DEFAULT 0,
            date_pesage TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (camion_id) REFERENCES camions(id)
        );
        
        CREATE TABLE IF NOT EXISTS bateaux (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_bateau_id INT,
            nom VARCHAR(100) NOT NULL,
            immatriculation VARCHAR(50) UNIQUE,
            capitaine VARCHAR(100) NOT NULL,
            agence VARCHAR(100),
            hauteur DECIMAL(10,2),
            longueur DECIMAL(10,2),
            largeur DECIMAL(10,2),
            port_origine_id INT,
            port_destination_id INT,
            date_entree TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_sortie TIMESTAMP NULL,
            statut ENUM('entree', 'sortie') DEFAULT 'entree',
            FOREIGN KEY (type_bateau_id) REFERENCES types_bateaux(id),
            FOREIGN KEY (port_origine_id) REFERENCES ports(id),
            FOREIGN KEY (port_destination_id) REFERENCES ports(id)
        );
        
        CREATE TABLE IF NOT EXISTS marchandises_bateaux (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bateau_id INT,
            type_marchandise_id INT,
            poids DECIMAL(10,2),
            quantite INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bateau_id) REFERENCES bateaux(id) ON DELETE CASCADE,
            FOREIGN KEY (type_marchandise_id) REFERENCES types_marchandises(id)
        );
        
        CREATE TABLE IF NOT EXISTS passagers_bateaux (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bateau_id INT,
            numero_passager INT NOT NULL,
            poids_marchandises DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bateau_id) REFERENCES bateaux(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        ";
        
        $pdo->exec($sql);
        
        // Créer un utilisateur admin par défaut
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $adminExists = $stmt->fetchColumn();
        
        if (!$adminExists) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, role, first_login) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Admin', 'Système', 'admin@port-bujumbura.com', $adminPassword, 'admin', 0]);
        }
        
        // Créer le Port de BUJUMBURA par défaut
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ports WHERE UPPER(nom) = UPPER(?)");
        $stmt->execute(['BUJUMBURA']);
        $bujumburaExists = $stmt->fetchColumn();
        
        if (!$bujumburaExists) {
            $stmt = $pdo->prepare("INSERT INTO ports (nom, pays, description) VALUES (?, ?, ?)");
            $stmt->execute(['BUJUMBURA', 'Burundi', 'Port principal de Bujumbura']);
        }
        
        return true;
    } catch(PDOException $e) {
        echo "Erreur lors de l'initialisation: " . $e->getMessage();
        return false;
    }
}
?>