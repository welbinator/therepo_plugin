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
        add_action('wp_ajax_install_github_plugin', [$this, 'handle_install']); // New action for installation
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
                const resultsHtml = data.results.map(repo => `
                    <div class="the-repo_card">
                        <div class="content">
                            <h2 class="title">
                                <a href="${repo.html_url}" target="_blank" rel="noopener noreferrer">${repo.full_name}</a>
                            </h2>
                            <p class="description">${repo.description || 'No description available.'}</p>
                            <p class="plugin-website">${repo.homepage ? `<a href="${repo.homepage}" target="_blank" rel="noopener noreferrer" class="link">Visit Plugin Website</a>` : ''}</p>
                            <button class="install-btn" data-repo="${repo.html_url}">Install</button>
                        </div>
                    </div>
                `).join('');

                resultsContainer.innerHTML = resultsHtml;

                // Generate pagination
                const paginationHtml = generatePagination(data.total_pages, page, query);
                paginationContainer.innerHTML = paginationHtml;

                // Add event listeners for pagination buttons
                document.querySelectorAll('.github-page-link').forEach(link => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        const newPage = parseInt(this.getAttribute('data-page'));
                        searchGitHub(query, newPage);
                    });
                });

                // Add click events to install buttons
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
                                if (data.success) {
                                    alert(data.data.message);
                                } else {
                                    alert(data.data.message);
                                }
                                installButton.disabled = false;
                                installButton.textContent = 'Install';
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while installing the plugin.');
                                installButton.disabled = false;
                                installButton.textContent = 'Install';
                            });
                    });
                });
            } else {
                resultsContainer.innerHTML = '<p>No results found.</p>';
            }
        })
        .catch(error => {
            console.error(error);
            resultsContainer.innerHTML = '<p>Error fetching results. Please try again later.</p>';
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
    

    public function handle_ajax() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            wp_send_json([]);
        }
    
        $query = sanitize_text_field($_GET['query']);
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $per_page = 12; // Results per page
        $offset = ($page - 1) * $per_page;
    
        // Topics to search for
        $topics = ['wordpress-plugin', 'wordpress'];
        $all_results = [];
    
        // Fetch results for each topic
        foreach ($topics as $topic) {
            $api_query = urlencode($query) . "+topic:" . $topic;
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
        $paginated_results = array_slice($filtered_results, $offset, $per_page);
      
        $response = [
            'results' => $paginated_results,
            'total_pages' => ceil(count($filtered_results) / $per_page),
        ];
    
        wp_send_json($response);
    }
    
    

    public function filter_repositories($repositories) {
        $results = [];
    
        foreach ($repositories as $repo) {
            if (isset($repo['topics']) && (in_array('wordpress', $repo['topics']) || in_array('wordpress-plugin', $repo['topics']))) {
                $results[] = [
                    'full_name'   => $repo['full_name'],
                    'html_url'    => $repo['html_url'],
                    'description' => $repo['description'] ?: 'No description available.',
                    'homepage'    => !empty($repo['homepage']) ? esc_url($repo['homepage']) : null, // Add homepage
                ];
            }
        }
    
        return $results;
    }

    public function handle_install() {
        // Check permissions
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to install plugins.', 'the-repo-plugin')]);
        }
    
        // Get repo URL from AJAX request
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        if (empty($repo_url)) {
            wp_send_json_error(['message' => __('Invalid repository URL.', 'the-repo-plugin')]);
        }
    
        // Fetch the latest release
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', $repo_url) . '/releases/latest';
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('GitHub API Error: ' . $response->get_error_message());
            wp_send_json_error(['message' => __('Failed to fetch release information.', 'the-repo-plugin')]);
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($release_data['assets'][0]['browser_download_url'])) {
            wp_send_json_error(['message' => __('No downloadable ZIP found for the latest release.', 'the-repo-plugin')]);
        }
    
        $zip_url = $release_data['assets'][0]['browser_download_url'];
    
        // Include required WordPress files
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    
        // Initialize the filesystem
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            wp_send_json_error(['message' => __('Failed to initialize filesystem.', 'the-repo-plugin')]);
        }
    
        // Download the ZIP file
        $temp_file = download_url($zip_url);
    
        if (is_wp_error($temp_file)) {
            error_log('Error downloading ZIP file: ' . $temp_file->get_error_message());
            wp_send_json_error(['message' => __('Failed to download the plugin ZIP.', 'the-repo-plugin')]);
        }
    
        // Use Plugin_Upgrader to handle installation
        $upgrader = new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin());

        // Perform the installation
        $result = $upgrader->install($zip_url);
    
        // Clean up temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    
        if (is_wp_error($result)) {
            error_log('Error during plugin installation: ' . $result->get_error_message());
            wp_send_json_error(['message' => __('Failed to install the plugin.', 'the-repo-plugin')]);
        }
    
        // Check if the plugin was installed successfully
        if (!$result) {
            wp_send_json_error(['message' => __('Plugin installation failed. Please try again.', 'the-repo-plugin')]);
        }
    
        wp_send_json_success(['message' => __('Plugin installed successfully! Please activate it from the Plugins page.', 'the-repo-plugin')]);
    }
    
    
    
}



new GitHubPluginSearch();
