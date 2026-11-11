# Hardware & Security Network Management (HSNM)

[![Version](https://img.shields.io/badge/version-1.15.5-blue.svg)](modules/changelog/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-336791.svg?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v3-38B2AC.svg?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Status](https://img.shields.io/badge/Status-Production%20Ready-emerald.svg)]()

**HSNM** is an enterprise healthcare IT infrastructure, network operations, and asset lifecycle platform engineered to unify hardware inventory, satellite branch facilities, IPv4 subnet allocation, network diagnostics, software licensing, and preventive maintenance audits across hospital environments.

---

## 🚀 Quick Navigation

### Documentation & Guides
- 📖 [Technical Architecture & System Manual (SYSTEM_DOCUMENTATION.md)](SYSTEM_DOCUMENTATION.md)
- 📘 [Operations & Standard Operating Procedures (USER_MANUAL.md)](USER_MANUAL.md)
- 🧭 [Developer & Architecture Walkthrough (System Walkthrough Guide.md)](walkthrough.md)
- 🔍 [System Diagnostics & Bug Check Report (bug_check_report.md)](bug_check_report.md)

### Workstation & Satellite Inventories
- 🏥 **Central Computer Inventory**: `http://ihoms/HSNM/modules/computers/`
- 🛰️ **Satellite Computer Facilities**:
  - **JASMC-ST** (Sto. Tomas): `http://ihoms/HSNM/modules/jasmc_st/`
  - **JASMC-VT** (Victoria Tarlac): `http://ihoms/HSNM/modules/jasmc_vt/`
  - **DOH-JBL-CMC** (Children's Medical Center): `http://ihoms/HSNM/modules/doh_jbl_cmc/`

### IP Address Management & Satellite Subnets
- 🌐 **Hospital Main IP Space**: `http://ihoms/HSNM/modules/ips/`
- 📡 **Satellite IP Inventories**:
  - **JASMC-ST IPs**: `http://ihoms/HSNM/modules/ips_jasmc_st/`
  - **JASMC-VT IPs**: `http://ihoms/HSNM/modules/ips_jasmc_vt/`
  - **DOH-JBL-CMC IPs**: `http://ihoms/HSNM/modules/ips_doh_jbl_cmc/`

### Property, Maintenance & Network Hardware
- 📦 **Asset Registry & Valuation**: `http://ihoms/HSNM/modules/assets/`
- 🔧 **Preventive Maintenance (PM) Suite**: `http://ihoms/HSNM/modules/maintenance/`
- 🔀 **Reconciliation (Compare & Sync)**: `http://ihoms/HSNM/modules/reconciliation/`
- 🖧 **Network Routers**: `http://ihoms/HSNM/modules/routers/`
- 🎛️ **Network Switches**: `http://ihoms/HSNM/modules/switches/`
- 🛡️ **Endpoint & Firewall Compliance**: `http://ihoms/HSNM/modules/firewall/`
- 🔑 **Microsoft Office Licenses**: `http://ihoms/HSNM/modules/office/`

### Auxiliaries & System Administration
- 🖨️ **Printer Inventory**: `http://ihoms/HSNM/modules/printers/`
- 📞 **PABX Hospital Telephone Directory**: `http://ihoms/HSNM/modules/pabx/`
- 📺 **Queueing TV Displays**: `http://ihoms/HSNM/modules/queueing_tv/`
- 📄 **Inventory Custodian Slips (ICS)**: `http://ihoms/HSNM/modules/ics/`
- 🔗 **iHOMS Quick Links**: `http://ihoms/HSNM/modules/ihoms_links/`
- 📊 **Tabular Reports Engine**: `http://ihoms/HSNM/modules/reports/`
- 📜 **Audit Logs**: `http://ihoms/HSNM/modules/logs/`
- 📋 **System Changelog**: `http://ihoms/HSNM/modules/changelog/`
- 🔄 **Cloud Data Sync (Neon)**: `http://ihoms/HSNM/sync_neon.php`
- ⚙️ **User & Access Settings**: `http://ihoms/HSNM/modules/settings/`

---

## 💻 Tech Stack & Architecture

- **Backend Language**: PHP 8.1+ with native `PDO_PGSQL`, `ZipArchive`, `OpenSSL`, and `mbstring`.
- **Primary Database**: PostgreSQL 15+ (`unified_network_inventory`) hosted locally via XAMPP.
- **Cloud Disaster Recovery**: Neon Serverless PostgreSQL with SSL pooling and bi-directional deletion reconciliation.
- **Frontend & Styling**: Tailwind CSS (Utility-first with dark/light theme persistence), Vanilla JS (AJAX live search, modal management).
- **Offline Assets**: Self-hosted WOFF2 fonts (`Inter` and `Material Symbols Outlined`) and local Tailwind JavaScript runtime for complete air-gapped intranet operation.
- **Local DNS & Networking**: Acrylic DNS Proxy broadcasting `ihoms` across the hospital `/21` subnet (`192.163.8.0/21`).

---

## 🌟 Key Features

1. **Central & Satellite Workstation Management**:
   - Lifecycle tracking: `Working`, `Maintenance`, `Transferred`, `Condemned`.
   - **Automated Satellite Transfers**: Marking a machine as `Transferred` pops up the destination selector (`JASMC-ST`, `JASMC-VT`, `DOH-JBL-CMC`) and replicates the record with preserved hardware specs and source ID linkage.
   - Quick facility switcher tab bar on top of all Computer and IP modules.

2. **Capital Asset Registry & Real-Time Sync**:
   - Property management tracking PTR numbers, financial acquisition costs (PHP), item codes, and procurement sources.
   - **One-Click Workstation Sync**: Web runner (`run_computers_to_assets_sync.php`) upserts workstations into the asset registry and seeds historical PMS audit logs.
   - Real-time `syncComputerToAsset()` triggers keep physical assets synchronized whenever computers are added or updated.

3. **Digital Preventive Maintenance (PM) Suite**:
   - Replaces paper logs with an official 22-point hardware and software inspection checklist.
   - Real-time dynamic scoring engine (0% to 100%) highlighting optimal health or urgent actions.
   - Generates official printable PM certificates and audit forms (`modules/maintenance/print.php`).

4. **Multi-Facility IP Address Management & Non-ICMP Discovery**:
   - Comprehensive subnet tracking across headquarters and all 3 satellite facilities.
   - Single and batch ICMP ping diagnostics with automatic NetBIOS hostname resolution.
   - **Non-ICMP Discovery**: Polling via ARP, NBTSTAT, and GETMAC detects live machines even when Windows Firewall drops echo packets.

5. **Compare & Sync (Reconciliation Engine)**:
   - Automated bi-directional cross-referencing between Computer Inventory and IP Address tables.
   - Detects data mismatches (differing IPs/MACs for the same control number) and identity conflicts (duplicate IP/MAC claimed by multiple units).

6. **Isolated Metric Resilience**:
   - Executive dashboard uses isolated try/catch query evaluators (`$safeCount`, `$safeSum`), preventing any single table drift from interrupting overview KPI ribbons.

7. **Bi-Directional Cloud Data Synchronization 2.0**:
   - Web-based sync console (`sync_neon.php`) connecting local PostgreSQL to Neon Cloud PostgreSQL.
   - Handles schema detection, column intersection, unique constraint resolution, and deletion reconciliation using a max ID heuristic across 22 system tables.

---

## 🛠️ Installation & Setup

### 1. Place Project in XAMPP Root
```
c:\xampp\htdocs\HSNM
```

### 2. Configure Apache Environment Variable
In `C:\xampp\apache\conf\httpd.conf`, inject the server's LAN IP address:
```apache
SetEnv LOCAL_HOST_IP 192.163.10.11
```

### 3. Setup Apache VirtualHost
In `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs"
    ServerName ihoms
    ServerAlias 192.163.10.11
    <Directory "C:/xampp/htdocs">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Configure Acrylic DNS Proxy
1. In `C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicHosts.txt`:
   ```
   192.163.10.11 ihoms
   ```
2. In `C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicConfiguration.ini`, allow hospital IP ranges (`192.163.8.*` through `192.163.15.*`).
3. Restart Acrylic DNS Proxy service:
   ```powershell
   Restart-Service -Name "AcrylicDNSProxySvc"
   ```

### 5. Execute Database Migrations
Open your browser and navigate to:
```
http://localhost/HSNM/run_status_migration.php
```
Or execute the database SQL migrations in `c:\xampp\htdocs\HSNM\database\` via `psql`:
```bash
psql -U postgres -d unified_network_inventory -f database/add_status_and_satellite_tables.sql
psql -U postgres -d unified_network_inventory -f database/add_assets_maintenance_tables.sql
psql -U postgres -d unified_network_inventory -f database/add_satellite_ip_tables.sql
psql -U postgres -d unified_network_inventory -f database/add_maintenance_status_option.sql
```

### 6. Populate Asset Registry & PMS Audits
Navigate to:
```
http://localhost/HSNM/run_computers_to_assets_sync.php
```
Click **Execute One-Click Sync** to synchronize all workstation records to the Asset Registry and seed historical PM inspection logs.

---

## 🔒 Default Access & Roles

| Role | Access Level | Permitted Actions |
|---|---|---|
| **Admin** | Full System Access | CRUD on all modules, Settings, User Management, Cloud Data Sync (`sync_neon.php`), Audit Logs, Migration Runners. |
| **Editor** | Operational CRUD | Create, edit, and export across permitted modules; view Audit Logs; no access to Settings or DB Sync. |
| **Viewer** | Read-Only Access | Search, filter, view details, and export reports in permitted modules; cannot make edits or view audit trails. |

*Per-user module permissions are dynamically customizable via JSONB checkboxes in **Sidebar → Settings**.*

---

## 📁 Repository Directory Structure

```
c:/xampp/htdocs/HSNM/
├── config.php                     # Central configuration, DB connection, RBAC, CSRF, & audit helpers
├── index.php                      # Executive dashboard with isolated metric queries
├── login.php / logout.php         # Authentication gate with session timeout & no-cache headers
├── sync_neon.php                  # Multi-table cloud database synchronization engine 2.0
├── db_compare.php                 # Schema & row-count comparator (Local vs. Cloud)
├── run_status_migration.php       # Web runner for database schema updates
├── run_computers_to_assets_sync.php # One-click workstation to asset registry synchronization runner
├── README.md                      # System overview and quick-start guide (this document)
├── SYSTEM_DOCUMENTATION.md        # Detailed technical architecture and system manual
├── USER_MANUAL.md                 # User operations and standard operating procedures (SOP)
├── walkthrough.md                 # In-depth architectural and developer walkthrough guide
├── System Walkthrough Guide.md    # Canonical developer guide mirror
├── assets/                        # Offline styling, local fonts, and icons
│   ├── css/fonts.css              # Self-hosted typography stylesheets
│   ├── fonts/                     # WOFF2 binaries (Inter, Material Symbols)
│   └── js/tailwind.js             # Standalone Tailwind CSS JavaScript bundle
├── database/                      # SQL schema definitions, migrations, and backups
├── includes/                      # Global UI components & shared partials
│   ├── header.php / footer.php    # Page layout headers, meta tags, and scripts
│   ├── sidebar.php                # Left navigation tree with RBAC visibility controls
│   ├── satellite_computer_tabs.php # Switcher tabs for computer inventories
│   └── satellite_ip_tabs.php      # Switcher tabs for IP inventories
└── modules/                       # Functional business modules
    ├── computers/                 # Central Workstations & transfer dispatch
    ├── jasmc_st/                  # JASMC-ST Satellite Computer Inventory
    ├── jasmc_vt/                  # JASMC-VT Satellite Computer Inventory
    ├── doh_jbl_cmc/               # DOH-JBL-CMC Satellite Computer Inventory
    ├── ips/                       # Hospital IPv4 space & diagnostic polling
    ├── ips_jasmc_st/              # JASMC-ST Satellite IP Inventory
    ├── ips_jasmc_vt/              # JASMC-VT Satellite IP Inventory
    ├── ips_doh_jbl_cmc/           # DOH-JBL-CMC Satellite IP Inventory
    ├── assets/                    # Asset Registry, valuation, & print forms
    ├── maintenance/               # 22-point PM audit checklist & certificates
    ├── routers/                   # Network Routers & gateway monitoring
    ├── switches/                  # Network Switchgear & port allocation
    ├── firewall/                  # Endpoint security & firewall compliance
    ├── office/                    # Microsoft Office License management
    ├── reconciliation/            # Compare & Sync: IP-to-Computer drift detection
    ├── printers/                  # Standalone Printer Inventory
    ├── pabx/                      # Hospital Telephone Directory
    ├── queueing_tv/               # OPD / Waiting Area Queue Display TV management
    ├── ics/                       # Inventory Custodian Slip (ICS) tracking
    ├── ihoms_links/               # Hospital Operations Management portal links
    ├── reports/                   # Cross-module tabular reporting engine
    ├── logs/                      # Centralized audit trails & diff logging
    ├── changelog/                 # File checksum differential scanner & release history
    └── settings/                  # User accounts, passwords, & module permission matrices
```

---

## 📄 License & Attribution

Developed and maintained by **IHOMS-MIKE** for hospital IT infrastructure operations. Internal enterprise use only.
