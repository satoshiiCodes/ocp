/* fuel_records.js
 * Extracted from fuel_records.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_FUEL_RECORDS
 * (rendered by includes/page_data.php).
 */
            
            // Auto show modal if URL has #fuel-records hash
            document.addEventListener('DOMContentLoaded', function() {
                if (window.location.hash === '#fuel-records') {
                    var fuelModal = new bootstrap.Modal(document.getElementById('fuelRecordsModal'));
                    fuelModal.show();
                }
            });
