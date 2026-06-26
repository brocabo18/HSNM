# HSNM — System Walkthrough Guide

> **Hardware & Software Network Management** — a web-based IT asset and network management system built on PHP + PostgreSQL (XAMPP stack), styled with Tailwind CSS.

---

## Table of Contents

1. [System Architecture](#1-system-architecture)
2. [Technology Stack](#2-technology-stack)
3. [Directory Structure](#3-directory-structure)
4. [Authentication & Session Management](#4-authentication--session-management)
5. [Front-End Layout](#5-front-end-layout)
6. [Role-Based Access Control](#6-role-based-access-control)
7. [Modules Overview](#7-modules-overview)
8. [Database Schema](#8-database-schema)
9. [Back-End Patterns](#9-back-end-patterns)
10. [Security Features](#10-security-features)

---

## 1. System Architecture

```
Browser (Tailwind CSS + Vanilla JS)
        │
        ▼
XAMPP Apache (PHP 8.x)
        │
        ├── config.php          ← DB connection, session, CSRF, helpers
        ├── index.php           ← Dashboard
        ├── login.php / logout.php
        ├── includes/           ← Shared header, sidebar, footer
        └── modules/            ← One folder per feature module
                │
                ▼
        PostgreSQL (unified_network_inventory)
```

- **No framework** — pure PHP with PDO.
- All pages share a single `config.php` that bootstraps the DB connection, session, and helper functions.
- Each module is self-contained: one `index.php` handles its own GET/POST logic.

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Web server | Apache (XAMPP) |
| Backend language | PHP 8.x |
| Database | PostgreSQL |
| DB abstraction | PDO (prepared statements) |
| Frontend CSS | Tailwind CSS (CDN) |
| Icons | Google Material Symbols |
| JavaScript | Vanilla JS (no framework) |
| Session | PHP native sessions |
| Authentication | Password hashing via `password_hash()` / `password_verify()` |

---

## 3. Directory Structure

```
HSNM/
├── config.php              ← Central bootstrap (DB, session, CSRF, helpers)
├── index.php               ← Dashboard (overview + stats)
├── login.php               ← Login page + POST handler
├── logout.php              ← Session destroy + redirect
├── .htaccess               ← URL rewriting / access control
│
├── includes/
│   ├── header.php          ← HTML <head>, Tailwind, dark mode script
│   ├── sidebar.php         ← Navigation sidebar (role-aware)
│   └── footer.php          ← Closing HTML tags
│
├── modules/
│   ├── routers/            ← Router inventory
│   ├── switches/           ← Network switch inventory
│   ├── ips/                ← IP address management
│   ├── computers/          ← Computer inventory (main module)
│   ├── office/             ← MS Office license tracking
│   ├── reconciliation/     ← Cross-module data comparison (IPs vs Computers)
│   ├── ics/                ← ICS (Inventory Control Sheet) — printable forms
│   ├── pabx/               ← PABX telephone directory
│   ├── ihoms_links/        ← IS/iHOMS quick-access links
│   ├── printers/           ← Standalone printer inventory
│   ├── queueing_tv/        ← Hospital queue display management
│   ├── logs/               ← Audit log viewer (admin/editor only)
│   ├── settings/           ← User management (admin only)
│   └── changelog/          ← System changelog viewer
│
└── assets/                 ← Static assets (CSS, images)
```

---

## 4. Authentication & Session Management

### Login Flow (`login.php`)

1. User submits username + password via POST.
2. PHP queries `users` table: `SELECT * FROM users WHERE username = ?`
3. `password_verify($input, $hash)` validates the bcrypt hash.
4. On success, session stores:
   - `$_SESSION['user_id']`
   - `$_SESSION['username']`
   - `$_SESSION['role']` → `'admin'` | `'editor'` | `'viewer'`
   - `$_SESSION['module_access']` → JSON array of allowed module keys
5. Redirect to `/HSNM/`.

### Session Guard (`requireLogin()`)

Every module starts with:
```php
require_once '../../config.php';
requireLogin();
```

`requireLogin()` checks `$_SESSION['user_id']`; if absent, redirects to `/login`.

### Logout (`logout.php`)

```php
session_destroy();
header("Location: /HSNM/login");
```

---

## 5. Front-End Layout

### Page Structure

Every authenticated page follows this layout:

```
┌──────────────────────────────────────────────────────┐
│ SIDEBAR (fixed, 256px)      │  MAIN CONTENT (flex-1) │
│                             │  ┌──────────────────┐  │
│  Logo + App Name            │  │ Sticky Header     │  │
│  ─────────────────          │  │ (Title + Actions) │  │
│  Overview (Dashboard)       │  └──────────────────┘  │
│  Routers                    │  Stats Cards (3-grid)   │
│  Switches                   │  Control Bar:           │
│  IP Addresses               │   Row 1: Sort + Pag.   │
│  Computers                  │   Row 2: Search+Filter  │
│  MS Office                  │  Data Table             │
│  Compare & Sync             │  Bottom Pagination      │
│  ICS Inventory              │                         │
│  PABX Directory             │                         │
│  IS Links                   │                         │
│  Printers                   │                         │
│  Queueing TV                │                         │
│  ─────────────────          │                         │
│  Audit Logs                 │                         │
│  Settings (admin)           │                         │
│  Changelog                  │                         │
│  ─────────────────          │                         │
│  🌙 Dark/Light toggle       │                         │
│  👤 Username + Logout       │                         │
└──────────────────────────────────────────────────────┘
```

### Control Bar Pattern (all table modules)

```
Row 1 (right-aligned):  [Sort ▾]  [Page X of Y]  [‹]  [›]  [Show: 50 ▾]
Row 2 (left-aligned):   [🔍 Search...]  [Filter ▾]  [Filter ▾]  ...
```

Both rows use `flex-wrap` so they collapse neatly on smaller screens.

### Dark Mode

Toggled by JavaScript via `document.documentElement.classList.toggle('dark')`. State saved to `localStorage`. The sidebar has a toggle button at the bottom. Default preference is controlled by the `dark_mode` setting in the `settings` table.

### Modals

Add/Edit/Delete confirmations use custom `toggleModal(id)` JavaScript:
```js
function toggleModal(id) {
    document.getElementById(id).classList.toggle('hidden');
}
```

---

## 6. Role-Based Access Control

### Roles

| Role | Capabilities |
|---|---|
| `admin` | Full access — all modules, Settings, Audit Logs, user management |
| `editor` | CRUD on all inventory modules; can view Audit Logs; no Settings |
| `viewer` | Read-only access to permitted modules; no Audit Logs; no Settings |

### Module-Level Permissions

Each user has a `module_access` JSON column (e.g. `["computers","ips","pabx"]`). The sidebar and each module check:

```php
function canAccessModule($module) {
    $allowed = json_decode($_SESSION['module_access'], true);
    return in_array($module, $allowed);
}
```

If `module_access` is `null`, all modules are accessible (backward-compatible default).

---

## 7. Modules Overview

### 7.1 Overview / Dashboard (`index.php`)

Aggregates live stats from all tables:
- Total computers, routers, switches, IPs, PABX entries, printers
- Recent audit log entries displayed as activity feed

### 7.2 Routers (`modules/routers/`)

Manages network routers.

**Key fields:** serial number, brand, IP, MAC, LAN IP, SSID, WiFi password, admin credentials, status, firmware status, uptime, remarks.

**Statuses:** Online · Offline · Warning · High Latency · Power Fail · Maintenance

**Features:** Add/Edit/Delete, status badges with color coding, Export CSV.

### 7.3 Switches (`modules/switches/`)

Manages network switches.

**Key fields:** Switch ID, model, manufacturer, serial, IP, MAC, building+floor location, ports, port details, status, last/next maintenance, personnel.

**Statuses:** Active · Maintenance · Inactive

**Features:** Add/Edit/Delete, maintenance scheduling, Export CSV.

### 7.4 IP Address Management (`modules/ips/`)

Tracks all IP addresses with subnet awareness.

**Key fields:** IP address, MAC, hostname, control number, department, OM name, status, device type, subnet, remarks.

**Statuses:** active · reserved · conflict · static · offline

**Features:** Duplicate IP detection (ignores "obtain"/"wifi" strings), subnet filtering, Export CSV, Print.

### 7.5 Computer Inventory (`modules/computers/`) ⭐ Primary Module

The most feature-rich module.

**Key fields (per computer):**
- **Assignment:** department, end user, MR/PAR number, control number
- **Peripherals + S/N:** system unit, monitor, mouse, keyboard, printer, scanner, AVR/UPS
- **Specs:** processor, memory, storage
- **Software:** OS, OS product key, license (Y/N), MS Office version, MS Office email
- **Network:** IP address, MAC address, Endpoint Secure (Y/N)
- **Accountability:** checked by, encoded by, remarks

**Features:**
- Multi-filter: Department, Printer, Memory, OS, MS Office, Checked By
- Full-text AJAX search across 30+ fields
- Sort + Pagination (top-right row) / Search+Filters (bottom row)
- Export selected rows to CSV
- Import from CSV (duplicate detection)
- Print selected records
- CSRF-protected add/edit/delete

### 7.6 MS Office Licenses (`modules/office/`)

Dedicated tracking for Office 365 / volume licenses.

Synced with the `computers` table: when a `computers` record's `ms_office_email` is updated, the linked `office_licenses` record auto-updates.

### 7.7 Compare & Sync (`modules/reconciliation/`)

Cross-references IP inventory (`ips`) vs Computer inventory (`computers`) by `control_number`.

Highlights:
- IPs in `ips` with no matching computer
- Computers with IP addresses not registered in `ips`
- Mismatched data (department, hostname, etc.)

### 7.8 ICS Inventory (`modules/ics/`)

Generates printable **Inventory Custodian Slips** (ICS) — an official government inventory document format.

**Features:**
- Form-based entry with property details, serial numbers, acquisition cost, date issued
- `print_form.php` renders a print-optimized HTML layout
- Print CSS hides UI chrome (sidebar, buttons)

### 7.9 PABX Directory (`modules/pabx/`)

Hospital telephone extension directory.

**Key fields:** local number (unique), IP address, department, building, floor, display name.

**Features:**
- Building + Floor filters
- Import/Export CSV
- Print view with feature code reference table (call transfer, forward, conference codes)

### 7.10 IS Links (`modules/ihoms_links/`)

Quick-access bookmark manager for internal systems (iHOMS, HIS portals, etc.).

Simple URL + label + category list. Acts as a staff intranet link hub.

### 7.11 Printers (`modules/printers/`)

Standalone printer asset tracking separate from the computer-attached printers in the Computers module.

**Key fields:** printer model, brand, serial number, location, department, status, remarks.

**Features:** Add/Edit/Delete, print selected records (checkbox + print), Export CSV.

### 7.12 Queueing TV (`modules/queueing_tv/`)

Manages a hospital queue display system shown on TV screens.

Controls queue numbers and window assignments displayed on a separate public-facing `queueing_tv` page.

### 7.13 Audit Logs (`modules/logs/`)

Viewable by admin and editor roles only.

Displays all actions logged via `logAudit()`:
- Who performed the action (user_id + username)
- Action type (e.g. `add_computer`, `delete_pabx`, `login`)
- Details (full record snapshot)
- Resource type + ID
- IP address of the actor
- Timestamp

### 7.14 Settings (`modules/settings/`)

Admin-only user management:
- Create / edit / deactivate users
- Assign roles (admin · editor · viewer)
- Set per-user module access (JSON array)
- Change passwords (bcrypt hashed)

### 7.15 Changelog (`modules/changelog/`)

Displays the system version history. Entries are auto-created by `logChangelog()` calls in module actions (currently disabled/removed in most places to reduce noise — entries can still be added manually via SQL).

---

## 8. Database Schema

**Database:** `unified_network_inventory` (PostgreSQL)
**Connection:** PDO with persistent connections, exception mode enabled.

---

### `users`

```sql
id          SERIAL PRIMARY KEY
username    VARCHAR(50) UNIQUE NOT NULL
email       VARCHAR(100)
password    VARCHAR(255) NOT NULL        -- bcrypt hash
full_name   VARCHAR(100)
role        VARCHAR(20) CHECK IN ('admin','editor','viewer')
is_active   BOOLEAN DEFAULT TRUE
module_access TEXT                        -- JSON array e.g. '["computers","ips"]'
last_login  TIMESTAMP
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

---

### `settings`

```sql
id            SERIAL PRIMARY KEY
setting_key   VARCHAR(100) UNIQUE
setting_value TEXT
description   TEXT
updated_at    TIMESTAMP
```

Default records: `system_name = 'HSNM'`, `dark_mode = '1'`

---

### `audit_logs`

```sql
id            SERIAL PRIMARY KEY
user_id       INT → users(id) ON DELETE SET NULL
action_type   VARCHAR(50)     -- e.g. 'add_computer', 'login', 'delete_pabx'
details       TEXT            -- human-readable snapshot
resource_type VARCHAR(50)     -- 'computer', 'pabx', 'system', etc.
resource_id   INT
ip_address    VARCHAR(45)
created_at    TIMESTAMP
```

Indexes: `created_at`, `action_type`, `(user_id, created_at)`

---

### `routers`

```sql
id              SERIAL PRIMARY KEY
serial_number   VARCHAR(50) NOT NULL
brand           VARCHAR(100) NOT NULL
location        VARCHAR(100)
ip_address      VARCHAR(45)
mac_address     VARCHAR(17)
lan_ip          VARCHAR(45)
ssid, wifi_password, admin_user, admin_password VARCHAR(100)
status          CHECK IN ('Online','Offline','Warning','High Latency','Power Fail','Maintenance')
firmware_status CHECK IN ('Up to Date','Update Available','Critical Update')
uptime          VARCHAR(50)
remarks         TEXT
last_seen, created_at TIMESTAMP
```

---

### `switches`

```sql
id                SERIAL PRIMARY KEY
switch_id         VARCHAR(50) UNIQUE NOT NULL
model, manufacturer VARCHAR(100)
serial            VARCHAR(100) UNIQUE
ip_address        VARCHAR(45)
mac_address       VARCHAR(17)
building_location VARCHAR(200)
floor             VARCHAR(20)
ports, port_details, ports_status VARCHAR(100/200/50)
status            CHECK IN ('Active','Maintenance','Inactive')
personnel         VARCHAR(100)
last_maintenance  DATE
next_maintenance  VARCHAR(50)
remarks           TEXT
created_at, updated_at TIMESTAMP
```

---

### `subnets`

```sql
id          SERIAL PRIMARY KEY
name        VARCHAR(100)
network     VARCHAR(50)
cidr        VARCHAR(20)
gateway     VARCHAR(50)
vlan_id     INT
description TEXT
location    VARCHAR(100)
created_at  TIMESTAMP
```

---

### `ips`

```sql
id             SERIAL PRIMARY KEY
ip_address     VARCHAR(50) UNIQUE NOT NULL
mac_address    VARCHAR(17)
hostname       VARCHAR(255)
control_number VARCHAR(100)
department     VARCHAR(100)
om_name        VARCHAR(100)
status         CHECK IN ('active','reserved','conflict','static','offline')
device_type    VARCHAR(50)
description    TEXT
subnet_id      INT → subnets(id) ON DELETE SET NULL
remarks        TEXT
last_seen      TIMESTAMP
created_at     TIMESTAMP
```

Indexes: `control_number`, `mac_address`, `status`, `(control_number, ip_address)`

---

### `computers`

```sql
id              SERIAL PRIMARY KEY
department      VARCHAR(100)
end_user        VARCHAR(100)
mr_par          VARCHAR(100)
control_number  VARCHAR(100)
-- Peripherals (model + S/N pairs):
system_unit, system_unit_sn   VARCHAR(100)
monitor, monitor_sn           VARCHAR(100)
mouse, mouse_sn               VARCHAR(100)
keyboard, keyboard_sn         VARCHAR(100)
printer, printer_sn           VARCHAR(100)
scanner, scanner_sn           VARCHAR(100)
avr_ups, avr_ups_sn           VARCHAR(100)
-- Specs:
processor       VARCHAR(200)
memory          VARCHAR(100)
storage         VARCHAR(100)
-- Software:
os              VARCHAR(100)
license         CHAR(1) CHECK IN ('Y','N')
microsoft_office VARCHAR(100)
ms_office_email  VARCHAR(100)
os_product_key  VARCHAR(255)
-- Network:
ip_address      VARCHAR(45)
mac_address     VARCHAR(17)
endpoint_secure CHAR(1) CHECK IN ('Y','N')
-- Accountability:
checked_by      VARCHAR(100)
encoded_by      VARCHAR(100)
remarks         TEXT
created_at, updated_at TIMESTAMP
```

Indexes: `control_number`, `ip_address`, `mac_address`, `(control_number, ip_address)`

---

### `pabx_directory`

```sql
id           SERIAL PRIMARY KEY
local_number VARCHAR(50) UNIQUE NOT NULL
ip_address   VARCHAR(45)
department   VARCHAR(100)
building     VARCHAR(100) NOT NULL
floor        VARCHAR(10) NOT NULL
display_name VARCHAR(200) NOT NULL
created_at, updated_at TIMESTAMP
```

Indexes: `local_number`, `building`, `department`, `ip_address`, `display_name`, `floor`

---

### `office_licenses`

```sql
id              SERIAL PRIMARY KEY
control_number  VARCHAR(100)
email           VARCHAR(100)    -- synced from computers.ms_office_email
license_type    VARCHAR(100)
product_key     VARCHAR(255)
assigned_to     VARCHAR(100)
department      VARCHAR(100)
created_at, updated_at TIMESTAMP
```

---

### `printers` (standalone)

```sql
id           SERIAL PRIMARY KEY
brand        VARCHAR(100)
model        VARCHAR(100)
serial_number VARCHAR(100)
location_id  INT → locations(id)
department   VARCHAR(100)
status       VARCHAR(50)
remarks      TEXT
created_at   TIMESTAMP
```

---

### `changelog`

```sql
id          SERIAL PRIMARY KEY
version     VARCHAR(20)         -- e.g. '1.4.23'
change_date DATE
change_type VARCHAR(50)         -- 'feature','bugfix','enhancement'
module      VARCHAR(100)
title       VARCHAR(255)
description TEXT
```

---

### `ics_items`

```sql
id              SERIAL PRIMARY KEY
article         VARCHAR(200)      -- item name
description     TEXT
property_number VARCHAR(100)
serial_number   VARCHAR(100)
unit_of_measure VARCHAR(50)
unit_value      DECIMAL(12,2)
quantity        INT
total_value     DECIMAL(12,2)
date_issued     DATE
end_user        VARCHAR(100)
department      VARCHAR(100)
created_at      TIMESTAMP
```

---

### `queueing_tv` (queue management)

```sql
id          SERIAL PRIMARY KEY
window_name VARCHAR(100)
queue_number INT
status      VARCHAR(50)
created_at  TIMESTAMP
```

---

## 9. Back-End Patterns

### Standard Module Pattern

Every module's `index.php` follows this structure:

```php
<?php
require_once '../../config.php';
requireLogin();

// 1. Handle POST actions (add / edit / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) { die(); }
    // ... switch on $_POST['action']
    logAudit($pdo, 'action_name', 'detail string', 'resource_type', $id);
}

// 2. Build WHERE clause from GET filters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'All';
// ... build $where_clauses[], $params[]

// 3. Pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = ($_GET['limit'] === 'all') ? 999999 : (int)$_GET['limit'];
$total_pages = ceil($total_items / $limit);

// 4. Query
$sql = "SELECT * FROM table WHERE $where_sql ORDER BY $order_by LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// 5. Render HTML
$page_title = "Module Name";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<!-- HTML with Tailwind classes -->
```

### AJAX Search Pattern (Computers module)

The search input fires a debounced AJAX GET to `?ajax=1&search=...`. The server detects `$is_ajax = isset($_GET['ajax'])` and returns only the table `<tbody>` HTML, which JS injects into `#computer-table-container`.

### CSV Export Pattern

```php
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Col1', 'Col2', ...]);
    foreach ($rows as $row) {
        fputcsv($output, [escapeCsvField($row['col1']), ...]);
    }
    fclose($output); exit;
}
```

`escapeCsvField()` prefixes cells starting with `=`, `+`, `-`, `@` with a single quote to prevent CSV formula injection.

### Duplicate Detection Pattern

Before inserting, always check:
```php
$check = $pdo->prepare("SELECT COUNT(*) FROM table WHERE unique_col = ? AND id != ?");
$check->execute([$value, $id ?? 0]);
if ($check->fetchColumn() > 0) { $error_msg = "Duplicate found"; }
```

Special case in IPs/Computers: values containing "obtain" or "wifi" are excluded from duplicate checks.

---

## 10. Security Features

| Feature | Implementation |
|---|---|
| **CSRF Protection** | Every POST form includes `<?= getCsrfInput() ?>` — a hidden token verified server-side with `hash_equals()` |
| **SQL Injection** | All queries use PDO prepared statements with bound parameters |
| **XSS Prevention** | All output uses `htmlspecialchars()` |
| **Password Hashing** | bcrypt via `password_hash()` / `password_verify()` |
| **Session Security** | HttpOnly + SameSite=Lax cookies; no-cache headers to prevent auth page caching |
| **Security Headers** | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection` |
| **CSV Injection** | `escapeCsvField()` prepends `'` to formula-starting characters |
| **Access Control** | `requireLogin()` on every page; `canAccessModule()` on every nav link and module entry |
| **Error Reporting** | `display_errors = 0` in production; errors logged silently |
| **CLI Bypass** | CSRF and login checks are skipped when `php_sapi_name() === 'cli'` for migration scripts |

---

## Quick Reference: URL Structure

| URL | Module |
|---|---|
| `/HSNM/` | Dashboard |
| `/HSNM/login` | Login |
| `/HSNM/modules/routers/` | Router Inventory |
| `/HSNM/modules/switches/` | Switch Inventory |
| `/HSNM/modules/ips/` | IP Address Management |
| `/HSNM/modules/computers/` | Computer Inventory |
| `/HSNM/modules/office/` | MS Office Licenses |
| `/HSNM/modules/reconciliation/` | Compare & Sync |
| `/HSNM/modules/ics/` | ICS Inventory |
| `/HSNM/modules/pabx/` | PABX Directory |
| `/HSNM/modules/ihoms_links/` | IS Links |
| `/HSNM/modules/printers/` | Printers |
| `/HSNM/modules/queueing_tv/` | Queueing TV |
| `/HSNM/modules/logs/` | Audit Logs |
| `/HSNM/modules/settings/` | Settings (admin) |
| `/HSNM/modules/changelog/` | Changelog |
