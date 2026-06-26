@echo off
echo ========================================
echo Network Switch Inventory - Database Setup
echo ========================================
echo.

echo Running authentication schema...
mysql -u root -p -e "source schema_auth.sql"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: Failed to run authentication schema
    pause
    exit /b 1
)

echo.
echo ========================================
echo Database setup completed successfully!
echo ========================================
echo.
echo Default login credentials:
echo Username: admin
echo Password: password
echo.
pause
