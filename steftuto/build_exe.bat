@echo off
REM Fabrique Steftuto.exe a partir de steftuto.py, a lancer une seule fois
REM sur un PC Windows ou Python est deja installe.
REM Le .exe genere n'a ensuite plus besoin de Python : il peut etre copie
REM sur n'importe quel autre PC Windows.

echo Installation de PyInstaller (si necessaire)...
python -m pip install --upgrade pyinstaller
if errorlevel 1 goto erreur

echo.
echo Fabrication de Steftuto.exe...
python -m PyInstaller --onefile --windowed --name Steftuto --icon icone.ico steftuto.py
if errorlevel 1 goto erreur

echo.
echo Termine ! Le fichier se trouve dans le dossier "dist" :
echo   dist\Steftuto.exe
echo Vous pouvez le copier ou l'envoyer ou vous voulez.
pause
exit /b 0

:erreur
echo.
echo Une erreur est survenue. Verifiez que Python est bien installe
echo et accessible (commande "python" dans une invite de commandes).
pause
exit /b 1
