<?php

namespace TheRepoPlugin\AjaxHandlers;

use TheRepoPlugin\Database\RepositoryCache;

class AjaxHandlers {
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_empty_github_repositories', [$this, 'empty_github_repositories_table']);
        add_action('wp_ajax_github_plugin_search', [$this, 'handle_ajax']);
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

        // Fetch all installed plugins
        $installed_plugins = get_plugins();

        // Perform the database search
        $results = $this->search_database($search_term);

        // Add `is_installed` flag to each result using `find_plugin_file`
        foreach ($results as &$result) {
            if (!isset($result['slug'])) {
                error_log("[DEBUG] Missing 'slug' in result: " . print_r($result, true));
                $result['is_installed'] = false; // Default to not installed
                continue;
            }

            $plugin_file = find_plugin_file($installed_plugins, $result['slug']);
            $result['is_installed'] = !empty($plugin_file);
        }

        if (empty($results)) {
            wp_send_json_success(['results' => []]); // Return an empty array if no matches are found
        }

        // Send the search results back to the client
        wp_send_json_success(['results' => $results]);
    }

    private function search_database($search_term) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'github_repositories';

        // Search query
        $query = $wpdb->prepare(
            "SELECT slug, full_name, description, topics, html_url, homepage FROM $table_name 
             WHERE full_name LIKE %s 
             OR description LIKE %s 
             OR topics LIKE %s",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    public function empty_github_repositories_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'github_repositories';

        // Validate user permissions
        if (!current_user_can('manage_options')) {
            error_log('[DEBUG] User does not have permission to empty the repositories table.');
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'the-repo-plugin')]);
            return;
        }

        // Validate nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'empty_repositories_nonce')) {
            error_log('[DEBUG] Invalid or missing nonce in the empty_github_repositories_table request.');
            wp_send_json_error(['message' => __('Invalid request. Please refresh the page and try again.', 'the-repo-plugin')]);
            return;
        }

        // Attempt to empty the table
        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        if ($result === false) {
            error_log('[DEBUG] wpdb->query returned false. Possible issue with the table: ' . $table_name);
            error_log('[DEBUG] Last error from wpdb: ' . $wpdb->last_error);
            wp_send_json_error(['message' => __('Failed to empty the GitHub repositories table.', 'the-repo-plugin')]);
        } else {
            error_log('[DEBUG] Successfully emptied the GitHub repositories table.');
            wp_send_json_success(['message' => __('Successfully emptied the GitHub repositories table.', 'the-repo-plugin')]);
        }
    }
}
