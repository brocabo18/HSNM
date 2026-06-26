# Database Index Analysis

## Current Indexes (Automatic)

### Indexes Created by Constraints

#### `users` table
- **PRIMARY KEY**: `id`
- **UNIQUE**: `username` (auto-indexed)

#### `settings` table
- **PRIMARY KEY**: `id`
- **UNIQUE**: `setting_key` (auto-indexed)

#### `audit_logs` table
- **PRIMARY KEY**: `id`
- **FOREIGN KEY**: `user_id` → `users(id)` (auto-indexed)

#### `routers` table
- **PRIMARY KEY**: `id`
- ⚠️ NO other indexes

#### `switches` table
- **PRIMARY KEY**: `id`
- **UNIQUE**: `switch_id` (auto-indexed)
- **UNIQUE**: `serial` (auto-indexed)

#### `subnets` table
- **PRIMARY KEY**: `id`
- ⚠️ NO other indexes

#### `ips` table
- **PRIMARY KEY**: `id`
- **UNIQUE**: `ip_address` (auto-indexed)
- **FOREIGN KEY**: `subnet_id` → `subnets(id)` (auto-indexed)

#### `computers` table
- **PRIMARY KEY**: `id`
- ⚠️ NO other indexes

---

## Missing Indexes (Critical)

### High-Priority Missing Indexes

#### `ips` table
❌ **Missing Index on `control_number`**
- Used in reconciliation module for JOINs
- Used in searches across the application
- **Impact**: Slow reconciliation queries and searches

❌ **Missing Index on `mac_address`**
- Used in reconciliation for matching
- Used in ARP scan updates
- **Impact**: Slow MAC address lookups and updates

❌ **Missing Index on `status`**
- Used in filtering (active/offline/reserved)
- **Impact**: Slow status-based queries

❌ **Missing Composite Index on `(control_number, ip_address)`**
- Used in complex reconciliation queries
- **Impact**: Very slow conflict detection

#### `computers` table
❌ **Missing Index on `control_number`**
- Used in reconciliation JOINs
- **Impact**: Critical for reconciliation performance

❌ **Missing Index on `ip_address`**
- Used in reconciliation matching
- **Impact**: Slow conflict detection

❌ **Missing Index on `mac_address`**
- Used in reconciliation matching
- **Impact**: Slow conflict detection

❌ **Missing Composite Index on `(control_number, ip_address)`**
- Used in conflict queries
- **Impact**: Very slow reconciliation queries

#### `routers` table
❌ **Missing Index on `ip_address`**
- Used for lookups and searches
- **Impact**: Slow IP-based searches

❌ **Missing Index on `status`**
- Used for filtering by status
- **Impact**: Slow status queries

#### `audit_logs` table
❌ **Missing Index on `created_at`**
- Used for date-range queries and sorting
- **Impact**: Slow audit log viewing

❌ **Missing Index on `action_type`**
- Used for filtering by action
- **Impact**: Slow audit filtering

❌ **Missing Composite Index on `(user_id, created_at)`**
- Common query pattern
- **Impact**: Slow user activity reports

---

## Recommended Index Creation SQL

### Critical Indexes (Implement Immediately)

```sql
-- IP Inventory Indexes
ALTER TABLE ips ADD INDEX idx_control_number (control_number);
ALTER TABLE ips ADD INDEX idx_mac_address (mac_address);
ALTER TABLE ips ADD INDEX idx_status (status);
ALTER TABLE ips ADD INDEX idx_control_ip (control_number, ip_address);

-- Computer Inventory Indexes
ALTER TABLE computers ADD INDEX idx_control_number (control_number);
ALTER TABLE computers ADD INDEX idx_ip_address (ip_address);
ALTER TABLE computers ADD INDEX idx_mac_address (mac_address);
ALTER TABLE computers ADD INDEX idx_control_ip (control_number, ip_address);

-- Audit Logs Indexes
ALTER TABLE audit_logs ADD INDEX idx_created_at (created_at);
ALTER TABLE audit_logs ADD INDEX idx_action_type (action_type);
ALTER TABLE audit_logs ADD INDEX idx_user_created (user_id, created_at);

-- Router Indexes
ALTER TABLE routers ADD INDEX idx_ip_address (ip_address);
ALTER TABLE routers ADD INDEX idx_status (status);

-- Switch Indexes (already has unique indexes on switch_id and serial)
ALTER TABLE switches ADD INDEX idx_ip_address (ip_address);
ALTER TABLE switches ADD INDEX idx_status (status);
```

### Performance Impact Estimates

With the above indexes:
- **Reconciliation queries**: 10-100x faster (especially with large datasets)
- **Search operations**: 5-50x faster
- **ARP scan updates**: 5-10x faster
- **Audit log viewing**: 10-20x faster
- **Status filtering**: 5-15x faster

---

## Index Maintenance Recommendations

1. **Monitor Index Usage**
   ```sql
   -- Check which indexes are being used
   SHOW INDEX FROM ips;
   SHOW INDEX FROM computers;
   ```

2. **Optimize Queries**
   - Use EXPLAIN to check query execution plans
   - Ensure queries utilize indexes

3. **Regular Maintenance**
   - Run OPTIMIZE TABLE monthly for large tables
   - Monitor slow query log

---

## Summary

**Current State**: ❌ Critical indexes missing  
**Recommended Action**: Run the index creation SQL immediately  
**Expected Improvement**: 10-100x performance boost on large datasets  
**Tables Affected**: `ips`, `computers`, `audit_logs`, `routers`, `switches`
