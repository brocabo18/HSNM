@echo off
set PGPASSWORD=fTYMTwPRmJIanIITTypnhLWbQQfZXxwQ
set PSQL="C:\Program Files\PostgreSQL\18\bin\psql.exe"
set HOST=reseau.proxy.rlwy.net
set PORT=18700
set USER=postgres
set DB=railway

echo Testing connection...
%PSQL% -h %HOST% -p %PORT% -U %USER% -d %DB% -c "SELECT COUNT(*) FROM users;"
echo.
echo Importing data...
%PSQL% -h %HOST% -p %PORT% -U %USER% -d %DB% -f "C:\xampp\htdocs\HSNM\database\schema_export.sql"
echo.
echo Done! Verifying...
%PSQL% -h %HOST% -p %PORT% -U %USER% -d %DB% -c "SELECT table_name, (SELECT COUNT(*) FROM information_schema.columns WHERE table_name=t.table_name) as cols FROM information_schema.tables t WHERE table_schema='public' ORDER BY table_name;"
