document.getElementById('refresh-db-button').addEventListener('click', function () {
    const button = this;
    const message = document.getElementById('refresh-db-message');

    button.disabled = true;
    button.textContent = 'Refreshing...';
    message.textContent = '';

    fetch(theRepoPluginAjax.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'refresh_repositories',
            _ajax_nonce: theRepoPluginAjax.nonce,
        }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                message.textContent = data.message;
            } else {
                message.textContent = data.data.message || 'An error occurred.';
            }
        })
        .catch(error => {
            console.error('Error refreshing repositories:', error);
            message.textContent = 'An error occurred while refreshing the repositories.';
        })
        .finally(() => {
            button.disabled = false;
            button.textContent = 'Refresh Repositories';
        });
});
