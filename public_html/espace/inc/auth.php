<?php
/*
 * Sessions, connexion/déconnexion et petites protections associées.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const TENTATIVES_MAX     = 5;    // essais ratés avant blocage temporaire
const BLOCAGE_SECONDES   = 900;  // 15 minutes

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

/*
 * À placer en tête de chaque page réservée. Renvoie vers la page de connexion
 * si la personne n'est pas identifiée, en mémorisant où elle voulait aller.
 */
function exige_connexion(): array
{
    $adherent = adherent_connecte();
    if ($adherent === null) {
        $_SESSION['retour'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: connexion.php');
        exit;
    }
    return $adherent;
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
        'SELECT id, identifiant, nom, email, telephone, mot_de_passe, administrateur, actif
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

    $_SESSION['adherent'] = [
        'id'             => (int) $ligne['id'],
        'identifiant'    => $ligne['identifiant'],
        'nom'            => $ligne['nom'],
        'email'          => $ligne['email'],
        'telephone'      => $ligne['telephone'],
        'administrateur' => (bool) $ligne['administrateur'],
    ];

    $maj = base_de_donnees()->prepare('UPDATE adherents SET derniere_connexion = NOW() WHERE id = ?');
    $maj->execute([$ligne['id']]);

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
