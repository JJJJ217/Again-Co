@echo off
REM Simple test runner for Windows debugging

echo === Again^&Co Test Runner ===
php -v
echo Current Directory: %CD%
echo.

echo === Checking PHP Extensions ===
php -m | findstr "xml mbstring curl zip json"
echo.

echo === Validating Composer ===
composer validate
echo.

echo === Installing Dependencies ===
composer install --no-interaction
echo.

echo === Checking PHPUnit ===
vendor\bin\phpunit --version
echo.

echo === Running Smoke Test ===
vendor\bin\phpunit tests\SmokeTest.php
echo.

echo === Running All Tests ===
vendor\bin\phpunit --testdox
pause