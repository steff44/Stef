@echo off
REM Fabrique ConvertisseurPhotos.exe a partir de convertisseur_photos.py, a
REM lancer une seule fois sur un PC Windows ou Python est deja installe.
REM Le .exe genere n'a ensuite plus besoin de Python : il peut etre copie
REM sur n'importe quel autre PC Windows.

echo Installation de PyInstaller et des modules photo (si necessaire)...
python -m pip install --upgrade pyinstaller pillow rawpy exifread pillow-heif
if errorlevel 1 goto erreur

echo.
echo Fabrication de ConvertisseurPhotos.exe...
REM Meme icone que Convertisseur PDF (dossier voisin).
python -m PyInstaller --onefile --windowed --name ConvertisseurPhotos --icon ..\convertisseur_pdf\icone.ico ^
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
echo Une erreur est survenue. Verifiez que Python est bien installe
echo et accessible (commande "python" dans une invite de commandes).
pause
exit /b 1
