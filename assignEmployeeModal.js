document.addEventListener('DOMContentLoaded', function () {
    // Function to add a new employee assignment field
    function addEmployeeField(bookingID) {
        const container = document.getElementById(`employeeFieldContainer${bookingID}`);
        const newField = document.createElement('div');
        newField.classList.add('employee-assignment-field');

        let selectHTML = `<select name="assignedEmployee" class="employee-select"> <option value="">Select an Employee</option>`;
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

    // Function to confirm assignments
    function confirmAssignments(bookingID) {
        const assignedEmployees = document.querySelectorAll(`#employeeFieldContainer${bookingID} .employee-select`);
        const assignments = Array.from(assignedEmployees).map(select => select.value).filter(phoneNo => phoneNo); // Filter out unselected options

        // Prepare URL-encoded data string
        let data = `bookingID=${encodeURIComponent(bookingID)}`;
        assignments.forEach((phoneNo, index) => {
            data += `&assignedEmployees[${index}]=${encodeURIComponent(phoneNo)}`;
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
});
