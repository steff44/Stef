<?php
/*
 * Client SMTP minimal, écrit à la main — même philosophie que xlsx.php
 * (pas de Composer/PHPMailer, cohérent avec un projet sans build).
 *
 * Ajouté le 23/09/2026 : PHP mail() sur cet hébergement passe par un relais
 * mutualisé (MailChannels, voir inc/mail.php) qui ignore l'enveloppe
 * demandée (`-f`) et livre les messages sans signature DKIM, avec un
 * retard important et irrégulier. Envoyer en SMTP authentifié, directement
 * avec une vraie boîte du domaine, contourne ce relais.
 *
 * Une SEULE connexion par page (25/09/2026) : une notification part vers
 * tous les adhérents d'un coup — ouvrir puis authentifier une nouvelle
 * connexion par destinataire faisait des dizaines de connexions en
 * quelques secondes, ce que les serveurs d'envoi limitent, et chaque échec
 * retombait sur mail(). La session reste ouverte d'un message au suivant
 * (RSET entre deux) et se ferme à la fin de la page.
 */

declare(strict_types=1);

class ErreurSmtp extends RuntimeException
{
}

/* Échec à l'ouverture (serveur injoignable, TLS, authentification) : inutile
   de réessayer pour le destinataire suivant, contrairement à un refus qui ne
   concerne qu'une adresse. */
class ErreurConnexionSmtp extends ErreurSmtp
{
}

final class SessionSmtp
{
    /** @var resource|null */
    private $connexion = null;

    public function __construct(
        private string $hote,
        private int $port,
        private string $utilisateur,
        private string $mot_de_passe
    ) {
    }

    public function est_ouverte(): bool
    {
        return $this->connexion !== null;
    }

    public function ouvrir(): void
    {
        // Port 465 : TLS dès la connexion. Sinon (587) : STARTTLS.
        $tls_implicite = $this->port === 465;
        $adresse = ($tls_implicite ? 'ssl://' : 'tcp://') . "{$this->hote}:{$this->port}";

        $connexion = @stream_socket_client($adresse, $code_erreur, $message_erreur, 10);
        if ($connexion === false) {
            throw new ErreurConnexionSmtp("Connexion à {$this->hote}:{$this->port} impossible : {$message_erreur}");
        }
        stream_set_timeout($connexion, 15);
        $this->connexion = $connexion;

        try {
            $this->attendre('220'); // bannière du serveur
            $this->commande('EHLO focalclub.fr', '250');

            if (!$tls_implicite) {
                $this->commande('STARTTLS', '220');
                if (!stream_socket_enable_crypto($connexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new ErreurSmtp('Passage en TLS (STARTTLS) impossible.');
                }
                // Un second EHLO est exigé après STARTTLS.
                $this->commande('EHLO focalclub.fr', '250');
            }

            $this->commande('AUTH LOGIN', '334');
            $this->commande(base64_encode($this->utilisateur), '334');
            $this->commande(base64_encode($this->mot_de_passe), '235');
        } catch (Throwable $e) {
            $this->abandonner();
            throw new ErreurConnexionSmtp($e->getMessage(), 0, $e);
        }
    }

    /* $message : en-têtes + ligne vide + corps, lignes terminées par CRLF. */
    public function envoyer(string $destinataire, string $message): void
    {
        if ($this->connexion === null) {
            $this->ouvrir();
        }
        try {
            $this->commande("MAIL FROM:<{$this->utilisateur}>", '250');
            $this->commande("RCPT TO:<{$destinataire}>", '250');
            $this->commande('DATA', '354');
            // Transparence SMTP : une ligne qui commencerait par un point
            // serait sinon lue comme la fin des données.
            $message = preg_replace('/^\./m', '..', $message);
            $this->ecrire(rtrim($message, "\r\n") . "\r\n.");
            $this->attendre('250');
        } catch (Throwable $e) {
            // État de la conversation incertain : on repart d'une connexion
            // neuve au prochain envoi plutôt que de la réutiliser.
            $this->abandonner();
            throw $e;
        }
        // Remet la conversation à zéro pour le message suivant.
        try {
            $this->commande('RSET', '250');
        } catch (Throwable $e) {
            $this->abandonner();
        }
    }

    public function fermer(): void
    {
        if ($this->connexion === null) {
            return;
        }
        try {
            $this->ecrire('QUIT');
        } catch (Throwable $e) {
            // Rien à faire : on ferme de toute façon.
        }
        $this->abandonner();
    }

    private function abandonner(): void
    {
        if ($this->connexion !== null) {
            @fclose($this->connexion);
            $this->connexion = null;
        }
    }

    private function ecrire(string $ligne): void
    {
        if ($this->connexion === null || @fwrite($this->connexion, $ligne . "\r\n") === false) {
            throw new ErreurSmtp('Connexion SMTP interrompue.');
        }
    }

    private function commande(string $ligne, string $code_attendu): string
    {
        $this->ecrire($ligne);
        return $this->attendre($code_attendu);
    }

    /* Une réponse peut tenir sur plusieurs lignes ("250-..." puis "250 ...")
       : on lit jusqu'à celle dont le 4ᵉ caractère n'est pas un tiret. */
    private function attendre(string $code_attendu): string
    {
        $reponse = '';
        do {
            $ligne = $this->connexion !== null ? fgets($this->connexion, 515) : false;
            if ($ligne === false) {
                break;
            }
            $reponse = $ligne;
        } while (isset($ligne[3]) && $ligne[3] === '-');

        if (substr($reponse, 0, 3) !== $code_attendu) {
            $reponse = trim($reponse) !== '' ? trim($reponse) : '(aucune réponse du serveur)';
            throw new ErreurSmtp("Réponse inattendue (attendu {$code_attendu}) : {$reponse}");
        }
        return $reponse;
    }
}
