<?php
/*
 * Client SMTP minimal, écrit à la main — même philosophie que xlsx.php
 * (pas de Composer/PHPMailer, cohérent avec un projet sans build).
 *
 * Ajouté le 23/09/2026 : PHP mail() sur cet hébergement passe par un relais
 * mutualisé (MailChannels, voir inc/mail.php) qui ignore l'enveloppe
 * demandée (`-f`) et livre les messages sans signature DKIM, avec un
 * retard important et irrégulier (jusqu'à plusieurs heures, confirmé par
 * les horodatages des e-mails de diagnostic reçus en rafale le lendemain).
 * Envoyer en SMTP authentifié, directement avec une vraie boîte du
 * domaine, contourne ce relais : le message part alors comme un vrai
 * e-mail du compte, avec DKIM appliqué par Hostinger et sans le délai
 * de mise en attente que Gmail impose à un expéditeur peu authentifié.
 */

declare(strict_types=1);

class ErreurSmtp extends RuntimeException
{
}

/*
 * $entetes doit se terminer par \r\n (comme construit dans inc/mail.php) ;
 * l'enveloppe (MAIL FROM) est toujours $utilisateur — la boîte
 * authentifiée — Hostinger refuse en général qu'une connexion SMTP
 * authentifiée envoie sous une autre adresse en enveloppe.
 */
function envoyer_via_smtp(
    string $hote,
    int $port,
    string $utilisateur,
    string $mot_de_passe,
    string $destinataire,
    string $entetes,
    string $sujet_encode,
    string $corps_html
): void {
    $connexion = @stream_socket_client(
        "tcp://{$hote}:{$port}",
        $code_erreur,
        $message_erreur,
        10
    );
    if ($connexion === false) {
        throw new ErreurSmtp("Connexion à {$hote}:{$port} impossible : {$message_erreur}");
    }
    stream_set_timeout($connexion, 15);

    // Une réponse SMTP peut tenir sur plusieurs lignes ("250-..." puis
    // "250 ..." pour la dernière) : on lit jusqu'à la ligne dont le
    // 4ᵉ caractère n'est pas un tiret.
    $lire = static function () use ($connexion): string {
        $reponse = '';
        do {
            $ligne = fgets($connexion, 515);
            if ($ligne === false) {
                break;
            }
            $reponse = $ligne;
        } while (isset($ligne[3]) && $ligne[3] === '-');
        return $reponse;
    };

    $envoyer = static function (string $commande) use ($connexion): void {
        fwrite($connexion, $commande . "\r\n");
    };

    $attendre = static function (string $code_attendu) use ($lire): string {
        $reponse = $lire();
        if (substr($reponse, 0, 3) !== $code_attendu) {
            throw new ErreurSmtp("Réponse SMTP inattendue (attendu {$code_attendu}) : {$reponse}");
        }
        return $reponse;
    };

    try {
        $lire(); // bannière 220 du serveur

        $envoyer('EHLO focalclub.fr');
        $attendre('250');

        $envoyer('STARTTLS');
        $attendre('220');
        if (!stream_socket_enable_crypto($connexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new ErreurSmtp('Passage en TLS (STARTTLS) impossible.');
        }
        // Un second EHLO est exigé après STARTTLS : la première liste
        // d'extensions n'est plus valable une fois le chiffrement actif.
        $envoyer('EHLO focalclub.fr');
        $attendre('250');

        $envoyer('AUTH LOGIN');
        $attendre('334');
        $envoyer(base64_encode($utilisateur));
        $attendre('334');
        $envoyer(base64_encode($mot_de_passe));
        $attendre('235');

        $envoyer("MAIL FROM:<{$utilisateur}>");
        $attendre('250');
        $envoyer("RCPT TO:<{$destinataire}>");
        $attendre('250');

        $envoyer('DATA');
        $attendre('354');

        $message = "Subject: {$sujet_encode}\r\n{$entetes}\r\n{$corps_html}";
        // Transparence SMTP : une ligne du message qui commencerait par un
        // point serait sinon interprétée comme la fin des données.
        $message = preg_replace('/^\./m', '..', $message);
        $envoyer($message . "\r\n.");
        $attendre('250');

        $envoyer('QUIT');
    } finally {
        fclose($connexion);
    }
}
