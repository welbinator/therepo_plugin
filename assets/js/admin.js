document.getElementById("refresh-repositories").addEventListener("click", function () {
    if (!confirm(theRepoPluginAjax.confirm_refresh_message)) {
        return;
    }

    const button = this;
    const message = document.getElementById("refresh-db-message");

    button.disabled = true;
    button.textContent = theRepoPluginAjax.refreshing_message;

    // Clear the message if it exists
    if (message) {
        message.textContent = "";
    }

    fetch(theRepoPluginAjax.ajax_url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "refresh_repositories",
            nonce: theRepoPluginAjax.refresh_nonce, // Use the correct localized nonce
        }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then((data) => {
            
            if (data.success) {
                if (message) {
                    message.textContent = data.data.message || theRepoPluginAjax.success_refresh_message;
                }
            } else {
                console.error("[DEBUG] Failed to refresh repositories:", data.data?.message || "Unknown error");
                if (message) {
                    message.textContent = data.data?.message || theRepoPluginAjax.error_refresh_message;
                }
            }
        })
        .catch((error) => {
            console.error("[ERROR] An error occurred during the sync:", error);
            if (message) {
                message.textContent = theRepoPluginAjax.error_refresh_message;
            }
        })
        .finally(() => {
            button.disabled = false;
            button.textContent = theRepoPluginAjax.refresh_button_text; // Restore localized button text
        });
});


document.getElementById('empty-repositories-table').addEventListener('click', function () {
    if (!confirm('Are you sure you want to empty the repositories table? This action cannot be undone.')) {
        return;
    }

    const button = this;
    button.disabled = true;
    button.textContent = 'Emptying...';

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'empty_github_repositories',
            nonce: theRepoPluginAjax.empty_repositories_nonce, // Use localized nonce here
        }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.data.message);
            } else {
                alert(data.data.message || 'Failed to empty the table.');
            }
        })
        .catch(error => {
            console.error('[ERROR] An error occurred:', error);
            alert('An error occurred. Please try again.');
        })
        .finally(() => {
            button.disabled = false;
            button.textContent = 'Empty Repositories Table';
        });
});

