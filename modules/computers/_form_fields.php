<?php
/**
 * Shared form fields for satellite computer inventory modules (JASMC-ST, JASMC-VT, DOH-JBL-CMC).
 * Included inside both Add and Edit modal forms.
 * Variables available from parent: $staff_users, $printer_options, $scanner_options, $avr_ups_options
 * For edit forms the JS openEditModal() populates values via DOM; for add forms fields start empty.
 *
 * Field prefix convention: edit form uses id="edit_*" on wrappers, name attributes are the same for both.
 */
$is_edit = isset($is_edit_form) && $is_edit_form;
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <!-- Department -->
    <div class="md:col-span-2">
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Department / Location</label>
        <input type="text" name="department" placeholder="e.g. Nursing Station"
            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent">
    </div>
    <!-- Status -->
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
        <select name="status" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
            <option value="Working">Working</option>
            <option value="Maintenance">Maintenance</option>
            <option value="Transferred">Transferred</option>
            <option value="Condemned">Condemned</option>
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <!-- End User -->
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End User</label>
        <input type="text" name="end_user" placeholder="Full name"
            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
    </div>
    <!-- MR/PAR -->
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">MR/PAR #</label>
        <input type="text" name="mr_par"
            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
    </div>
    <!-- Control Number -->
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Control Number</label>
        <input type="text" name="control_number"
            class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
    </div>
</div>

<div class="border-t border-slate-200 dark:border-[#232b3d] pt-4">
    <p class="text-xs font-bold text-slate-400 uppercase mb-3">Hardware</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">System Unit</label>
            <input type="text" name="system_unit" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">System Unit S/N</label>
            <input type="text" name="system_unit_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Monitor</label>
            <input type="text" name="monitor" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Monitor S/N</label>
            <input type="text" name="monitor_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mouse</label>
            <input type="text" name="mouse" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mouse S/N</label>
            <input type="text" name="mouse_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Keyboard</label>
            <input type="text" name="keyboard" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Keyboard S/N</label>
            <input type="text" name="keyboard_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
    </div>
</div>

<div class="border-t border-slate-200 dark:border-[#232b3d] pt-4">
    <p class="text-xs font-bold text-slate-400 uppercase mb-3">Peripherals</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Printer -->
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Printer</label>
            <input type="text" name="printer" placeholder="Printer model(s)"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Printer S/N</label>
            <input type="text" name="printer_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <!-- Scanner -->
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Scanner</label>
            <input type="text" name="scanner" placeholder="Scanner model(s)"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Scanner S/N</label>
            <input type="text" name="scanner_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <!-- AVR/UPS -->
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">AVR/UPS</label>
            <input type="text" name="avr_ups" placeholder="AVR/UPS model(s)"
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">AVR/UPS S/N</label>
            <input type="text" name="avr_ups_sn" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
    </div>
</div>

<div class="border-t border-slate-200 dark:border-[#232b3d] pt-4">
    <p class="text-xs font-bold text-slate-400 uppercase mb-3">System Specs</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Processor</label>
            <input type="text" name="processor" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Memory (RAM)</label>
            <input type="text" name="memory" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Storage</label>
            <input type="text" name="storage" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Operating System</label>
            <input type="text" name="os" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">OS Product Key</label>
            <input type="text" name="os_product_key" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">License</label>
            <select name="license" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                <option value="N">No</option>
                <option value="Y">Yes</option>
            </select>
        </div>
    </div>
</div>

<div class="border-t border-slate-200 dark:border-[#232b3d] pt-4">
    <p class="text-xs font-bold text-slate-400 uppercase mb-3">Office & Network</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Microsoft Office</label>
            <input type="text" name="microsoft_office" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">MS Office Email</label>
            <input type="email" name="ms_office_email" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">IP Address</label>
            <input type="text" name="ip_address" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">MAC Address</label>
            <input type="text" name="mac_address" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Endpoint Secure</label>
            <select name="endpoint_secure" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                <option value="N">No</option>
                <option value="Y">Yes</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Firewall</label>
            <select name="firewall" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
                <option value="N">No</option>
                <option value="Y">Yes</option>
            </select>
        </div>
    </div>
</div>

<div class="border-t border-slate-200 dark:border-[#232b3d] pt-4">
    <p class="text-xs font-bold text-slate-400 uppercase mb-3">Personnel & Remarks</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Checked By <span class="text-red-500">*</span></label>
            <input type="text" name="checked_by" list="staff_list_sat" required
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
            <datalist id="staff_list_sat">
                <?php foreach ($staff_users as $su): ?>
                    <option value="<?= htmlspecialchars($su) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Encoded By <span class="text-red-500">*</span></label>
            <input type="text" name="encoded_by" list="staff_list_sat" required
                class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Remarks Date</label>
            <input type="date" name="remarks_date" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Remarks</label>
            <textarea name="remarks" rows="2" class="w-full bg-slate-50 dark:bg-[#101622] border border-slate-200 dark:border-[#232b3d] rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
        </div>
    </div>
</div>
