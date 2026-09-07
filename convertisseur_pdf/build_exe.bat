@echo off
REM Fabrique ConvertisseurPDF.exe a partir de convertisseur_pdf.py, a lancer
REM une seule fois sur un PC Windows ou Python est deja installe.
REM Le .exe genere n'a ensuite plus besoin de Python : il peut etre copie
REM sur n'importe quel autre PC Windows.

echo Installation de PyInstaller (si necessaire)...
python -m pip install --upgrade pyinstaller
if errorlevel 1 goto erreur

echo.
echo Installation de pywin32 (pour utiliser Microsoft Word, si installe)...
python -m pip install --upgrade pywin32
if errorlevel 1 goto erreur

echo.
echo Fabrication de ConvertisseurPDF.exe...
python -m PyInstaller --onefile --windowed --name ConvertisseurPDF --icon icone.ico convertisseur_pdf.py
if errorlevel 1 goto erreur

echo.
echo Termine ! Le fichier se trouve dans le dossier "dist" :
echo   dist\ConvertisseurPDF.exe
echo Vous pouvez le copier ou l'envoyer ou vous voulez.
pause
exit /b 0

:erreur
echo.
echo Une erreur est survenue. Verifiez que Python est bien installe
echo et accessible (commande "python" dans une invite de commandes).
pause
exit /b 1
