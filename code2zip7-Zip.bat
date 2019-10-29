@echo off

SET currentDir=%~dp0
SET sevenZIPDir=C:\Program Files (x86)\_Standard\7-Zip\

echo loeschen..
del "%currentDir%_ZIP\Modulkataloggenerator.zip"
echo geloescht!
echo.

echo neu packen...
"%sevenZIPDir%7z.exe" a "%currentDir%_ZIP\Modulkataloggenerator.zip" "%currentDir%Code\*"
echo neu gepackt und fertig!
