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
  adresse            VARCHAR(190) DEFAULT NULL,
  code_postal        VARCHAR(10)  DEFAULT NULL,
  ville              VARCHAR(120) DEFAULT NULL,
  -- Nom du boîtier (appareil photo) de l'adhérent, obligatoire à
  -- l'inscription (voir inscription.php) mais nullable ici : un compte créé
  -- avant ce champ n'a pas cette information.
  boitier            VARCHAR(120) DEFAULT NULL,
  administrateur     TINYINT(1)   NOT NULL DEFAULT 0,
  -- Éditeur : mêmes droits que responsable sur les comptes, les documents
  -- et l'agenda, sans accès aux réglages du site (parametres.php reste
  -- réservé à administrateur) — nouveau rôle du 23/08/2026, choix explicite
  -- de l'utilisateur.
  editeur            TINYINT(1)   NOT NULL DEFAULT 0,
  actif              TINYINT(1)   NOT NULL DEFAULT 1,
  -- Distinct de `actif` : un compte créé par inscription.php démarre à 0
  -- (en attente qu'un responsable ou un éditeur valide), un compte créé par
  -- un responsable/éditeur depuis adherents.php démarre à 1 (défaut de la
  -- colonne).
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

-- Catégories des photos, partagées par la Galerie privée (photos_privees) et
-- la Galerie du Club (photos_club), modifiables par un responsable depuis
-- parametres.php — voir inc/galerie_categories.php et
-- CATEGORIES_GALERIE_PAR_DEFAUT (inc/migration.php) pour le semis initial.
CREATE TABLE IF NOT EXISTS categories_galerie (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nom   VARCHAR(120) NOT NULL,
  ordre INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos réservées aux adhérents. Le fichier est stocké dans espace/photos/,
-- dossier interdit d'accès direct : il est servi par telecharger.php.
-- Mêmes possibilités que photos_club (catégorie, nom affiché) depuis le
-- 21/08/2026 — voir photos_club plus bas pour le détail de ces deux champs.
CREATE TABLE IF NOT EXISTS photos_privees (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  titre        VARCHAR(190) NOT NULL,
  description  TEXT         DEFAULT NULL,
  nom_affiche  VARCHAR(120) DEFAULT NULL,
  fichier      VARCHAR(190) NOT NULL,
  categorie_id INT          DEFAULT NULL,
  depose_par   INT          DEFAULT NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_categorie FOREIGN KEY (categorie_id) REFERENCES categories_galerie(id) ON DELETE SET NULL,
  CONSTRAINT fk_photo_adherent  FOREIGN KEY (depose_par)   REFERENCES adherents(id)          ON DELETE SET NULL
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
-- classe le document dans une catégorie — `categorie` (VARCHAR) est l'ancien
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

-- Catégories du blog du club (espace/blog.php), modifiables par un
-- responsable depuis parametres.php — voir inc/blog.php et
-- CATEGORIES_BLOG_PAR_DEFAUT (inc/migration.php) pour le semis initial.
-- Une liste à plat, comme categories_galerie : pas de rubriques.
CREATE TABLE IF NOT EXISTS categories_blog (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nom   VARCHAR(120) NOT NULL,
  ordre INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Articles du blog du club — page PUBLIQUE (espace/blog.php,
-- espace/blog-article.php), comme l'agenda, mais rédigés uniquement par un
-- responsable ou un éditeur (voir exige_gestionnaire() dans inc/auth.php).
-- `auteur_nom` signe l'article (préempli avec le nom de l'adhérent connecté
-- à la rédaction, modifiable) — `image` est la photo de couverture,
-- facultative, dans espace/photos_blog/, servie par telecharger.php
-- (type=blog, public comme les photos de sortie et de la Galerie du Club).
-- `contenu` passe par texte_riche_html() (inc/blog.php) à l'affichage :
-- paragraphes séparés par une ligne vide, **texte** pour le gras — même
-- convention minimale que les e-mails de notification (inc/mail.php), pas
-- d'éditeur riche.
CREATE TABLE IF NOT EXISTS articles_blog (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  titre        VARCHAR(190) NOT NULL,
  extrait      TEXT         DEFAULT NULL,
  contenu      TEXT         NOT NULL,
  image        VARCHAR(190) DEFAULT NULL,
  categorie_id INT          DEFAULT NULL,
  auteur_nom   VARCHAR(120) DEFAULT NULL,
  depose_par   INT          DEFAULT NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_article_categorie FOREIGN KEY (categorie_id) REFERENCES categories_blog(id) ON DELETE SET NULL,
  CONSTRAINT fk_article_adherent  FOREIGN KEY (depose_par)   REFERENCES adherents(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Albums de la page publique « Nos Sorties » (nos-sorties.html) : un album
-- = une sortie du club (« Expo 2026 », « Croisière Penbron », « Fête de la
-- mer »…). Deux façons de les alimenter (choix explicite de l'utilisateur,
-- 27/08/2026) selon `type` : 'drive' (par défaut), les photos vivent sur
-- Google Drive — `dossier_drive` est alors l'identifiant du dossier Drive de
-- l'album (celui de son URL : drive.google.com/drive/folders/CET_IDENTIFIANT),
-- qui contient un sous-dossier par adhérent — 'local', les photos sont
-- déposées directement par les adhérents sur cet hébergement (voir
-- photos_sorties plus bas) — réservé aux sorties avec peu de photos, pour ne
-- pas charger l'hébergement Hostinger. `dossier_drive` reste une chaîne vide
-- pour un album local, jamais utilisée. Créés et modifiés par un responsable
-- depuis parametres.php — volontairement non semés, c'est au club de créer
-- ses propres albums. Voir inc/albums.php et infos-albums.php.
CREATE TABLE IF NOT EXISTS albums_sorties (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(120) NOT NULL,
  dossier_drive VARCHAR(190) NOT NULL,
  type          VARCHAR(10)  NOT NULL DEFAULT 'drive',
  ordre         INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos d'un album « Nos Sorties » hébergé sur Hostinger (albums_sorties.type
-- = 'local') : déposées directement par les adhérents, sur le même principe
-- que photos_club (voir plus haut). Le fichier est stocké dans
-- espace/photos_sorties/, dossier interdit d'accès direct, servi par
-- telecharger.php (type=sortie_album, public comme les photos de sortie et de
-- la Galerie du Club). `nom_affiche` permet de signer autrement que son
-- identifiant de connexion — vide, l'affichage retombe sur le nom de
-- l'adhérent. Supprimer l'album (parametres.php) efface d'abord les fichiers
-- sur disque, puis les lignes ici via la suppression en cascade.
CREATE TABLE IF NOT EXISTS photos_sorties (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  album_id    INT          NOT NULL,
  titre       VARCHAR(190) NOT NULL,
  description TEXT         DEFAULT NULL,
  nom_affiche VARCHAR(120) DEFAULT NULL,
  fichier     VARCHAR(190) NOT NULL,
  depose_par  INT          DEFAULT NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_sortie_album    FOREIGN KEY (album_id)   REFERENCES albums_sorties(id) ON DELETE CASCADE,
  CONSTRAINT fk_photo_sortie_adherent FOREIGN KEY (depose_par) REFERENCES adherents(id)      ON DELETE SET NULL
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

-- Statistiques de fréquentation (choix explicite de l'utilisatrice,
-- 01/09/2026) : une ligne par page vue, posée par le point d'accès public
-- enregistrer-visite.php (racine, hors de espace/, appelé en arrière-plan par
-- js/main.js sur chaque page). `ip` est l'adresse IPv4/IPv6 du visiteur avec
-- les derniers bits mis à zéro (anonymiser_ip() dans enregistrer-visite.php)
-- avant tout usage, y compris pour la géolocalisation — jamais l'adresse
-- complète, ni stockée ni transmise au service de géolocalisation tiers.
-- `pays`/`ville` restent NULL si la géolocalisation échoue ou n'a pas de
-- réponse ; la visite est comptée quand même. `referent` est le domaine
-- d'où vient le clic (moteur de recherche, réseau social, autre site) —
-- NULL si navigation directe ou interne au site. Consultée depuis
-- espace/statistiques.php, réservée au responsable (voir
-- exige_administrateur()).
CREATE TABLE IF NOT EXISTS visites (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  page     VARCHAR(190) NOT NULL,
  referent VARCHAR(190) DEFAULT NULL,
  ip       VARCHAR(45)  DEFAULT NULL,
  pays     VARCHAR(80)  DEFAULT NULL,
  ville    VARCHAR(120) DEFAULT NULL,
  cree_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visites_cree_le (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
