document.getElementById('github-search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const query = document.getElementById('github-search-query').value.trim();
    searchGitHub(query, 1);
});

function searchGitHub(query, page) {
    const resultsContainer = document.getElementById('github-search-results');
    const searchingMessage = document.getElementById('searching-message');
    const searchButton = document.getElementById('search-button');
    const paginationContainer = document.getElementById('github-search-pagination');

    // Show "Searching..." and hide the search button
    searchButton.style.display = 'none';
    searchingMessage.style.display = 'flex';

    // Clear previous results
    resultsContainer.innerHTML = '';
    paginationContainer.innerHTML = '';

    fetch(`${github_plugin_search.ajax_url}?action=github_plugin_search&query=${encodeURIComponent(query)}&page=${page}`)
        .then(response => {
            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");
            let buffer = "";
            let isFirstChunk = true;

            function processChunk({ done, value }) {
                if (done) {
                    console.log("Stream complete");

                    document.querySelectorAll('.plugin-btn').forEach(button => {
                        button.removeAttribute('disabled');
                    });

                    // Hide "Searching..." and show the search button
                    searchingMessage.style.display = 'none';
                    searchButton.style.display = 'inline';

                    // Show "No results found" if nothing was rendered
                    if (!resultsContainer.innerHTML) {
                        resultsContainer.innerHTML = '<p>No results found.</p>';
                    }
                    return; // Exit the loop
                }

                if (value) {
                    buffer += decoder.decode(value, { stream: true });

                    // Handle the first chunk to remove static JSON parts
                    if (isFirstChunk) {
                        buffer = buffer.replace(/^\{"success": true, "data": {"results": \[/, '');
                        isFirstChunk = false;
                    }

                    // Remove closing JSON part if it exists
                    buffer = buffer.replace(/\]}}$/, '');

                    // Parse and render complete JSON objects
                    let startIdx = buffer.indexOf("{");
                    while (startIdx >= 0) {
                        let endIdx = buffer.indexOf("}", startIdx);
                        if (endIdx < 0) break; // Wait for more data if no closing brace is found

                        const jsonString = buffer.substring(startIdx, endIdx + 1);
                        try {
                            const repo = JSON.parse(jsonString);
                            renderResult(repo, resultsContainer); // Render individual result
                        } catch (e) {
                            console.error("Error parsing JSON part:", jsonString);
                        }

                        buffer = buffer.substring(endIdx + 1); // Move to the next part
                        startIdx = buffer.indexOf("{");
                    }
                }

                // Continue reading the next chunk
                return reader.read().then(processChunk);
            }

            return reader.read().then(processChunk);
        })
        .catch(error => {
            console.error("Error fetching results:", error);

            // Hide "Searching..." and show the search button
            searchingMessage.style.display = 'none';
            searchButton.style.display = 'inline';

            resultsContainer.innerHTML = '<p>Error fetching results. Please try again later.</p>';
        });
}


function renderResult(repo, resultsContainer) {
    // Extract the repo name (after the slash)
    const repoName = repo.full_name.split('/')[1];

    // Format the repo name: capitalize each word, remove dashes, and correct "WordPress"
    const formattedName = repoName
        .replace(/-/g, ' ') // Replace dashes with spaces
        .replace(/\b\w+/g, word => word.charAt(0).toUpperCase() + word.slice(1)) // Capitalize each word
        .replace(/wordpress/gi, 'WordPress'); // Ensure "WordPress" capitalization

    let buttonHtml;
    if (repo.is_active) {
        buttonHtml = `<a class="plugin-btn deactivate-btn" disabled data-folder="${repoName}">Deactivate</a> | <a class="plugin-btn delete-btn" disabled data-folder="${repoName}">Delete</a>`;
    } else if (repo.is_installed) {
        buttonHtml = `<a class="plugin-btn activate-btn" disabled data-folder="${repoName}">Activate</a> | <a class="plugin-btn delete-btn" disabled data-folder="${repoName}">Delete</a>`;
    } else {
        buttonHtml = `<button class="plugin-btn install-btn" disabled data-repo="${repo.html_url}">Install</button>`;
    }

    const resultHtml = `
        <div class="the-repo_card">
            <div class="content">
                <h2 class="title">
                    <a href="${repo.html_url}" target="_blank" rel="noopener noreferrer">${formattedName}</a>
                </h2>
                <p class="description">${repo.description || 'No description available.'}</p>
                <p class="plugin-website">${repo.homepage ? `<a href="${repo.homepage}" target="_blank" rel="noopener noreferrer" class="link">Visit Plugin Website</a>` : ''}</p>
                ${buttonHtml}
            </div>
        </div>
    `;
    resultsContainer.insertAdjacentHTML("beforeend", resultHtml);

    // Rebind event listeners for dynamic buttons
    addEventListeners();
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


