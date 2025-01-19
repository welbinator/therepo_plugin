document.getElementById('github-search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const query = document.getElementById('github-search-query').value.trim();
    searchGitHub(query, 1);
});

function searchGitHub(query) {
    const resultsContainer = document.getElementById('github-search-results');
    const searchingMessage = document.getElementById('searching-message');
    const searchButton = document.getElementById('search-button');

    // Show "Searching..." and hide the search button
    searchButton.style.display = 'none';
    searchingMessage.style.display = 'flex';

    // Clear previous results
    resultsContainer.innerHTML = '';

    // Create the data to send via POST
    const formData = new FormData();
    formData.append('action', 'github_plugin_search');
    formData.append('search_term', query);

    fetch(github_plugin_search.ajax_url, {
        method: 'POST',
        body: formData,
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Hide "Searching..." and show the search button
            searchingMessage.style.display = 'none';
            searchButton.style.display = 'inline';

            if (!data.success) {
                // Handle errors returned by the server
                resultsContainer.innerHTML = `<p>${data.data.message}</p>`;
                return;
            }

            const results = data.data.results;

            if (results.length === 0) {
                resultsContainer.innerHTML = '<p>No matching results found. Please try a different search term.</p>';
                return;
            }

            // Render the search results
            results.forEach(repo => renderResult(repo, resultsContainer));
        })
        .catch(error => {
            console.error('[ERROR] Fetch failed:', error);

            // Hide "Searching..." and show the search button
            searchingMessage.style.display = 'none';
            searchButton.style.display = 'inline';

            resultsContainer.innerHTML = '<p>Error fetching results. Please try again later.</p>';
        });
}


function renderResult(repo, resultsContainer) {
    // Safeguard properties and handle missing data
    const repoName = repo.full_name
        ? repo.full_name.split('/')[1]
        : repo.name || 'Unknown Repo';

    // Format the repo name: capitalize each word, remove dashes, and correct "WordPress"
    const formattedName = repoName
        .replace(/-/g, ' ') // Replace dashes with spaces
        .replace(/\b\w+/g, word => word.charAt(0).toUpperCase() + word.slice(1)) // Capitalize each word
        .replace(/wordpress/gi, 'WordPress'); // Ensure "WordPress" capitalization

    const repoHtmlUrl = repo.html_url || '#';
    const repoDescription = repo.description || 'No description available.';
    const repoHomepage = repo.homepage || '';

    const resultHtml = `
        <div class="the-repo_card" data-repo="${repoName}">
            <div class="content">
                <h2 class="title">
                    <a href="${repoHtmlUrl}" target="_blank" rel="noopener noreferrer">${formattedName}</a>
                </h2>
                <p class="description">${repoDescription}</p>
                <p class="plugin-website">${repoHomepage ? `<a href="${repoHomepage}" target="_blank" rel="noopener noreferrer" class="link">Visit Plugin Website</a>` : ''}</p>
            </div>
        </div>
    `;

    resultsContainer.insertAdjacentHTML('beforeend', resultHtml);
}





function addEventListeners() {
    // Remove existing event listeners to prevent duplication
    document.querySelectorAll('.install-btn, .activate-btn, .deactivate-btn, .delete-btn').forEach(button => {
        const clone = button.cloneNode(true);
        button.parentNode.replaceChild(clone, button);
    });
    // Delete button
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const folderName = this.dataset.folder;
            const deleteButton = this;

            console.log('Delete button clicked for folder:', folderName);

            if (!folderName) {
                alert('Error: Missing plugin folder name.');
                return;
            }

            if (!confirm('Are you sure you want to delete this plugin? This action cannot be undone.')) {
                return;
            }

            deleteButton.disabled = true;
            deleteButton.textContent = 'Deleting...';

            fetch(`${github_plugin_search.ajax_url}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'delete_plugin', // PHP handler for deletion
                    slug: folderName,       // Plugin folder name
                }),
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Delete response:', data);

                    if (data.success) {
                        alert(data.message || 'Plugin deleted successfully.');
                        deleteButton.closest('.the-repo_card').remove(); // Remove the card from the DOM
                    } else {
                        alert(data.message || 'An error occurred while deleting the plugin.');
                        deleteButton.textContent = 'Delete';
                    }

                    deleteButton.disabled = false;
                })
                .catch(error => {
                    console.error('Error during deletion:', error);
                    alert('An error occurred while deleting the plugin. Please try again.');
                    deleteButton.textContent = 'Delete';
                    deleteButton.disabled = false;
                });
        });
    });

    // Install button
    document.querySelectorAll('.install-btn').forEach(button => {
        button.addEventListener('click', function () {
            const repoUrl = this.dataset.repo;
            const installButton = this;
    
            console.log('Install button clicked for:', repoUrl);
    
            installButton.disabled = true;
            installButton.textContent = 'Installing...';
    
            fetch(`${github_plugin_search.ajax_url}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'install_github_plugin',
                    repo_url: repoUrl,
                }),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Install response:', data);
                if (data.success) {
                    const folderName = data.data.folder_name;
    
                    if (!folderName) {
                        console.error('Error: folder_name is undefined in the response.');
                        installButton.disabled = false;
                        installButton.textContent = 'Install';
                        return;
                    }
    
                    // Update the card dynamically
                    const repoCard = installButton.closest('.the-repo_card');
    
                    // Find the plugin-actions container and update it
                    const buttonContainer = repoCard.querySelector('.plugin-actions');
                    if (buttonContainer) {
                        buttonContainer.innerHTML = `
                            <a class="plugin-btn activate-btn" data-folder="${folderName}">Activate</a> |
                            <a class="plugin-btn delete-btn" data-folder="${folderName}">Delete</a>
                        `;
                    } else {
                        // If no buttonContainer exists, create it
                        const content = repoCard.querySelector('.content');
                        const newButtonContainer = document.createElement('p');
                        newButtonContainer.classList.add('plugin-actions');
                        newButtonContainer.innerHTML = `
                            <a class="plugin-btn activate-btn" data-folder="${folderName}">Activate</a> |
                            <a class="plugin-btn delete-btn" data-folder="${folderName}">Delete</a>
                        `;
                        content.insertBefore(newButtonContainer, content.querySelector('.description'));
                    }
    
                    // Remove the install button entirely
                    const pluginButtonsContainer = repoCard.querySelector('.plugin-buttons');
                    if (pluginButtonsContainer) {
                        pluginButtonsContainer.innerHTML = '';
                    }
    
                    addEventListeners(); // Rebind event listeners for the new buttons
                } else {
                    alert(data.data.message || 'An error occurred.');
                    installButton.textContent = 'Install';
                    installButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error during installation:', error);
                alert('An error occurred while installing the plugin.');
                installButton.textContent = 'Install';
                installButton.disabled = false;
            });
        });
    });
    

    // Similar cleanup logic for Activate and Deactivate buttons
    document.querySelectorAll('.activate-btn').forEach(button => {
    button.addEventListener('click', function () {
        const folderName = this.dataset.folder;
        const activateButton = this;

        console.log('Activate button clicked for folder:', folderName);

        if (!folderName) {
            alert('Error: Missing plugin folder name.');
            return;
        }

        activateButton.disabled = true;
        activateButton.textContent = 'Activating...';

        // Prepare the request payload
        const requestBody = new URLSearchParams({
            action: 'activate_plugin', // PHP handler for activation
            slug: folderName,         // Plugin folder name
        });

        console.log('Activate request payload:', Object.fromEntries(requestBody));

        // Send the AJAX request
        fetch(`${github_plugin_search.ajax_url}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: requestBody,
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Activate response:', data);

                if (data.success) {
                    activateButton.textContent = 'Deactivate';
                    activateButton.classList.remove('activate-btn');
                    activateButton.classList.add('deactivate-btn');
                } else {
                    const errorMessage = data.message || 'An error occurred.';
                    console.error('Activation failed:', errorMessage);
                    alert(errorMessage);
                    activateButton.textContent = 'Activate';
                }

                activateButton.disabled = false;

                // Rebind event listeners for updated button state
                addEventListeners();
            })
            .catch(error => {
                console.error('Error during activation:', error);
                alert('An error occurred while activating the plugin. Please try again.');
                activateButton.textContent = 'Activate';
                activateButton.disabled = false;
            });
    });
});

    

    document.querySelectorAll('.deactivate-btn').forEach(button => {
        button.addEventListener('click', function () {
            const folderName = this.dataset.folder;
            const deactivateButton = this;

            console.log('Deactivate button clicked for folder:', folderName);

            if (!folderName) {
                alert('Error: Missing plugin folder name.');
                return;
            }

            deactivateButton.disabled = true;
            deactivateButton.textContent = 'Deactivating...';

            fetch(`${github_plugin_search.ajax_url}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'deactivate_plugin',
                    slug: folderName,
                }),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Deactivate response:', data);
                if (data.success) {
                    deactivateButton.textContent = 'Activate';
                    deactivateButton.classList.remove('deactivate-btn');
                    deactivateButton.classList.add('activate-btn');
                } else {
                    alert(data.data.message || 'An error occurred.');
                    deactivateButton.textContent = 'Deactivate';
                }
                deactivateButton.disabled = false;

                addEventListeners(); // Rebind listeners for the updated button state
            })
            .catch(error => {
                console.error('Error during deactivation:', error);
                alert('An error occurred while deactivating the plugin.');
                deactivateButton.textContent = 'Deactivate';
                deactivateButton.disabled = false;
            });
        });
    });
}


