<?php
/*
 * DIAGNOSTIC TEMPORAIRE — à supprimer une fois la cause trouvée. Vérifie,
 * indépendamment de toute manipulation au clavier/souris de l'utilisatrice,
 * si une vraie soumission POST à mot-de-passe-oublie.php (faite par un
 * script externe, cookie de session + jeton CSRF compris, pour reproduire
 * exactement un navigateur) atteint bien le code qui pose le jeton de
 * réinitialisation en base — et, si oui, si envoyer_mail() avec le VRAI
 * Reply-To (parametres_site.email) réussit ou échoue, contrairement à
 * diag-reset-mail.php qui utilise un Reply-To factice toujours valide.
 *
 * Protégé par un secret dans l'URL plutôt que par une connexion : ce script
 * est appelé par un workflow GitHub Actions externe (curl), pas par un
 * navigateur connecté.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

const DIAG_SECRET = 'ba0551506f815f0c8a71ffa5b2ace98b6f4a4b009b3516a4';

header('Content-Type: application/json; charset=UTF-8');

if (($_GET['secret'] ?? '') !== DIAG_SECRET) {
    http_response_code(404);
    echo json_encode(['erreur' => 'introuvable']);
    exit;
}

$pdo = base_de_donnees();
$action = $_GET['action'] ?? '';
$identifiant = trim((string) ($_GET['identifiant'] ?? ''));

function trouver($pdo, string $saisie): ?array
{
    $requete = $pdo->prepare(
        'SELECT id, identifiant, nom, email, actif,
                jeton_reinitialisation, jeton_expire_le
           FROM adherents
          WHERE identifiant = ? OR email = ?
          LIMIT 1'
    );
    $requete->execute([$saisie, $saisie]);
    $ligne = $requete->fetch();
    return $ligne ?: null;
}

if ($action === 'effacer') {
    $adherent = trouver($pdo, $identifiant);
    if (!$adherent) {
        echo json_encode(['erreur' => 'compte introuvable']);
        exit;
    }
    $pdo->prepare('UPDATE adherents SET jeton_reinitialisation = NULL, jeton_expire_le = NULL WHERE id = ?')
        ->execute([$adherent['id']]);
    echo json_encode(['ok' => true, 'action' => 'efface', 'id' => $adherent['id']]);
    exit;
}

if ($action === 'lire') {
    $adherent = trouver($pdo, $identifiant);
    if (!$adherent) {
        echo json_encode(['erreur' => 'compte introuvable']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'identifiant' => $adherent['identifiant'],
        'actif' => (bool) $adherent['actif'],
        'email' => $adherent['email'],
        'jeton_present' => $adherent['jeton_reinitialisation'] !== null,
        'jeton_expire_le' => $adherent['jeton_expire_le'],
    ]);
    exit;
}

if ($action === 'tester_envoi_reel') {
    // Reproduit EXACTEMENT envoyer_mail() tel qu'appelé par
    // mot-de-passe-oublie.php, avec le vrai Reply-To (parametres_site.email)
    // au lieu du Reply-To factice de diag-reset-mail.php — pour isoler si
    // c'est spécifiquement cette valeur qui fait échouer mail().
    $adherent = trouver($pdo, $identifiant);
    if (!$adherent) {
        echo json_encode(['erreur' => 'compte introuvable']);
        exit;
    }
    if (!$adherent['email']) {
        echo json_encode(['erreur' => 'pas d\'e-mail sur ce compte']);
        exit;
    }

    $email_club_brut = valeur_parametre($pdo, 'email');
    $email_club = $email_club_brut ?: 'cooky44.sl@gmail.com';

    $entetes = "From: Focal Club Turballais <noreply@myfocal.online>\r\n"
             . "Reply-To: {$email_club}\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n";
    $sujet = '=?UTF-8?B?' . base64_encode('[Diagnostic jeton] Test avec le vrai Reply-To') . '?=';
    $corps = corps_html("Ceci est un e-mail de test — Reply-To = " . $email_club_brut . ".");

    error_clear_last();
    $reussi = @mail($adherent['email'], $sujet, $corps, $entetes);
    $erreur = error_get_last();

    echo json_encode([
        'ok' => true,
        'email_club_brut' => $email_club_brut,
        'email_club_brut_hex' => $email_club_brut !== null ? bin2hex($email_club_brut) : null,
        'email_club_brut_longueur' => $email_club_brut !== null ? strlen($email_club_brut) : null,
        'destinataire' => $adherent['email'],
        'envoi_reussi' => $reussi,
        'derniere_erreur' => $erreur['message'] ?? null,
    ]);
    exit;
}

if ($action === 'comparer_expediteur') {
    // Envoie le VRAI contenu de l'e-mail "en attente de validation"
    // (inscription.php) deux fois, avec un From différent à chaque fois —
    // myfocal.online (actuel) contre focalclub.fr (candidat) — pour établir
    // si le domaine du From explique pourquoi cet e-mail précis n'arrive
    // jamais, alors que le diagnostic simple (tester_envoi_reel) arrive.
    $adherent = trouver($pdo, $identifiant);
    if (!$adherent || !$adherent['email']) {
        echo json_encode(['erreur' => 'compte introuvable ou sans e-mail']);
        exit;
    }

    $corps_reel = "Bonjour {$adherent['nom']},\n\n"
        . "Votre inscription à l'espace adhérents du Focal Club Turballais a bien été "
        . "enregistrée. Elle est en attente de validation par un responsable du club : "
        . "vous recevrez un nouvel e-mail dès que votre compte sera activé.\n\n"
        . "**Pensez à vérifier aussi votre dossier de courriers indésirables (spams)** "
        . "si vous ne voyez pas cet e-mail de validation arriver.\n\n"
        . "À bientôt,\nLe Focal Club Turballais";
    $corps_html = corps_html($corps_reel);

    $resultats = [];
    foreach (['myfocal.online', 'focalclub.fr'] as $domaine) {
        $entetes = "From: Focal Club Turballais <noreply@{$domaine}>\r\n"
                 . "Reply-To: {$adherent['email']}\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n";
        $sujet = '=?UTF-8?B?' . base64_encode("[Comparatif From] Depuis {$domaine}") . '?=';

        error_clear_last();
        $reussi = @mail($adherent['email'], $sujet, $corps_html, $entetes);
        $erreur = error_get_last();

        $resultats[] = [
            'domaine_from' => $domaine,
            'envoi_reussi' => $reussi,
            'derniere_erreur' => $erreur['message'] ?? null,
        ];
    }

    echo json_encode(['ok' => true, 'destinataire' => $adherent['email'], 'resultats' => $resultats]);
    exit;
}

http_response_code(400);
echo json_encode(['erreur' => 'action inconnue (effacer, lire, tester_envoi_reel, comparer_expediteur)']);
