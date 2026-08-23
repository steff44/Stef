<?php
/*
 * Gestion des comptes — réservée aux responsables et aux éditeurs
 * (est_gestionnaire(), rôle éditeur ajouté le 23/08/2026, choix explicite
 * de l'utilisateur, avec les mêmes droits que responsable sur cette page,
 * y compris nommer/retirer un rôle et supprimer un compte).
 *
 * Un compte auto-inscrit démarre non validé (`valide = 0`, inscription.php) :
 * l'action « valider » (bouton « Valider ») l'active et prévient la personne
 * par e-mail. Un compte peut aussi être supprimé définitivement (action
 * « supprimer », choix explicite de l'utilisateur, 23/08/2026 — sert à
 * rejeter une inscription indésirable, ou à retirer un compte déjà actif
 * plutôt que de l'invalider, ce qui n'aurait plus vraiment d'utilité une
 * fois validé). Les photos et documents déjà déposés par la personne
 * restent : leur colonne depose_par passe simplement à NULL (voir les clés
 * étrangères ON DELETE SET NULL dans schema.sql) ; seules ses inscriptions
 * aux sorties disparaissent avec elle (ON DELETE CASCADE).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

$adherent = exige_gestionnaire();
$pdo      = base_de_donnees();

/* Mot de passe provisoire lisible, à transmettre au nouvel adhérent. */
function mot_de_passe_provisoire(): string
{
    // Sans caractères ambigus (0/O, 1/l/I) : il sera recopié à la main.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $mot      = '';
    for ($i = 0; $i < 12; $i++) {
        $mot .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $mot;
}

/* Vrai si, en excluant $id_exclu, plus aucun responsable actif ne resterait
   — évite de se fermer la porte au nez en supprimant le dernier compte
   capable de gérer les réglages du site. */
function dernier_responsable_actif(PDO $pdo, int $id_exclu): bool
{
    $requete = $pdo->prepare('SELECT COUNT(*) FROM adherents WHERE administrateur = 1 AND actif = 1 AND id != ?');
    $requete->execute([$id_exclu]);
    return (int) $requete->fetchColumn() === 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'creer') {
        $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
        $nom         = trim((string) ($_POST['nom'] ?? ''));
        $email       = trim((string) ($_POST['email'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $identifiant)) {
            definir_message('erreur', "L'identifiant doit faire 3 à 60 caractères, sans espace ni accent.");
        } elseif ($nom === '') {
            definir_message('erreur', "Le nom est obligatoire.");
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            definir_message('erreur', "L'adresse e-mail n'est pas valide.");
        } else {
            $provisoire = mot_de_passe_provisoire();
            try {
                $pdo->prepare(
                    'INSERT INTO adherents (identifiant, nom, email, telephone, mot_de_passe, administrateur, editeur)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $identifiant,
                    $nom,
                    $email ?: null,
                    trim((string) ($_POST['telephone'] ?? '')) ?: null,
                    password_hash($provisoire, PASSWORD_DEFAULT),
                    isset($_POST['administrateur']) ? 1 : 0,
                    isset($_POST['editeur']) ? 1 : 0,
                ]);
                // Affiché une seule fois : il n'est stocké nulle part en clair.
                definir_message('succes', "Compte créé. Mot de passe provisoire de {$nom} : {$provisoire} — notez-le et transmettez-le, il ne sera plus affiché.");
            } catch (PDOException $e) {
                // 23000 = contrainte violée, ici l'identifiant déjà pris.
                if ($e->getCode() === '23000') {
                    definir_message('erreur', "L'identifiant « {$identifiant} » est déjà utilisé.");
                } else {
                    throw $e;
                }
            }
        }

    } elseif ($action === 'reinitialiser') {
        $provisoire = mot_de_passe_provisoire();
        $requete    = $pdo->prepare('SELECT nom FROM adherents WHERE id = ?');
        $requete->execute([$id]);

        if ($nom = $requete->fetchColumn()) {
            $pdo->prepare('UPDATE adherents SET mot_de_passe = ? WHERE id = ?')
                ->execute([password_hash($provisoire, PASSWORD_DEFAULT), $id]);
            definir_message('succes', "Nouveau mot de passe provisoire de {$nom} : {$provisoire} — notez-le, il ne sera plus affiché.");
        }

    } elseif ($action === 'deconnecter') {
        // On ne supprime pas la session côté serveur — on ne peut pas
        // atteindre celle d'un autre visiteur. On date la coupure : à sa
        // requête suivante, signaler_presence() constate que sa session est
        // antérieure et la ferme. La personne peut se reconnecter ensuite.
        $requete = $pdo->prepare('SELECT nom FROM adherents WHERE id = ?');
        $requete->execute([$id]);

        if ($nom = $requete->fetchColumn()) {
            $pdo->prepare(
                'UPDATE adherents SET deconnecte_le = NOW(), derniere_activite = NULL WHERE id = ?'
            )->execute([$id]);
            $precision = $id === $adherent['id'] ? " Vous allez être renvoyé à la page de connexion." : '';
            definir_message('succes', "Session de {$nom} fermée.{$precision}");
        }

    } elseif ($action === 'basculer_actif') {
        // Un responsable ne peut pas se désactiver lui-même : ce serait le
        // meilleur moyen de se fermer la porte au nez.
        if ($id === $adherent['id']) {
            definir_message('erreur', "Vous ne pouvez pas désactiver votre propre compte.");
        } else {
            $pdo->prepare('UPDATE adherents SET actif = 1 - actif WHERE id = ?')->execute([$id]);
            definir_message('succes', "Statut du compte modifié.");
        }

    } elseif ($action === 'basculer_admin') {
        $requete = $pdo->prepare('SELECT administrateur, actif FROM adherents WHERE id = ?');
        $requete->execute([$id]);
        $cible = $requete->fetch();

        if ($id === $adherent['id']) {
            definir_message('erreur', "Vous ne pouvez pas retirer votre propre rôle de responsable.");
        } elseif (!$cible) {
            definir_message('erreur', "Ce compte n'existe plus.");
        } elseif ($cible['administrateur'] && $cible['actif'] && dernier_responsable_actif($pdo, $id)) {
            // Ne bloque que le retrait (passer 1 → 0) sur le dernier
            // responsable actif restant ; nommer quelqu'un responsable
            // (0 → 1) ne passe jamais par cette branche.
            definir_message('erreur', "Impossible de retirer le rôle du dernier responsable actif.");
        } else {
            $pdo->prepare('UPDATE adherents SET administrateur = 1 - administrateur WHERE id = ?')->execute([$id]);
            definir_message('succes', "Rôle modifié.");
        }

    } elseif ($action === 'basculer_editeur') {
        if ($id === $adherent['id']) {
            definir_message('erreur', "Vous ne pouvez pas retirer votre propre rôle d'éditeur.");
        } else {
            $pdo->prepare('UPDATE adherents SET editeur = 1 - editeur WHERE id = ?')->execute([$id]);
            definir_message('succes', "Rôle modifié.");
        }

    } elseif ($action === 'valider') {
        $requete = $pdo->prepare('SELECT nom, email, valide FROM adherents WHERE id = ?');
        $requete->execute([$id]);
        $cible = $requete->fetch();

        if (!$cible) {
            definir_message('erreur', "Ce compte n'existe plus.");
        } elseif ($cible['valide']) {
            definir_message('erreur', "Ce compte est déjà validé.");
        } else {
            $pdo->prepare('UPDATE adherents SET valide = 1 WHERE id = ?')->execute([$id]);

            if ($cible['email']) {
                envoyer_mail(
                    $cible['email'],
                    valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com',
                    "Votre inscription au Focal Club Turballais a été validée",
                    "Bonjour,\n\n"
                    . "Bonne nouvelle : votre inscription à l'espace adhérents du Focal Club "
                    . "Turballais vient d'être validée par un responsable. Vous pouvez dès à "
                    . "présent vous connecter avec l'identifiant et le mot de passe que vous "
                    . "avez choisis à l'inscription.\n\n"
                    . "À bientôt,\nLe Focal Club Turballais"
                );
            }
            definir_message('succes', "Compte de {$cible['nom']} validé.");
        }

    } elseif ($action === 'supprimer') {
        if ($id === $adherent['id']) {
            definir_message('erreur', "Vous ne pouvez pas supprimer votre propre compte.");
        } else {
            $requete = $pdo->prepare('SELECT nom, administrateur, actif FROM adherents WHERE id = ?');
            $requete->execute([$id]);
            $cible = $requete->fetch();

            if (!$cible) {
                definir_message('erreur', "Ce compte n'existe plus.");
            } elseif ($cible['administrateur'] && $cible['actif'] && dernier_responsable_actif($pdo, $id)) {
                definir_message('erreur', "Impossible de supprimer le dernier responsable actif.");
            } else {
                $pdo->prepare('DELETE FROM adherents WHERE id = ?')->execute([$id]);
                definir_message('succes', "Compte de {$cible['nom']} supprimé.");
            }
        }
    }

    header('Location: adherents.php');
    exit;
}

// « Connecté » = une page consultée dans les dernières minutes, et pas de
// coupure demandée depuis. C'est le plus près de la vérité qu'on puisse être :
// rien ne signale au serveur qu'un onglet vient d'être fermé.
$requete = $pdo->prepare(
    'SELECT id, identifiant, nom, email, telephone, administrateur, editeur, actif, valide,
            derniere_connexion, derniere_activite,
            (derniere_activite IS NOT NULL
              AND derniere_activite >= (NOW() - INTERVAL ? MINUTE)
              AND (deconnecte_le IS NULL OR derniere_activite > deconnecte_le)) AS en_ligne
       FROM adherents
      ORDER BY valide ASC, actif DESC, nom'
);
$requete->execute([DELAI_PRESENCE_MINUTES]);
$membres = $requete->fetchAll();

debut_page("Adhérents", 'adherents');
titre_page(
    "Gestion des adhérents",
    "Créer les comptes, réinitialiser les mots de passe, activer ou désactiver un accès.",
    true
);
?>
<!-- Conteneur élargi : ce tableau porte six colonnes, il respire mal dans la
     largeur de lecture habituelle du site. -->
<section class="section"><div class="container container-large">
  <?php afficher_message(); ?>

  <details class="depot-bloc">
    <summary>Créer un compte adhérent</summary>
    <form method="post" class="form-card" style="margin-top:16px;">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="creer">
      <div class="field">
        <label for="identifiant">Identifiant de connexion</label>
        <input type="text" id="identifiant" name="identifiant" required placeholder="claire">
      </div>
      <div class="field">
        <label for="nom">Nom complet</label>
        <input type="text" id="nom" name="nom" required placeholder="Claire Martin">
      </div>
      <div class="field">
        <label for="email">E-mail (facultatif)</label>
        <input type="email" id="email" name="email">
      </div>
      <div class="field">
        <label for="telephone">Téléphone (facultatif)</label>
        <input type="tel" id="telephone" name="telephone">
      </div>
      <label class="case-a-cocher">
        <input type="checkbox" name="administrateur" value="1">
        Responsable (tous les droits, y compris les réglages du site)
      </label>
      <label class="case-a-cocher" style="margin-top:8px;">
        <input type="checkbox" name="editeur" value="1">
        Éditeur (peut gérer les comptes, les documents et l'agenda, sans accès aux réglages du site)
      </label>
      <p class="form-note" style="margin:14px 0;">
        Un mot de passe provisoire sera affiché une seule fois, juste après la création.
        Notez-le et transmettez-le à l'adhérent, qui pourra le changer depuis l'onglet Annuaire.
      </p>
      <button type="submit" class="btn btn-primary">Créer le compte</button>
    </form>
  </details>

  <div class="tableau-defilant">
    <table class="tableau-adherents">
      <thead>
        <tr>
          <th>Connecté</th>
          <th>Nom</th>
          <th>Pseudo</th>
          <th>E-mail</th>
          <th>Dernière connexion</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($membres as $membre): ?>
          <?php $en_ligne = (bool) $membre['en_ligne']; ?>
          <tr<?= $membre['actif'] ? '' : ' class="compte-inactif"' ?>>
            <td>
              <?php if ($en_ligne): ?>
                <span class="pastille-en-ligne" title="Actif il y a moins de <?= DELAI_PRESENCE_MINUTES ?> minutes"></span>
                <span class="etat-en-ligne">en ligne</span>
              <?php else: ?>
                <span class="pastille-hors-ligne"></span>
                <span class="etat-hors-ligne">hors ligne</span>
              <?php endif; ?>
            </td>
            <td>
              <?= e($membre['nom']) ?>
              <?= $membre['administrateur'] ? ' <span class="badge-admin">responsable</span>' : '' ?>
              <?= $membre['editeur'] ? ' <span class="badge-editeur">éditeur</span>' : '' ?>
              <?= $membre['actif'] ? '' : ' <span class="badge-inactif">désactivé</span>' ?>
              <?= $membre['valide'] ? '' : ' <span class="badge-attente">en attente de validation</span>' ?>
            </td>
            <td><?= e($membre['identifiant']) ?></td>
            <td>
              <?= $membre['email'] ? e($membre['email']) : '—' ?>
              <?php if ($membre['telephone']): ?>
                <br><span class="contact-secondaire"><?= e($membre['telephone']) ?></span>
              <?php endif; ?>
            </td>
            <td class="colonne-date">
              <?php if ($membre['derniere_connexion']): ?>
                <?= e(date_courte($membre['derniere_connexion'])) ?>
                <span class="heure-secondaire"><?= e(heure_courte($membre['derniere_connexion'])) ?></span>
              <?php else: ?>
                <span class="jamais-connecte">jamais</span>
              <?php endif; ?>
            </td>
            <td class="cellule-actions">
              <?php if ($en_ligne): ?>
                <form method="post" onsubmit="return confirm(<?= $membre['id'] === $adherent['id'] ? "'Fermer votre propre session ? Vous devrez vous reconnecter.'" : "'Fermer la session de cet adhérent ?'" ?>);">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="deconnecter">
                  <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                  <button type="submit" class="lien-action lien-deconnecter">Déconnecter</button>
                </form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Générer un nouveau mot de passe provisoire ?');">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="reinitialiser">
                <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                <button type="submit" class="lien-action">Réinitialiser le mot de passe</button>
              </form>
              <?php if ((int) $membre['id'] !== $adherent['id']): ?>
                <?php if (!$membre['valide']): ?>
                  <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="valider">
                    <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                    <button type="submit" class="lien-action">Valider</button>
                  </form>
                <?php endif; ?>
                <form method="post">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="basculer_actif">
                  <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                  <button type="submit" class="lien-action"><?= $membre['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
                </form>
                <form method="post" onsubmit="return confirm('Supprimer définitivement le compte de <?= e(addslashes($membre['nom'])) ?> ? Cette action est irréversible.');">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="supprimer">
                  <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                  <button type="submit" class="lien-danger">Supprimer</button>
                </form>
                <div class="case-role-ligne">
                  <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="basculer_admin">
                    <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                    <button type="submit" class="case-role<?= $membre['administrateur'] ? ' case-role-actif' : '' ?>"
                            aria-pressed="<?= $membre['administrateur'] ? 'true' : 'false' ?>"
                            title="<?= $membre['administrateur'] ? 'Retirer le rôle responsable' : 'Nommer responsable' ?>">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="5" x2="19" y2="19"></line><line x1="19" y1="5" x2="5" y2="19"></line></svg>
                    </button>
                  </form>
                  <span class="case-role-label">Responsable</span>
                </div>
                <div class="case-role-ligne">
                  <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="basculer_editeur">
                    <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                    <button type="submit" class="case-role<?= $membre['editeur'] ? ' case-role-actif' : '' ?>"
                            aria-pressed="<?= $membre['editeur'] ? 'true' : 'false' ?>"
                            title="<?= $membre['editeur'] ? "Retirer le rôle éditeur" : 'Nommer éditeur' ?>">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="5" x2="19" y2="19"></line><line x1="19" y1="5" x2="5" y2="19"></line></svg>
                    </button>
                  </form>
                  <span class="case-role-label">Éditeur</span>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></section>
<?php
fin_page();
