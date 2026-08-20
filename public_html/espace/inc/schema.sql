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
  code_postal        VARCHAR(10)  DEFAULT NULL,
  ville              VARCHAR(120) DEFAULT NULL,
  administrateur     TINYINT(1)   NOT NULL DEFAULT 0,
  actif              TINYINT(1)   NOT NULL DEFAULT 1,
  -- Distinct de `actif` : un compte créé par inscription.php démarre à 0
  -- (en attente qu'un responsable valide), un compte créé par un
  -- responsable depuis adherents.php démarre à 1 (défaut de la colonne).
  valide             TINYINT(1)   NOT NULL DEFAULT 1,
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

-- Catégories des photos de la Galerie du Club, modifiables par un
-- responsable depuis parametres.php — voir inc/galerie_club.php et
-- CATEGORIES_GALERIE_PAR_DEFAUT (inc/migration.php) pour le semis initial.
CREATE TABLE IF NOT EXISTS categories_galerie (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nom   VARCHAR(120) NOT NULL,
  ordre INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos de la Galerie du Club (espace/galerie-club.php) : déposées par
-- n'importe quel adhérent, classées par catégorie. Contrairement à
-- photos_privees, elles sont PUBLIQUES une fois en ligne — reprises sur la
-- page publique galerie.html via infos-galerie-club.php et servies par
-- telecharger.php (type=galerie_club, public comme les photos de sortie).
-- `nom_affiche` permet de signer autrement que son identifiant de connexion ;
-- vide, l'affichage retombe sur le nom de l'adhérent (voir depose_par).
CREATE TABLE IF NOT EXISTS photos_club (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  titre        VARCHAR(190) NOT NULL,
  description  TEXT         DEFAULT NULL,
  nom_affiche  VARCHAR(120) DEFAULT NULL,
  fichier      VARCHAR(190) NOT NULL,
  categorie_id INT          DEFAULT NULL,
  depose_par   INT          DEFAULT NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_club_categorie FOREIGN KEY (categorie_id) REFERENCES categories_galerie(id) ON DELETE SET NULL,
  CONSTRAINT fk_photo_club_adherent  FOREIGN KEY (depose_par)   REFERENCES adherents(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rubriques et catégories de classement des documents du club, modifiables
-- par un responsable depuis parametres.php — voir inc/documents_categories.php
-- et RUBRIQUES_DOCUMENTS_PAR_DEFAUT (inc/migration.php) pour le semis initial.
CREATE TABLE IF NOT EXISTS rubriques_documents (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nom   VARCHAR(120) NOT NULL,
  ordre INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories_documents (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rubrique_id INT          NOT NULL,
  nom         VARCHAR(120) NOT NULL,
  ordre       INT          NOT NULL DEFAULT 0,
  CONSTRAINT fk_categorie_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents du club (comptes rendus, statuts, bulletins…). `categorie_id`
-- classe le document dans une catégorie ; `categorie` (VARCHAR) est l'ancien
-- classement en texte libre, conservé pour ne perdre aucune donnée mais plus
-- lu par le code (voir inc/migration.php).
CREATE TABLE IF NOT EXISTS documents (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  titre        VARCHAR(190) NOT NULL,
  description  TEXT         DEFAULT NULL,
  fichier      VARCHAR(190) NOT NULL,
  nom_origine  VARCHAR(190) NOT NULL,
  taille       INT          NOT NULL DEFAULT 0,
  categorie    VARCHAR(60)  NOT NULL DEFAULT 'Documents internes',
  categorie_id INT          DEFAULT NULL,
  depose_par   INT          DEFAULT NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_adherent  FOREIGN KEY (depose_par)   REFERENCES adherents(id)          ON DELETE SET NULL,
  CONSTRAINT fk_document_categorie FOREIGN KEY (categorie_id) REFERENCES categories_documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda des sorties, cours et réunions (voir CATEGORIES_SORTIES dans inc/agenda.php).
CREATE TABLE IF NOT EXISTS sorties (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  titre        VARCHAR(190) NOT NULL,
  categorie    VARCHAR(30)  NOT NULL DEFAULT 'Sortie photo',
  description  TEXT         DEFAULT NULL,
  lieu         VARCHAR(190) DEFAULT NULL,
  debut        DATETIME     NOT NULL,
  rendez_vous  VARCHAR(190) DEFAULT NULL,
  covoiturage  TINYINT(1)   NOT NULL DEFAULT 0,
  -- Nom de fichier dans espace/photos/ (même dépôt que photos_privees),
  -- redimensionnée en carré 400×400 à l'envoi. Facultative.
  photo        VARCHAR(190) DEFAULT NULL,
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

-- Coordonnées du club (adresse, téléphone, e-mail, présentation), modifiables
-- par un responsable dans parametres.php et affichées sur les pages publiques
-- statiques via infos-club.php. Les valeurs par défaut sont posées par
-- appliquer_migrations() dans inc/migration.php, pas ici : ce fichier ne
-- s'exécute qu'à l'installation, alors que la migration s'applique aussi aux
-- bases déjà en production.
CREATE TABLE IF NOT EXISTS parametres_site (
  cle    VARCHAR(60) PRIMARY KEY,
  valeur TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
