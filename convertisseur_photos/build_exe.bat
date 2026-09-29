@echo off
REM Fabrique Convertisseur Photos a partir de convertisseur_photos.py, a
REM lancer sur un PC Windows ou Python est deja installe. Le resultat n'a
REM ensuite plus besoin de Python.
REM
REM Version allegee :
REM  - fabrication dans un environnement Python dedie (.env_fabrication),
REM    qui ne contient QUE les modules utiles : rien d'autre installe sur le
REM    PC ne vient alourdir le logiciel ;
REM  - modules inutiles exclus (HEIC, AVIF, polices, reseau, tests...) ;
REM  - logiciel fabrique sous forme de DOSSIER plutot que d'un seul gros
REM    fichier : il s'ouvre en une ou deux secondes, au lieu d'etre
REM    decompresse en entier a chaque lancement ;
REM  - raccourci cree sur le Bureau, et une archive .zip pour le partager.

setlocal
cd /d "%~dp0"

echo [1/5] Preparation d'un environnement Python dedie (une seule fois)...
if not exist ".env_fabrication\Scripts\python.exe" python -m venv .env_fabrication
if errorlevel 1 goto erreur
set PY=.env_fabrication\Scripts\python.exe
"%PY%" -m pip install --quiet --upgrade pip
"%PY%" -m pip install --quiet --upgrade pyinstaller pillow rawpy exifread
if errorlevel 1 goto erreur

echo.
echo [2/5] Fabrication de l'icone...
"%PY%" -c "import base64,io,convertisseur_photos as c;from PIL import Image;Image.open(io.BytesIO(base64.b64decode(c.ICONE_FENETRE_BASE64))).save('icone.ico',sizes=[(16,16),(32,32),(48,48),(64,64),(128,128)])"
if errorlevel 1 goto erreur

echo.
echo [3/5] Fabrication du logiciel (une a deux minutes)...
if exist "dist\ConvertisseurPhotos.exe" del /q "dist\ConvertisseurPhotos.exe"
"%PY%" -m PyInstaller --noconfirm --clean --onedir --windowed --name ConvertisseurPhotos --icon icone.ico ^
  --collect-submodules exifread ^
  --exclude-module pillow_heif --exclude-module PIL._avif --exclude-module PIL.AvifImagePlugin ^
  --exclude-module PIL._imagingft ^
  --exclude-module ssl --exclude-module _ssl --exclude-module _hashlib ^
  --exclude-module unittest --exclude-module pydoc --exclude-module doctest ^
  --exclude-module xmlrpc --exclude-module sqlite3 --exclude-module _sqlite3 --exclude-module lib2to3 ^
  --exclude-module numpy.f2py --exclude-module numpy.testing ^
  convertisseur_photos.py
if errorlevel 1 goto erreur

echo.
echo [4/5] Creation du raccourci "Convertisseur Photos" sur le Bureau...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$d=[Environment]::GetFolderPath('Desktop'); $s=(New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $d 'Convertisseur Photos.lnk')); $s.TargetPath='%CD%\dist\ConvertisseurPhotos\ConvertisseurPhotos.exe'; $s.WorkingDirectory='%CD%\dist\ConvertisseurPhotos'; $s.IconLocation='%CD%\dist\ConvertisseurPhotos\ConvertisseurPhotos.exe,0'; $s.Save()"

echo.
echo [5/5] Archive pour partager le logiciel...
powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path 'dist\ConvertisseurPhotos' -DestinationPath 'dist\ConvertisseurPhotos.zip' -Force"

echo.
echo Termine !
echo  - Le logiciel est dans le dossier : dist\ConvertisseurPhotos
echo    (ne pas separer ConvertisseurPhotos.exe du reste de ce dossier)
echo  - Un raccourci "Convertisseur Photos" a ete ajoute sur votre Bureau.
echo  - Pour le donner a quelqu'un : dist\ConvertisseurPhotos.zip
echo    (a decompresser, puis double-cliquer sur ConvertisseurPhotos.exe).
pause
exit /b 0

:erreur
echo.
echo Une erreur est survenue (voir le message ci-dessus).
pause
exit /b 1
