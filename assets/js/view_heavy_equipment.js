/* view_heavy_equipment.js
 * Extracted from view_heavy_equipment.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_VIEW_HEAVY_EQUIPMENT
 * (rendered by includes/page_data.php).
 */
            
            window.addEventListener('DOMContentLoaded', event => {
                const fuelTable = document.getElementById('fuelTable');
                if (fuelTable) {
                    new simpleDatatables.DataTable(fuelTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const partsTable = document.getElementById('partsTable');
                if (partsTable) {
                    new simpleDatatables.DataTable(partsTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const rentalsTable = document.getElementById('rentalsTable');
                if (rentalsTable) {
                    new simpleDatatables.DataTable(rentalsTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
            });
