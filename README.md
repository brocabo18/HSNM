# Hardware & Security Network Management (HSNM)

[![Version](https://img.shields.io/badge/version-1.15.2-blue.svg)](modules/changelog/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-336791.svg?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v3-38B2AC.svg?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)

**HSNM** is an enterprise-grade healthcare IT infrastructure, network operations, and asset management platform designed to unify hardware inventories, satellite facilities, IP allocation, software licensing, and preventive maintenance audits across hospital environments.

---

## 🚀 Quick Navigation

- 📖 **Full System Manual & Technical Specs**: [SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md)
- 🏥 **Central Computer Inventory**: `http://ihoms/HSNM/modules/computers/`
- 🛰️ **Satellite Facilities**:
  - **JASMC-ST** (Sto. Tomas): `http://ihoms/HSNM/modules/jasmc_st/`
  - **JASMC-VT** (Victoria Tarlac): `http://ihoms/HSNM/modules/jasmc_vt/`
  - **DOH-JBL-CMC** (Children's Medical Center): `http://ihoms/HSNM/modules/doh_jbl_cmc/`
- 📦 **Asset Inventory**: `http://ihoms/HSNM/modules/assets/`
- 🔧 **Preventive Maintenance Suite**: `http://ihoms/HSNM/modules/maintenance/`
- 🌐 **IP Management & Diagnostics**: `http://ihoms/HSNM/modules/ips/`
- 🔄 **Reconciliation (Compare & Sync)**: `http://ihoms/HSNM/modules/reconciliation/`
- 📋 **System Changelog**: `http://ihoms/HSNM/modules/changelog/`

---

## 💻 Tech Stack

- **Runtime & Backend**: PHP 8.1+ (Native PDO PostgreSQL)
- **Database**: PostgreSQL 15+ (Local XAMPP & Cloud Neon Disaster Recovery)
- **Frontend**: Tailwind CSS (Dark/Light mode support), Vanilla JS, Material Symbols
- **Network / DNS**: Acrylic DNS Proxy broadcast on Port 53 (`ihoms` -> `192.163.10.11`)

---

## 🛠️ Quick Installation & Setup

1. **Clone / Place in XAMPP root**:
   ```
   c:\xampp\htdocs\HSNM
   ```
2. **Database Migration**:
   Navigate to `http://localhost/HSNM/run_status_migration.php` in your browser to execute database schema updates.
3. **Environment Variable Configuration**:
   Add to `C:\xampp\apache\conf\httpd.conf`:
   ```apache
   SetEnv LOCAL_HOST_IP 192.163.10.11
   ```
4. **Apache VirtualHost Setup**:
   Add to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
   ```apache
   <VirtualHost *:80>
       DocumentRoot "C:/xampp/htdocs"
       ServerName ihoms
       ServerAlias 192.163.10.11
   </VirtualHost>
   ```

For full setup procedures, database schemas, and revision history, refer to [SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md).
