@echo off
chcp 65001 >nul
title AUTO RUN DOCKER - WATER PIPE GAME LARAVEL 12 HUIT
color 0B

cd /d "%~dp0"

echo ============================================================================
echo   KHOI CHAY TU DONG DO AN GAME NOI ONG NUOC TRONG DOCKER - HUIT LARAVEL 12
echo ============================================================================
echo.

echo [BUOC 1/6] Kiem tra trang thai Docker Desktop...
docker info >nul 2>&1
if not errorlevel 1 goto DOCKER_READY

echo   - Docker Desktop chua bat. Dang tu dong khoi dong Docker Desktop...
if exist "C:\Program Files\Docker\Docker\Docker Desktop.exe" start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe"

:WAIT_DOCKER
echo   - Dang doi Docker Engine khoi dong xong - vui long doi 5 giay...
timeout /t 5 /nobreak >nul
docker info >nul 2>&1
if errorlevel 1 goto WAIT_DOCKER

:DOCKER_READY
echo   - [OK] Docker Engine da san sang!
echo.

echo [BUOC 2/6] Cap nhat file cau hinh moi truong .env cho Docker...
if exist ".env.docker" copy /Y ".env.docker" ".env" >nul
echo   - [OK] Da nap cau hinh .env!
echo.

echo [BUOC 3/6] Tai tung Docker Image co che tu dong thu lai khi mang nghen...

set /a TRY_PHP=0
:PULL_PHP
set /a TRY_PHP+=1
echo   - Dang tai Image php:8.3-apache - Lan thu %TRY_PHP%...
docker pull php:8.3-apache
if not errorlevel 1 goto PULL_MYSQL_START
if %TRY_PHP% LSS 5 timeout /t 3 >nul & goto PULL_PHP
goto ERROR_NETWORK

:PULL_MYSQL_START
set /a TRY_MYSQL=0
:PULL_MYSQL
set /a TRY_MYSQL+=1
echo   - Dang tai Image mysql:8.0 - Lan thu %TRY_MYSQL%...
docker pull mysql:8.0
if not errorlevel 1 goto PULL_PMA_START
if %TRY_MYSQL% LSS 5 timeout /t 3 >nul & goto PULL_MYSQL
goto ERROR_NETWORK

:PULL_PMA_START
set /a TRY_PMA=0
:PULL_PMA
set /a TRY_PMA+=1
echo   - Dang tai Image phpmyadmin/phpmyadmin:latest - Lan thu %TRY_PMA%...
docker pull phpmyadmin/phpmyadmin:latest
if not errorlevel 1 goto BUILD_CONTAINERS
if %TRY_PMA% LSS 5 timeout /t 3 >nul & goto PULL_PMA
goto ERROR_NETWORK

:BUILD_CONTAINERS
echo.
echo [BUOC 4/6] Dang build va khoi chay toan bo Container trong Docker...
docker compose down --remove-orphans >nul 2>&1
docker compose up -d --build
if errorlevel 1 goto ERROR_BUILD

echo.
echo [BUOC 5/6] Dang doi MySQL Container khoi tao va nap CSDL mau...
set /a WAIT_DB=0
:WAIT_MYSQL_LOOP
set /a WAIT_DB+=1
timeout /t 3 /nobreak >nul
docker exec huit_pipegame_mysql mysqladmin ping -h127.0.0.1 -uroot -prootpassword --silent >nul 2>&1
if not errorlevel 1 goto IMPORT_SQL
if %WAIT_DB% LSS 15 echo   - Dang doi MySQL khoi dong... [%WAIT_DB%/15] & goto WAIT_MYSQL_LOOP

:IMPORT_SQL
docker exec -i huit_pipegame_mysql mysql -uroot -prootpassword huit_water_pipe_db < "database\sql\huit_water_pipe_db.sql" >nul 2>&1
echo   - [OK] Da khoi tao 10 bang MySQL va Seed du lieu mau thanh cong!
echo.

echo [BUOC 6/6] Dang mo trinh duyet truy cap du an...
start http://localhost:8000
start http://localhost:8000/portal
start http://localhost:8081

color 0A
echo ============================================================================
echo   HOAN TAT! TOAN BO DU AN DANG CHAY TRONG DOCKER:
echo   1. Giao dien Game goc:         http://localhost:8000
echo   2. Cong Quan tri MVC / RBAC:   http://localhost:8000/portal
echo   3. Quan ly CRUD Man choi:      http://localhost:8000/levels
echo   4. Phong Lab PDO SQL nang cao: http://localhost:8000/pdo-lab
echo   5. Phong Lab OOP PHP:          http://localhost:8000/oop-lab
echo   6. Quan ly CSDL phpMyAdmin:    http://localhost:8081 [root / rootpassword]
echo.
echo   Tai khoan Demo [Mat khau chung: password123]:
echo   - Admin:  admin
echo   - Staff:  staff
echo   - Player: player1
echo ============================================================================
pause
exit /b 0

:ERROR_NETWORK
color 0C
echo.
echo ============================================================================
echo   [LOI KET NOI MANG DOCKER HUB]
echo   May tinh khong the phan giai ten mien registry-1.docker.io.
echo   Cach khac phuc:
echo   1. Mo Docker Desktop -^> Settings [Hinh banh rang] -^> Docker Engine
echo   2. Them dong cau hinh DNS: "dns": ["8.8.8.8", "1.1.1.1"]
echo   3. Nhan Apply ^& Restart roi chay lai file setup.bat nay.
echo ============================================================================
pause
exit /b 1

:ERROR_BUILD
color 0C
echo.
echo [LOI] Qua trinh docker compose up -d --build gap su co. Vui long xem thong bao loi phia tren!
pause
exit /b 1