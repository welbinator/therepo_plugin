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
            // error_log('[DEBUG] Repository Saved: ' . $repo_data['full_name']);
        }
    }
    

    public static function create_table() {
        global $wpdb;
    
        $table_name = $wpdb->prefix . 'github_repositories';
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            repo_id bigint(20) NOT NULL UNIQUE,
            slug varchar(255) NOT NULL,
            full_name varchar(255) NOT NULL,
            html_url varchar(255) NOT NULL,
            description text NULL,
            topics text NULL,
            latest_release_date datetime NULL,
            homepage varchar(255) NULL,
            PRIMARY KEY (id),
            UNIQUE (slug)
        ) $charset_collate;";
    
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    
        // Populate `slug` column for existing rows
        $wpdb->query("
            UPDATE $table_name
            SET slug = SUBSTRING_INDEX(full_name, '/', -1)
            WHERE slug IS NULL OR slug = ''
        ");
    }
    
}
