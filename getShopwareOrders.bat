@ECHO OFF
cd C:\eNVenta-ERP\BMECat\shopware\debug
:loop
ECHO Executing script...
php\php.exe index.php
ECHO.
ECHO Waiting 30 Minutes...
TIMEOUT 1800
ECHO.
GOTO :loop
