<?php

namespace TheRepoPlugin\Database;

class RepositoryCache {
    private static $table_name;

    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'github_repositories';
    }

    public static function search_repositories($query) {
        global $wpdb;
        self::init();
    
        // Check if the table is empty
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_name);
        if ($row_count == 0) {
            return false; // Indicate the database has no data
        }
    
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . " WHERE full_name LIKE %s OR description LIKE %s",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            )
        );
    }
    

    public static function save_repository($repo_data) {
        global $wpdb;
        self::init();
    
        // Attempt to insert or update the repository
        $result = $wpdb->replace(self::$table_name, $repo_data);
        if ($result === false) {
            error_log('[DEBUG] Database Insert Error: ' . $wpdb->last_error);
        } else {
            error_log('[DEBUG] Repository Saved: ' . $repo_data['full_name']);
        }
    }
    

    public static function create_table() {
        global $wpdb;
        self::init();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . self::$table_name . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            repo_id BIGINT(20) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            html_url VARCHAR(255) NOT NULL,
            description TEXT,
            topics TEXT,
            latest_release_date DATETIME DEFAULT NULL,
            homepage VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY repo_id (repo_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
