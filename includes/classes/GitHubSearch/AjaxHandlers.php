<?php 

namespace TheRepoPlugin\AjaxHandlers;

use TheRepoPlugin\Database\RepositoryCache;

class AjaxHandlers {
    public function __construct() {
        // The constructor remains empty for now. Registration is handled outside this class.
    }

    public function handle_ajax() {
        // Check if the search term is present
        if (!isset($_POST['search_term']) || empty($_POST['search_term'])) {
            error_log("[ERROR] Missing search term in AJAX request.");
            wp_send_json_error(['message' => 'Missing search term.']);
            return;
        }

        // Sanitize the search term
        $search_term = sanitize_text_field($_POST['search_term']);

        // Perform the GitHub search
        $results = $this->search_github($search_term);

        // Check for errors in the GitHub search
        if (is_wp_error($results)) {
            $error_message = $results->get_error_message();
            error_log("[ERROR] GitHub search failed: $error_message");
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        // Debugging: Log the search results
        // error_log("[DEBUG] GitHub search results: " . print_r($results, true));

        // Send the search results back to the client
        wp_send_json_success(['results' => $results]);
    }

    function search_github($search_term) {
        $date = date('Y-m-d', strtotime('-6 months'));
        $url = "https://api.github.com/search/repositories?q=" . urlencode($search_term) . 
               "+topic:wordpress-plugin+has:releases+pushed:>{$date}";
    
        // error_log("[DEBUG] GitHub API URL: $url");
    
        $response = wp_remote_get($url, [
            'headers' => [
                'User-Agent' => 'TheRepoPlugin',
            ],
        ]);
    
        if (is_wp_error($response)) {
            return $response;
        }
    
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
    
        if (!isset($data['items'])) {
            return new WP_Error('invalid_response', 'Invalid response from GitHub API');
        }
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
        $active_plugins = get_option('active_plugins', []); // Get all active plugins
    
        $results = [];
        foreach ($data['items'] as $item) {
            // Validate releases by fetching the releases URL
            $releases_url = str_replace('{/id}', '', $item['releases_url']);
            $releases_response = wp_remote_get($releases_url, [
                'headers' => [
                    'User-Agent' => 'TheRepoPlugin',
                ],
            ]);
    
            if (is_wp_error($releases_response)) {
                continue; // Skip this repository if the release fetch fails
            }
    
            $releases_body = wp_remote_retrieve_body($releases_response);
            $releases_data = json_decode($releases_body, true);
    
            if (empty($releases_data)) {
                continue; // Skip repositories with no releases
            }
    
            // Determine if the plugin is installed and active
            $repo_name = $item['name'];
            $plugin_installed = false;
            $plugin_active = false;
    
            foreach ($installed_plugins as $plugin_file => $plugin_data) {
                if (strpos($plugin_file, $repo_name) !== false) {
                    $plugin_installed = true;
                    $plugin_active = in_array($plugin_file, $active_plugins, true);
                    break;
                }
            }
    
            $results[] = [
                'name' => $item['name'],
                'full_name' => $item['full_name'],
                'description' => $item['description'],
                'html_url' => $item['html_url'],
                'homepage' => $item['homepage'] ?? null,
                'is_installed' => $plugin_installed,
                'is_active' => $plugin_active,
            ];
        }
    
        return $results;
    }
    
}
