@echo off
REM Fabrique ConvertisseurPhotos.exe a partir de convertisseur_photos.py, a
REM lancer une seule fois sur un PC Windows ou Python est deja installe.
REM Le .exe genere n'a ensuite plus besoin de Python : il peut etre copie
REM sur n'importe quel autre PC Windows.

REM Se place dans le dossier de ce fichier, meme lance depuis ailleurs.
cd /d "%~dp0"

echo Installation de PyInstaller et des modules photo (si necessaire)...
python -m pip install --upgrade pyinstaller pillow rawpy exifread pillow-heif
if errorlevel 1 goto erreur

echo.
echo Fabrication de l'icone (icone.ico) a partir de celle du logiciel...
python -c "import base64,io,convertisseur_photos as c;from PIL import Image;Image.open(io.BytesIO(base64.b64decode(c.ICONE_FENETRE_BASE64))).save('icone.ico',sizes=[(16,16),(32,32),(48,48),(64,64),(128,128)])"
if errorlevel 1 goto erreur

echo.
echo Fabrication de ConvertisseurPhotos.exe...
python -m PyInstaller --noconfirm --onefile --windowed --name ConvertisseurPhotos --icon icone.ico ^
  --collect-all rawpy --collect-all pillow_heif --collect-submodules exifread ^
  convertisseur_photos.py
if errorlevel 1 goto erreur

echo.
echo Termine ! Le fichier se trouve dans le dossier "dist" :
echo   dist\ConvertisseurPhotos.exe
echo Vous pouvez le copier ou l'envoyer ou vous voulez.
pause
exit /b 0

:erreur
echo.
echo Une erreur est survenue (voir le message ci-dessus).
pause
exit /b 1
