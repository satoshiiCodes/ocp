/* registration.js
 * Extracted from registration.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_REGISTRATION
 * (rendered by includes/page_data.php).
 */
            // Initialize DataTable
            document.addEventListener('DOMContentLoaded', function() {
                const dataTable = new simpleDatatables.DataTable("#usersTable", {
                    searchable: true,
                    perPage: 10,
                    perPageSelect: [5, 10, 15, 20],
                    labels: {
                        placeholder: "Search users...",
                        searchTitle: "Search within table",
                        pageTitle: "Page {page}",
                        perPage: "entries per page",
                        noRows: "No users found",
                        info: "Showing {start} to {end} of {rows} users",
                        noResults: "No results match your search query"
                    }
                });
            });

            // Function to toggle password visibility
            function togglePassword(inputId) {
                const passwordInput = document.getElementById(inputId);
                const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            }
            
            // Define positions for each department
            const departmentPositions = {
                'Engineering': ['Operations Manager', 'Project Manager', 'Jr. Project Manager', 'Project Engineer', 'Technical DOC'],
                'Warehouse': ['Maintenance Engineer', 'Assistant Maintenance', 'Warehouse Manager', 'Warehouse Custodian'],
                'Motorpool': ['Motorpool Manager', 'Motorpool Custodian'],
                'Admin': ['CEO', 'Accounting', 'HR Officer', 'Bookkeeper', 'Cashier', 'Purchaser'],
                'Site': ['Site Supervisor'],
                'IT': ['Assistant IT Programmer'],
                'BAC': ['Chairman', 'Vice Chairman', 'Member']
            };
            
            // Function to update positions based on selected department (for add modal)
            function updateAddPositions() {
                const departmentSelect = document.getElementById('addDepartment');
                const positionSelect = document.getElementById('addPosition');
                const selectedDepartment = departmentSelect.value;
                
                // Clear current options
                positionSelect.innerHTML = '<option value="">Select Position</option>';
                
                // Add positions for the selected department
                if (selectedDepartment && departmentPositions[selectedDepartment]) {
                    departmentPositions[selectedDepartment].forEach(position => {
                        const option = document.createElement('option');
                        option.value = position;
                        option.textContent = position;
                        positionSelect.appendChild(option);
                    });
                }
            }
            
            // Function to update positions in edit modal based on selected department
            function updateEditPositions() {
                const departmentSelect = document.getElementById('editDepartment');
                const positionSelect = document.getElementById('editPosition');
                const selectedDepartment = departmentSelect.value;
                
                // Clear current options
                positionSelect.innerHTML = '<option value="">Select Position</option>';
                
                // Add positions for the selected department
                if (selectedDepartment && departmentPositions[selectedDepartment]) {
                    departmentPositions[selectedDepartment].forEach(position => {
                        const option = document.createElement('option');
                        option.value = position;
                        option.textContent = position;
                        positionSelect.appendChild(option);
                    });
                }
            }
            
            // View user functionality
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-btn')) {
                    const button = e.target.closest('.view-btn');
                    const userId = button.getAttribute('data-id');
                    viewUser(userId);
                }
                
                if (e.target.closest('.edit-btn')) {
                    const button = e.target.closest('.edit-btn');
                    const userId = button.getAttribute('data-id');
                    editUser(userId);
                }
                
                if (e.target.closest('.delete-btn')) {
                    const button = e.target.closest('.delete-btn');
                    const userId = button.getAttribute('data-id');
                    const userName = button.closest('tr').querySelector('td:nth-child(2)').textContent;
                    deleteUserConfirmation(userId, userName);
                }
            });
            
            function viewUser(userId) {
                // In a real application, you would fetch this data from the server
                // For this example, we'll get it from the table row
                const row = document.querySelector(`.view-btn[data-id="${userId}"]`).closest('tr');
                const cells = row.querySelectorAll('td');
                
                const userDetails = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> ${cells[0].textContent}</p>
                            <p><strong>Name:</strong> ${cells[1].textContent}</p>
                            <p><strong>Department:</strong> ${cells[2].textContent}</p>
                            <p><strong>Position:</strong> ${cells[3].textContent}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong> ${cells[4].textContent}</p>
                            <p><strong>Username:</strong> ${cells[5].textContent}</p>
                            <p><strong>Status:</strong> ${cells[6].textContent}</p>
                            <p><strong>Registration Date:</strong> ${cells[7].textContent}</p>
                        </div>
                    </div>
                `;
                
                document.getElementById('viewUserDetails').innerHTML = userDetails;
                const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                viewModal.show();
            }
            
            function editUser(userId) {
                // Use AJAX to fetch user data from the server
                const formData = new FormData();
                formData.append('action', 'get_user');
                formData.append('id', userId);
                
                fetch('actions/registration-actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        
                        // Populate the form with user data
                        document.getElementById('editUserId').value = user.id;
                        document.getElementById('editLastName').value = user.lastname;
                        document.getElementById('editFirstName').value = user.firstname;
                        document.getElementById('editMiddleName').value = user.middlename || '';
                        document.getElementById('editSuffix').value = user.suffix || '';
                        document.getElementById('editDepartment').value = user.department;
                        
                        // Update positions for the department
                        updateEditPositions();
                        setTimeout(() => {
                            document.getElementById('editPosition').value = user.position;
                        }, 100);
                        
                        document.getElementById('editAddress').value = user.address || '';
                        document.getElementById('editContact').value = user.contact || '';
                        document.getElementById('editStatus').value = user.status;
                        document.getElementById('editAccountType').value = user.accounttype;
                        document.getElementById('editEmail').value = user.email;
                        document.getElementById('editUsername').value = user.username;
                        
                        // Reset password fields
                        document.getElementById('changePasswordCheck').checked = false;
                        document.getElementById('passwordFields').style.display = 'none';
                        document.getElementById('editChangePassword').value = '0';
                        document.getElementById('editPassword').value = '';
                        document.getElementById('editPasswordConfirm').value = '';
                        
                        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                        editModal.show();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error: ' + data.message
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching user data:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error loading user data. Please try again.'
                    });
                });
            }
            
            // Toggle password fields in edit form
            document.getElementById('changePasswordCheck').addEventListener('change', function() {
                const passwordFields = document.getElementById('passwordFields');
                passwordFields.style.display = this.checked ? 'flex' : 'none';
                document.getElementById('editChangePassword').value = this.checked ? '1' : '0';
                
                // Make password fields required if checked
                document.getElementById('editPassword').required = this.checked;
                document.getElementById('editPasswordConfirm').required = this.checked;
            });
            
            // Save user changes
            document.getElementById('saveUserChanges').addEventListener('click', function() {
                // Validate form
                const form = document.getElementById('editUserForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                
                // Check if passwords match if changing password
                if (document.getElementById('changePasswordCheck').checked) {
                    const password = document.getElementById('editPassword').value;
                    const confirmPassword = document.getElementById('editPasswordConfirm').value;
                    
                    if (password !== confirmPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Passwords do not match!'
                        });
                        return;
                    }
                    
                    if (password.length < 6) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Password must be at least 6 characters long!'
                        });
                        return;
                    }
                }
                
                // Collect form data
                const formData = new FormData(document.getElementById('editUserForm'));
                formData.append('action', 'update_user');
                
                // Show loading indicator
                Swal.fire({
                    title: 'Updating User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send data to server via AJAX
                fetch('actions/registration-actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Reload the page to see changes
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error updating user. Please try again.'
                    });
                });
            });
            
            // Add new user functionality
            document.getElementById('saveNewUser').addEventListener('click', function() {
                // Validate form
                const form = document.getElementById('addUserForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                
                // Check if passwords match
                const password = document.getElementById('addPassword').value;
                const confirmPassword = document.getElementById('addPasswordConfirm').value;
                
                if (password !== confirmPassword) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Passwords do not match!'
                    });
                    return;
                }
                
                if (password.length < 6) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Password must be at least 6 characters long!'
                    });
                    return;
                }
                
                // Collect form data
                const formData = new FormData(document.getElementById('addUserForm'));
                formData.append('action', 'add_user');
                
                // Show loading indicator
                Swal.fire({
                    title: 'Adding User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send data to server via AJAX
                fetch('actions/registration-actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Close the modal and reload the page
                                const addModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                                addModal.hide();
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error adding user. Please try again.'
                    });
                });
            });
            
            // Delete user functionality
            function deleteUserConfirmation(userId, userName) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete user: ${userName}. This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteUser(userId);
                    }
                });
            }
            
            function deleteUser(userId) {
                // Send delete request to the server
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('id', userId);
                
                // Show loading indicator
                Swal.fire({
                    title: 'Deleting User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch('actions/registration-actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Reload the page to see changes
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error deleting user. Please try again.'
                    });
                });
            }
            
            // Reset add user form when modal is closed
            document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('addUserForm').reset();
                document.getElementById('addPosition').innerHTML = '<option value="">Select Position</option>';
            });
