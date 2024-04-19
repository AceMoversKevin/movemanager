document.addEventListener('DOMContentLoaded', function () {
    // Function to toggle edit mode for a field
    function toggleEdit(editIcon) {
        const parentDiv = editIcon.closest('.editableField');
        const displaySpan = parentDiv.querySelector('span');
        const editInput = parentDiv.querySelector('.editInput');
        const saveButton = parentDiv.querySelector('.saveEdit');
        const cancelButton = parentDiv.querySelector('.cancelEdit');
        const editPencil = parentDiv.querySelector('.fa-pencil-square-o');

        displaySpan.style.display = 'none';
        editInput.style.display = 'block';
        saveButton.style.display = 'inline-block';
        cancelButton.style.display = 'inline-block';
        editPencil.style.display = 'none';
        editInput.setAttribute('data-original', editInput.value);
        editInput.focus();
    }

    // Function to revert edit mode
    function cancelEdit(editOrCancelButton) {
        const parentDiv = editOrCancelButton.closest('.editableField');
        const displaySpan = parentDiv.querySelector('span');
        const editInput = parentDiv.querySelector('.editInput');
        const saveButton = parentDiv.querySelector('.saveEdit');
        const cancelButton = parentDiv.querySelector('.cancelEdit');
        const editPencil = parentDiv.querySelector('.fa-pencil-square-o');

        // Hide the input field and buttons, show the span and pencil icon
        displaySpan.style.display = 'block';
        editInput.style.display = 'none';
        saveButton.style.display = 'none';
        cancelButton.style.display = 'none';
        editPencil.style.display = 'block';

        // Revert the value of the input to the original data
        editInput.value = displaySpan.textContent;
    }

    document.querySelectorAll('.cancel-booking').forEach(function(button) {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel this booking?')) {
                const bookingID = button.getAttribute('data-bookingid');

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'cancelBooking.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        // Handle success (maybe refresh the list or show a message)
                        alert(this.responseText);
                        // Optionally, remove the card from view or mark it as cancelled
                        button.closest('.card').style.display = 'none';
                    } else {
                        // Handle error
                        alert('An error occurred while cancelling the booking.');
                    }
                };
                xhr.onerror = function() {
                    alert('An error occurred during the request.');
                };
                xhr.send('bookingID=' + bookingID);
            }
        });
    });



    // Add click event to all edit icons
    document.querySelectorAll('.fa-pencil-square-o').forEach(function (icon) {
        icon.addEventListener('click', function () {
            toggleEdit(icon);
        });
    });

    // Add click event to all cancel buttons
    document.querySelectorAll('.cancelEdit').forEach(function (button) {
        button.addEventListener('click', function () {
            cancelEdit(button);
        });
    });


    // Add click event to all save buttons
    document.querySelectorAll('.saveEdit').forEach(function (button) {
        button.addEventListener('click', function () {
            const parentDiv = button.closest('.editableField');
            const editInput = parentDiv.querySelector('.editInput');
           // const cancelButton = parentDiv.querySelector('.cancelEdit');
            const bookingID = button.closest('.modal').dataset.bookingid; // assumes you have data-bookingid attribute in modal
            const field = editInput.name; // assumes input has a name attribute matching the database column
            const newValue = editInput.value;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'updateBooking.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');  // Make sure this is set
            xhr.onload = function () {
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    if (response.success) {
                        // Update the display span with new value and revert view
                        parentDiv.querySelector('span').textContent = newValue;
                        cancelEdit(button); // Revert the view to non-edit mode
                        //cancelEdit(cancelButton);
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            };
            xhr.onerror = function () {
                alert('An error occurred during the request.');
            };
            const data = `bookingID=${bookingID}&field=${field}&newValue=${encodeURIComponent(newValue)}`;
            xhr.send(data);
        });
    });
});