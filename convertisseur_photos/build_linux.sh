#!/usr/bin/env bash
# Fabrique Convertisseur Photos pour GNU/Linux a partir de
# convertisseur_photos.py (equivalent de build_exe.bat pour Windows).
#
# A lancer une fois, dans un terminal, depuis ce dossier :
#     bash build_linux.sh
#
# Il faut Python 3 avec Tkinter et venv. Sur Debian/Ubuntu/Linux Mint :
#     sudo apt install python3 python3-venv python3-tk
# Sur Fedora :            sudo dnf install python3 python3-tkinter
# Sur Arch/Manjaro :      sudo pacman -S python tk
#
# Resultat :
#  - dist/ConvertisseurPhotos/ : le logiciel (ne pas separer le programme
#    ConvertisseurPhotos du reste de ce dossier) ;
#  - une entree « Convertisseur Photos » dans le menu des applications (et
#    sur le Bureau s'il existe) ;
#  - dist/ConvertisseurPhotos-linux.tar.gz : pour le donner a quelqu'un, qui
#    n'aura pas besoin de Python (decompresser, puis lancer installer.sh).

set -e
cd "$(dirname "$(readlink -f "$0")")"

PYTHON=${PYTHON:-python3}

echo "[1/5] Verifications..."
if ! command -v "$PYTHON" >/dev/null 2>&1; then
    echo "Python 3 est introuvable. Installez-le (voir l'en-tete de ce script)."
    exit 1
fi
if ! "$PYTHON" -c "import tkinter" >/dev/null 2>&1; then
    echo "Tkinter est absent. Sur Debian/Ubuntu/Mint : sudo apt install python3-tk"
    exit 1
fi

echo "[2/5] Preparation d'un environnement Python dedie (une seule fois)..."
if [ ! -x .env_fabrication_linux/bin/python ]; then
    if ! "$PYTHON" -m venv .env_fabrication_linux; then
        echo "Le module venv est absent. Sur Debian/Ubuntu/Mint : sudo apt install python3-venv"
        exit 1
    fi
fi
PY=.env_fabrication_linux/bin/python
"$PY" -m pip install --quiet --upgrade pip
"$PY" -m pip install --quiet --upgrade pyinstaller pillow rawpy exifread

echo "[3/5] Fabrication de l'icone..."
"$PY" -c "import base64,io,convertisseur_photos as c;from PIL import Image;Image.open(io.BytesIO(base64.b64decode(c.ICONE_FENETRE_BASE64))).convert('RGBA').resize((128,128)).save('icone.png')"

echo "[4/5] Fabrication du logiciel (une a deux minutes)..."
"$PY" -m PyInstaller --noconfirm --clean --onedir --windowed --name ConvertisseurPhotos \
  --collect-submodules exifread \
  --exclude-module pillow_heif --exclude-module PIL._avif --exclude-module PIL.AvifImagePlugin \
  --exclude-module PIL._imagingft \
  --exclude-module unittest --exclude-module pydoc --exclude-module doctest \
  --exclude-module xmlrpc --exclude-module sqlite3 --exclude-module _sqlite3 --exclude-module lib2to3 \
  --exclude-module numpy.f2py --exclude-module numpy.testing \
  convertisseur_photos.py

cp icone.png dist/ConvertisseurPhotos/icone.png

# Petit installateur livre avec le logiciel : il cree l'entree du menu des
# applications (et l'icone du Bureau) en pointant vers l'endroit ou le dossier
# se trouve, quel qu'il soit.
cat > dist/ConvertisseurPhotos/installer.sh <<'INSTALL'
#!/usr/bin/env bash
# Ajoute « Convertisseur Photos » au menu des applications (et au Bureau).
# A relancer si vous deplacez ce dossier.
set -e
ICI="$(dirname "$(readlink -f "$0")")"
chmod +x "$ICI/ConvertisseurPhotos"
FICHIER="[Desktop Entry]
Type=Application
Name=Convertisseur Photos
Comment=Convertir des photos (RAW, JPEG...) en JPEG ou WebP pour l'ecran
Exec=\"$ICI/ConvertisseurPhotos\"
Path=$ICI
Icon=$ICI/icone.png
Terminal=false
Categories=Graphics;Photography;"
mkdir -p "$HOME/.local/share/applications"
printf '%s\n' "$FICHIER" > "$HOME/.local/share/applications/convertisseur-photos.desktop"
chmod +x "$HOME/.local/share/applications/convertisseur-photos.desktop"
BUREAU="$(xdg-user-dir DESKTOP 2>/dev/null || echo "$HOME/Desktop")"
if [ -d "$BUREAU" ]; then
    printf '%s\n' "$FICHIER" > "$BUREAU/convertisseur-photos.desktop"
    chmod +x "$BUREAU/convertisseur-photos.desktop"
    gio set "$BUREAU/convertisseur-photos.desktop" metadata::trusted true 2>/dev/null || true
fi
echo "Convertisseur Photos a ete ajoute au menu des applications."
INSTALL
chmod +x dist/ConvertisseurPhotos/installer.sh dist/ConvertisseurPhotos/ConvertisseurPhotos

echo "[5/5] Raccourcis et archive pour partager le logiciel..."
dist/ConvertisseurPhotos/installer.sh
tar -czf dist/ConvertisseurPhotos-linux.tar.gz -C dist ConvertisseurPhotos

echo
echo "Termine !"
echo " - Le logiciel est dans le dossier : dist/ConvertisseurPhotos"
echo " - « Convertisseur Photos » a ete ajoute au menu des applications."
echo " - Pour le donner a quelqu'un : dist/ConvertisseurPhotos-linux.tar.gz"
echo "   (a decompresser, puis lancer installer.sh dans le dossier obtenu)."
