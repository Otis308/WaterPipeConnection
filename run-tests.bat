@echo off
chcp 65001 >nul
title TEST RUNNER - GAME NOI ONG NUOC HUIT
color 0B

echo ============================================================================
echo   DANG CHAY BO KIEM THU TU DONG (23 ACCEPTANCE TESTS) TRONG DOCKER...
echo ============================================================================
echo.

docker exec -i huit_pipegame_app php tests/run_tests.php

if errorlevel 1 (
    color 0C
    echo [THAT BAI] Co ca kiem thu khong dat.
) else (
    color 0A
    echo [THANH CONG] Toan bo 23 ca kiem thu deu PASS!
)

echo.
pause
