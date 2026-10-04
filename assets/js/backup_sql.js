/* backup_sql.js
 * Extracted from backup_sql.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_BACKUP_SQL
 * (rendered by includes/page_data.php).
 */
            // Logout function
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'actions/logout.php';
                }
            });
            
