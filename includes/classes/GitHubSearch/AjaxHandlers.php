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
        $topics = ['wordpress-plugin'];
    
        // Get installed and active plugins
        $installed_plugins = get_plugins(); // All installed plugins
        $active_plugins = get_option('active_plugins', []); // Active plugins
    
        // Start output buffering
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
                    $repo = reset($unique_repo); // Take the first valid result
    
                    // Extract plugin slug and folder
                    $plugin_slug = strtolower(basename($repo['html_url'])); // Repo slug
    
                    // Extended matching logic
                    $repo['is_installed'] = false;
                    $repo['is_active'] = false;
    
                    foreach ($installed_plugins as $plugin_file => $plugin_data) {
                        $plugin_folder_name = strtolower(dirname($plugin_file));
                        $plugin_basename = strtolower(basename($plugin_file, '.php'));
                        $plugin_title = sanitize_title($plugin_data['Name']);
                        $latest_release_zip_name = $this->get_latest_release_zip_name($repo['html_url']);
    
                        // Flexible matching
                        if (
                            $plugin_folder_name === $plugin_slug ||
                            $plugin_basename === $plugin_slug ||
                            strpos($plugin_folder_name, $plugin_slug) !== false || // Partial match for folder name
                            strpos($plugin_title, $plugin_slug) !== false ||     // Partial match for plugin title
                            ($latest_release_zip_name && strpos($plugin_folder_name, $latest_release_zip_name) !== false)
                        ) {
                            $repo['is_installed'] = true;
                            $repo['is_active'] = in_array($plugin_file, $active_plugins);
                            break;
                        }
                    }
    
                    // Add a comma before every result except the first
                    if (!$first) {
                        echo ',';
                    } else {
                        $first = false;
                    }
    
                    echo json_encode($repo);
                    flush(); // Send the current repository to the client
                }
            }
        }
    
        // End the JSON response
        echo ']}}';
        flush();
        die();
    }
    

    function get_latest_release_zip_name($repo_url) {
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
    
}
