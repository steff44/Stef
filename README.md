# Photo Club Lumière — site web

Site statique responsive pour un club photo : présentation du club, page adhérents, galerie individuelle par adhérent, page contact.

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
