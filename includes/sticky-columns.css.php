/* Sticky Columns CSS for Module Tables */
<style>
    /* First column: Checkbox - sticky */
    thead tr th:nth-child(1),
    tbody tr td:nth-child(1) {
        position: sticky !important;
        left: 0;
        z-index: 20;
        background-color: white;
    }

    html.dark thead tr th:nth-child(1),
    html.dark tbody tr td:nth-child(1) {
        background-color: #1a2130;
    }

    /* Second column: Control Number - sticky */
    thead tr th:nth-child(2),
    tbody tr td:nth-child(2) {
        position: sticky !important;
        left: 32px;
        /* Width of first column (w-8 = 32px) */
        z-index: 19;
        background-color: white;
    }

    html.dark thead tr th:nth-child(2),
    html.dark tbody tr td:nth-child(2) {
        background-color: #1a2130;
    }

    /* Add subtle shadow to first column for visual separation */
    thead tr th:nth-child(1)::after,
    tbody tr td:nth-child(1)::after {
        content: '';
        position: absolute;
        top: 0;
        right: -8px;
        bottom: 0;
        width: 8px;
        background: linear-gradient(to right, rgba(0, 0, 0, 0.05), transparent);
        pointer-events: none;
    }

    html.dark thead tr th:nth-child(1)::after,
    html.dark tbody tr td:nth-child(1)::after {
        background: linear-gradient(to right, rgba(0, 0, 0, 0.15), transparent);
    }

    /* Add subtle shadow to second column for visual separation */
    thead tr th:nth-child(2)::after,
    tbody tr td:nth-child(2)::after {
        content: '';
        position: absolute;
        top: 0;
        right: -8px;
        bottom: 0;
        width: 8px;
        background: linear-gradient(to right, rgba(0, 0, 0, 0.05), transparent);
        pointer-events: none;
    }

    html.dark thead tr th:nth-child(2)::after,
    html.dark tbody tr td:nth-child(2)::after {
        background: linear-gradient(to right, rgba(0, 0, 0, 0.15), transparent);
    }
</style>