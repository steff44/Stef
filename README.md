# Focal Club Turballais — site web

Site statique responsive pour un club photo : présentation du club, page adhérents, galerie individuelle par adhérent, page contact.

**En ligne :** https://myfocal.online

## Structure

```
public_html/        ← racine du site (déployée telle quelle sur Hostinger)
  index.html
  membres.html
  galerie.html       ← galerie d'un adhérent, via ?id=<identifiant>
  contact.html
  css/style.css
  js/data.js          ← données des adhérents et de leurs photos
  js/main.js
```

## Modifier le contenu

- **Adhérents et photos** : tout se trouve dans `public_html/js/data.js`. Ajoutez ou modifiez un objet dans le tableau `membres` (id, nom, role, bio, photos) — les pages se mettent à jour automatiquement.
- **Vraies photos** : les vignettes sont actuellement des dégradés de couleur (placeholders). Pour utiliser de vraies photos, déposez vos images dans `public_html/images/` et remplacez, dans `js/main.js`, le style `background` des `.photo-frame`/`.member-cover` par une image (`<img>` ou `background-image: url(...)`).
- **Nom du club, coordonnées** : à modifier directement dans les fichiers HTML (`index.html`, `contact.html`, etc.).

## Déploiement automatique vers Hostinger

Le déploiement se fait via GitHub Actions (`.github/workflows/deploy.yml`), qui synchronise `public_html/` vers le serveur par SSH/rsync à chaque push. **Aucun fichier existant sur le serveur n'est supprimé** (pas d'option `--delete`) — seuls les fichiers nouveaux ou modifiés sont envoyés.

### Configuration requise (une seule fois)

Dans le dépôt GitHub : **Settings → Secrets and variables → Actions → New repository secret**, ajoutez :

| Secret | Valeur |
|---|---|
| `HOSTINGER_HOST` | `91.108.101.117` |
| `HOSTINGER_PORT` | `65002` |
| `HOSTINGER_USER` | `u912253694` |
| `HOSTINGER_SSH_KEY` | Clé privée SSH (format PEM, voir ci-dessous) |
| `HOSTINGER_TARGET_DIR` | `/home/u912253694/public_html/` (chemin exact à vérifier dans hPanel) |

### Générer une clé SSH pour le déploiement

1. Dans hPanel Hostinger → **Avancé → Accès SSH**, générez une nouvelle paire de clés (ou ajoutez-en une). Hostinger vous propose de télécharger la clé privée et ajoute automatiquement la clé publique aux `authorized_keys` du compte.
2. Copiez le contenu de la clé **privée** téléchargée (commence par `-----BEGIN OPENSSH PRIVATE KEY-----`) dans le secret GitHub `HOSTINGER_SSH_KEY`.
3. Vérifiez le chemin exact de `public_html` pour votre compte (visible dans le Gestionnaire de fichiers hPanel) et renseignez-le dans `HOSTINGER_TARGET_DIR`.

Une fois les secrets renseignés, tout push sur la branche de déploiement déclenche automatiquement la mise à jour du site. Le workflow peut aussi être lancé manuellement depuis l'onglet **Actions** du dépôt (bouton "Run workflow").

## Espace adhérents (connexion)

L'espace réservé vit dans `public_html/espace/` et fonctionne en **PHP + MySQL**, directement sur l'hébergement Hostinger. Une fois connecté, un adhérent accède à quatre rubriques : galerie privée, documents du club, agenda des sorties (avec inscription) et annuaire des membres.

Deux rôles existent : **adhérent** (consulte tout, dépose des photos, s'inscrit aux sorties, modifie ses coordonnées et son mot de passe) et **responsable** (en plus : crée les comptes, réinitialise les mots de passe, dépose les documents, gère l'agenda).

### Mise en service (une seule fois)

1. **Créer la base MySQL** — hPanel → *Bases de données* → *MySQL*. Notez le nom de la base, l'utilisateur et le mot de passe.
2. **Créer le fichier de configuration** — hPanel → *Gestionnaire de fichiers* → `public_html/espace/inc/`. Copiez `config.example.php` sous le nom **`config.local.php`**, puis remplacez-y **les trois textes en majuscules** par les valeurs de votre base. Ne touchez pas à `'hote' => 'localhost'`, déjà correct chez Hostinger. Gardez les apostrophes et la virgule de fin de ligne :

   ```php
   'hote'         => 'localhost',            // à laisser tel quel
   'base'         => 'u912253694_focal',     // ← le nom de votre base
   'utilisateur'  => 'u912253694_stef',      // ← l'utilisateur MySQL
   'mot_de_passe' => 'VotreMotDePasseIci',   // ← son mot de passe
   ```

   `base` et `utilisateur` sont deux choses distinctes, même si leurs noms se ressemblent : Hostinger vous fait créer l'une *et* l'autre, et préfixe les deux par l'identifiant de votre compte.

   > Ce fichier se crée à la main, et jamais dans Git : **le dépôt est public**, un mot de passe de base de données y serait visible de tous. Le déploiement ne l'écrase pas (rsync tourne sans `--delete`).
3. **Lancer l'installation** — ouvrez `https://myfocal.online/espace/installation.php`. Le formulaire crée les tables et **votre compte responsable**.
4. **Verrouillage automatique** — dès qu'un compte existe, cette page refuse de servir à nouveau. Vous pouvez ensuite supprimer `espace/installation.php`.
5. **Créer les comptes des adhérents** — connectez-vous, onglet *Adhérents*. Chaque création affiche **une seule fois** un mot de passe provisoire, à transmettre à la personne, qui pourra le changer depuis l'onglet *Annuaire*.

### Suivre et déconnecter les adhérents

L'onglet **Adhérents**, réservé aux responsables, affiche un tableau : *Connecté*, *Nom*, *Pseudo*, *E-mail*, *Dernière connexion*, et les actions.

- La pastille verte **« en ligne »** signale une page consultée dans les **15 dernières minutes**. C'est le plus près de la réalité qu'on puisse être : rien ne prévient le serveur qu'un onglet a été fermé. Une déconnexion volontaire, elle, fait disparaître la pastille immédiatement.
- Le bouton **Déconnecter** n'apparaît que pour les personnes en ligne. Il ferme leur session : à leur page suivante, elles reviennent à l'écran de connexion avec le message « Un responsable a fermé votre session ». **Elles peuvent se reconnecter aussitôt** avec leur mot de passe — pour barrer durablement l'accès, utilisez plutôt *Désactiver*.

Utile quand quelqu'un a oublié de se déconnecter sur un poste partagé.

### Modifier les coordonnées affichées sur le site public

L'onglet **Réglages du site**, réservé aux responsables, permet de modifier le lieu de réunion (nom, adresse), les horaires (jour, créneau, fréquence), le téléphone, l'e-mail et le texte de présentation du pied de page — sans passer par un développeur.

Ces informations apparaissent sur l'accueil, la page Contact et le pied de page de chaque page du site. Une modification met **quelques minutes** à s'afficher partout : c'est le navigateur de chaque visiteur qui va chercher la valeur à jour, pas une republication du site.

### Ce qui protège l'espace

- Mots de passe stockés **hachés** (`password_hash`, jamais en clair) ; blocage 15 minutes après 5 essais ratés.
- Requêtes SQL **préparées** (aucune injection possible) et affichage systématiquement échappé.
- Jeton **anti-CSRF** sur chaque formulaire ; identifiant de session renouvelé à la connexion ; cookie `HttpOnly` + `SameSite`.
- Fichiers déposés : le type réel est lu dans le **contenu** du fichier, jamais dans son nom ; le nom d'enregistrement est réinventé aléatoirement. Un script PHP renommé en `.png` est refusé.
- Les dossiers `espace/inc/`, `espace/photos/` et `espace/fichiers/` sont **fermés par `.htaccess`** : les fichiers privés ne sont servis que par `telecharger.php`, après vérification de la connexion.
- Les pages de l'espace sont en `noindex` : elles ne remonteront pas dans les moteurs de recherche.

> **À noter :** ces pages sont en PHP, elles ne fonctionnent donc **pas sur la préversion GitHub Pages**, qui ne sert que des fichiers statiques. L'espace adhérents ne peut se tester qu'une fois en ligne sur Hostinger.

## Préversion : relire avant de publier

Avant qu'une modification n'arrive sur le site en ligne, on peut la regarder sur une préversion : **https://steff44.github.io/Stef/**

Le workflow `.github/workflows/preview.yml` y publie le contenu de `public_html/` à chaque push sur une branche `claude/**`. Il ne touche jamais au site en ligne — le déploiement Hostinger, lui, ne part que de `main`.

- Une seule préversion existe à la fois : c'est toujours la branche poussée le plus récemment qui s'affiche.
- La préversion est protégée de l'indexation (`robots.txt` + balise `noindex`), pour qu'elle ne concurrence pas le vrai site dans les résultats de recherche. Ces protections sont ajoutées à la volée lors de la publication, jamais dans les fichiers du dépôt.
- Le workflow peut aussi être lancé manuellement : onglet **Actions** → « Préversion GitHub Pages » → *Run workflow*.

### Configuration requise (une seule fois, déjà faite)

**Settings → Pages → Build and deployment → Source = "GitHub Actions".** Seul le propriétaire du dépôt peut activer Pages : le jeton des workflows n'en a pas le droit (`Resource not accessible by integration`). Si la préversion échoue à l'étape « Configuration de Pages », c'est ce réglage qu'il faut vérifier.

Le parcours complet est donc : brancher en `claude/**` → pousser → relire la préversion → fusionner sur `main` → mise en ligne sur Hostinger.
