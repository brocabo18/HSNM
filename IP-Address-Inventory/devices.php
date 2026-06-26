<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Get device data if editing
$device = null;
$isEdit = false;

if (isset($_GET['id'])) {
    $isEdit = true;
    $stmt = $pdo->prepare("SELECT * FROM ip_inventory WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $device = $stmt->fetch();

    if (!$device) {
        redirect('index.php');
    }
}

// Get subnets for dropdown
$subnetsStmt = $pdo->query("SELECT id, name, cidr FROM subnets ORDER BY name");
$subnets = $subnetsStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $ip = sanitizeInput($_POST['ip_address']);
    $hostname = sanitizeInput($_POST['hostname']);
    $mac = formatMAC(sanitizeInput($_POST['mac_address'] ?? ''));
    $status = sanitizeInput($_POST['status']);
    $deviceType = sanitizeInput($_POST['device_type']);
    $location = sanitizeInput($_POST['location']);
    $vlan = !empty($_POST['vlan_id']) ? (int) $_POST['vlan_id'] : null;
    $subnetId = !empty($_POST['subnet_id']) ? (int) $_POST['subnet_id'] : null;
    $notes = sanitizeInput($_POST['notes']);

    $error = '';

    // Validate
    if (!isValidIP($ip)) {
        $error = 'Invalid IP address';
    } elseif (!empty($mac) && !isValidMAC($mac)) {
        $error = 'Invalid MAC address format';
    } else {
        // Check duplicate IP
        if ($isEdit) {
            $checkStmt = $pdo->prepare("SELECT id FROM ip_inventory WHERE ip_address = ? AND id != ?");
            $checkStmt->execute([$ip, $id]);
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM ip_inventory WHERE ip_address = ?");
            $checkStmt->execute([$ip]);
        }

        if ($checkStmt->fetch()) {
            $error = 'IP address already exists';
        }
    }

    if (empty($error)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE ip_inventory 
                    SET ip_address = ?, hostname = ?, mac_address = ?, status = ?, 
                        device_type = ?, location = ?, vlan_id = ?, subnet_id = ?, 
                        notes = ?, last_seen = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$ip, $hostname, $mac, $status, $deviceType, $location, $vlan, $subnetId, $notes, $id]);
                logAudit('update', 'device', $id, "Updated device: $ip ($hostname)");
                $successMsg = 'Device updated successfully';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ip_inventory 
                    (ip_address, hostname, mac_address, status, device_type, location, vlan_id, subnet_id, notes, last_seen, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$ip, $hostname, $mac, $status, $deviceType, $location, $vlan, $subnetId, $notes, getCurrentUserId()]);
                logAudit('create', 'device', $pdo->lastInsertId(), "Created device: $ip ($hostname)");
                $successMsg = 'Device created successfully';
            }

            $_SESSION['success_message'] = $successMsg;
            redirect('index.php');
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit Device' : 'Add New Device';
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>
        <?php echo $pageTitle; ?> - IP Manager
    </title>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#137fec",
                        "background-dark": "#111418",
                        "surface-dark": "#283039",
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "body": ["Noto Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-background-dark text-white font-display min-h-screen p-6">
    <div class="w-full max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <a href="index.php"
                class="inline-flex items-center gap-2 text-[#9dabb9] hover:text-white transition-colors mb-4">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Dashboard
            </a>
            <h1 class="text-white text-3xl font-bold">
                <?php echo $pageTitle; ?>
            </h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg flex items-start gap-3">
                <span class="material-symbols-outlined text-red-500 text-xl">error</span>
                <p class="text-red-400 text-sm">
                    <?php echo $error; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-surface-dark border border-white/10 rounded-2xl p-8">
            <form method="POST" action="" class="space-y-6">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $device['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- IP Address -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">IP Address *</label>
                        <input type="text" name="ip_address" value="<?php echo $device['ip_address'] ?? ''; ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            placeholder="192.168.1.100" required>
                    </div>

                    <!-- Hostname -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Hostname</label>
                        <input type="text" name="hostname" value="<?php echo $device['hostname'] ?? ''; ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            placeholder="server-01">
                    </div>

                    <!-- MAC Address -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">MAC Address</label>
                        <input type="text" name="mac_address" value="<?php echo $device['mac_address'] ?? ''; ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            placeholder="00:1A:2B:3C:4D:5E">
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Status *</label>
                        <select name="status"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            required>
                            <option value="active" <?php echo (($device['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="reserved" <?php echo (($device['status'] ?? '') === 'reserved') ? 'selected' : ''; ?>>Reserved</option>
                            <option value="static" <?php echo (($device['status'] ?? '') === 'static') ? 'selected' : ''; ?>>Static</option>
                            <option value="offline" <?php echo (($device['status'] ?? 'offline') === 'offline') ? 'selected' : ''; ?>>Offline</option>
                            <option value="conflict" <?php echo (($device['status'] ?? '') === 'conflict') ? 'selected' : ''; ?>>Conflict</option>
                        </select>
                    </div>

                    <!-- Device Type -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Device Type</label>
                        <input type="text" name="device_type" value="<?php echo $device['device_type'] ?? ''; ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            placeholder="Server, Router, Desktop, etc.">
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Location</label>
                        <input type="text" name="location" value="<?php echo $device['location'] ?? ''; ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            placeholder="Server Room, Office Floor 3, etc.">
                    </div>

                    <!-- VLAN ID -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">VLAN ID</label>
                        <input type="number" name="vlan_id" value="<?php echo $device['vlan_id'] ?? ''; ?>"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                            placeholder="10">
                    </div>

                    <!-- Subnet -->
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Subnet</label>
                        <select name="subnet_id"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3">
                            <option value="">Select Subnet</option>
                            <?php foreach ($subnets as $subnet): ?>
                                <option value="<?php echo $subnet['id']; ?>" <?php echo (($device['subnet_id'] ?? 0) == $subnet['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subnet['name'] . ' (' . $subnet['cidr'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">Notes</label>
                    <textarea name="notes" rows="3"
                        class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3"
                        placeholder="Additional notes or comments..."><?php echo $device['notes'] ?? ''; ?></textarea>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 bg-primary hover:bg-blue-600 text-white font-medium py-3 px-4 rounded-lg transition-all shadow-lg shadow-primary/25">
                        <?php echo $isEdit ? 'Update Device' : 'Add Device'; ?>
                    </button>
                    <a href="index.php"
                        class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>