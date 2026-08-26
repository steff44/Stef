<?php
/*
 * Mise à niveau automatique du schéma.
 *
 * Pourquoi automatique ? Parce qu'il n'y a aucun autre moyen de la déclencher :
 * le sandbox n'a pas d'accès SSH, installation.php se verrouille dès le premier
 * compte créé, et une page de migration réservée aux responsables serait
 * inaccessible — le code neuf interroge les nouvelles colonnes DÈS la
 * connexion, qui échouerait donc avant d'avoir pu ouvrir cette page.
 *
 * Chaque étape est idempotente : on regarde si la colonne répond, on ne
 * l'ajoute que si elle manque. Aucune donnée n'est jamais touchée.
 */

declare(strict_types=1);

// Pour VACANCES_SCOLAIRES et vacances_du_jour(), utilisées par le semis de
// la réunion hebdomadaire plus bas.
require_once __DIR__ . '/agenda.php';

// Colonnes attendues sur `adherents`, avec leur définition SQL.
const COLONNES_ATTENDUES = [
    'derniere_activite' => 'DATETIME DEFAULT NULL',
    'deconnecte_le'     => 'DATETIME DEFAULT NULL',
    'code_postal'       => 'VARCHAR(10) DEFAULT NULL',
    'ville'             => 'VARCHAR(120) DEFAULT NULL',
    // Distinct de `actif` — voir schema.sql. DEFAULT 1 pour que les comptes
    // déjà en base (créés avant ce champ) restent utilisables sans y toucher.
    // Un compte auto-inscrit depuis inscription.php force `valide = 0` : la
    // connexion est refusée tant qu'un responsable ou un éditeur ne l'a pas
    // validé depuis adherents.php.
    'valide'            => 'TINYINT(1) NOT NULL DEFAULT 1',
    // Éditeur : mêmes droits que responsable sur les comptes, les documents
    // et l'agenda, sans accès aux réglages du site — nouveau rôle du
    // 23/08/2026, choix explicite de l'utilisateur. Voir est_gestionnaire()
    // dans inc/auth.php.
    'editeur'           => 'TINYINT(1) NOT NULL DEFAULT 0',
];

// Colonnes attendues sur `sorties` — même principe, table différente.
// La valeur par défaut de `categorie` reprend la première catégorie de
// CATEGORIES_SORTIES (inc/agenda.php), pour que les sorties déjà en base
// restent classées. `photo` est facultative : nom de fichier dans
// espace/photos/, posée par sorties-a-venir.php, redimensionnée en carré
// 400×400 à l'envoi.
const COLONNES_SORTIES_ATTENDUES = [
    'categorie' => "VARCHAR(30) NOT NULL DEFAULT 'Sortie photo'",
    'photo'     => 'VARCHAR(190) DEFAULT NULL',
];

// Colonnes attendues sur `documents`. `categorie` (VARCHAR) est l'ancien
// classement, figé dans le code — conservé tel quel pour ne perdre aucune
// donnée, mais plus lu par le code depuis le passage aux rubriques/
// catégories en base (20/08/2026, voir inc/documents_categories.php et
// RUBRIQUES_DOCUMENTS_PAR_DEFAUT plus bas). `categorie_id` le remplace :
// référence vers `categories_documents`, posée par la bascule automatique
// ci-dessous à partir de l'ancienne valeur.
const COLONNES_DOCUMENTS_ATTENDUES = [
    'categorie'    => "VARCHAR(60) NOT NULL DEFAULT 'Documents internes'",
    'categorie_id' => 'INT DEFAULT NULL',
];

// Colonnes attendues sur `photos_privees` — mêmes possibilités que
// `photos_club` (catégorie, nom affiché), choix explicite de l'utilisateur,
// 21/08/2026. Les deux tables partagent la table `categories_galerie` : une
// même liste de catégories pour la Galerie privée et la Galerie du Club.
const COLONNES_PHOTOS_PRIVEES_ATTENDUES = [
    'nom_affiche'  => 'VARCHAR(120) DEFAULT NULL',
    'categorie_id' => 'INT DEFAULT NULL',
];

// Classification par défaut des documents, semée une seule fois — la
// première fois que `rubriques_documents` est créée — par
// appliquer_migrations() ci-dessous. Un responsable la modifie ensuite
// depuis parametres.php (renommer, ajouter, supprimer une rubrique ou une
// catégorie) : ce n'est donc plus une constante que le code relit.
const RUBRIQUES_DOCUMENTS_PAR_DEFAUT = [
    'Débuter la photo' => [
        'Bases techniques',
        'Premiers réglages',
        'Guides simplifiés pour les seniors',
    ],
    'Ateliers techniques du club' => [
        'Lumière',
        'Exposition',
        'Composition',
        'Matériel',
        'Post-traitement',
    ],
    'Thèmes photographiques' => [
        'Portrait',
        'Paysage',
        'Macro',
        'Nature',
        'Street',
        'Architecture',
        'Noir & Blanc',
        'Créatif',
    ],
    'Administration du club' => [
        'Planning',
        'Comptes rendus',
        'Documents internes',
    ],
];

// Catégories par défaut de la Galerie du Club (espace/galerie-club.php),
// semées une seule fois — la première fois que `categories_galerie` est
// créée — par appliquer_migrations() ci-dessous. Reprend les catégories de
// la rubrique « Thèmes photographiques » des documents, pour partir d'une
// liste cohérente ; un responsable la modifie ensuite depuis parametres.php.
// « Macro / Proxi » (pas juste « Macro ») pour correspondre exactement au
// thème déjà utilisé dans CLUB_DATA.themes (js/data.js) — sans quoi la page
// Galerie publique affiche deux filtres qui font double emploi (constaté le
// 21/08/2026).
const CATEGORIES_GALERIE_PAR_DEFAUT = [
    'Portrait',
    'Paysage',
    'Macro / Proxi',
    'Nature',
    'Photo de rue',
    'Architecture',
    'Noir & Blanc',
    'Créatif',
];

// Catégories par défaut du blog du club (espace/blog.php), semées une seule
// fois — la première fois que `categories_blog` est créée — par
// appliquer_migrations() ci-dessous. Reprend la classification du blog cité
// en référence par l'utilisateur (24/08/2026) ; un responsable la modifie
// ensuite depuis parametres.php.
const CATEGORIES_BLOG_PAR_DEFAUT = [
    'À la une',
    'Club photo',
    'Concours',
    'Culture photographique',
    'Exposition',
    'Festival photo',
    'Livre photo',
    'Stage',
    "Travail d'auteur",
];

// Réunion hebdomadaire du club, semée une seule fois dans `sorties` — choix
// explicite de l'utilisateur, 22/08/2026 : tous les jeudis de 20h30 à
// 23h00, sauf pendant les vacances scolaires (VACANCES_SCOLAIRES,
// inc/agenda.php), du 10/09/2026 au 30/06/2027. Semée par
// appliquer_migrations() ci-dessous si aucune réunion de ce titre n'existe
// déjà — jamais réintroduite si un responsable a depuis tout supprimé, même
// principe que les autres semis PAR_DEFAUT de ce fichier. Chaque séance
// reste ensuite une sortie ordinaire, modifiable ou supprimable comme les
// autres depuis sorties-a-venir.php.
const REUNION_HEBDOMADAIRE_TITRE = 'Réunion hebdomadaire';
const REUNION_HEBDOMADAIRE_LIEU  = 'Foyer des Vignes, 17 rue Michelet, 44420 La Turballe';
const REUNION_HEBDOMADAIRE_DEBUT = '2026-09-10'; // un jeudi
const REUNION_HEBDOMADAIRE_FIN   = '2027-06-30';

// Coordonnées du club, modifiables par un responsable depuis parametres.php
// et affichées sur les pages publiques statiques via infos-club.php. Les
// valeurs par défaut reprennent EXACTEMENT ce qui est déjà écrit en dur dans
// le HTML : tant que personne n'y touche, rien ne change visuellement.
const PARAMETRES_PAR_DEFAUT = [
    'nom_lieu'            => 'Foyer des Vignes',
    'adresse_rue'         => '17 rue Michelet',
    'adresse_code_postal' => '44420',
    'adresse_ville'       => 'La Turballe',
    'telephone'           => '06 17 11 77 65',
    'email'               => 'cooky44.sl@gmail.com',
    'horaires_creneau'    => 'Jeudi 20h30-23h00',
    'horaires_frequence'  => 'Réunions hebdomadaires',
    'presentation'        => "Passionnés de photographie, nous partageons notre amour de l'image à travers des sorties, des expositions et des moments de convivialité.",
];

function appliquer_migrations(PDO $pdo): void
{
    // Une seule fois par requête.
    static $deja_fait = false;
    if ($deja_fait) {
        return;
    }
    $deja_fait = true;

    // Témoin sur disque : une fois la base à jour, on ne l'interroge plus du
    // tout. S'il ne peut pas être écrit (droits), on refait simplement le
    // contrôle à chaque page — plus lent, mais toujours correct.
    $temoin = __DIR__ . '/.schema-a-jour';
    if (is_file($temoin) && trim((string) @file_get_contents($temoin)) === signature_schema()) {
        return;
    }

    $reussi = true;

    foreach (COLONNES_ATTENDUES as $colonne => $definition) {
        if (colonne_absente($pdo, 'adherents', $colonne)) {
            try {
                $pdo->exec("ALTER TABLE adherents ADD COLUMN {$colonne} {$definition}");
            } catch (PDOException $e) {
                // Table pas encore créée : c'est une première installation,
                // schema.sql posera les colonnes lui-même. On ne pose pas le
                // témoin, la vérification sera refaite plus tard.
                error_log('Espace adhérents — migration ' . $colonne . ' : ' . $e->getMessage());
                $reussi = false;
            }
        }
    }

    foreach (COLONNES_SORTIES_ATTENDUES as $colonne => $definition) {
        if (colonne_absente($pdo, 'sorties', $colonne)) {
            try {
                $pdo->exec("ALTER TABLE sorties ADD COLUMN {$colonne} {$definition}");
            } catch (PDOException $e) {
                error_log('Espace adhérents — migration sorties.' . $colonne . ' : ' . $e->getMessage());
                $reussi = false;
            }
        }
    }

    foreach (COLONNES_DOCUMENTS_ATTENDUES as $colonne => $definition) {
        if (colonne_absente($pdo, 'documents', $colonne)) {
            try {
                $pdo->exec("ALTER TABLE documents ADD COLUMN {$colonne} {$definition}");
            } catch (PDOException $e) {
                error_log('Espace adhérents — migration documents.' . $colonne . ' : ' . $e->getMessage());
                $reussi = false;
            }
        }
    }

    foreach (COLONNES_PHOTOS_PRIVEES_ATTENDUES as $colonne => $definition) {
        if (colonne_absente($pdo, 'photos_privees', $colonne)) {
            try {
                $pdo->exec("ALTER TABLE photos_privees ADD COLUMN {$colonne} {$definition}");
            } catch (PDOException $e) {
                error_log('Espace adhérents — migration photos_privees.' . $colonne . ' : ' . $e->getMessage());
                $reussi = false;
            }
        }
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS parametres_site (
                cle    VARCHAR(60) PRIMARY KEY,
                valeur TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        // INSERT IGNORE : ne pose que les clés manquantes, ne touche jamais
        // une valeur déjà modifiée par un responsable.
        $insertion = $pdo->prepare('INSERT IGNORE INTO parametres_site (cle, valeur) VALUES (?, ?)');
        foreach (PARAMETRES_PAR_DEFAUT as $cle => $valeur) {
            $insertion->execute([$cle, $valeur]);
        }
    } catch (PDOException $e) {
        error_log('Espace adhérents — migration parametres_site : ' . $e->getMessage());
        $reussi = false;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rubriques_documents (
                id    INT AUTO_INCREMENT PRIMARY KEY,
                nom   VARCHAR(120) NOT NULL,
                ordre INT          NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS categories_documents (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                rubrique_id INT          NOT NULL,
                nom         VARCHAR(120) NOT NULL,
                ordre       INT          NOT NULL DEFAULT 0,
                CONSTRAINT fk_categorie_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques_documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Semis une seule fois, à la toute première création de la table :
        // si un responsable a depuis renommé, ajouté ou supprimé des
        // rubriques, on ne réintroduit jamais la liste par défaut.
        if ((int) $pdo->query('SELECT COUNT(*) FROM rubriques_documents')->fetchColumn() === 0) {
            $inserer_rubrique  = $pdo->prepare('INSERT INTO rubriques_documents (nom, ordre) VALUES (?, ?)');
            $inserer_categorie = $pdo->prepare('INSERT INTO categories_documents (rubrique_id, nom, ordre) VALUES (?, ?, ?)');

            $ordre_rubrique = 0;
            foreach (RUBRIQUES_DOCUMENTS_PAR_DEFAUT as $nom_rubrique => $categories) {
                $inserer_rubrique->execute([$nom_rubrique, $ordre_rubrique++]);
                $rubrique_id = (int) $pdo->lastInsertId();

                $ordre_categorie = 0;
                foreach ($categories as $nom_categorie) {
                    $inserer_categorie->execute([$rubrique_id, $nom_categorie, $ordre_categorie++]);
                }
            }
        }

        // Bascule les documents déposés avant ce changement (ancien
        // classement en texte libre, colonne `categorie`) vers la nouvelle
        // référence `categorie_id`, en retrouvant la catégorie de même nom.
        // Ne touche que les documents pas encore basculés ; un document dont
        // l'ancienne catégorie ne correspond plus à rien de connu reste sans
        // categorie_id (affiché dans « Autres documents » par documents.php).
        $manquants = $pdo->query('SELECT id, categorie FROM documents WHERE categorie_id IS NULL')->fetchAll();
        if ($manquants) {
            $correspondance = [];
            foreach ($pdo->query('SELECT id, nom FROM categories_documents')->fetchAll() as $ligne) {
                $correspondance[$ligne['nom']] = (int) $ligne['id'];
            }
            $basculer = $pdo->prepare('UPDATE documents SET categorie_id = ? WHERE id = ?');
            foreach ($manquants as $document) {
                if (isset($correspondance[$document['categorie']])) {
                    $basculer->execute([$correspondance[$document['categorie']], $document['id']]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Espace adhérents — migration rubriques_documents : ' . $e->getMessage());
        $reussi = false;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS categories_galerie (
                id    INT AUTO_INCREMENT PRIMARY KEY,
                nom   VARCHAR(120) NOT NULL,
                ordre INT          NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS photos_club (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Même principe que rubriques_documents plus haut : semé une seule
        // fois, jamais réintroduit si un responsable a depuis tout supprimé.
        if ((int) $pdo->query('SELECT COUNT(*) FROM categories_galerie')->fetchColumn() === 0) {
            $inserer = $pdo->prepare('INSERT INTO categories_galerie (nom, ordre) VALUES (?, ?)');
            $ordre   = 0;
            foreach (CATEGORIES_GALERIE_PAR_DEFAUT as $nom_categorie) {
                $inserer->execute([$nom_categorie, $ordre++]);
            }
        }

        // Correction ponctuelle (21/08/2026) : la catégorie semée « Macro »
        // faisait double emploi avec le thème « Macro / Proxi » de
        // CLUB_DATA.themes (js/data.js) — les deux apparaissaient comme deux
        // filtres séparés sur la page Galerie publique. Renommer plutôt que
        // supprimer : les photos déjà classées dans « Macro » gardent leur
        // categorie_id, rien n'est perdu. Sans effet si un responsable a
        // depuis renommé ou supprimé cette catégorie lui-même (la clause
        // WHERE ne trouve alors plus rien).
        $pdo->exec("UPDATE categories_galerie SET nom = 'Macro / Proxi' WHERE nom = 'Macro'");

        // Correction ponctuelle (26/08/2026, signalée par l'utilisateur par
        // capture d'écran) : la catégorie semée « Street » était le seul mot
        // anglais du site, et ne correspondait à aucun des thèmes affichés
        // sur la page Galerie publique (js/data.js, resynchronisé le même
        // jour) — d'où deux listes de pastilles visiblement différentes
        // entre la Galerie du Club et la page publique. Même principe que
        // la correction « Macro » ci-dessus : renommer plutôt que
        // supprimer, les photos déjà classées gardent leur categorie_id.
        $pdo->exec("UPDATE categories_galerie SET nom = 'Photo de rue' WHERE nom = 'Street'");
    } catch (PDOException $e) {
        error_log('Espace adhérents — migration categories_galerie : ' . $e->getMessage());
        $reussi = false;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS categories_blog (
                id    INT AUTO_INCREMENT PRIMARY KEY,
                nom   VARCHAR(120) NOT NULL,
                ordre INT          NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS articles_blog (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Même principe que categories_galerie plus haut : semé une seule
        // fois, jamais réintroduit si un responsable a depuis tout supprimé.
        if ((int) $pdo->query('SELECT COUNT(*) FROM categories_blog')->fetchColumn() === 0) {
            $inserer = $pdo->prepare('INSERT INTO categories_blog (nom, ordre) VALUES (?, ?)');
            $ordre   = 0;
            foreach (CATEGORIES_BLOG_PAR_DEFAUT as $nom_categorie) {
                $inserer->execute([$nom_categorie, $ordre++]);
            }
        }
    } catch (PDOException $e) {
        error_log('Espace adhérents — migration categories_blog : ' . $e->getMessage());
        $reussi = false;
    }

    try {
        // Semé une seule fois : si aucune réunion de ce titre n'existe déjà
        // (première migration après l'ajout de ce semis, ou responsable qui
        // a tout supprimé), on ne réintroduit jamais la série.
        $compteur = $pdo->prepare('SELECT COUNT(*) FROM sorties WHERE titre = ?');
        $compteur->execute([REUNION_HEBDOMADAIRE_TITRE]);
        if ((int) $compteur->fetchColumn() === 0) {
            $inserer_reunion = $pdo->prepare(
                'INSERT INTO sorties (titre, categorie, description, lieu, debut, rendez_vous, covoiturage)
                 VALUES (?, ?, ?, ?, ?, ?, 0)'
            );
            $jour         = new DateTime(REUNION_HEBDOMADAIRE_DEBUT);
            $dernier_jour = new DateTime(REUNION_HEBDOMADAIRE_FIN);
            while ($jour <= $dernier_jour) {
                $iso = $jour->format('Y-m-d');
                if (vacances_du_jour($iso) === null) {
                    $inserer_reunion->execute([
                        REUNION_HEBDOMADAIRE_TITRE,
                        'Réunion',
                        'De 20h30 à 23h00.',
                        REUNION_HEBDOMADAIRE_LIEU,
                        $iso . ' 20:30:00',
                        null,
                    ]);
                }
                $jour->modify('+7 days');
            }
        }
    } catch (PDOException $e) {
        error_log('Espace adhérents — migration réunion hebdomadaire : ' . $e->getMessage());
        $reussi = false;
    }

    if ($reussi) {
        @file_put_contents($temoin, signature_schema());
    }
}

/* Change dès qu'on ajoute une colonne ou une clé : invalide le témoin des installations existantes. */
function signature_schema(): string
{
    return md5(
        implode('|', array_keys(COLONNES_ATTENDUES)) . '||' .
        implode('|', array_keys(COLONNES_SORTIES_ATTENDUES)) . '||' .
        implode('|', array_keys(COLONNES_DOCUMENTS_ATTENDUES)) . '||' .
        implode('|', array_keys(COLONNES_PHOTOS_PRIVEES_ATTENDUES)) . '||' .
        implode('|', array_keys(PARAMETRES_PAR_DEFAUT)) . '||' .
        'rubriques_documents_v1' . '||' .
        'categories_galerie_v3' . '||' .
        'reunion_hebdomadaire_v1' . '||' .
        'categories_blog_v1' . '||' .
        'articles_blog_v1'
    );
}

/*
 * Test volontairement naïf mais valable pour MySQL comme pour SQLite : on
 * tente de lire la colonne. INFORMATION_SCHEMA aurait imposé du code
 * différent selon le moteur, et le banc d'essai hors ligne tourne sur SQLite.
 * $table n'est jamais une valeur utilisateur — toujours un littéral passé
 * par le code ci-dessus — donc l'interpoler directement est sans risque.
 */
function colonne_absente(PDO $pdo, string $table, string $colonne): bool
{
    try {
        $pdo->query("SELECT {$colonne} FROM {$table} LIMIT 1");
        return false;
    } catch (PDOException) {
        return true;
    }
}
