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

        // Perform the database search
        $results = $this->search_database($search_term);

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
            "SELECT * FROM $table_name 
             WHERE full_name LIKE %s 
             OR description LIKE %s 
             OR topics LIKE %s",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        );

        // Execute query
        $results = $wpdb->get_results($query, ARRAY_A);

        return $results;
    }
}
