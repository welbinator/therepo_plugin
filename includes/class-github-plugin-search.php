<?php

namespace TheRepoPlugin\GitHubSearch;

class GitHubPluginSearch {

    private $github_base_url = 'https://api.github.com';
    private $github_headers;

    public function __construct() {
        $github_pat = get_option('the_repo_plugin_github_pat', '');
        $this->github_headers = [
            'User-Agent' => 'WordPress GitHub Plugin Search',
        ];
        if (!empty($github_pat)) {
            $this->github_headers['Authorization'] = 'token ' . $github_pat;
        }
    
        add_action('wp_ajax_github_plugin_search', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_github_plugin_search', [$this, 'handle_ajax']);
        add_action('wp_ajax_install_github_plugin', [$this, 'handle_install']);
        add_action('wp_ajax_activate_plugin', [$this, 'handle_activate_plugin']);
        add_action('wp_ajax_deactivate_plugin', [$this, 'handle_deactivate_plugin']);
    }

    public function handle_ajax() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            wp_send_json([]);
        }
    
        $query = sanitize_text_field($_GET['query']);
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $per_page = 12; // Results per page
        $offset = ($page - 1) * $per_page;
    
        // Get the date 6 months ago
        $six_months_ago = date('Y-m-d', strtotime('-6 months'));
    
        // Topics to search for
        $topics = ['wordpress-plugin'];
        $all_results = [];
    
        // Fetch results for each topic
        foreach ($topics as $topic) {
            $api_query = urlencode($query) . "+topic:" . $topic . "+pushed:>=" . $six_months_ago;
            $api_url = "{$this->github_base_url}/search/repositories?q=" . $api_query . "&per_page=100";
    
            $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
            if (is_wp_error($response)) {
                error_log('GitHub API Error for topic ' . $topic . ': ' . $response->get_error_message());
                continue; // Skip this topic if there's an error
            }
    
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            if (!isset($data['items']) || !is_array($data['items'])) {
                error_log("Invalid or missing 'items' in API response for topic: $topic");
                continue;
            }
    
            // Merge the results
            $all_results = array_merge($all_results, $data['items']);
        }
    
        // Remove duplicate repositories
        $unique_results = array_map('unserialize', array_unique(array_map('serialize', $all_results)));
    
        // Apply the filter to repositories
        $filtered_results = $this->filter_repositories($unique_results);
    
        // Check installation and activation status for each plugin
        $installed_plugins = get_plugins(); // Fetch all installed plugins for matching
        foreach ($filtered_results as &$repo) {
            $repo_slug = $repo['full_name']; // Example: 'the-events-calendar/event-tickets'
            $repo_folder = strtolower(explode('/', $repo_slug)[1]); // Extract the folder name.
        
            $repo['is_installed'] = false;
            $repo['is_active'] = false;
        
            // Fetch the latest release data for additional checks
            $repo_url = $repo['html_url'];
            $latest_release_zip_name = $this->get_latest_release_zip_name($repo_url);
        
            foreach ($installed_plugins as $plugin_file => $plugin_data) {
                $plugin_folder = strtolower(dirname($plugin_file));
                $plugin_basename = strtolower(basename($plugin_file, '.php'));
                $plugin_title = sanitize_title($plugin_data['Name']);
        
                // Match against folder name, basename, sanitized title, or latest release ZIP name
                if (
                    $repo_folder === $plugin_folder || 
                    $repo_folder === $plugin_basename || 
                    $repo_folder === $plugin_title || 
                    ($latest_release_zip_name && $plugin_folder === $latest_release_zip_name)
                ) {
                    $repo['is_installed'] = true;
                    $repo['is_active'] = is_plugin_active($plugin_file);
                    break;
                }
            }
        }
        
    
        $paginated_results = array_slice($filtered_results, $offset, $per_page);
    
        $response = [
            'results' => $paginated_results,
            'total_pages' => ceil(count($filtered_results) / $per_page),
        ];
    
        wp_send_json($response);
    }
    
    private function get_latest_release_zip_name($repo_url) {
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases/latest';
    
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('[DEBUG] Failed to fetch latest release for ' . $repo_url . ': ' . $response->get_error_message());
            return null;
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
    
        if (isset($release_data['assets'][0]['name'])) {
            $zip_name = strtolower(pathinfo($release_data['assets'][0]['name'], PATHINFO_FILENAME));
            error_log('[DEBUG] Latest release ZIP name for ' . $repo_url . ': ' . $zip_name);
            return $zip_name;
        }
    
        error_log('[DEBUG] No ZIP name found for latest release of ' . $repo_url);
        return null;
    }
    
    
    public function handle_install() {
        // Check permissions
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to install plugins.', 'the-repo-plugin')]);
        }
    
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        if (empty($repo_url)) {
            wp_send_json_error(['message' => __('Invalid repository URL.', 'the-repo-plugin')]);
        }
    
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', $repo_url) . '/releases/latest';
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('[DEBUG] Failed to fetch release information: ' . $response->get_error_message());
            wp_send_json_error(['message' => __('Failed to fetch release information.', 'the-repo-plugin')]);
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        $zip_url = '';
        $assets = isset($release_data['assets']) ? $release_data['assets'] : [];

        // Search for the first custom ZIP asset
        foreach ($assets as $asset) {
            if (isset($asset['name']) && isset($asset['browser_download_url']) && str_ends_with($asset['name'], '.zip')) {
                $zip_url = $asset['browser_download_url'];
                break;
            }
        }


        if (empty($zip_url)) {
            if (!empty($release_data['zipball_url'])) {
                $zip_url = $release_data['zipball_url'];
                error_log('[DEBUG] Falling back to Source code (zip) for the latest release.');
            } else {
                error_log('[DEBUG] No downloadable ZIP found for the latest release.');
                wp_send_json_error(['message' => __('No downloadable ZIP found for the latest release.', 'the-repo-plugin')]);
            }
        }

    
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            error_log('[DEBUG] Failed to initialize filesystem.');
            wp_send_json_error(['message' => __('Failed to initialize filesystem.', 'the-repo-plugin')]);
        }
    
        $temp_file = download_url($zip_url);
    
        if (is_wp_error($temp_file)) {
            error_log('[DEBUG] Failed to download the plugin ZIP: ' . $temp_file->get_error_message());
            wp_send_json_error(['message' => __('Failed to download the plugin ZIP.', 'the-repo-plugin')]);
        }
    
        $temp_dir = WP_CONTENT_DIR . '/uploads/temp-plugin-extract';
        if (!wp_mkdir_p($temp_dir)) {
            unlink($temp_file);
            error_log('[DEBUG] Failed to create temporary extraction directory.');
            wp_send_json_error(['message' => __('Failed to create temporary extraction directory.', 'the-repo-plugin')]);
        }
    
        $unzip_result = unzip_file($temp_file, $temp_dir);
        if (is_wp_error($unzip_result)) {
            unlink($temp_file);
            $wp_filesystem->delete($temp_dir, true);
            error_log('[DEBUG] Failed to extract the plugin ZIP: ' . $unzip_result->get_error_message());
            wp_send_json_error(['message' => __('Failed to extract the plugin ZIP.', 'the-repo-plugin')]);
        }
    
        $extracted_folders = array_diff(scandir($temp_dir), ['.', '..']);
        error_log('[DEBUG] Extracted folders: ' . print_r($extracted_folders, true));

        if (empty($extracted_folders)) {
            unlink($temp_file);
            $wp_filesystem->delete($temp_dir, true);
            error_log('[DEBUG] No files found in the plugin ZIP.');
            wp_send_json_error(['message' => __('No files found in the ZIP.', 'the-repo-plugin')]);
        }

        // Check if the extracted ZIP has a root folder
        if (count($extracted_folders) === 1 && is_dir($temp_dir . '/' . reset($extracted_folders))) {
            // Proper root folder exists
            $plugin_folder_name = reset($extracted_folders);
        } else {
            // No root folder - Create one
            $plugin_folder_name = sanitize_file_name(basename($temp_file, '.zip'));
            $new_plugin_dir = $temp_dir . '/' . $plugin_folder_name;

            if (!mkdir($new_plugin_dir, 0755)) {
                unlink($temp_file);
                $wp_filesystem->delete($temp_dir, true);
                error_log('[DEBUG] Failed to create root folder for plugin.');
                wp_send_json_error(['message' => __('Failed to create root folder for plugin.', 'the-repo-plugin')]);
            }

            // Move all extracted files into the new root folder
            foreach ($extracted_folders as $file) {
                rename($temp_dir . '/' . $file, $new_plugin_dir . '/' . $file);
            }
        }

        // Verify the new plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_folder_name;

        if (!rename($temp_dir . '/' . $plugin_folder_name, $plugin_dir)) {
            unlink($temp_file);
            $wp_filesystem->delete($temp_dir, true);
            error_log('[DEBUG] Failed to move plugin to the plugins directory.');
            wp_send_json_error(['message' => __('Failed to move plugin to the plugins directory.', 'the-repo-plugin')]);
        }

        // Clean up temporary files
        $wp_filesystem->delete($temp_dir, true);
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        if (!is_dir($plugin_dir)) {
            error_log('[DEBUG] Plugin directory was not created: ' . $plugin_folder_name);
            wp_send_json_error(['message' => __('Plugin installation failed.', 'the-repo-plugin')]);
        }

        error_log('[DEBUG] Successfully installed plugin. Folder name: ' . $plugin_folder_name);

        wp_send_json_success([
            'message' => __('Plugin installed successfully! Please activate it from the Plugins page.', 'the-repo-plugin'),
            'folder_name' => $plugin_folder_name,
        ]);

    }
    
    
    public function handle_activate_plugin() {
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to activate plugins.', 'the-repo-plugin')]);
        }
    
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        if (empty($repo_slug)) {
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Attempting to activate plugin with slug: ' . $repo_slug);
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
    
        // Match plugin file by folder name, basename, or slug
        $plugin_file = $this->find_plugin_file($installed_plugins, $repo_slug);
    
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Found plugin file for activation: ' . $plugin_file);
    
        // Attempt to activate the plugin
        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            error_log('[DEBUG] Activation error: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
    
        error_log('[DEBUG] Plugin activated successfully: ' . $plugin_file);
        wp_send_json_success(['message' => __('Plugin activated successfully.', 'the-repo-plugin')]);
    }
    
    
    public function handle_deactivate_plugin() {
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to deactivate plugins.', 'the-repo-plugin')]);
        }
    
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        if (empty($repo_slug)) {
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Attempting to deactivate plugin with slug: ' . $repo_slug);
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
    
        // Match plugin file by folder name, basename, or slug
        $plugin_file = $this->find_plugin_file($installed_plugins, $repo_slug);
    
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        deactivate_plugins($plugin_file);
    
        if (!is_plugin_active($plugin_file)) {
            error_log('[DEBUG] Plugin deactivated successfully: ' . $plugin_file);
            wp_send_json_success(['message' => __('Plugin deactivated successfully.', 'the-repo-plugin')]);
        } else {
            wp_send_json_error(['message' => __('Failed to deactivate the plugin.', 'the-repo-plugin')]);
        }
    }
    
    
    private function find_plugin_file($installed_plugins, $repo_slug) {
        error_log('[DEBUG] Searching for plugin file with slug: ' . $repo_slug);
    
        // Normalize slug for matching
        $normalized_slug = strtolower($repo_slug);
    
        foreach ($installed_plugins as $file => $data) {
            $plugin_folder = strtolower(dirname($file));
            $plugin_basename = strtolower(basename($file, '.php'));
            $plugin_title = sanitize_title($data['Name']);
    
            error_log("[DEBUG] Checking plugin: Folder: $plugin_folder, Basename: $plugin_basename, Title: $plugin_title");
    
            // Match against folder name, basename, or sanitized title
            if (
                $plugin_folder === $normalized_slug || 
                $plugin_basename === $normalized_slug || 
                $plugin_title === $normalized_slug
            ) {
                error_log('[DEBUG] Match found: ' . $file);
                return $file;
            }
    
            // Check if the slug is contained within the folder name or title
            if (
                strpos($plugin_folder, $normalized_slug) !== false || 
                strpos($plugin_title, $normalized_slug) !== false
            ) {
                error_log('[DEBUG] Partial match found: ' . $file);
                return $file;
            }
        }
    
        // Fallback: Try matching latest release ZIP name
        $latest_release_zip_name = $this->get_latest_release_zip_name("https://github.com/$repo_slug");
        if ($latest_release_zip_name) {
            $normalized_zip_name = strtolower($latest_release_zip_name);
            foreach ($installed_plugins as $file => $data) {
                $plugin_folder = strtolower(dirname($file));
                if (
                    $plugin_folder === $normalized_zip_name || 
                    strpos($plugin_folder, $normalized_zip_name) !== false
                ) {
                    error_log('[DEBUG] Match found using ZIP name: ' . $file);
                    return $file;
                }
            }
        }
    
        error_log('[DEBUG] No match found for slug: ' . $repo_slug);
        return false;
    }
    
    
    

    public function render_form() {
        ?>
        <div id="github-plugin-search">
            <form id="github-search-form">
                <input type="text" id="github-search-query" placeholder="Search for WordPress plugins..." />
                <button type="submit">Search</button>
            </form>
            <div id="github-search-results" class="grid"></div>
            <div id="github-search-pagination"></div>
        </div>
    
        <script>
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

    fetch(`<?php echo admin_url('admin-ajax.php'); ?>?action=github_plugin_search&query=${encodeURIComponent(query)}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.results.length > 0) {
                const resultsHtml = data.results.map(repo => {
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

                addEventListeners();
            } else {
                resultsContainer.innerHTML = '<p>No results found.</p>';
            }
        })
        .catch(error => {
            console.error(error);
            resultsContainer.innerHTML = '<p>Error fetching results. Please try again later.</p>';
        });
}


function addEventListeners() {
    // Clear existing listeners to prevent duplication
    document.querySelectorAll('.install-btn, .activate-btn, .deactivate-btn').forEach(button => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
    });

    // Install button
    document.querySelectorAll('.install-btn').forEach(button => {
        button.addEventListener('click', function () {
            const repoUrl = this.dataset.repo;
            const installButton = this;

            installButton.disabled = true;
            installButton.textContent = 'Installing...';

            fetch(`<?php echo admin_url('admin-ajax.php'); ?>`, {
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
        console.log('Install response:', data); // Debugging log
        if (data.success) {
            const folderName = data.data.folder_name; // Correctly access folder_name from data.data

            if (!folderName) {
                console.error('Error: folder_name is undefined in the response.');
                installButton.disabled = false;
                installButton.textContent = 'Install';
                return;
            }

            // Update button to "Activate" with the correct folder name
            installButton.textContent = 'Activate';
            installButton.classList.remove('install-btn');
            installButton.classList.add('activate-btn');
            installButton.setAttribute('data-folder', folderName);
            installButton.disabled = false;

            console.log('Set data-folder to:', folderName);

            addEventListeners(); // Rebind listeners for updated button state
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


    // Activate button
    document.querySelectorAll('.activate-btn').forEach(button => {
        button.addEventListener('click', function () {
            const folderName = this.dataset.folder;
            const activateButton = this;

            console.log('Activate button clicked. Folder name:', folderName);

            if (!folderName) {
                alert('Error: Missing plugin folder name.');
                return;
            }

            activateButton.disabled = true;
            activateButton.textContent = 'Activating...';

            fetch(`<?php echo admin_url('admin-ajax.php'); ?>`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'activate_plugin',
                    slug: folderName,
                }),
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Activate response:', data); // Debugging log
                    if (data.success) {
                        activateButton.textContent = 'Deactivate';
                        activateButton.classList.remove('activate-btn');
                        activateButton.classList.add('deactivate-btn');
                    } else {
                        alert(data.data.message || 'An error occurred.');
                        activateButton.textContent = 'Activate';
                    }
                    activateButton.disabled = false;

                    addEventListeners(); // Rebind listeners for updated state
                })
                .catch(error => {
                    console.error('Error during activation:', error);
                    alert('An error occurred while activating the plugin.');
                    activateButton.textContent = 'Activate';
                    activateButton.disabled = false;
                });
        });
    });

    // Deactivate button
    document.querySelectorAll('.deactivate-btn').forEach(button => {
        button.addEventListener('click', function () {
            const folderName = this.dataset.folder;
            const deactivateButton = this;

            console.log('Deactivate button clicked. Folder name:', folderName);

            if (!folderName) {
                alert('Missing plugin folder name.');
                return;
            }

            deactivateButton.disabled = true;
            deactivateButton.textContent = 'Deactivating...';

            fetch(`<?php echo admin_url('admin-ajax.php'); ?>`, {
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
                    console.log('Deactivate response:', data); // Debugging log
                    if (data.success) {
                        deactivateButton.textContent = 'Activate';
                        deactivateButton.classList.remove('deactivate-btn');
                        deactivateButton.classList.add('activate-btn');
                    } else {
                        alert(data.data.message || 'An error occurred.');
                        deactivateButton.textContent = 'Deactivate';
                    }
                    deactivateButton.disabled = false;

                    addEventListeners(); // Rebind listeners for updated state
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



function generatePagination(totalPages, currentPage, query) {
    let html = '';

    if (currentPage > 1) {
        html += `<a href="#" class="github-page-link" data-page="${currentPage - 1}">Previous</a>`;
    }

    for (let i = 1; i <= totalPages; i++) {
        html += `<a href="#" class="github-page-link ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
    }

    if (currentPage < totalPages) {
        html += `<a href="#" class="github-page-link" data-page="${currentPage + 1}">Next</a>`;
    }

    return html;
}


        </script>
        <style>
            #github-plugin-search {
                max-width: 95%;
                margin: 20px 0;
            }
            #github-search-results {
                margin-top: 50px;
            }
            #github-search-pagination {
                margin-top: 20px;
            }
            #github-search-pagination a {
                margin: 0 5px;
                text-decoration: none;
                padding: 5px 10px;
                border: 1px solid #ccc;
                display: inline-block;
                color: #000;
            }
            #github-search-pagination a.active {
                font-weight: bold;
                background-color: #f0f0f0;
            }
            .grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }
            .the-repo_card {
                background-color: white;
                overflow: hidden;
                padding: .75rem;
                border: 1px solid #cdcdcd;
            }
            .content {
                padding: .5rem;
            }
            .title a {
                font-size: 1.25rem;
                font-weight: 600;
                text-decoration: none;
                color: #000;
            }
            .title a:hover {
                text-decoration: underline;
            }
            .description {
                color: #4a5568; /* Gray color */
                margin-bottom: 1rem;
            }
            .link {
                color: #3182ce; /* Blue color */
                text-decoration: none;
                transition: color 0.2s ease-in-out;
            }
            .link:hover {
                color: #2b6cb0; /* Darker blue color */
            }
            .install-btn {
                display: inline-block;
                margin-top: 1rem;
                padding: 0.5rem 1rem;
                background-color: #3182ce;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            .install-btn:hover {
                background-color: #2b6cb0;
            }
        </style>
        <?php
        return ob_get_clean();
    }


    public function filter_repositories($repositories) {
        $results = [];
        $cached_releases = [];
    
        foreach ($repositories as $repo) {
            if (isset($repo['topics']) && in_array('wordpress-plugin', $repo['topics'])) {
                $repo_url = $repo['html_url'];
    
                // Check if releases are cached
                $cache_key = 'repo_releases_' . md5($repo_url);
                if (isset($cached_releases[$repo_url])) {
                    $has_releases = $cached_releases[$repo_url];
                } else {
                    $cached = get_transient($cache_key);
                    if ($cached !== false) {
                        $has_releases = $cached;
                    } else {
                        // If not cached, check for releases
                        $has_releases = $this->has_releases($repo_url);
                        set_transient($cache_key, $has_releases, DAY_IN_SECONDS);
                    }
                    $cached_releases[$repo_url] = $has_releases;
                }
    
                if ($has_releases) {
                    $results[] = [
                        'full_name'   => $repo['full_name'],
                        'html_url'    => $repo['html_url'],
                        'description' => $repo['description'] ?: 'No description available.',
                        'homepage'    => !empty($repo['homepage']) ? esc_url($repo['homepage']) : null,
                    ];
                }
            }
        }
    
        return $results;
    }
    
    
    private function has_releases($repo_url) {
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases';
    
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('GitHub API error for releases: ' . $response->get_error_message());
            return false;
        }
    
        $releases = json_decode(wp_remote_retrieve_body($response), true);
    
        // Return true if releases exist and are valid
        return !empty($releases) && is_array($releases);
    }
    
    

     
 
}



new GitHubPluginSearch();
