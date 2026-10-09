# Convertisseur Photos

Convertisseur Photos est un petit logiciel de bureau qui prépare vos photos
pour l'écran (site du club, diaporama, envoi par e-mail…). Il accepte :

- les **fichiers RAW de tous les constructeurs** : Nikon (`.nef`), Canon
  (`.cr2`, `.cr3`), Sony (`.arw`), Fujifilm (`.raf`), Olympus / OM System
  (`.orf`), Panasonic (`.rw2`), Pentax (`.pef`), Leica, Hasselblad, DNG…
- les **JPEG**,
- et la plupart des autres formats : TIFF, PNG, WebP, BMP, PSD (image
  aplatie)…

Le format **HEIC des iPhone n'est volontairement pas pris en charge** : son
module pesait à lui seul près de la moitié du logiciel. Les photos d'iPhone
envoyées par e-mail ou messagerie arrivent en général déjà en JPEG ; sinon,
l'iPhone peut enregistrer directement en JPEG (Réglages > Appareil photo >
Formats > « Le plus compatible »). Un fichier HEIC ajouté par erreur est
signalé clairement dans le détail, sans bloquer les autres photos.

et les transforme, au choix, en **JPEG** ou en **WebP** :

- plus grand côté ramené à **1920 pixels** (réglable) — une photo plus
  petite garde sa taille : elle n'est **jamais agrandie** ;
- **poids maximum de 500 Ko** par photo (réglable, 0 = sans limite) : si la
  photo est trop lourde, le logiciel baisse d'abord un peu la qualité (sans
  descendre sous 70), puis, si ça ne suffit pas, réduit légèrement ses
  dimensions ;
- résolution **72 ppp** ;
- **netteté accentuée pour l'écran**, appliquée après la réduction (Aucune,
  Légère, Normale, Forte) ;
- couleurs converties en **sRGB**, l'espace des écrans et des navigateurs
  (une photo en Adobe RGB ne paraîtra donc pas terne sur le web) ;
- rotation portrait/paysage appliquée automatiquement ;
- **métadonnées conservées ou retirées, au choix** (appareil, objectif,
  date, vitesse, ouverture, ISO, auteur, copyright…). La position GPS a sa
  propre case, décochée par défaut, pour ne pas publier par mégarde
  l'endroit où une photo a été prise.

Vos photos d'origine ne sont jamais modifiées, et un fichier déjà présent
dans le dossier de destination n'est jamais écrasé (le nouveau reçoit
« (2) », « (3) »… à la fin de son nom).

## Installation

Il faut Python 3 (sur Windows, téléchargez-le sur
[python.org](https://www.python.org/downloads/) en cochant bien « Add
Python to PATH »), puis ces modules, en une seule commande :

```
pip install pillow rawpy exifread
```

- `pillow` : indispensable ;
- `rawpy` : pour les fichiers RAW (il contient LibRaw, le moteur de
  développement RAW utilisé par de nombreux logiciels photo) ;
- `exifread` : pour lire les métadonnées de certains RAW (CR3, RAF, ORF…).

S'il en manque un, le logiciel vous le dit au démarrage et fonctionne quand
même pour les autres formats.

## Lancer Convertisseur Photos

Dans le dossier `convertisseur_photos` :

```
python convertisseur_photos.py
```

## Fabriquer le logiciel Windows (à double-cliquer)

Sur un PC Windows où Python est installé, double-cliquez sur
`build_exe.bat`. Au bout de quelques minutes (la première fois ; plus vite
ensuite), vous obtenez :

- le dossier `convertisseur_photos\dist\ConvertisseurPhotos`, qui contient
  le logiciel (`ConvertisseurPhotos.exe`) et ses fichiers — **ne séparez pas
  l'`.exe` du reste de ce dossier** ;
- un **raccourci « Convertisseur Photos » sur votre Bureau**, pour le lancer
  d'un double-clic ;
- `dist\ConvertisseurPhotos.zip`, pour le donner à quelqu'un (à
  décompresser, puis double-cliquer sur `ConvertisseurPhotos.exe`) : il n'a
  pas besoin de Python.

Le logiciel est fabriqué sous forme de dossier plutôt que d'un seul gros
fichier `.exe` : un fichier unique doit être entièrement décompressé à
chaque lancement (et analysé par l'antivirus), ce qui le rendait lent à
s'ouvrir. Le script travaille aussi dans un environnement Python dédié
(`.env_fabrication`, créé la première fois) qui ne contient que les modules
utiles, et exclut tout ce dont le logiciel ne se sert pas : il est ainsi
nettement plus léger qu'avant.

## Sous GNU/Linux

Le logiciel fonctionne tel quel sous GNU/Linux (Ubuntu, Linux Mint, Debian,
Fedora…). Il faut d'abord Python 3 avec Tkinter et venv ; sur
Debian/Ubuntu/Linux Mint :

```
sudo apt install python3 python3-venv python3-tk
```

(Fedora : `sudo dnf install python3 python3-tkinter` ; Arch/Manjaro :
`sudo pacman -S python tk`.)

Ensuite, dans un terminal ouvert dans le dossier `convertisseur_photos` :

```
bash build_linux.sh
```

Au bout de quelques minutes (la première fois), vous obtenez :

- le dossier `dist/ConvertisseurPhotos`, qui contient le logiciel
  (`ConvertisseurPhotos`) et ses fichiers — **ne séparez pas le programme du
  reste de ce dossier** ;
- une entrée **« Convertisseur Photos » dans le menu des applications**
  (et une icône sur le Bureau s'il existe) ;
- `dist/ConvertisseurPhotos-linux.tar.gz`, pour le donner à quelqu'un : il
  le décompresse, puis lance `installer.sh` dans le dossier obtenu
  (double-clic, ou `bash installer.sh` dans un terminal) pour ajouter le
  logiciel à son menu. Il n'a pas besoin de Python. Si vous déplacez le
  dossier plus tard, relancez `installer.sh`.

Le logiciel fabriqué fonctionne sur les distributions aussi récentes ou plus
récentes que celle sur laquelle il a été fabriqué : pour le partager le plus
largement, fabriquez-le sur une distribution un peu ancienne.

Sans rien fabriquer, vous pouvez aussi le lancer directement avec Python :

```
python3 -m pip install --user pillow rawpy exifread
python3 convertisseur_photos.py
```

## Utilisation

1. **Photos à convertir** : « Ajouter des photos… » (une seule ou
   plusieurs, avec Ctrl ou Maj dans la fenêtre de choix) et/ou « Ajouter un
   dossier… » (avec ou sans ses sous-dossiers). Vous pouvez combiner les
   deux, et retirer des photos de la liste (touche Suppr). Le tableau
   indique pour chaque photo ses dimensions et son poids, ainsi que le poids
   total (et celui des photos sélectionnées).
2. **Réglages** : cochez **« Réglages Focal Club (site du club) »** pour
   préparer des photos destinées au site : format WebP, 1920 px, 500 Ko,
   netteté écran normale et métadonnées conservées sont alors imposés (ces
   réglages sont grisés tant que la case est cochée ; la qualité et la
   position GPS restent au choix). Sans cette case, tout est réglable :
   format (JPEG ou WebP), taille, poids maximum, qualité (90 conseillé en
   JPEG, 85 en WebP), netteté, métadonnées. Sur un très petit écran, cette
   colonne défile avec la molette.
3. **Nom des photos** : garder le nom d'origine, ou renommer avec un
   numéro — début du nom (ex. `Sortie_Croisic_`), premier numéro, nombre de
   chiffres (3 → `001`), et ordre de numérotation (nom de fichier, date de
   prise de vue, ou ordre de la liste). Un exemple s'affiche en direct.
4. **Dossier de destination** (en bas de la fenêtre) : un dossier « Photos
   converties » à côté de vos photos est proposé ; le bouton « Choisir le
   dossier de destination… » permet d'en choisir un autre. Le chemin
   complet du dossier s'affiche à côté, en entier.
5. Cliquez sur **« Convertir les photos »**. Chaque photo s'affiche avec une
   coche verte (✓), ses dimensions et son poids final (et la qualité
   utilisée si elle a dû être baissée), ou une croix rouge (✗) et la raison
   en cas d'échec — les autres photos continuent. Le bouton « Arrêter »
   interrompt le lot.

Vos réglages sont mémorisés d'une fois sur l'autre.

À savoir : le format WebP n'a pas de notion de « résolution en ppp » ; la
valeur 72 ppp y est seulement inscrite dans les métadonnées quand vous les
conservez. Pour un écran, cela ne change rien : seule la taille en pixels
compte.
