# Focal Club Turballais — site web

Site statique responsive pour un club photo : présentation du club, page adhérents, galerie individuelle par adhérent, page contact.

**En ligne :** https://myfocalclub.online

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
2. **Créer le fichier de configuration** — hPanel → *Gestionnaire de fichiers* → `public_html/espace/inc/`. Copiez `config.example.php` sous le nom **`config.local.php`** et renseignez-y les quatre valeurs.
   > Ce fichier se crée à la main, et jamais dans Git : **le dépôt est public**, un mot de passe de base de données y serait visible de tous. Le déploiement ne l'écrase pas (rsync tourne sans `--delete`).
3. **Lancer l'installation** — ouvrez `https://myfocalclub.online/espace/installation.php`. Le formulaire crée les tables et **votre compte responsable**.
4. **Verrouillage automatique** — dès qu'un compte existe, cette page refuse de servir à nouveau. Vous pouvez ensuite supprimer `espace/installation.php`.
5. **Créer les comptes des adhérents** — connectez-vous, onglet *Adhérents*. Chaque création affiche **une seule fois** un mot de passe provisoire, à transmettre à la personne, qui pourra le changer depuis l'onglet *Annuaire*.

### Ce qui protège l'espace

- Mots de passe stockés **hachés** (`password_hash`, jamais en clair) ; blocage 15 minutes après 5 essais ratés.
- Requêtes SQL **préparées** (aucune injection possible) et affichage systématiquement échappé.
- Jeton **anti-CSRF** sur chaque formulaire ; identifiant de session renouvelé à la connexion ; cookie `HttpOnly` + `SameSite`.
- Fichiers déposés : le type réel est lu dans le **contenu** du fichier, jamais dans son nom ; le nom d'enregistrement est réinventé aléatoirement. Un script PHP renommé en `.png` est refusé.
- Les dossiers `espace/inc/`, `espace/photos/` et `espace/fichiers/` sont **fermés par `.htaccess`** : les fichiers privés ne sont servis que par `telecharger.php`, après vérification de la connexion.
- Les pages de l'espace sont en `noindex` : elles ne remonteront pas dans les moteurs de recherche.

> **À noter :** ces pages sont en PHP, elles ne fonctionnent donc **pas sur la préversion GitHub Pages**, qui ne sert que des fichiers statiques. L'espace adhérents ne peut se tester qu'une fois en ligne sur Hostinger.
