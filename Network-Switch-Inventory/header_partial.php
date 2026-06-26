<header
    class="h-16 border-b border-border-dark bg-background-dark flex items-center justify-between px-8 flex-shrink-0 sticky top-0 z-10">
    <div class="flex items-center gap-6">
        <!-- Global Search -->
        <div class="relative w-96 group">
            <span
                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#9da6b9] text-xl group-focus-within:text-primary transition-colors">search</span>
            <input type="text" id="globalSearch" placeholder="Search switches, serials, IPs..."
                class="w-full bg-[#1a2130] border border-border-dark text-white text-sm rounded-xl pl-11 pr-4 py-2 focus:ring-2 focus:ring-primary focus:border-primary transition-all shadow-inner">

            <!-- Search Results Dropdown -->
            <div id="searchResults"
                class="hidden absolute top-full left-0 right-0 mt-2 bg-background-dark border border-border-dark rounded-xl shadow-2xl overflow-hidden z-20 max-h-96 overflow-y-auto custom-scrollbar">
                <div class="p-2 border-b border-border-dark bg-[#1a2130]">
                    <p class="text-[10px] uppercase font-bold text-[#4e5666] tracking-widest px-2">Quick Results</p>
                </div>
                <div id="searchResultsList" class="p-1"></div>
            </div>
        </div>
        <h2 class="text-white font-bold opacity-0 lg:opacity-100 transition-opacity">
            <?php echo $pageTitle ?? 'Dashboard'; ?>
        </h2>
    </div>

    <div class="flex items-center gap-4">
        <?php echo $extraHeaderContent ?? ''; ?>
        <!-- Notifications -->
        <div class="relative">
            <button id="notificationBtn"
                class="size-10 rounded-xl bg-[#1a2130] border border-border-dark flex items-center justify-center text-[#9da6b9] hover:text-white hover:border-primary/50 transition-all relative group">
                <span
                    class="material-symbols-outlined text-xl group-hover:scale-110 transition-transform">notifications</span>
                <span id="notifBadge"
                    class="hidden absolute -top-1 -right-1 size-4 bg-red-500 border-2 border-background-dark rounded-full flex items-center justify-center text-[8px] font-bold text-white"></span>
            </button>

            <!-- Notifications Dropdown -->
            <div id="notificationDropdown"
                class="hidden absolute top-full right-0 mt-2 w-80 bg-background-dark border border-border-dark rounded-xl shadow-2xl overflow-hidden z-20">
                <div class="p-4 border-b border-border-dark bg-[#1a2130] flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Recent Notifications</h3>
                    <span class="text-[10px] text-[#9da6b9] uppercase font-bold tracking-widest">System Alerts</span>
                </div>
                <div id="notificationList" class="max-h-80 overflow-y-auto custom-scrollbar p-1">
                    <div class="p-8 text-center text-[#4e5666]">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-20">notifications_paused</span>
                        <p class="text-xs">No unread notifications</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    // Global Search & Notifications Logic
    const globalSearch = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    const searchResultsList = document.getElementById('searchResultsList');
    let searchTimeout;

    if (globalSearch) {
        globalSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const q = e.target.value.trim();
            if (q.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`api?action=search&q=${encodeURIComponent(q)}`);
                    const data = await response.json();
                    if (data.success) {
                        renderSearchResults(data.results);
                    }
                } catch (err) { console.error('Search error:', err); }
            }, 300);
        });
    }

    function renderSearchResults(results) {
        searchResultsList.innerHTML = '';
        if (results.length === 0) {
            searchResultsList.innerHTML = '<div class="p-4 text-center text-xs text-[#4e5666]">No switches found</div>';
        } else {
            results.forEach(item => {
                const div = document.createElement('div');
                div.className = 'p-3 hover:bg-primary/10 rounded-lg transition-colors cursor-pointer group mb-1';
                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="size-8 rounded bg-primary/20 flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined text-lg">router</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-white truncate">${item.switch_id || "N/A"}</p>
                            <p class="text-[10px] text-[#9da6b9] truncate">${item.model || "Unknown"} • ${item.ip || "No IP"}</p>
                        </div>
                        <span class="material-symbols-outlined text-[#4e5666] group-hover:text-primary transition-colors">arrow_forward</span>
                    </div>
                `;
                div.onclick = () => window.location.href = `dashboard?search=${item.switch_id || item.ip}`;
                searchResultsList.appendChild(div);
            });
        }
        searchResults.classList.remove('hidden');
    }

    // Close search on click outside
    document.addEventListener('click', (e) => {
        if (globalSearch && !globalSearch.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
        if (notificationDropdown && !notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.add('hidden');
        }
    });

    // Notifications Logic
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationList = document.getElementById('notificationList');
    const notifBadge = document.getElementById('notifBadge');

    if (notificationBtn) {
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });
    }

    async function loadNotifications() {
        try {
            const response = await fetch('api?action=notifications_list');
            const data = await response.json();
            if (data.success) {
                renderNotifications(data.notifications);
            }
        } catch (err) { console.error('Notif load error:', err); }
    }

    function renderNotifications(notifs) {
        if (notifs.length === 0) {
            notificationList.innerHTML = `
                <div class="p-8 text-center text-[#4e5666]">
                    <span class="material-symbols-outlined text-4xl mb-2 opacity-20">notifications_paused</span>
                    <p class="text-xs">No unread notifications</p>
                </div>`;
            notifBadge.classList.add('hidden');
            return;
        }

        notifBadge.textContent = notifs.length;
        notifBadge.classList.remove('hidden');

        notificationList.innerHTML = '';
        notifs.forEach(n => {
            const div = document.createElement('div');
            div.className = 'p-4 hover:bg-white/5 border-b border-border-dark/50 last:border-0 transition-colors relative group';
            const icon = n.type === 'warning' ? 'warning' : 'info';
            const color = n.type === 'warning' ? 'text-amber-500' : 'text-primary';

            div.innerHTML = `
                <div class="flex gap-3">
                    <span class="material-symbols-outlined ${color} text-xl mt-0.5">${icon}</span>
                    <div class="flex-1">
                        <p class="text-xs font-bold text-white mb-1">${n.title}</p>
                        <p class="text-[10px] text-[#9da6b9] leading-normal mb-2">${n.message}</p>
                        <p class="text-[9px] text-[#4e5666] font-medium">${new Date(n.created_at).toLocaleString()}</p>
                    </div>
                    <button class="size-6 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-white/10 flex items-center justify-center text-[#4e5666] hover:text-white transition-all">
                        <span class="material-symbols-outlined text-sm">check</span>
                    </button>
                </div>
            `;

            div.querySelector('button').onclick = async (e) => {
                e.stopPropagation();
                await markAsRead(n.id);
            };

            notificationList.appendChild(div);
        });
    }

    async function markAsRead(id) {
        try {
            await fetch('api?action=notifications_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            loadNotifications();
        } catch (err) { console.error('Mark read error:', err); }
    }

    // Initial check
    loadNotifications();
    setInterval(loadNotifications, 30000); // Check every 30s
</script>