<?php 

namespace TheRepoPlugin\AjaxHandlers;

class AjaxHandlers {
    private $github_base_url;
    private $github_headers;
    private $plugin_installer;
    private $api_call_count = 0; // Counter for API calls

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
        $topics = ['wordpress-plugin'];
    
        // Check if results for this query are cached
        $cache_key = 'github_search_' . md5($query);
        $cached_results = get_transient($cache_key);
    
        // If cached results exist, send them all at once
        if ($cached_results) {
            error_log('[DEBUG] Returning cached results for query: ' . $query);
            echo '{"success": true, "data": {"results": ';
            echo json_encode($cached_results);
            echo '}}';
            die();
        }
    
        // Get installed and active plugins
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
    
        // Initialize results array for caching
        $results = [];
    
        // Start output buffering for streaming
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        ob_implicit_flush(true);
        ob_end_flush();
    
        header('Content-Type: application/json');
        header('X-Accel-Buffering: no'); // Disable buffering on Nginx
        header('Cache-Control: no-cache');
    
        // Begin the JSON response
        echo '{"success": true, "data": {"results": [';
        flush();
    
        $first = true;
    
        foreach ($topics as $topic) {
            $api_query = urlencode($query) . "+topic:" . $topic;
            $api_url = "{$this->github_base_url}/search/repositories?q=" . $api_query . "&per_page=100";
    
            error_log('[DEBUG] GitHub API Query URL: ' . $api_url);
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
    
            foreach ($data['items'] as $repo) {
                $unique_repo = filter_repositories_by_release_date([$repo], $this->github_headers);
    
                if (!empty($unique_repo)) {
                    $repo = reset($unique_repo);
    
                    // Get release info (this can also be cached separately)
                    $latest_release_zip_name = $this->get_latest_release_zip_name($repo['html_url']);
    
                    // Extended matching logic
                    $repo['is_installed'] = false;
                    $repo['is_active'] = false;
    
                    foreach ($installed_plugins as $plugin_file => $plugin_data) {
                        $plugin_folder_name = strtolower(dirname($plugin_file));
                        $plugin_basename = strtolower(basename($plugin_file, '.php'));
                        $plugin_title = sanitize_title($plugin_data['Name']);
    
                        if (
                            $plugin_folder_name === strtolower(basename($repo['html_url'])) ||
                            $plugin_basename === strtolower(basename($repo['html_url'])) ||
                            strpos($plugin_folder_name, strtolower(basename($repo['html_url']))) !== false ||
                            strpos($plugin_title, strtolower(basename($repo['html_url']))) !== false
                        ) {
                            $repo['is_installed'] = true;
                            $repo['is_active'] = in_array($plugin_file, $active_plugins);
                            break;
                        }
                    }
    
                    // Append the result to the cache array
                    $results[] = $repo;
    
                    // Stream each result to the client
                    if (!$first) {
                        echo ',';
                    } else {
                        $first = false;
                    }
                    echo json_encode($repo);
                    flush();
                }
            }
        }
    
        // End the JSON response
        echo ']}}';
        flush();
    
        // Cache the results for future queries
        set_transient($cache_key, $results, 12 * HOUR_IN_SECONDS);
        error_log('[DEBUG] Caching results for query: ' . $query);
    
        die();
    }
    

    function get_latest_release_zip_name($repo_url) {
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases/latest';

        // Log API call for releases
        error_log('[DEBUG] GitHub Release API Query: ' . $api_url);

        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
        $this->api_call_count++; // Increment API call counter

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
}
