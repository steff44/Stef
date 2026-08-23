<?php
/*
 * Sessions, connexion/déconnexion et petites protections associées.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const TENTATIVES_MAX     = 5;    // essais ratés avant blocage temporaire
const BLOCAGE_SECONDES   = 900;  // 15 minutes

// Au-delà de ce délai sans consulter une page, un adhérent n'est plus compté
// comme « connecté » dans le tableau des responsables. Le web étant sans
// mémoire, il n'existe aucun moyen de savoir qu'un onglet a été fermé : on
// raisonne donc en activité récente, pas en présence certaine.
const DELAI_PRESENCE_MINUTES = 15;

function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Le site est en HTTPS : le cookie de session ne doit jamais circuler en clair.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,          // le cookie expire à la fermeture du navigateur
        'path'     => '/',
        'httponly' => true,       // inaccessible au JavaScript
        'secure'   => $https,
        'samesite' => 'Lax',      // limite les requêtes venues d'autres sites
    ]);
    session_name('focalclub');
    session_start();
}

function adherent_connecte(): ?array
{
    demarrer_session();
    return $_SESSION['adherent'] ?? null;
}

function est_administrateur(): bool
{
    return (bool) (adherent_connecte()['administrateur'] ?? false);
}

function est_editeur(): bool
{
    return (bool) (adherent_connecte()['editeur'] ?? false);
}

/*
 * Éditeur : rôle du 23/08/2026 (choix explicite de l'utilisateur), mêmes
 * droits que responsable sur les comptes (adherents.php), les documents
 * (documents.php) et l'agenda (sorties-a-venir.php), mais sans accès aux
 * réglages du site (parametres.php reste réservé à exige_administrateur()).
 */
function est_gestionnaire(): bool
{
    return est_administrateur() || est_editeur();
}

/*
 * À placer en tête de chaque page réservée. Renvoie vers la page de
 * connexion si la personne n'est pas identifiée — qui, une fois connectée,
 * envoie toujours vers le tableau de bord (choix explicite de
 * l'utilisateur, 18/08/2026), jamais vers cette page d'origine.
 */
function exige_connexion(): array
{
    $adherent = adherent_connecte();
    if ($adherent === null) {
        header('Location: connexion.php');
        exit;
    }

    signaler_presence($adherent);

    return $adherent;
}

/*
 * Appelé une fois par page réservée. Deux rôles :
 *   1. couper la session si un responsable a demandé la déconnexion ;
 *   2. rafraîchir l'horodatage d'activité, qui alimente la colonne
 *      « Connecté » du tableau des adhérents.
 */
function signaler_presence(array $adherent): void
{
    // Une seule vérification par requête, même si la fonction est rappelée.
    static $deja_fait = false;
    if ($deja_fait) {
        return;
    }
    $deja_fait = true;

    // L'instant de coupure est lu en secondes depuis l'horloge de MySQL, la
    // même qui a servi à dater l'ouverture de session : comparer deux horloges
    // différentes (PHP et MySQL) donnerait des résultats faux dès que leurs
    // fuseaux divergent.
    $requete = base_de_donnees()->prepare(
        'SELECT UNIX_TIMESTAMP(deconnecte_le) AS coupure, actif FROM adherents WHERE id = ?'
    );
    $requete->execute([$adherent['id']]);
    $ligne = $requete->fetch();

    // Compte supprimé ou désactivé entre-temps : la session ne vaut plus rien.
    if (!$ligne || !$ligne['actif']) {
        fermer_session_a_distance("Votre compte a été désactivé.");
    }

    // Déconnexion demandée par un responsable : toute session ouverte avant
    // cet instant tombe. Une reconnexion normale reste possible.
    //
    // La comparaison est LARGE (<=) et non stricte : MySQL date à la seconde,
    // et un responsable qui clique dans la seconde même où quelqu'un se
    // connecte doit voir sa demande aboutir. Le prix de ce choix est une
    // coupure superflue si la personne se reconnecte dans cette même seconde —
    // elle se reconnecte alors une fois de plus, sans autre conséquence.
    $coupure = (int) ($ligne['coupure'] ?? 0);
    if ($coupure && ($adherent['connecte_depuis'] ?? 0) <= $coupure) {
        fermer_session_a_distance("Un responsable a fermé votre session. Reconnectez-vous.");
    }

    $maj = base_de_donnees()->prepare('UPDATE adherents SET derniere_activite = NOW() WHERE id = ?');
    $maj->execute([$adherent['id']]);
}

function fermer_session_a_distance(string $motif): never
{
    deconnecter();
    demarrer_session();
    $_SESSION['message'] = ['type' => 'erreur', 'texte' => $motif];
    header('Location: connexion.php');
    exit;
}

function exige_administrateur(): array
{
    $adherent = exige_connexion();
    if (!est_administrateur()) {
        page_erreur(
            "Accès réservé",
            "Cette page est réservée aux responsables du club.",
            403
        );
    }
    return $adherent;
}

function exige_gestionnaire(): array
{
    $adherent = exige_connexion();
    if (!est_gestionnaire()) {
        page_erreur(
            "Accès réservé",
            "Cette page est réservée aux responsables et aux éditeurs du club.",
            403
        );
    }
    return $adherent;
}

/*
 * Vérifie l'identifiant et le mot de passe. Renvoie un message d'erreur, ou
 * null si la connexion a réussi.
 */
function tenter_connexion(string $identifiant, string $mot_de_passe): ?string
{
    demarrer_session();

    // Blocage temporaire après plusieurs échecs, pour décourager les essais
    // automatisés de mots de passe.
    $echecs = $_SESSION['echecs'] ?? 0;
    $depuis = $_SESSION['dernier_echec'] ?? 0;
    if ($echecs >= TENTATIVES_MAX && (time() - $depuis) < BLOCAGE_SECONDES) {
        $minutes = (int) ceil((BLOCAGE_SECONDES - (time() - $depuis)) / 60);
        return "Trop de tentatives. Réessayez dans {$minutes} minute" . ($minutes > 1 ? 's' : '') . ".";
    }

    $requete = base_de_donnees()->prepare(
        'SELECT id, identifiant, nom, email, telephone, mot_de_passe, administrateur, editeur, actif, valide
           FROM adherents
          WHERE identifiant = ?
          LIMIT 1'
    );
    $requete->execute([$identifiant]);
    $ligne = $requete->fetch();

    // password_verify est appelé même quand le compte n'existe pas : sans ça,
    // le temps de réponse révélerait quels identifiants existent.
    $hachage = $ligne['mot_de_passe'] ?? '$2y$12$indisponibleindisponibleindisponibleindisponibleind';
    $correct = password_verify($mot_de_passe, $hachage);

    if (!$ligne || !$correct || !$ligne['actif']) {
        $_SESSION['echecs']        = $echecs + 1;
        $_SESSION['dernier_echec'] = time();
        // Message unique : ne jamais indiquer si c'est l'identifiant ou le mot
        // de passe qui est faux.
        return "Identifiant ou mot de passe incorrect.";
    }

    // Identifiants corrects, mais inscription pas encore validée par un
    // responsable ou un éditeur (choix explicite de l'utilisateur, 23/08/2026
    // — remet en place le verrou retiré plus tôt dans la journée : un
    // adhérent s'est connecté sans validation entre-temps, ce qui n'était pas
    // voulu). Le message peut être spécifique ici — la personne vient de
    // créer ce compte elle-même, ça ne révèle rien qu'elle ne sache déjà. On
    // ne compte pas ça comme un échec (compteur de blocage) : le mot de passe
    // était correct.
    if (!$ligne['valide']) {
        return "Votre inscription est en attente de validation par un responsable.";
    }

    // Le mot de passe est bon : on réinitialise les compteurs.
    unset($_SESSION['echecs'], $_SESSION['dernier_echec']);

    // Si le coût du hachage a changé depuis la création du compte, on remet le
    // mot de passe à jour au passage.
    if (password_needs_rehash($ligne['mot_de_passe'], PASSWORD_DEFAULT)) {
        $maj = base_de_donnees()->prepare('UPDATE adherents SET mot_de_passe = ? WHERE id = ?');
        $maj->execute([password_hash($mot_de_passe, PASSWORD_DEFAULT), $ligne['id']]);
    }

    // Nouvel identifiant de session : empêche la « fixation de session ».
    session_regenerate_id(true);

    // deconnecte_le est remis à zéro : la coupure demandée par un responsable
    // visait les sessions d'alors, pas celle-ci. Sans cet effacement, quelqu'un
    // qui se reconnecte dans la seconde suivant sa déconnexion forcée serait
    // éjecté une seconde fois, sans comprendre pourquoi.
    $maj = base_de_donnees()->prepare(
        'UPDATE adherents
            SET derniere_connexion = NOW(), derniere_activite = NOW(), deconnecte_le = NULL
          WHERE id = ?'
    );
    $maj->execute([$ligne['id']]);

    // Daté par l'horloge de MySQL, pour être comparable à deconnecte_le.
    // Une coupure demandée AVANT cette seconde ne concerne pas cette session.
    $ouverture = (int) base_de_donnees()->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();

    $_SESSION['adherent'] = [
        'id'              => (int) $ligne['id'],
        'identifiant'     => $ligne['identifiant'],
        'nom'             => $ligne['nom'],
        'email'           => $ligne['email'],
        'telephone'       => $ligne['telephone'],
        'administrateur'  => (bool) $ligne['administrateur'],
        'editeur'         => (bool) $ligne['editeur'],
        'connecte_depuis' => $ouverture,
    ];

    return null;
}

function deconnecter(): void
{
    demarrer_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------------------------------------------------------------------
 * Jeton anti-CSRF : garantit qu'un formulaire envoyé vient bien de notre site
 * et non d'une page malveillante ouverte dans un autre onglet.
 * ------------------------------------------------------------------------- */

function jeton_csrf(): string
{
    demarrer_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(jeton_csrf(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifier_csrf(): void
{
    demarrer_session();
    $envoye = $_POST['csrf'] ?? '';
    if (!is_string($envoye) || !hash_equals($_SESSION['csrf'] ?? '', $envoye)) {
        page_erreur(
            "Formulaire expiré",
            "Votre page est restée ouverte trop longtemps. Revenez en arrière et recommencez.",
            400
        );
    }
}

/* Raccourci d'échappement, utilisé partout dans l'affichage. */
function e(?string $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}
