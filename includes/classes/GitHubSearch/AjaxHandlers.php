<?php 

namespace TheRepoPlugin\AjaxHandlers;

class AjaxHandlers {
    private $github_base_url;
    private $github_headers;
    private $plugin_installer;

    public function __construct($github_base_url, $github_headers, $plugin_installer) {
        $this->github_base_url = $github_base_url;
        $this->github_headers = $github_headers;
        $this->plugin_installer = $plugin_installer;
    }

    function handle_ajax() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            error_log('[DEBUG] Query parameter is missing or empty.');
            wp_send_json_error(['error' => 'Query parameter is missing.']);
        }

        $query = sanitize_text_field($_GET['query']);
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;

        error_log('[DEBUG] Received query: ' . $query . ', Page: ' . $page);

        // Process the API request
        $topics = ['wordpress-plugin'];
        $all_results = [];
        foreach ($topics as $topic) {
            $api_query = urlencode($query) . "+topic:" . $topic;
            $api_url = "{$this->github_base_url}/search/repositories?q=" . $api_query . "&per_page=100";

            $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
            if (is_wp_error($response)) {
                error_log('[DEBUG] GitHub API Error: ' . $response->get_error_message());
                continue;
            }

            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);

            if (!isset($data['items']) || !is_array($data['items'])) {
                error_log('[DEBUG] Invalid or missing "items" in GitHub API response: ' . $response_body);
                continue;
            }

            $all_results = array_merge($all_results, $data['items']);
        }

        $unique_results = array_map('unserialize', array_unique(array_map('serialize', $all_results)));
        $filtered_results = filter_repositories_by_release_date($unique_results, $this->github_headers);

        // Check installed and activation status
        $installed_plugins = get_plugins(); // Get all installed plugins
        $active_plugins = get_option('active_plugins', []); // Get active plugins

        foreach ($filtered_results as &$repo) {
            $plugin_slug = strtolower(basename($repo['html_url'])); // Extract repo slug (folder name)
            $plugin_folder = $plugin_slug . '/' . $plugin_slug . '.php'; // Typical plugin file path format

            if (isset($installed_plugins[$plugin_folder])) {
                $repo['is_installed'] = true;
                $repo['is_active'] = in_array($plugin_folder, $active_plugins);
            } else {
                $repo['is_installed'] = false;
                $repo['is_active'] = false;
            }
        }

        error_log('[DEBUG] Filtered results count: ' . count($filtered_results));

        $paginated_results = array_slice($filtered_results, ($page - 1) * 12, 12);

        wp_send_json([
            'success' => true,
            'data' => [
                'results' => $paginated_results,
                'total_pages' => ceil(count($filtered_results) / 12),
            ],
        ]);
    }
}
