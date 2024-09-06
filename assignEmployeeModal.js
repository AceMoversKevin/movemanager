document.addEventListener('DOMContentLoaded', function () {
    // Function to add a new employee assignment field
    function addEmployeeField(bookingID) {
        const container = document.getElementById(`employeeFieldContainer${bookingID}`);
        const newField = document.createElement('div');
        newField.classList.add('employee-assignment-field');

        let selectHTML = `<select name="assignedEmployee" class="employee-select form-control form-control-sm"> <option value="">Select an Employee</option>`;
        availableEmployees.forEach(function (employee) {
            selectHTML += `<option value="${employee.PhoneNo}">${employee.Name} (${employee.EmployeeType})</option>`;
        });
        selectHTML += '</select>';

        newField.innerHTML = selectHTML + ' ' + `<i class="fa fa-eraser remove-field" aria-hidden="true" style="cursor: pointer;"></i>`;
        container.appendChild(newField);

        // Add event listener for removing the field
        newField.querySelector('.remove-field').addEventListener('click', function () {
            newField.remove();
        });
    }

    // Function to add two default employee assignment fields
    function addDefaultEmployeeFields(bookingID) {
        // Add two employee fields by default
        addEmployeeField(bookingID);
        addEmployeeField(bookingID);
    }

    // Function to confirm assignments
    function confirmAssignments(bookingID) {
        const assignedEmployees = document.querySelectorAll(`#employeeFieldContainer${bookingID} .employee-select`);
        const assignments = Array.from(assignedEmployees).map(select => {
            return select.value ? encodeURIComponent(select.value) : null;
        }).filter(phoneNo => phoneNo !== null); // Ensure we filter out null or empty entries

        // Prepare URL-encoded data string
        let data = `bookingID=${encodeURIComponent(bookingID)}`;
        assignments.forEach((phoneNo, index) => {
            data += `&assignedEmployees[${index}][EmployeePhoneNo]=${phoneNo}`;
        });

        // Create and send XHR request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'assignEmployees.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            if (this.status === 200) {
                try {
                    const response = JSON.parse(this.responseText);
                    if (response.success) {
                        alert(response.message); // Additional UI updates can be made here
                        window.location.href = 'unassignedJobs.php'; // Redirect to the unassigned jobs page
                    } else {
                        alert('Failed to assign employees: ' + response.message);
                    }
                } catch (e) {
                    alert('Error parsing the response: ' + e.message);
                }
            }
        };
        xhr.onerror = function () {
            alert('An error occurred during the request.');
        };
        xhr.send(data);
    }

    // Handle quick-assign button clicks
    document.querySelectorAll('.quick-assign').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = this.getAttribute('data-value');
            const targetField = this.closest('p').querySelector('.editable');
            const field = targetField.getAttribute('data-field');
            const bookingId = targetField.getAttribute('data-id');

            targetField.innerText = value;

            // Update the database with the new value
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_booking.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    console.log('Updated successfully.');
                } else {
                    console.error('Error updating the record.');
                }
            };
            xhr.send(`booking_id=${bookingId}&field=${field}&value=${encodeURIComponent(value)}`);
        });
    });

    // Make modal fields editable
    function makeFieldsEditable(bookingID) {
        document.querySelectorAll(`#assignJobsModal${bookingID} .editable`).forEach(field => {
            field.addEventListener('dblclick', function () {
                var $td = this;
                var originalValue = $td.innerText;
                var field = $td.getAttribute('data-field');
                var bookingId = $td.getAttribute('data-id');

                var input = document.createElement('input');
                input.type = 'text';
                input.value = originalValue !== 'Not assigned' ? originalValue : '';

                input.addEventListener('blur', function () {
                    var newValue = input.value.trim();
                    $td.innerText = newValue !== '' ? newValue : 'Not assigned';

                    // Update the database with the new value
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'update_booking.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            console.log('Updated successfully.');
                        } else {
                            console.error('Error updating the record.');
                        }
                    };
                    xhr.send(`booking_id=${bookingId}&field=${field}&value=${encodeURIComponent(newValue)}`);
                });

                input.addEventListener('keyup', function (e) {
                    if (e.key === 'Enter') {
                        input.blur();
                    }
                });

                $td.innerHTML = '';
                $td.appendChild(input);
                input.focus();
            });
        });
    }

    // Initialize CKEditor for Additional Details rich text editor
    function initializeRichTextEditor(bookingID) {
        ClassicEditor.create(document.querySelector(`#additionalDetails${bookingID}`), {
            toolbar: ['bold', 'italic', 'link', 'bulletedList', 'numberedList', 'undo', 'redo']
        }).then(editor => {
            editor.model.document.on('change:data', () => {
                const newValue = editor.getData();
                const bookingId = bookingID;

                // Update the database with the new value
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_booking.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        console.log('Additional details updated successfully.');
                    } else {
                        console.error('Error updating additional details.');
                    }
                };
                xhr.send(`booking_id=${bookingId}&field=AdditionalDetails&value=${encodeURIComponent(newValue)}`);
            });
        }).catch(error => {
            console.error(error);
        });
    }

    // Event listeners for adding new employee fields
    document.querySelectorAll('.add-employee-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const bookingID = this.dataset.bookingid;
            addEmployeeField(bookingID);
        });
    });

    // Event listeners for confirming assignments
    document.querySelectorAll('.confirm-assignment-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const bookingID = this.dataset.bookingid;
            confirmAssignments(bookingID);
        });
    });

    // Initialize editable fields, CKEditor, and two default employee fields for each booking modal
    document.querySelectorAll('.modal').forEach(modal => {
        const bookingID = modal.getAttribute('id').replace('assignJobsModal', '');
        makeFieldsEditable(bookingID);
        initializeRichTextEditor(bookingID); // Initialize CKEditor for Additional Details
        addDefaultEmployeeFields(bookingID); // Add two default employee fields
    });
});
