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
    
    
    
}
