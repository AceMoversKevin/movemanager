// Function to keep the session alive by sending periodic requests
function keepSessionAlive() {
    // This sends a GET request to the 'keep-session-alive.php' file
    fetch('keep-session-alive.php')
    .then(response => {
        // Optionally log the response or handle any errors
        console.log('Session refreshed');
    })
    .catch(error => console.error('Error keeping session alive:', error));
}

// Set an interval to call the function every 1 minutes (60000 milliseconds)
setInterval(keepSessionAlive, 60000); // 1 minutes

// Optionally call it immediately on page load to ensure the session starts alive
keepSessionAlive();