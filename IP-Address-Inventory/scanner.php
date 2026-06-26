<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

$scanResults = [];
$scanning = false;
$scanError = '';

// Detect workstation's network adapter IP
$detectedIP = '';
$suggestedRange = '';

// Get server IP (workstation IP when running on localhost)
if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && $_SERVER['SERVER_ADDR'] !== '::1') {
    $detectedIP = $_SERVER['SERVER_ADDR'];
} else {
    // Try to get local IP address
    $localIP = gethostbyname(gethostname());
    if ($localIP && $localIP !== gethostname()) {
        $detectedIP = $localIP;
    }
}

// Suggest scan range based on detected IP
if (!empty($detectedIP)) {
    // Extract first 3 octets for /21 subnet suggestion
    $ipParts = explode('.', $detectedIP);
    if (count($ipParts) === 4) {
        // For /21, align to the subnet boundary
        $thirdOctet = (int) $ipParts[2];
        $alignedThirdOctet = floor($thirdOctet / 8) * 8; // Align to /21 boundary
        $suggestedRange = $ipParts[0] . '.' . $ipParts[1] . '.' . $alignedThirdOctet . '.0/21';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['start_ip'])) {
    $scanning = true;
    $startIP = sanitizeInput($_GET['start_ip']);
    $endIP = sanitizeInput($_GET['end_ip'] ?? '');
    $scanType = sanitizeInput($_GET['scan_type'] ?? 'fast');

    // Parse CIDR if provided
    if (strpos($startIP, '/') !== false) {
        $range = cidrToRange($startIP);
        $startIP = $range['start'];
        $endIP = $range['end'];
    }

    if (!isValidIP($startIP)) {
        $scanError = 'Invalid start IP address';
        $scanning = false;
    } elseif (!empty($endIP) && !isValidIP($endIP)) {
        $scanError = 'Invalid end IP address';
        $scanning = false;
    } else {
        // If no end IP, just scan the single IP
        if (empty($endIP)) {
            $endIP = $startIP;
        }

        $startLong = ip2long($startIP);
        $endLong = ip2long($endIP);

        if ($startLong > $endLong) {
            $scanError = 'Start IP must be less than or equal to end IP';
            $scanning = false;
        } elseif (($endLong - $startLong) > 2047) {
            $scanError = 'IP range too large. Maximum 2,048 IPs per scan (supports up to /21 subnet).';
            $scanning = false;
        } else {
            // Perform scan
            for ($i = $startLong; $i <= $endLong; $i++) {
                $ip = long2ip($i);

                // Check if IP already exists in database
                $stmt = $pdo->prepare("SELECT * FROM ip_inventory WHERE ip_address = ?");
                $stmt->execute([$ip]);
                $existing = $stmt->fetch();

                $isOnline = false;
                $hostname = '';

                // Ping check (fast scan)
                if ($scanType === 'fast' || $scanType === 'deep') {
                    // Simple ping check using fsockopen
                    $fp = @fsockopen($ip, 80, $errno, $errstr, 1);
                    if ($fp) {
                        $isOnline = true;
                        fclose($fp);
                    } else {
                        // Try ping on port 443
                        $fp = @fsockopen($ip, 443, $errno, $errstr, 1);
                        if ($fp) {
                            $isOnline = true;
                            fclose($fp);
                        }
                    }

                    // Try hostname resolution
                    if ($isOnline) {
                        $hostname = @gethostbyaddr($ip);
                        if ($hostname === $ip) {
                            $hostname = '';
                        }
                    }
                }

                $scanResults[] = [
                    'ip' => $ip,
                    'status' => $isOnline ? 'active' : 'offline',
                    'hostname' => $hostname,
                    'existing' => $existing !== false,
                    'existing_data' => $existing
                ];
            }

            // Log scan activity
            logAudit('scan', 'network', null, "Scanned IP range: $startIP - $endIP");
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>IP Scanner - IP Manager</title>
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

        .icon-fill {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-background-dark text-white font-display min-h-screen p-6">
    <div class="w-full max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <a href="index.php"
                class="inline-flex items-center gap-2 text-[#9dabb9] hover:text-white transition-colors mb-4">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Dashboard
            </a>
            <h1 class="text-white text-3xl font-bold mb-2">IP Scanner</h1>
            <p class="text-[#9dabb9]">Scan your network for active devices</p>
        </div>

        <!-- Network Detection Info -->
        <?php if (!empty($detectedIP)): ?>
            <div class="mb-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-lg flex items-start gap-3">
                <span class="material-symbols-outlined text-blue-400 text-xl">info</span>
                <div class="flex-1">
                    <p class="text-blue-400 text-sm font-medium mb-1">Network Adapter Detected</p>
                    <p class="text-[#9dabb9] text-sm">
                        Workstation IP: <span
                            class="text-white font-mono"><?php echo htmlspecialchars($detectedIP); ?></span>
                        <?php if ($suggestedRange): ?>
                            | Suggested Scan: <span
                                class="text-white font-mono"><?php echo htmlspecialchars($suggestedRange); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Scanner Form -->
        <div class="bg-surface-dark border border-white/10 rounded-2xl p-8 mb-6">
            <?php if ($scanError): ?>
                <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-500 text-xl">error</span>
                    <p class="text-red-400 text-sm">
                        <?php echo $scanError; ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="GET" action="" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Start IP / CIDR *</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9]">router</span>
                            <input type="text" name="start_ip"
                                value="<?php echo htmlspecialchars($_GET['start_ip'] ?? $suggestedRange); ?>"
                                class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary pl-10 px-4 py-3"
                                placeholder="<?php echo $suggestedRange ?: '192.163.10.0/21'; ?>" required>
                        </div>
                        <p class="text-[#9dabb9] text-xs mt-1">Supports /21 subnets (2,048 IPs)</p>
                    </div>

                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">End IP (Optional)</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#9dabb9]">router</span>
                            <input type="text" name="end_ip"
                                value="<?php echo htmlspecialchars($_GET['end_ip'] ?? ''); ?>"
                                class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary pl-10 px-4 py-3"
                                placeholder="Leave empty to use CIDR range">
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">Scan Type</label>
                        <select name="scan_type"
                            class="w-full bg-background-dark border border-gray-700 text-white text-sm rounded-lg focus:ring-2 focus:ring-primary px-4 py-3">
                            <option value="fast">Fast Scan (Ping)</option>
                            <option value="deep">Deep Scan (Ports)</option>
                        </select>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-primary hover:bg-blue-600 text-white font-medium py-3 px-4 rounded-lg transition-all shadow-lg shadow-primary/25 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">play_arrow</span>
                    Start Scan
                </button>
            </form>
        </div>

        <!-- Scan Results -->
        <?php if ($scanning && !empty($scanResults)): ?>
            <div class="bg-surface-dark border border-white/10 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-[#3e4a56]">
                    <h3 class="text-white text-base font-bold">Scan Results</h3>
                    <span class="bg-[#111418] text-[#9dabb9] text-xs px-2 py-0.5 rounded-full border border-[#3e4a56]">
                        <?php echo count($scanResults); ?> IPs Scanned
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#1e2329] border-b border-[#3e4a56]">
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">IP Address
                                </th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Status</th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">Hostname</th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider">In Database
                                </th>
                                <th class="p-4 text-xs font-semibold text-[#9dabb9] uppercase tracking-wider text-right">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3e4a56]">
                            <?php foreach ($scanResults as $result): ?>
                                <tr class="hover:bg-[#323c47] transition-colors">
                                    <td class="p-4 text-sm text-white font-mono">
                                        <?php echo $result['ip']; ?>
                                    </td>
                                    <td class="p-4">
                                        <div
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full <?php echo $result['status'] === 'active' ? 'bg-emerald-500/10 border border-emerald-500/20' : 'bg-gray-500/10 border border-gray-500/20'; ?>">
                                            <div
                                                class="w-1.5 h-1.5 rounded-full <?php echo $result['status'] === 'active' ? 'bg-emerald-500' : 'bg-gray-500'; ?>">
                                            </div>
                                            <span
                                                class="text-xs font-medium <?php echo $result['status'] === 'active' ? 'text-emerald-500' : 'text-gray-500'; ?>">
                                                <?php echo ucfirst($result['status']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-[#d0d6dc]">
                                        <?php echo htmlspecialchars($result['hostname'] ?: 'N/A'); ?>
                                    </td>
                                    <td
                                        class="p-4 text-sm <?php echo $result['existing'] ? 'text-emerald-500' : 'text-[#9dabb9]'; ?>">
                                        <?php echo $result['existing'] ? 'Yes' : 'No'; ?>
                                    </td>
                                    <td class="p-4 text-right">
                                        <?php if ($result['existing']): ?>
                                            <a href="devices.php?id=<?php echo $result['existing_data']['id']; ?>"
                                                class="text-primary hover:text-blue-400 text-sm font-medium">View</a>
                                        <?php else: ?>
                                            <a href="devices.php?ip=<?php echo urlencode($result['ip']); ?>&hostname=<?php echo urlencode($result['hostname']); ?>&status=<?php echo $result['status']; ?>"
                                                class="text-emerald-500 hover:text-emerald-400 text-sm font-medium">Add to
                                                Inventory</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($scanning && empty($scanResults)): ?>
            <div class="bg-surface-dark border border-white/10 rounded-2xl p-12 text-center">
                <span class="material-symbols-outlined text-4xl text-[#9dabb9] mb-4 block">radar</span>
                <p class="text-[#9dabb9]">No results to display</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>