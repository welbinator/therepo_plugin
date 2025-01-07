<?php

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

        add_shortcode('github_plugin_search', [$this, 'render_shortcode']);
        add_action('wp_ajax_github_plugin_search', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_github_plugin_search', [$this, 'handle_ajax']);
    }

    public function render_shortcode() {
        ob_start(); ?>
        <div id="github-plugin-search">
            <form id="github-search-form">
                <input type="text" id="github-search-query" placeholder="Search for WordPress plugins..." />
                <button type="submit">Search</button>
            </form>
            <div id="github-search-results"></div>
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
                                <div class="repo">
                                    <h3><a href="${repo.html_url}" target="_blank">${repo.full_name}</a></h3>
                                    <p>${repo.description || 'No description available.'}</p>
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
                max-width: 600px;
                margin: 0 auto;
            }
            #github-search-results .repo {
                border: 1px solid #ccc;
                padding: 10px;
                margin-bottom: 10px;
            }
            #github-search-results .repo h3 {
                margin: 0 0 5px;
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

        // Construct the GitHub API search query
        $api_query = "topic:wordpress-plugin OR topic:wordpress";
        if (!empty($query)) {
            $api_query = urlencode($query) . " " . $api_query;
        }
        $api_url = "{$this->github_base_url}/search/repositories?q=" . $api_query . "&per_page=100";

        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
        if (is_wp_error($response)) {
            wp_send_json([]);
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        if (!isset($data['items']) || !is_array($data['items'])) {
            wp_send_json([]);
        }

        $filtered_results = $this->filter_repositories($data['items']);
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
                ];
            }
        }

        return $results;
    }
}

new GitHubPluginSearch();
