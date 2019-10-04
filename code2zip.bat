@echo off

SET currentDir=%~dp0
SET winrarDir=C:\Program Files (x86)\_Standard\WinRAR\

echo loeschen..
del "%currentDir%_ZIP\Modulkataloggenerator.zip"
echo geloescht!
echo.

echo neu packen...
"%winrarDir%winrar.exe" a -r -o+ -ep -ep1 -ibck "%currentDir%_ZIP\Modulkataloggenerator.zip" "%currentDir%Code\"
echo neu gepackt und fertig!
rem pause