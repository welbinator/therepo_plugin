document.getElementById('github-search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const query = document.getElementById('github-search-query').value.trim();
    searchGitHub(query, 1);
});

function searchGitHub(query, page) {
    const resultsContainer = document.getElementById('github-search-results');
    const paginationContainer = document.getElementById('github-search-pagination');

    resultsContainer.innerHTML = 'Searching...';
    paginationContainer.innerHTML = '';

    console.log('AJAX request payload:', { query, page });
    fetch(`${github_plugin_search.ajax_url}?action=github_plugin_search&query=${encodeURIComponent(query)}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            console.log('Parsed response:', data); // Log the full response for debugging

            if (data.success && data.data.results.length > 0) {
                renderResults(data.data.results, resultsContainer); // Pass resultsContainer
            } else {
                resultsContainer.innerHTML = '<p>No results found.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching results:', error);
            resultsContainer.innerHTML = '<p>Error fetching results. Please try again later.</p>';
        });
}

// Updated renderResults function to accept resultsContainer as a parameter
function renderResults(results, resultsContainer) {
    const resultsHtml = results.map(repo => {
        const pluginFolderName = repo.full_name.split('/')[1]; // Extract folder name

        let buttonHtml;
        if (repo.is_active) {
            buttonHtml = `<button class="deactivate-btn" data-folder="${pluginFolderName}">Deactivate</button>`;
        } else if (repo.is_installed) {
            buttonHtml = `<button class="activate-btn" data-folder="${pluginFolderName}">Activate</button>`;
        } else {
            buttonHtml = `<button class="install-btn" data-repo="${repo.html_url}">Install</button>`;
        }

        return `
            <div class="the-repo_card">
                <div class="content">
                    <h2 class="title">
                        <a href="${repo.html_url}" target="_blank" rel="noopener noreferrer">${repo.full_name}</a>
                    </h2>
                    <p class="description">${repo.description || 'No description available.'}</p>
                    <p class="plugin-website">${repo.homepage ? `<a href="${repo.homepage}" target="_blank" rel="noopener noreferrer" class="link">Visit Plugin Website</a>` : ''}</p>
                    ${buttonHtml}
                </div>
            </div>
        `;
    }).join('');
    resultsContainer.innerHTML = resultsHtml;

    // Rebind event listeners for dynamic buttons
    addEventListeners();
}


function addEventListeners() {
    // Remove existing event listeners to prevent duplication
    document.querySelectorAll('.install-btn, .activate-btn, .deactivate-btn').forEach(button => {
        const clone = button.cloneNode(true);
        button.parentNode.replaceChild(clone, button);
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

                    installButton.textContent = 'Activate';
                    installButton.classList.remove('install-btn');
                    installButton.classList.add('activate-btn');
                    installButton.setAttribute('data-folder', folderName);
                    installButton.disabled = false;

                    console.log('Set data-folder to:', folderName);

                    addEventListeners(); // Rebind listeners for the updated button state
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


