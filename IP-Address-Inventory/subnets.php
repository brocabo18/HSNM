<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Get subnets with utilization
$stmt = $pdo->query("
    SELECT 
        s.*,
        COUNT(i.id) as used_ips,
        (s.total_ips - COUNT(i.id)) as available_ips,
        ROUND((COUNT(i.id) / s.total_ips * 100), 2) as utilization_percent
    FROM subnets s
    LEFT JOIN ip_inventory i ON s.id = i.subnet_id AND i.status IN ('active', 'reserved', 'static')
    GROUP BY s.id
    ORDER BY s.name
");
$subnets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Subnets - IP Manager</title>
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
            <a href="index"
                class="inline-flex items-center gap-2 text-[#9dabb9] hover:text-white transition-colors mb-4">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Dashboard
            </a>
            <h1 class="text-white text-3xl font-bold mb-2">Subnet Management</h1>
            <p class="text-[#9dabb9]">Manage your network subnets and CIDR blocks</p>
        </div>

        <!-- Subnets Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($subnets as $subnet): ?>
                <div class="bg-surface-dark border border-white/10 rounded-2xl p-6 hover:border-primary/30 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-white text-xl font-bold mb-1">
                                <?php echo htmlspecialchars($subnet['name']); ?>
                            </h3>
                            <p class="text-[#9dabb9] text-sm font-mono">
                                <?php echo htmlspecialchars($subnet['cidr']); ?>
                            </p>
                        </div>
                        <?php if ($subnet['vlan_id']): ?>
                            <span class="bg-primary/20 text-primary text-xs px-3 py-1 rounded-full font-medium">VLAN
                                <?php echo $subnet['vlan_id']; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4">
                        <!-- Gateway -->
                        <?php if ($subnet['gateway']): ?>
                            <div class="flex items-center gap-2 text-sm">
                                <span class="material-symbols-outlined text-[#9dabb9] text-[18px]">router</span>
                                <span class="text-[#d0d6dc]">Gateway:</span>
                                <span class="text-white font-mono">
                                    <?php echo htmlspecialchars($subnet['gateway']); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Utilization Bar -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-[#d0d6dc]">IP Utilization</span>
                                <span class="text-white font-medium">
                                    <?php echo round($subnet['utilization_percent'], 1); ?>%
                                </span>
                            </div>
                            <div class="w-full bg-[#111418] rounded-full h-3">
                                <div class="<?php echo $subnet['utilization_percent'] > 80 ? 'bg-red-500' : ($subnet['utilization_percent'] > 60 ? 'bg-yellow-500' : 'bg-emerald-500'); ?> h-3 rounded-full transition-all"
                                    style="width: <?php echo min($subnet['utilization_percent'], 100); ?>%"></div>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 pt-4 border-t border-white/10">
                            <div>
                                <p class="text-[#9dabb9] text-xs mb-1">Total</p>
                                <p class="text-white font-bold text-lg">
                                    <?php echo number_format($subnet['total_ips']); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-[#9dabb9] text-xs mb-1">Used</p>
                                <p class="text-emerald-500 font-bold text-lg">
                                    <?php echo number_format($subnet['used_ips']); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-[#9dabb9] text-xs mb-1">Available</p>
                                <p class="text-blue-400 font-bold text-lg">
                                    <?php echo number_format($subnet['available_ips']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <?php if ($subnet['description']): ?>
                            <p class="text-[#9dabb9] text-sm pt-2 border-t border-white/10">
                                <?php echo htmlspecialchars($subnet['description']); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="flex gap-2 pt-4">
                            <a href="index?subnet=<?php echo $subnet['id']; ?>"
                                class="flex-1 text-center bg-primary/20 hover:bg-primary/30 text-primary px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                View Devices
                            </a>
                            <a href="scanner.php?start_ip=<?php echo urlencode($subnet['cidr']); ?>"
                                class="px-4 py-2 bg-[#323c47] hover:bg-[#3e4a56] text-white rounded-lg text-sm font-medium transition-colors">
                                Scan
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($subnets)): ?>
            <div class="bg-surface-dark border border-white/10 rounded-2xl p-12 text-center">
                <span class="material-symbols-outlined text-4xl text-[#9dabb9] mb-4 block">hub</span>
                <p class="text-[#9dabb9] mb-4">No subnets configured</p>
                <p class="text-[#9dabb9] text-sm">Add subnets to your database to manage your network segments</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>