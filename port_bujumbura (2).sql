-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 02 nov. 2025 à 09:00
-- Version du serveur : 8.2.0
-- Version de PHP : 8.3.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `port_bujumbura`
--

-- --------------------------------------------------------

--
-- Structure de la table `bateaux`
--

DROP TABLE IF EXISTS `bateaux`;
CREATE TABLE IF NOT EXISTS `bateaux` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type_bateau_id` int DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `immatriculation` varchar(50) DEFAULT NULL,
  `capitaine` varchar(100) NOT NULL,
  `agence` varchar(100) DEFAULT NULL,
  `hauteur` decimal(10,2) DEFAULT NULL,
  `longueur` decimal(10,2) DEFAULT NULL,
  `largeur` decimal(10,2) DEFAULT NULL,
  `port_origine_id` int DEFAULT NULL,
  `port_destination_id` int DEFAULT NULL,
  `date_entree` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_sortie` timestamp NULL DEFAULT NULL,
  `statut` enum('entree','sortie') DEFAULT 'entree',
  PRIMARY KEY (`id`),
  UNIQUE KEY `immatriculation` (`immatriculation`),
  UNIQUE KEY `immatriculation_2` (`immatriculation`),
  UNIQUE KEY `immatriculation_3` (`immatriculation`),
  KEY `type_bateau_id` (`type_bateau_id`),
  KEY `port_origine_id` (`port_origine_id`),
  KEY `port_destination_id` (`port_destination_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `bateaux`
--

INSERT INTO `bateaux` (`id`, `type_bateau_id`, `nom`, `immatriculation`, `capitaine`, `agence`, `hauteur`, `longueur`, `largeur`, `port_origine_id`, `port_destination_id`, `date_entree`, `date_sortie`, `statut`) VALUES
(1, 1, 'MV Lake Tanganyika', 'BT0012024', 'Jean Ndayishimiye', 'Transnav Ltd', 8.50, 45.00, 12.50, 3, 2, '2024-01-15 08:30:00', '2024-01-20 16:45:00', 'sortie'),
(2, 1, 'MV Bujumbura Express', 'BT0022024', 'Pierre Hakizimana', 'Lake Shipping Co', 7.80, 38.00, 10.20, 4, 2, '2024-02-10 10:15:00', '2024-02-15 14:20:00', 'sortie'),
(3, 2, 'MS Kigoma Passenger', 'BT0032024', 'David Nkurunziza', 'Passenger Lines', 6.20, 25.00, 8.50, 5, 2, '2024-03-05 09:45:00', '2024-03-07 17:30:00', 'sortie'),
(4, 1, 'MV Moba Cargo', 'BT0042024', 'Samuel Kubwimana', 'Cargo Trans Ltd', 9.10, 52.00, 14.80, 6, 2, '2024-04-12 11:20:00', '2024-04-18 15:10:00', 'sortie'),
(5, 2, 'MS Kalemie Ferry', 'BT0052024', 'Eric Manirakiza', 'Ferry Services', 5.80, 22.00, 7.20, 7, 2, '2024-05-08 07:50:00', '2024-05-10 18:15:00', 'sortie'),
(6, 1, 'MV Gitaza Trader', 'BT0062024', 'Alexis Niyonkuru', 'Trade Marine', 8.20, 41.00, 11.50, 3, 2, '2024-06-20 13:40:00', '2024-06-25 12:25:00', 'sortie'),
(7, 1, 'MV Rumonge Carrier', 'BT0072024', 'Fabrice Sindayigaya', 'Carrier Group', 7.50, 36.00, 9.80, 4, 2, '2024-07-14 15:30:00', '2024-07-19 11:45:00', 'sortie'),
(8, 2, 'MS Nyanza Voyager', 'BT0082024', 'Patrick Ndikumana', 'Voyager Lines', 6.50, 28.00, 8.00, 5, 2, '2024-08-22 12:10:00', '2024-08-24 16:50:00', 'sortie'),
(9, 1, 'MV Makobola Freight', 'BT0092024', 'Emmanuel Bararwandika', 'Freight Masters', 8.80, 48.00, 13.20, 6, 2, '2024-09-18 10:05:00', '2024-09-23 14:35:00', 'sortie'),
(10, 1, 'MV Uvira Express', 'BT0102024', 'Christian Ndayisaba', 'Express Shipping', 7.20, 34.00, 10.50, 7, 2, '2024-10-05 08:20:00', '2024-10-10 14:30:00', 'sortie'),
(11, 1, 'MV Kigoma Trader', 'BT0112024', 'Alain Niyonzima', 'African Shipping', 8.00, 42.00, 11.80, 8, 2, '2024-03-20 09:30:00', '2024-03-25 15:20:00', 'sortie'),
(12, 1, 'MV Bujumbura Carrier', 'BT0122024', 'Robert Ndayisenga', 'Burundi Cargo', 7.60, 39.00, 10.60, 9, 2, '2024-05-15 11:45:00', '2024-05-20 14:30:00', 'sortie'),
(13, 2, 'MS Tanganyika Voyager', 'BT0132024', 'Philippe Manirakiza', 'Lake Voyages', 6.80, 30.00, 8.80, 10, 2, '2024-07-10 10:20:00', '2024-07-12 18:45:00', 'sortie'),
(14, 1, 'MV Congo Express', 'BT0142024', 'Marc Nkurunziza', 'Congo Lines', 9.20, 55.00, 15.20, 11, 2, '2024-08-30 13:15:00', '2024-09-05 16:40:00', 'sortie'),
(15, 1, 'MV Tanzania Trader', 'BT0152024', 'Thomas Barampama', 'Tanzania Shipping', 8.40, 46.00, 12.80, 12, 2, '2024-10-12 14:50:00', '2024-10-18 11:20:00', 'sortie'),
(16, 1, 'MV New Horizon', 'BT0012025', 'Jean Bosco Ndayishimiye', 'Horizon Shipping', 8.70, 47.00, 13.50, 3, 2, '2025-01-15 09:30:00', '2025-01-21 15:45:00', 'sortie'),
(17, 1, 'MV Lake Prosperity', 'BT0022025', 'Alexandre Hakizimana', 'Prosperity Lines', 7.90, 40.00, 11.20, 4, 2, '2025-02-12 10:45:00', '2025-02-18 14:30:00', 'sortie'),
(18, 2, 'MS Peace Voyager', 'BT0032025', 'David Manirakiza', 'Peace Navigation', 6.40, 27.00, 8.80, 5, 2, '2025-03-08 08:15:00', '2025-03-10 17:20:00', 'sortie'),
(19, 1, 'MV Economic Growth', 'BT0042025', 'Samuel Niyonkuru', 'Growth Marine', 9.30, 53.00, 14.90, 6, 2, '2025-04-18 11:30:00', '2025-04-24 16:15:00', 'sortie'),
(20, 1, 'MV Trade Wind', 'BT0052025', 'Eric Ndikumana', 'Wind Trading', 8.10, 43.00, 12.10, 7, 2, '2025-05-22 13:20:00', '2025-05-28 12:40:00', 'sortie'),
(21, 1, 'MV Lake Explorer', 'BT0062025', 'Fabrice Bararwandika', 'Explorer Shipping', 7.70, 38.50, 10.80, 8, 2, '2025-06-25 14:10:00', '2025-07-01 13:25:00', 'sortie'),
(22, 2, 'MS Unity Passenger', 'BT0072025', 'Patrick Ndayisaba', 'Unity Lines', 6.60, 29.00, 8.50, 9, 2, '2025-07-30 09:45:00', '2025-08-01 18:30:00', 'sortie'),
(23, 1, 'MV Progress Carrier', 'BT0082025', 'Emmanuel Nkurunziza', 'Progress Cargo', 8.90, 49.00, 13.80, 10, 2, '2025-08-28 12:15:00', '2025-09-03 15:50:00', 'sortie'),
(24, 1, 'MV Future Trader', 'BT0092025', 'Christian Manirakiza', 'Future Trading', 8.20, 44.00, 12.40, 11, 2, '2025-09-20 10:30:00', '2025-09-26 14:20:00', 'sortie'),
(25, 1, 'MV Hope Express', 'BT0102025', 'Pierre Niyongabo', 'Hope Shipping', 7.50, 37.00, 10.60, 12, 2, '2025-10-15 08:20:00', NULL, 'entree'),
(26, 1, 'MV Lake Victory', 'BT0112025', 'Jean Pierre Ndayishimiye', 'Victory Shipping', 8.60, 46.00, 13.20, 3, 2, '2025-10-25 09:15:00', NULL, 'entree'),
(27, 1, 'MV Unity Express', 'BT0122025', 'David Nkurikiye', 'Unity Marine', 7.80, 39.50, 11.00, 4, 2, '2025-10-26 10:30:00', '2025-10-28 16:45:00', 'sortie'),
(28, 2, 'MS Tanganyika Star', 'BT0132025', 'Pierre Niyongabo', 'Star Navigation', 6.70, 31.00, 9.20, 5, 2, '2025-10-27 11:45:00', '2025-10-29 18:20:00', 'sortie'),
(29, 1, 'MV Prosperity Carrier', 'BT0142025', 'Samuel Manirakiza', 'Prosperity Cargo', 9.00, 51.00, 14.50, 6, 2, '2025-10-28 13:20:00', NULL, 'entree'),
(30, 1, 'MV Future Express', 'BT0152025', 'Eric Barampama', 'Future Express', 8.30, 44.50, 12.80, 7, 2, '2025-10-29 14:35:00', '2025-11-02 15:30:00', 'sortie');

-- --------------------------------------------------------

--
-- Structure de la table `camions`
--

DROP TABLE IF EXISTS `camions`;
CREATE TABLE IF NOT EXISTS `camions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type_camion_id` int DEFAULT NULL,
  `marque` varchar(100) NOT NULL,
  `immatriculation` varchar(50) NOT NULL,
  `chauffeur` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `agence` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `provenance_port_id` int DEFAULT NULL,
  `destinataire` varchar(255) DEFAULT NULL,
  `t1` varchar(100) DEFAULT NULL,
  `est_charge` tinyint(1) DEFAULT '0',
  `date_entree` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_sortie` timestamp NULL DEFAULT NULL,
  `statut` enum('entree','en_pesage','en_attente_sortie','sortie') DEFAULT 'entree' COMMENT 'Statut du camion dans le processus',
  `observations_sortie` text COMMENT 'Observations lors de la sortie',
  `retour_vide` tinyint(1) DEFAULT '0' COMMENT 'Indique si le camion est reparti à vide',
  PRIMARY KEY (`id`),
  UNIQUE KEY `immatriculation` (`immatriculation`),
  KEY `type_camion_id` (`type_camion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `camions`
--

INSERT INTO `camions` (`id`, `type_camion_id`, `marque`, `immatriculation`, `chauffeur`, `agence`, `provenance_port_id`, `destinataire`, `t1`, `est_charge`, `date_entree`, `date_sortie`, `statut`, `observations_sortie`, `retour_vide`) VALUES
(1, 1, 'VOLVO', 'BU1234AB', 'Jean Claude Niyonzima', 'Transco Burundi', 2, 'SOGEA BTP', 'CONT001', 1, '2024-01-10 07:30:00', '2024-01-10 16:45:00', 'sortie', 'Livraison complète - documents en règle', 0),
(2, 1, 'MERCEDES', 'BU5678CD', 'Pierre Nkurunziza', 'Kobil Trucking', 2, 'BRARUDI', 'CONT002', 1, '2024-01-12 08:15:00', '2024-01-12 15:20:00', 'sortie', 'Marchandises fragiles - manipulation soignée', 0),
(3, 1, 'SCANIA', 'BU9012EF', 'David Manirakiza', 'Logistique Centre', 2, 'MINISTERE TP', 'CONT003', 1, '2024-02-05 09:40:00', '2024-02-05 17:30:00', 'sortie', 'Contrôle douanier effectué', 0),
(4, 1, 'MAN', 'BU3456GH', 'Eric Ndayishimiye', 'Transafric', 2, 'SUPERMARKET TANGANYIKA', 'CONT004', 1, '2024-02-18 10:20:00', '2024-02-18 14:50:00', 'sortie', 'Déchargement rapide', 0),
(5, 1, 'IVECO', 'BU7890IJ', 'Alexis Hakizimana', 'Cargo Express', 2, 'BICOR', 'CONT005', 1, '2024-03-08 11:10:00', '2024-03-08 18:15:00', 'sortie', 'Retard dans le déchargement', 0),
(20, 1, 'FORD', 'BU8901MN', 'Christian Nkurikiye', 'Express Logistics', 2, 'BBD', 'CONT020', 0, '2024-10-15 16:20:00', '2024-10-15 22:30:00', 'sortie', 'Camion vide - retour', 1),
(21, 1, 'VOLVO', 'BU1357AC', 'André Nsabimana', 'Nordic Transport', 2, 'MINISTERE AGRICULTURE', 'CONT021', 1, '2024-02-28 08:30:00', '2024-02-28 17:15:00', 'sortie', 'Engrais agricoles - livraison saisonnière', 0),
(30, 1, 'FORD', 'BU0246JL', 'Zacharie Ndimurukundo', 'General Cargo', 2, 'ENTREPOT GENERAL', 'CONT030', 1, '2024-10-22 17:45:00', '2024-10-23 00:15:00', 'sortie', 'Marchandises générales', 0),
(31, 1, 'VOLVO', 'BU2025AA', 'Jean de Dieu Niyonsaba', 'New Age Transport', 2, 'CENTRE HOSPITALIER', 'CONT031', 1, '2025-01-08 07:15:00', '2025-01-08 16:30:00', 'sortie', 'Équipement médical urgent', 0),
(32, 1, 'MERCEDES', 'BU2025BB', 'Paul Nshimirimana', 'Premium Logistics', 2, 'UNIVERSITE DU BURUNDI', 'CONT032', 1, '2025-01-20 08:45:00', '2025-01-20 17:20:00', 'sortie', 'Matériel pédagogique', 0),
(33, 1, 'SCANIA', 'BU2025CC', 'Jacques Ndayisenga', 'Eco Transport', 2, 'CENTRE ENVIRONNEMENT', 'CONT033', 1, '2025-02-05 09:30:00', '2025-02-05 18:45:00', 'sortie', 'Équipement écologique', 0),
(34, 1, 'MAN', 'BU2025DD', 'Antoine Ntahomvukiye', 'Food Express', 2, 'SUPERMARCHE NOVATI', 'CONT034', 1, '2025-02-18 10:20:00', '2025-02-18 19:10:00', 'sortie', 'Produits alimentaires frais', 0),
(35, 1, 'IVECO', 'BU2025EE', 'Philippe Ndabaneze', 'Tech Solutions', 2, 'SOCIETE TECHNOLOGIE', 'CONT035', 1, '2025-03-10 11:15:00', '2025-03-10 20:25:00', 'sortie', 'Équipement high-tech', 0),
(36, 1, 'RENAULT', 'BU2025FF', 'Gaston Niyukuri', 'Construction Plus', 2, 'CHANTIER NOUVEAU', 'CONT036', 1, '2025-03-25 12:40:00', '2025-03-25 21:50:00', 'sortie', 'Matériaux construction premium', 0),
(37, 1, 'DAF', 'BU2025GG', 'Hervé Ndayizeye', 'Pharma Express', 2, 'LABORATOIRE PHARMA', 'CONT037', 1, '2025-04-12 13:25:00', '2025-04-12 22:35:00', 'sortie', 'Produits pharmaceutiques spéciaux', 0),
(38, 1, 'HINO', 'BU2025HH', 'Lucien Nkurikiye', 'Agri Solutions', 2, 'COOPERATIVE BANANE', 'CONT038', 1, '2025-04-28 14:50:00', '2025-04-28 23:40:00', 'sortie', 'Intrants agricoles', 0),
(39, 1, 'ISUZU', 'BU2025II', 'Marcel Niyongere', 'Energy Transport', 2, 'SOCIETE ENERGETIQUE', 'CONT039', 1, '2025-05-15 15:35:00', '2025-05-15 23:55:00', 'sortie', 'Équipement énergétique', 0),
(40, 1, 'FORD', 'BU2025JJ', 'Noël Ndimurukundo', 'Textile Plus', 2, 'USINE TEXTILE MODERNE', 'CONT040', 1, '2025-05-30 16:20:00', '2025-05-31 01:10:00', 'sortie', 'Textiles de qualité', 0),
(41, 1, 'VOLVO', 'BU2025KK', 'Olivier Nsengiyumva', 'Beverage Experts', 2, 'BRASSERIE NOUVELLE', 'CONT041', 1, '2025-06-12 07:45:00', '2025-06-12 16:55:00', 'sortie', 'Ingrédients boissons', 0),
(42, 1, 'MERCEDES', 'BU2025LL', 'Patrick Ntibazonkiza', 'Chemical Pro', 2, 'USINE CHIMIQUE AVANCEE', 'CONT042', 1, '2025-06-28 08:30:00', '2025-06-28 17:40:00', 'sortie', 'Produits chimiques spécialisés', 0),
(43, 1, 'SCANIA', 'BU2025MM', 'Robert Ndayishimiye', 'Electronics Trans', 2, 'DISTRIBUTEUR ELECTRONIQUE', 'CONT043', 1, '2025-07-14 09:15:00', '2025-07-14 18:25:00', 'sortie', 'Appareils électroniques', 0),
(44, 1, 'MAN', 'BU2025NN', 'Serge Niyonkuru', 'Furniture Masters', 2, 'USINE MEUBLES DESIGN', 'CONT044', 1, '2025-07-30 10:40:00', '2025-07-30 19:50:00', 'sortie', 'Meubles design', 0),
(45, 1, 'IVECO', 'BU2025OO', 'Thierry Hakizimana', 'Construction Elite', 2, 'CHANTIER PRESTIGE', 'CONT045', 1, '2025-08-16 11:25:00', '2025-08-16 20:35:00', 'sortie', 'Matériaux haut de gamme', 0),
(46, 1, 'RENAULT', 'BU2025PP', 'Urbain Nkurunziza', 'Food Quality', 2, 'SUPERMARCHE GOURMET', 'CONT046', 1, '2025-09-02 12:50:00', '2025-09-02 21:40:00', 'sortie', 'Produits alimentaires premium', 0),
(47, 1, 'DAF', 'BU2025QQ', 'Victor Barampama', 'Medical Supplies', 2, 'HOPITAL MODERNE', 'CONT047', 1, '2025-09-20 13:35:00', '2025-09-20 22:45:00', 'sortie', 'Fournitures médicales', 0),
(48, 1, 'HINO', 'BU2025RR', 'Xavier Ndayisaba', 'Tech Innovation', 2, 'CENTRE INNOVATION', 'CONT048', 1, '2025-10-08 14:20:00', '2025-10-08 23:30:00', 'sortie', 'Équipement innovant', 0),
(49, 1, 'ISUZU', 'BU2025SS', 'Yves Manirakiza', 'Agri Future', 2, 'FERME MODERNE', 'CONT049', 1, '2025-10-18 15:45:00', NULL, 'en_attente_sortie', NULL, 0),
(50, 1, 'FORD', 'BU2025TT', 'Zacharie Niyongabo', 'General Trade', 2, 'ENTREPOT CENTRAL', 'CONT050', 1, '2025-10-22 16:30:00', NULL, 'en_pesage', NULL, 0),
(51, 1, 'VOLVO', 'BU2025UU', 'Didier Nkurunziza', 'Global Transport', 2, 'USINE TEXTILE', 'CONT051', 1, '2025-10-25 08:15:00', NULL, 'entree', NULL, 0),
(52, 1, 'MERCEDES', 'BU2025VV', 'Eric Niyonkuru', 'Fast Cargo', 2, 'SUPERMARCHE CENTRAL', 'CONT052', 1, '2025-10-26 09:30:00', NULL, 'en_pesage', NULL, 0),
(53, 1, 'SCANIA', 'BU2025WW', 'Paul Ndayisenga', 'Heavy Transport', 2, 'CHANTIER NATIONAL', 'CONT053', 1, '2025-10-27 10:45:00', '2025-10-27 19:30:00', 'sortie', 'Matériaux lourds - déchargement sécurisé', 0),
(54, 1, 'MAN', 'BU2025XX', 'Alain Hakizimana', 'Food Distrib', 2, 'RESTAURANT CORPORATE', 'CONT054', 1, '2025-10-28 11:20:00', '2025-10-28 20:15:00', 'sortie', 'Produits alimentaires frais', 0),
(55, 1, 'IVECO', 'BU2025YY', 'Marc Nshimirimana', 'Tech Transport', 2, 'CENTRE TECHNOLOGIQUE', 'CONT055', 1, '2025-10-29 12:35:00', NULL, 'en_attente_sortie', NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `frais_transit`
--

DROP TABLE IF EXISTS `frais_transit`;
CREATE TABLE IF NOT EXISTS `frais_transit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('camion','bateau') NOT NULL,
  `ref_id` int NOT NULL,
  `mouvement` enum('entree','sortie') NOT NULL DEFAULT 'entree',
  `thc` decimal(12,2) DEFAULT NULL,
  `magasinage` decimal(12,2) DEFAULT NULL,
  `droits_douane` decimal(12,2) DEFAULT NULL,
  `surestaries` decimal(12,2) DEFAULT NULL,
  `total` decimal(12,2) GENERATED ALWAYS AS ((((coalesce(`thc`,0) + coalesce(`magasinage`,0)) + coalesce(`droits_douane`,0)) + coalesce(`surestaries`,0))) VIRTUAL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ref` (`type`,`ref_id`,`mouvement`),
  KEY `idx_type_ref` (`type`,`ref_id`),
  KEY `fk_frais_user` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb3;

--
-- Déchargement des données de la table `frais_transit`
--

INSERT INTO `frais_transit` (`id`, `type`, `ref_id`, `mouvement`, `thc`, `magasinage`, `droits_douane`, `surestaries`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'bateau', 1, 'entree', 12500.00, 8500.00, 45000.00, 0.00, 1, '2024-01-15 09:00:00', '2024-01-15 09:00:00'),
(2, 'bateau', 1, 'sortie', 8500.00, 0.00, 0.00, 0.00, 1, '2024-01-20 17:00:00', '2024-01-20 17:00:00'),
(3, 'bateau', 2, 'entree', 11800.00, 7200.00, 38500.00, 0.00, 1, '2024-02-10 10:30:00', '2024-02-10 10:30:00'),
(24, 'bateau', 11, 'entree', 13200.00, 8800.00, 47000.00, 0.00, 1, '2024-03-20 09:45:00', '2024-03-20 09:45:00'),
(25, 'bateau', 12, 'entree', 11500.00, 7500.00, 39000.00, 0.00, 1, '2024-05-15 12:00:00', '2024-05-15 12:00:00'),
(26, 'bateau', 16, 'entree', 14200.00, 9200.00, 48500.00, 0.00, 1, '2025-01-15 09:45:00', '2025-01-15 09:45:00'),
(27, 'bateau', 17, 'entree', 12800.00, 8100.00, 42500.00, 0.00, 1, '2025-02-12 11:00:00', '2025-02-12 11:00:00'),
(28, 'bateau', 18, 'entree', 9200.00, 4800.00, 13500.00, 0.00, 1, '2025-03-08 08:30:00', '2025-03-08 08:30:00'),
(29, 'bateau', 19, 'entree', 14800.00, 9800.00, 51200.00, 0.00, 1, '2025-04-18 11:45:00', '2025-04-18 11:45:00'),
(30, 'bateau', 20, 'entree', 13500.00, 8600.00, 44500.00, 0.00, 1, '2025-05-22 13:35:00', '2025-05-22 13:35:00'),
(31, 'camion', 31, 'entree', 2850.00, 1750.00, 9200.00, 0.00, 1, '2025-01-08 07:30:00', '2025-01-08 07:30:00'),
(32, 'camion', 32, 'entree', 2650.00, 1600.00, 8500.00, 0.00, 1, '2025-01-20 09:00:00', '2025-01-20 09:00:00'),
(33, 'camion', 33, 'entree', 2950.00, 1850.00, 9800.00, 0.00, 1, '2025-02-05 09:45:00', '2025-02-05 09:45:00'),
(34, 'camion', 34, 'entree', 2250.00, 1400.00, 7200.00, 0.00, 1, '2025-02-18 10:35:00', '2025-02-18 10:35:00'),
(35, 'camion', 35, 'entree', 3150.00, 1950.00, 10500.00, 0.00, 1, '2025-03-10 11:30:00', '2025-03-10 11:30:00'),
(36, 'camion', 36, 'entree', 2750.00, 1700.00, 8900.00, 0.00, 1, '2025-03-25 12:55:00', '2025-03-25 12:55:00'),
(37, 'camion', 37, 'entree', 3050.00, 1900.00, 10100.00, 0.00, 1, '2025-04-12 13:40:00', '2025-04-12 13:40:00'),
(38, 'camion', 38, 'entree', 2450.00, 1550.00, 8100.00, 0.00, 1, '2025-04-28 15:05:00', '2025-04-28 15:05:00'),
(39, 'camion', 39, 'entree', 3250.00, 2050.00, 10800.00, 0.00, 1, '2025-05-15 15:50:00', '2025-05-15 15:50:00'),
(40, 'camion', 40, 'entree', 2550.00, 1650.00, 8600.00, 0.00, 1, '2025-05-30 16:35:00', '2025-05-30 16:35:00'),
(41, 'camion', 41, 'entree', 2850.00, 1800.00, 9500.00, 0.00, 1, '2025-06-12 08:00:00', '2025-06-12 08:00:00'),
(42, 'camion', 42, 'entree', 3350.00, 2100.00, 11200.00, 0.00, 1, '2025-06-28 08:45:00', '2025-06-28 08:45:00'),
(43, 'camion', 43, 'entree', 2950.00, 1900.00, 10000.00, 0.00, 1, '2025-07-14 09:30:00', '2025-07-14 09:30:00'),
(44, 'camion', 44, 'entree', 2650.00, 1750.00, 9200.00, 0.00, 1, '2025-07-30 10:55:00', '2025-07-30 10:55:00'),
(45, 'camion', 45, 'entree', 3150.00, 2000.00, 10600.00, 0.00, 1, '2025-08-16 11:40:00', '2025-08-16 11:40:00'),
(46, 'bateau', 26, 'entree', 13800.00, 8900.00, 46500.00, 0.00, 1, '2025-10-25 09:30:00', '2025-10-25 09:30:00'),
(47, 'bateau', 27, 'entree', 12600.00, 8000.00, 41500.00, 0.00, 1, '2025-10-26 10:45:00', '2025-10-26 10:45:00'),
(48, 'bateau', 28, 'entree', 9500.00, 5000.00, 14200.00, 0.00, 1, '2025-10-27 12:00:00', '2025-10-27 12:00:00'),
(49, 'bateau', 29, 'entree', 14500.00, 9400.00, 49500.00, 0.00, 1, '2025-10-28 13:35:00', '2025-10-28 13:35:00'),
(50, 'bateau', 30, 'entree', 13200.00, 8500.00, 43800.00, 0.00, 1, '2025-10-29 14:50:00', '2025-10-29 14:50:00'),
(51, 'camion', 51, 'entree', 2750.00, 1700.00, 8900.00, 0.00, 1, '2025-10-25 08:30:00', '2025-10-25 08:30:00'),
(52, 'camion', 52, 'entree', 2450.00, 1550.00, 8100.00, 0.00, 1, '2025-10-26 09:45:00', '2025-10-26 09:45:00'),
(53, 'camion', 53, 'entree', 3250.00, 2050.00, 10800.00, 0.00, 1, '2025-10-27 11:00:00', '2025-10-27 11:00:00'),
(54, 'camion', 54, 'entree', 2250.00, 1400.00, 7200.00, 0.00, 1, '2025-10-28 11:35:00', '2025-10-28 11:35:00'),
(55, 'camion', 55, 'entree', 3050.00, 1900.00, 10100.00, 0.00, 1, '2025-10-29 12:50:00', '2025-10-29 12:50:00');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'Connexion', 'Connexion au système', '2025-10-24 13:29:04'),
(2, 1, 'Déconnexion', 'Déconnexion du système', '2025-10-24 13:29:26'),
(3, 1, 'Connexion', 'Connexion au système', '2024-01-10 07:00:00'),
(4, 1, 'Enregistrement camion', 'Camion BU1234AB enregistré', '2024-01-10 07:30:00'),
(62, 1, 'Enregistrement camion', 'Camion BU4680DF enregistré', '2024-07-30 11:35:00'),
(63, 1, 'Connexion', 'Connexion au système', '2025-01-08 06:45:00'),
(64, 1, 'Enregistrement bateau', 'Bateau MV New Horizon enregistré', '2025-01-15 09:30:00'),
(65, 1, 'Enregistrement camion', 'Camion BU2025AA enregistré', '2025-01-08 07:15:00'),
(66, 1, 'Enregistrement camion', 'Camion BU2025BB enregistré', '2025-01-20 08:45:00'),
(67, 1, 'Enregistrement bateau', 'Bateau MV Lake Prosperity enregistré', '2025-02-12 10:45:00'),
(68, 1, 'Enregistrement camion', 'Camion BU2025CC enregistré', '2025-02-05 09:30:00'),
(69, 1, 'Enregistrement camion', 'Camion BU2025DD enregistré', '2025-02-18 10:20:00'),
(70, 1, 'Enregistrement bateau', 'Bateau MS Peace Voyager enregistré', '2025-03-08 08:15:00'),
(71, 1, 'Enregistrement camion', 'Camion BU2025EE enregistré', '2025-03-10 11:15:00'),
(72, 1, 'Enregistrement camion', 'Camion BU2025FF enregistré', '2025-03-25 12:40:00'),
(73, 1, 'Enregistrement bateau', 'Bateau MV Economic Growth enregistré', '2025-04-18 11:30:00'),
(74, 1, 'Enregistrement camion', 'Camion BU2025GG enregistré', '2025-04-12 13:25:00'),
(75, 1, 'Enregistrement camion', 'Camion BU2025HH enregistré', '2025-04-28 14:50:00'),
(76, 1, 'Enregistrement bateau', 'Bateau MV Trade Wind enregistré', '2025-05-22 13:20:00'),
(77, 1, 'Enregistrement camion', 'Camion BU2025II enregistré', '2025-05-15 15:35:00'),
(78, 1, 'Enregistrement camion', 'Camion BU2025JJ enregistré', '2025-05-30 16:20:00'),
(79, 1, 'Enregistrement bateau', 'Bateau MV Lake Explorer enregistré', '2025-06-25 14:10:00'),
(80, 1, 'Enregistrement camion', 'Camion BU2025KK enregistré', '2025-06-12 07:45:00'),
(81, 1, 'Enregistrement camion', 'Camion BU2025LL enregistré', '2025-06-28 08:30:00'),
(82, 1, 'Enregistrement bateau', 'Bateau MS Unity Passenger enregistré', '2025-07-30 09:45:00'),
(83, 1, 'Enregistrement camion', 'Camion BU2025MM enregistré', '2025-07-14 09:15:00'),
(84, 1, 'Enregistrement camion', 'Camion BU2025NN enregistré', '2025-07-30 10:40:00'),
(85, 1, 'Enregistrement bateau', 'Bateau MV Progress Carrier enregistré', '2025-08-28 12:15:00'),
(86, 1, 'Enregistrement camion', 'Camion BU2025OO enregistré', '2025-08-16 11:25:00'),
(87, 1, 'Enregistrement bateau', 'Bateau MV Future Trader enregistré', '2025-09-20 10:30:00'),
(88, 1, 'Enregistrement camion', 'Camion BU2025PP enregistré', '2025-09-02 12:50:00'),
(89, 1, 'Enregistrement camion', 'Camion BU2025QQ enregistré', '2025-09-20 13:35:00'),
(90, 1, 'Enregistrement bateau', 'Bateau MV Hope Express enregistré', '2025-10-15 08:20:00'),
(91, 1, 'Enregistrement camion', 'Camion BU2025RR enregistré', '2025-10-08 14:20:00'),
(92, 1, 'Enregistrement camion', 'Camion BU2025SS enregistré', '2025-10-18 15:45:00'),
(93, 1, 'Connexion', 'Connexion au système', '2025-11-02 08:18:03');

-- --------------------------------------------------------

--
-- Structure de la table `marchandises_bateaux`
--

DROP TABLE IF EXISTS `marchandises_bateaux`;
CREATE TABLE IF NOT EXISTS `marchandises_bateaux` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bateau_id` int DEFAULT NULL,
  `type_marchandise_id` int DEFAULT NULL,
  `mouvement` enum('entree','sortie') NOT NULL DEFAULT 'entree',
  `poids` decimal(10,2) DEFAULT NULL,
  `quantite` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bateau_id` (`bateau_id`),
  KEY `type_marchandise_id` (`type_marchandise_id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `marchandises_bateaux`
--

INSERT INTO `marchandises_bateaux` (`id`, `bateau_id`, `type_marchandise_id`, `mouvement`, `poids`, `quantite`, `created_at`) VALUES
(1, 1, 2, 'entree', 250.00, 500, '2024-01-15 09:30:00'),
(2, 1, 4, 'entree', 180.00, 300, '2024-01-15 09:30:00'),
(20, 15, 15, 'entree', 35.00, 140, '2024-10-12 15:15:00'),
(21, 16, 16, 'entree', 320.00, 800, '2025-01-15 10:00:00'),
(22, 16, 17, 'entree', 280.00, 700, '2025-01-15 10:00:00'),
(23, 17, 18, 'entree', 190.00, 380, '2025-02-12 11:15:00'),
(24, 17, 19, 'entree', 210.00, 420, '2025-02-12 11:15:00'),
(25, 18, 20, 'entree', 85.00, 170, '2025-03-08 08:45:00'),
(26, 18, 21, 'entree', 95.00, 190, '2025-03-08 08:45:00'),
(27, 19, 22, 'entree', 270.00, 540, '2025-04-18 11:50:00'),
(28, 19, 23, 'entree', 310.00, 620, '2025-04-18 11:50:00'),
(29, 20, 24, 'entree', 180.00, 360, '2025-05-22 13:40:00'),
(30, 20, 25, 'entree', 220.00, 440, '2025-05-22 13:40:00'),
(31, 21, 16, 'entree', 290.00, 580, '2025-06-25 14:30:00'),
(32, 21, 17, 'entree', 260.00, 520, '2025-06-25 14:30:00'),
(33, 22, 18, 'entree', 75.00, 150, '2025-07-30 10:05:00'),
(34, 22, 19, 'entree', 65.00, 130, '2025-07-30 10:05:00'),
(35, 23, 20, 'entree', 240.00, 480, '2025-08-28 12:35:00'),
(36, 23, 21, 'entree', 195.00, 390, '2025-08-28 12:35:00'),
(37, 24, 22, 'entree', 305.00, 610, '2025-09-20 10:50:00'),
(38, 24, 23, 'entree', 285.00, 570, '2025-09-20 10:50:00'),
(39, 25, 24, 'entree', 265.00, 530, '2025-10-15 08:40:00'),
(40, 25, 25, 'entree', 245.00, 490, '2025-10-15 08:40:00'),
(41, 16, 18, 'sortie', 320.00, 800, '2025-01-21 15:45:00'),
(42, 16, 19, 'sortie', 280.00, 700, '2025-01-21 15:45:00'),
(43, 17, 20, 'sortie', 190.00, 380, '2025-02-18 14:30:00'),
(44, 17, 21, 'sortie', 210.00, 420, '2025-02-18 14:30:00'),
(45, 18, 22, 'sortie', 85.00, 170, '2025-03-10 17:20:00'),
(46, 18, 23, 'sortie', 95.00, 190, '2025-03-10 17:20:00'),
(47, 19, 24, 'sortie', 270.00, 540, '2025-04-24 16:15:00'),
(48, 19, 25, 'sortie', 310.00, 620, '2025-04-24 16:15:00'),
(49, 20, 16, 'sortie', 180.00, 360, '2025-05-28 12:40:00'),
(50, 20, 17, 'sortie', 220.00, 440, '2025-05-28 12:40:00'),
(51, 21, 18, 'sortie', 290.00, 580, '2025-07-01 13:25:00'),
(52, 21, 19, 'sortie', 260.00, 520, '2025-07-01 13:25:00'),
(53, 22, 20, 'sortie', 75.00, 150, '2025-08-01 18:30:00'),
(54, 22, 21, 'sortie', 65.00, 130, '2025-08-01 18:30:00'),
(55, 23, 22, 'sortie', 240.00, 480, '2025-09-03 15:50:00'),
(56, 23, 23, 'sortie', 195.00, 390, '2025-09-03 15:50:00'),
(57, 24, 24, 'sortie', 305.00, 610, '2025-09-26 14:20:00'),
(58, 24, 25, 'sortie', 285.00, 570, '2025-09-26 14:20:00');

-- --------------------------------------------------------

--
-- Structure de la table `marchandises_camions`
--

DROP TABLE IF EXISTS `marchandises_camions`;
CREATE TABLE IF NOT EXISTS `marchandises_camions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `camion_id` int DEFAULT NULL,
  `type_marchandise_id` int DEFAULT NULL,
  `mouvement` enum('entree','sortie') NOT NULL DEFAULT 'entree',
  `poids` decimal(10,2) DEFAULT NULL,
  `quantite` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `est_decharge` tinyint(1) DEFAULT '0' COMMENT 'Indique si la marchandise a été déchargée',
  `date_dechargement` datetime DEFAULT NULL COMMENT 'Date de déchargement de la marchandise',
  `est_sorti` tinyint(1) DEFAULT '0' COMMENT 'Indique si la marchandise est sortie du port',
  PRIMARY KEY (`id`),
  KEY `camion_id` (`camion_id`),
  KEY `type_marchandise_id` (`type_marchandise_id`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `marchandises_camions`
--

INSERT INTO `marchandises_camions` (`id`, `camion_id`, `type_marchandise_id`, `mouvement`, `poids`, `quantite`, `created_at`, `est_decharge`, `date_dechargement`, `est_sorti`) VALUES
(1, 1, 2, 'entree', 25.00, 50, '2024-01-10 08:00:00', 1, '2024-01-10 14:30:00', 0),
(2, 1, 4, 'entree', 18.00, 30, '2024-01-10 08:00:00', 1, '2024-01-10 14:30:00', 0),
(59, 29, 15, 'entree', 15.00, 300, '2024-10-20 16:30:00', 1, '2024-10-20 23:00:00', 0),
(60, 30, 8, 'entree', 30.00, 600, '2024-10-22 18:00:00', 1, '2024-10-22 23:30:00', 0),
(61, 31, 16, 'entree', 28.00, 560, '2025-01-08 07:30:00', 1, '2025-01-08 14:45:00', 0),
(62, 31, 16, 'sortie', 28.00, 560, '2025-01-08 16:00:00', 1, '2025-01-08 14:45:00', 1),
(63, 32, 17, 'entree', 22.00, 440, '2025-01-20 09:00:00', 1, '2025-01-20 15:30:00', 0),
(64, 32, 17, 'sortie', 22.00, 440, '2025-01-20 16:45:00', 1, '2025-01-20 15:30:00', 1),
(65, 33, 18, 'entree', 35.00, 700, '2025-02-05 09:45:00', 1, '2025-02-05 17:00:00', 0),
(66, 33, 18, 'sortie', 35.00, 700, '2025-02-05 18:15:00', 1, '2025-02-05 17:00:00', 1),
(67, 34, 19, 'entree', 18.00, 360, '2025-02-18 10:35:00', 1, '2025-02-18 17:25:00', 0),
(68, 34, 19, 'sortie', 18.00, 360, '2025-02-18 18:40:00', 1, '2025-02-18 17:25:00', 1),
(69, 35, 20, 'entree', 32.00, 640, '2025-03-10 11:30:00', 1, '2025-03-10 18:40:00', 0),
(70, 35, 20, 'sortie', 32.00, 640, '2025-03-10 19:55:00', 1, '2025-03-10 18:40:00', 1),
(71, 36, 21, 'entree', 27.00, 540, '2025-03-25 12:55:00', 1, '2025-03-25 20:05:00', 0),
(72, 36, 21, 'sortie', 27.00, 540, '2025-03-25 21:20:00', 1, '2025-03-25 20:05:00', 1),
(73, 37, 22, 'entree', 29.00, 580, '2025-04-12 13:40:00', 1, '2025-04-12 20:50:00', 0),
(74, 37, 22, 'sortie', 29.00, 580, '2025-04-12 22:05:00', 1, '2025-04-12 20:50:00', 1),
(75, 38, 23, 'entree', 24.00, 480, '2025-04-28 15:05:00', 1, '2025-04-28 22:15:00', 0),
(76, 38, 23, 'sortie', 24.00, 480, '2025-04-28 23:30:00', 1, '2025-04-28 22:15:00', 1),
(77, 39, 24, 'entree', 31.00, 620, '2025-05-15 15:50:00', 1, '2025-05-15 23:00:00', 0),
(78, 39, 24, 'sortie', 31.00, 620, '2025-05-16 00:15:00', 1, '2025-05-15 23:00:00', 1),
(79, 40, 25, 'entree', 26.00, 520, '2025-05-30 16:35:00', 1, '2025-05-30 23:45:00', 0),
(80, 40, 25, 'sortie', 26.00, 520, '2025-05-31 01:00:00', 1, '2025-05-30 23:45:00', 1),
(81, 41, 16, 'entree', 33.00, 660, '2025-06-12 08:00:00', 1, '2025-06-12 15:10:00', 0),
(82, 41, 16, 'sortie', 33.00, 660, '2025-06-12 16:25:00', 1, '2025-06-12 15:10:00', 1),
(83, 42, 17, 'entree', 28.00, 560, '2025-06-28 08:45:00', 1, '2025-06-28 15:55:00', 0),
(84, 42, 17, 'sortie', 28.00, 560, '2025-06-28 17:10:00', 1, '2025-06-28 15:55:00', 1),
(85, 43, 18, 'entree', 35.00, 700, '2025-07-14 09:30:00', 1, '2025-07-14 16:40:00', 0),
(86, 43, 18, 'sortie', 35.00, 700, '2025-07-14 17:55:00', 1, '2025-07-14 16:40:00', 1),
(87, 44, 19, 'entree', 30.00, 600, '2025-07-30 10:55:00', 1, '2025-07-30 18:05:00', 0),
(88, 44, 19, 'sortie', 30.00, 600, '2025-07-30 19:20:00', 1, '2025-07-30 18:05:00', 1),
(89, 45, 20, 'entree', 32.00, 640, '2025-08-16 11:40:00', 1, '2025-08-16 18:50:00', 0),
(90, 45, 20, 'sortie', 32.00, 640, '2025-08-16 20:05:00', 1, '2025-08-16 18:50:00', 1),
(91, 46, 21, 'entree', 27.00, 540, '2025-09-02 13:05:00', 1, '2025-09-02 20:15:00', 0),
(92, 46, 21, 'sortie', 27.00, 540, '2025-09-02 21:30:00', 1, '2025-09-02 20:15:00', 1),
(93, 47, 22, 'entree', 29.00, 580, '2025-09-20 13:50:00', 1, '2025-09-20 21:00:00', 0),
(94, 47, 22, 'sortie', 29.00, 580, '2025-09-20 22:15:00', 1, '2025-09-20 21:00:00', 1),
(95, 48, 23, 'entree', 31.00, 620, '2025-10-08 14:35:00', 1, '2025-10-08 21:45:00', 0),
(96, 48, 23, 'sortie', 31.00, 620, '2025-10-08 23:00:00', 1, '2025-10-08 21:45:00', 1),
(97, 49, 24, 'entree', 25.00, 500, '2025-10-18 16:00:00', 0, NULL, 0),
(98, 50, 25, 'entree', 28.00, 560, '2025-10-22 16:45:00', 0, NULL, 0),
(99, 49, 24, 'sortie', 25.00, 500, '2025-10-19 00:15:00', 1, '2025-10-18 23:00:00', 1),
(100, 50, 25, 'sortie', 28.00, 560, '2025-10-23 01:00:00', 1, '2025-10-22 23:45:00', 1);

-- --------------------------------------------------------

--
-- Structure de la table `passagers_bateaux`
--

DROP TABLE IF EXISTS `passagers_bateaux`;
CREATE TABLE IF NOT EXISTS `passagers_bateaux` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bateau_id` int DEFAULT NULL,
  `numero_passager` int NOT NULL,
  `poids_marchandises` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bateau_id` (`bateau_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `passagers_bateaux`
--

INSERT INTO `passagers_bateaux` (`id`, `bateau_id`, `numero_passager`, `poids_marchandises`, `created_at`) VALUES
(1, 3, 45, 2.50, '2024-03-05 10:15:00'),
(2, 3, 46, 1.80, '2024-03-05 10:15:00'),
(10, 8, 54, 1.70, '2024-08-22 12:30:00'),
(11, 18, 55, 2.30, '2025-03-08 08:30:00'),
(12, 18, 56, 1.90, '2025-03-08 08:30:00'),
(13, 18, 57, 3.10, '2025-03-08 08:30:00'),
(14, 22, 58, 2.10, '2025-07-30 10:00:00'),
(15, 22, 59, 1.70, '2025-07-30 10:00:00'),
(16, 22, 60, 2.80, '2025-07-30 10:00:00'),
(17, 22, 61, 1.50, '2025-07-30 10:00:00'),
(18, 18, 62, 2.40, '2025-03-08 08:30:00'),
(19, 22, 63, 1.80, '2025-07-30 10:00:00'),
(20, 22, 64, 2.20, '2025-07-30 10:00:00');

-- --------------------------------------------------------

--
-- Structure de la table `pesages`
--

DROP TABLE IF EXISTS `pesages`;
CREATE TABLE IF NOT EXISTS `pesages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `camion_id` int DEFAULT NULL,
  `ptav` decimal(10,2) DEFAULT NULL,
  `ptac` decimal(10,2) DEFAULT NULL,
  `ptra` decimal(10,2) DEFAULT NULL,
  `charge_essieu` decimal(10,2) DEFAULT NULL,
  `total_poids_marchandises` decimal(10,0) DEFAULT NULL,
  `surcharge` tinyint(1) DEFAULT NULL,
  `date_pesage` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mouvement` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `camion_id` (`camion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `pesages`
--

INSERT INTO `pesages` (`id`, `camion_id`, `ptav`, `ptac`, `ptra`, `charge_essieu`, `total_poids_marchandises`, `surcharge`, `date_pesage`, `mouvement`) VALUES
(1, 1, 12.50, 38.50, 26.00, 13.50, 26, 0, '2024-01-10 09:00:00', 'entree'),
(2, 2, 11.80, 31.80, 20.00, 10.50, 20, 0, '2024-01-12 09:30:00', 'entree'),
(30, 30, 14.00, 44.00, 30.00, 15.40, 30, 0, '2024-10-22 18:15:00', 'entree'),
(31, 31, 13.80, 41.80, 28.00, 14.20, 28, 0, '2025-01-08 07:45:00', 'entree'),
(32, 32, 12.20, 34.20, 22.00, 11.30, 22, 0, '2025-01-20 09:15:00', 'entree'),
(33, 33, 15.20, 50.20, 35.00, 17.80, 35, 0, '2025-02-05 10:00:00', 'entree'),
(34, 34, 11.50, 29.50, 18.00, 9.20, 18, 0, '2025-02-18 10:50:00', 'entree'),
(35, 35, 14.50, 46.50, 32.00, 16.50, 32, 0, '2025-03-10 11:45:00', 'entree'),
(36, 36, 13.20, 40.20, 27.00, 13.90, 27, 0, '2025-03-25 13:10:00', 'entree'),
(37, 37, 14.80, 43.80, 29.00, 14.80, 29, 0, '2025-04-12 13:55:00', 'entree'),
(38, 38, 12.80, 36.80, 24.00, 12.40, 24, 0, '2025-04-28 15:20:00', 'entree'),
(39, 39, 15.50, 46.50, 31.00, 16.20, 31, 0, '2025-05-15 16:05:00', 'entree'),
(40, 40, 13.50, 39.50, 26.00, 13.50, 26, 0, '2025-05-30 16:50:00', 'entree'),
(41, 41, 14.20, 47.20, 33.00, 17.10, 33, 0, '2025-06-12 08:15:00', 'entree'),
(42, 42, 13.80, 41.80, 28.00, 14.20, 28, 0, '2025-06-28 09:00:00', 'entree'),
(43, 43, 15.20, 50.20, 35.00, 17.80, 35, 0, '2025-07-14 09:45:00', 'entree'),
(44, 44, 13.50, 43.50, 30.00, 15.60, 30, 0, '2025-07-30 11:10:00', 'entree'),
(45, 45, 14.80, 46.80, 32.00, 16.50, 32, 0, '2025-08-16 11:55:00', 'entree'),
(46, 46, 12.80, 39.80, 27.00, 13.90, 27, 0, '2025-09-02 13:20:00', 'entree'),
(47, 47, 14.50, 43.50, 29.00, 14.80, 29, 0, '2025-09-20 14:05:00', 'entree'),
(48, 48, 15.20, 46.20, 31.00, 16.20, 31, 0, '2025-10-08 14:50:00', 'entree'),
(49, 49, 12.50, 37.50, 25.00, 12.80, 25, 0, '2025-10-18 16:15:00', 'entree'),
(50, 50, 14.00, 44.00, 30.00, 15.40, 30, 0, '2025-10-22 17:00:00', 'entree');

-- --------------------------------------------------------

--
-- Structure de la table `ports`
--

DROP TABLE IF EXISTS `ports`;
CREATE TABLE IF NOT EXISTS `ports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `pays` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `ports`
--

INSERT INTO `ports` (`id`, `nom`, `pays`, `description`, `created_at`) VALUES
(1, 'Lomé', 'TOGO', NULL, '2025-10-05 17:26:01'),
(2, 'BUJUMBURA', 'Burundi', 'Port principal de Bujumbura', '2025-10-07 14:31:52'),
(3, 'Kalemie', 'RDC', 'Port sur le lac Tanganyika', '2024-01-01 08:00:00'),
(4, 'Moba', 'RDC', 'Port congolais sur le lac Tanganyika', '2024-01-01 08:05:00'),
(5, 'Kigoma', 'Tanzanie', 'Port tanzanien sur le lac Tanganyika', '2024-01-01 08:10:00'),
(6, 'Uvira', 'RDC', 'Port dans le Sud-Kivu', '2024-01-01 08:15:00'),
(7, 'Barrow', 'Tanzanie', 'Port tanzanien', '2024-01-01 08:20:00'),
(8, 'Kipili', 'Tanzanie', 'Port de pêche tanzanien', '2024-01-01 08:25:00'),
(9, 'Nyanza', 'Burundi', 'Port secondaire burundais', '2024-01-01 08:30:00'),
(10, 'Rumonge', 'Burundi', 'Port de pêche burundais', '2024-01-01 08:35:00'),
(11, 'Gombe', 'RDC', 'Port congolais', '2024-01-01 08:40:00'),
(12, 'Kasanga', 'Tanzanie', 'Port tanzanien frontalier', '2024-01-01 08:45:00');

-- --------------------------------------------------------

--
-- Structure de la table `types_bateaux`
--

DROP TABLE IF EXISTS `types_bateaux`;
CREATE TABLE IF NOT EXISTS `types_bateaux` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `types_bateaux`
--

INSERT INTO `types_bateaux` (`id`, `nom`, `description`, `created_at`) VALUES
(1, 'Bateau cargo', NULL, '2025-10-07 14:11:56'),
(2, 'Bateau passager', NULL, '2025-10-07 14:12:16');

-- --------------------------------------------------------

--
-- Structure de la table `types_camions`
--

DROP TABLE IF EXISTS `types_camions`;
CREATE TABLE IF NOT EXISTS `types_camions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `types_camions`
--

INSERT INTO `types_camions` (`id`, `nom`, `description`, `created_at`) VALUES
(1, 'Camion', NULL, '2025-10-05 17:25:39');

-- --------------------------------------------------------

--
-- Structure de la table `types_marchandises`
--

DROP TABLE IF EXISTS `types_marchandises`;
CREATE TABLE IF NOT EXISTS `types_marchandises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `types_marchandises`
--

INSERT INTO `types_marchandises` (`id`, `nom`, `description`, `created_at`) VALUES
(2, 'Ciment', NULL, '2025-10-05 17:23:02'),
(3, 'Concombre', NULL, '2025-10-07 14:13:09'),
(4, 'Fer', NULL, '2025-10-07 14:13:14'),
(5, 'Marbre', NULL, '2025-10-07 14:13:19'),
(6, 'Riz', 'Riz importé de Tanzanie', '2024-01-01 08:20:00'),
(7, 'Sucre', 'Sucre en sacs de 50kg', '2024-01-01 08:25:00'),
(8, 'Café', 'Café arabica du Burundi', '2024-01-01 08:30:00'),
(9, 'Thé', 'Thé des collines burundaises', '2024-01-01 08:35:00'),
(10, 'Huile', 'Huile végétale alimentaire', '2024-01-01 08:40:00'),
(11, 'Engrais', 'Engrais agricoles', '2024-01-01 08:45:00'),
(12, 'Textile', 'Tissus et vêtements', '2024-01-01 08:50:00'),
(13, 'Produits chimiques', 'Produits chimiques industriels', '2024-01-01 08:55:00'),
(14, 'Équipement électronique', 'Appareils électroniques divers', '2024-01-01 09:00:00'),
(15, 'Meubles', 'Meubles en bois et articles de maison', '2024-01-01 09:05:00'),
(16, 'Matériaux construction', 'Matériaux de construction modernes', '2025-01-01 08:00:00'),
(17, 'Équipement médical', 'Appareils et fournitures médicales', '2025-01-01 08:05:00'),
(18, 'Produits pharmaceutiques', 'Médicaments et produits pharma', '2025-01-01 08:10:00'),
(19, 'Équipement sportif', 'Articles et équipements sportifs', '2025-01-01 08:15:00'),
(20, 'Produits cosmétiques', 'Cosmétiques et produits beauté', '2025-01-01 08:20:00'),
(21, 'Jouets et jeux', 'Jouets pour enfants et jeux', '2025-01-01 08:25:00'),
(22, 'Équipement de bureau', 'Mobilier et fournitures de bureau', '2025-01-01 08:30:00'),
(23, 'Produits automobiles', 'Pièces et accessoires automobiles', '2025-01-01 08:35:00'),
(24, 'Articles ménagers', 'Électroménagers et ustensiles', '2025-01-01 08:40:00'),
(25, 'Matériel informatique', 'Ordinateurs et périphériques', '2025-01-01 08:45:00');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','autorite','douanier','EnregistreurEntreeRoute','EnregistreurSortieRoute','peseur','EnregistreurBateaux') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `first_login` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `first_login`, `created_at`, `updated_at`) VALUES
(1, 'SODAHLON', 'Dotinmey', 'sodahlondotinmey@yahoo.fr', '$2y$10$aNzlpm.8Hh50E0GgwD1VQ.9amO5k3Tu0DF.SslCE/S0DayZMhSvvK', 'admin', 0, '2025-10-05 17:07:13', '2025-10-24 13:26:22');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bateaux`
--
ALTER TABLE `bateaux`
  ADD CONSTRAINT `bateaux_ibfk_1` FOREIGN KEY (`type_bateau_id`) REFERENCES `types_bateaux` (`id`),
  ADD CONSTRAINT `bateaux_ibfk_2` FOREIGN KEY (`port_origine_id`) REFERENCES `ports` (`id`),
  ADD CONSTRAINT `bateaux_ibfk_3` FOREIGN KEY (`port_destination_id`) REFERENCES `ports` (`id`);

--
-- Contraintes pour la table `camions`
--
ALTER TABLE `camions`
  ADD CONSTRAINT `camions_ibfk_1` FOREIGN KEY (`type_camion_id`) REFERENCES `types_camions` (`id`);

--
-- Contraintes pour la table `frais_transit`
--
ALTER TABLE `frais_transit`
  ADD CONSTRAINT `fk_frais_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `marchandises_bateaux`
--
ALTER TABLE `marchandises_bateaux`
  ADD CONSTRAINT `marchandises_bateaux_ibfk_1` FOREIGN KEY (`bateau_id`) REFERENCES `bateaux` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marchandises_bateaux_ibfk_2` FOREIGN KEY (`type_marchandise_id`) REFERENCES `types_marchandises` (`id`);

--
-- Contraintes pour la table `marchandises_camions`
--
ALTER TABLE `marchandises_camions`
  ADD CONSTRAINT `marchandises_camions_ibfk_1` FOREIGN KEY (`camion_id`) REFERENCES `camions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marchandises_camions_ibfk_2` FOREIGN KEY (`type_marchandise_id`) REFERENCES `types_marchandises` (`id`);

--
-- Contraintes pour la table `passagers_bateaux`
--
ALTER TABLE `passagers_bateaux`
  ADD CONSTRAINT `passagers_bateaux_ibfk_1` FOREIGN KEY (`bateau_id`) REFERENCES `bateaux` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `pesages`
--
ALTER TABLE `pesages`
  ADD CONSTRAINT `pesages_ibfk_1` FOREIGN KEY (`camion_id`) REFERENCES `camions` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
