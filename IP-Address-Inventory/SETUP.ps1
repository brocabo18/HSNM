# IP Inventory Management System - Quick Setup Guide

## Prerequisites Check
echo "============================================"
echo "IP Inventory Management System - Setup"
echo "============================================"
echo ""

# Check if XAMPP is installed
if (Test-Path "C:\xampp\mysql\bin\mysql.exe") {
    Write-Host "✓ XAMPP MySQL found" -ForegroundColor Green
} else {
    Write-Host "✗ XAMPP not found at C:\xampp\" -ForegroundColor Red
    Write-Host "  Please install XAMPP first: https://www.apachefriends.org/" -ForegroundColor Yellow
    exit
}

Write-Host ""
Write-Host "Setup Instructions:" -ForegroundColor Cyan
Write-Host "==================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Start XAMPP Control Panel" -ForegroundColor White
Write-Host "   - Start Apache" -ForegroundColor Gray
Write-Host "   - Start MySQL" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Import Database" -ForegroundColor White
Write-Host "   - Open: http://localhost/phpmyadmin" -ForegroundColor Gray
Write-Host "   - Click 'SQL' tab" -ForegroundColor Gray
Write-Host "   - Copy contents from: database/init_database.sql" -ForegroundColor Gray
Write-Host "   - Click 'Go'" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Access Application" -ForegroundColor White
Write-Host "   - URL: http://localhost/IP-Address-Inventory/" -ForegroundColor Cyan
Write-Host ""
Write-Host "4. Login Credentials" -ForegroundColor White
Write-Host "   - Username: admin" -ForegroundColor Yellow
Write-Host "   - Password: admin123" -ForegroundColor Yellow
Write-Host ""
Write-Host "⚠ IMPORTANT: Change the default password after first login!" -ForegroundColor Red
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Features Available:" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "✓ Login/Logout with audit logging" -ForegroundColor Green
Write-Host "✓ Dashboard with real-time statistics" -ForegroundColor Green
Write-Host "✓ Device Management (Add/Edit/Delete)" -ForegroundColor Green
Write-Host "✓ IP Scanner with CIDR support" -ForegroundColor Green
Write-Host "✓ Subnet Management" -ForegroundColor Green
Write-Host "✓ Comprehensive Reports with charts" -ForegroundColor Green
Write-Host "✓ Audit Logs with filtering" -ForegroundColor Green
Write-Host "✓ User Management (Admin only)" -ForegroundColor Green
Write-Host "✓ CSV Export functionality" -ForegroundColor Green
Write-Host ""
Write-Host "For detailed documentation, see README.md" -ForegroundColor Gray
Write-Host ""

# Offer to open browser
$response = Read-Host "Would you like to open phpMyAdmin now? (y/n)"
if ($response -eq 'y') {
    Start-Process "http://localhost/phpmyadmin"
    Write-Host "Opening phpMyAdmin..." -ForegroundColor Green
}

Write-Host ""
Write-Host "Setup guide complete! Good luck!" -ForegroundColor Green
