-- Structure de la base de l'espace adhérents.
-- Ce fichier est joué une seule fois par installation.php.
-- « IF NOT EXISTS » partout : le rejouer ne détruit jamais de données.

CREATE TABLE IF NOT EXISTS adherents (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  identifiant        VARCHAR(60)  NOT NULL UNIQUE,
  nom                VARCHAR(120) NOT NULL,
  email              VARCHAR(190) DEFAULT NULL,
  telephone          VARCHAR(30)  DEFAULT NULL,
  mot_de_passe       VARCHAR(255) NOT NULL,
  administrateur     TINYINT(1)   NOT NULL DEFAULT 0,
  actif              TINYINT(1)   NOT NULL DEFAULT 1,
  derniere_connexion DATETIME     DEFAULT NULL,
  -- Horodatage rafraîchi à chaque page consultée : sert à afficher qui est
  -- connecté en ce moment (voir DELAI_PRESENCE_MINUTES dans auth.php).
  derniere_activite  DATETIME     DEFAULT NULL,
  -- Posé par un responsable qui coupe la session à distance. Toute session
  -- ouverte AVANT cet instant est refusée à la requête suivante.
  deconnecte_le      DATETIME     DEFAULT NULL,
  cree_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos réservées aux adhérents. Le fichier est stocké dans espace/photos/,
-- dossier interdit d'accès direct : il est servi par telecharger.php.
CREATE TABLE IF NOT EXISTS photos_privees (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  titre       VARCHAR(190) NOT NULL,
  description TEXT         DEFAULT NULL,
  fichier     VARCHAR(190) NOT NULL,
  depose_par  INT          DEFAULT NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_adherent FOREIGN KEY (depose_par) REFERENCES adherents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents du club (comptes rendus, statuts, bulletins…).
CREATE TABLE IF NOT EXISTS documents (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  titre       VARCHAR(190) NOT NULL,
  description TEXT         DEFAULT NULL,
  fichier     VARCHAR(190) NOT NULL,
  nom_origine VARCHAR(190) NOT NULL,
  taille      INT          NOT NULL DEFAULT 0,
  depose_par  INT          DEFAULT NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_adherent FOREIGN KEY (depose_par) REFERENCES adherents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda des sorties.
CREATE TABLE IF NOT EXISTS sorties (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  titre        VARCHAR(190) NOT NULL,
  description  TEXT         DEFAULT NULL,
  lieu         VARCHAR(190) DEFAULT NULL,
  debut        DATETIME     NOT NULL,
  rendez_vous  VARCHAR(190) DEFAULT NULL,
  covoiturage  TINYINT(1)   NOT NULL DEFAULT 0,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qui participe à quelle sortie. La clé unique empêche une double inscription.
CREATE TABLE IF NOT EXISTS inscriptions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  sortie_id   INT NOT NULL,
  adherent_id INT NOT NULL,
  cree_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY une_seule_inscription (sortie_id, adherent_id),
  CONSTRAINT fk_inscription_sortie   FOREIGN KEY (sortie_id)   REFERENCES sorties(id)   ON DELETE CASCADE,
  CONSTRAINT fk_inscription_adherent FOREIGN KEY (adherent_id) REFERENCES adherents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
