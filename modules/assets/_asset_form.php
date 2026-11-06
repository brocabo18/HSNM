<?php
/**
 * Shared form fields for Asset add/edit modals.
 * JS populates values for edit; fields start empty for add.
 */
?>
<!-- Section: Identity -->
<div>
    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 dark:border-[#232b3d] pb-2 mb-4">General Information</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Brand / Model</label>
            <input type="text" name="brand_model" placeholder="e.g. Dell OptiPlex 7090"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
            <select name="status" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                <option value="Active">Active</option>
                <option value="Maintenance">Maintenance</option>
                <option value="Retired">Retired</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Control ID</label>
            <input type="text" name="control_id" placeholder="e.g. CU-2024-001"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Serial Number</label>
            <input type="text" name="serial_number" placeholder="SN-XXXX"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">PTR Number</label>
            <input type="text" name="ptr_number" placeholder="PTR-001"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Department</label>
            <input type="text" name="department" placeholder="e.g. IT Operations"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End User</label>
            <input type="text" name="end_user" placeholder="Full name"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Source</label>
            <select name="source" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                <option value="Purchased">Purchased</option>
                <option value="Donation">Donation</option>
                <option value="Personal">Personal</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">MR / ICS #</label>
            <input type="text" name="mr_ics" placeholder="Reference Number"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Item Code</label>
            <input type="text" name="item_code" placeholder="IC-A001"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cost (₱)</label>
            <input type="number" step="0.01" name="cost" placeholder="0.00"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Encoded By</label>
            <input type="text" name="encoded_by" list="staff_list_assets"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
            <datalist id="staff_list_assets">
                <?php foreach ($staff_users as $su): ?>
                    <option value="<?= htmlspecialchars($su) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
    </div>
</div>

<!-- Section: Hardware Specs -->
<div>
    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 dark:border-[#232b3d] pb-2 mb-4">Core Hardware Specifications</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl p-3">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Processor</label>
            <input type="text" name="processor" placeholder="Intel i7-13700K"
                class="w-full bg-transparent border-b border-slate-300 dark:border-[#232b3d] text-sm font-bold text-slate-800 dark:text-white focus:ring-0 focus:border-primary pb-1 outline-none">
        </div>
        <div class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl p-3">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Memory (RAM)</label>
            <input type="text" name="memory" placeholder="16GB DDR4"
                class="w-full bg-transparent border-b border-slate-300 dark:border-[#232b3d] text-sm font-bold text-slate-800 dark:text-white focus:ring-0 focus:border-primary pb-1 outline-none">
        </div>
        <div class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl p-3">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Storage</label>
            <input type="text" name="storage" placeholder="512GB NVMe"
                class="w-full bg-transparent border-b border-slate-300 dark:border-[#232b3d] text-sm font-bold text-slate-800 dark:text-white focus:ring-0 focus:border-primary pb-1 outline-none">
        </div>
        <div class="bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl p-3">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">GPU</label>
            <input type="text" name="gpu" placeholder="Integrated / RTX 4070"
                class="w-full bg-transparent border-b border-slate-300 dark:border-[#232b3d] text-sm font-bold text-slate-800 dark:text-white focus:ring-0 focus:border-primary pb-1 outline-none">
        </div>
    </div>
</div>

<!-- Section: Components -->
<div>
    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 dark:border-[#232b3d] pb-2 mb-4">Peripheral Components</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Monitor</label>
            <input type="text" name="monitor" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Monitor S/N</label>
            <input type="text" name="monitor_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Printer</label>
            <input type="text" name="printer" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Printer S/N</label>
            <input type="text" name="printer_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">UPS / AVR</label>
            <input type="text" name="ups_avr" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">UPS / AVR S/N</label>
            <input type="text" name="ups_avr_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
        <div class="md:col-span-2"><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Keyboard / Mouse</label>
            <input type="text" name="keyboard_mouse" placeholder="e.g. Logitech MK270"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></div>
    </div>
</div>

<!-- Section: System & Network -->
<div>
    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 dark:border-[#232b3d] pb-2 mb-4">System &amp; Network</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-bold text-slate-500 uppercase">OS Version</label>
                <label class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-[#232b3d] text-[11px] font-semibold text-slate-600 dark:text-slate-300 cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors select-none">
                    <input type="checkbox" name="os_licensed" class="rounded text-primary focus:ring-primary size-3.5">
                    <span>Licensed</span>
                </label>
            </div>
            <input type="text" name="os_version" placeholder="Windows 11 22H2"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">IP Address</label>
            <input type="text" name="ip_address" placeholder="192.163.10.x"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">MAC Address</label>
            <input type="text" name="mac_address" placeholder="00:1A:2B:3C:4D:5E"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-bold text-slate-500 uppercase">MS Office</label>
                <label class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-[#232b3d] text-[11px] font-semibold text-slate-600 dark:text-slate-300 cursor-pointer hover:bg-primary/10 hover:text-primary transition-colors select-none">
                    <input type="checkbox" name="ms_office_licensed" class="rounded text-primary focus:ring-primary size-3.5">
                    <span>Licensed</span>
                </label>
            </div>
            <input type="text" name="ms_office" placeholder="Microsoft 365"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Anti-Virus</label>
            <input type="text" name="antivirus" placeholder="e.g. Athena EPP"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Last Scan Date</label>
            <input type="date" name="scan_date"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Remarks</label>
            <textarea name="remarks" rows="2" placeholder="Additional notes..."
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
        </div>
    </div>
</div>
