<?php 

namespace TheRepoPlugin\CronTasks;

use TheRepoPlugin\Database\RepositoryCache;

class CronTasks {
    public static function schedule_sync() {
        if (!wp_next_scheduled('sync_github_repositories')) {
            wp_schedule_event(time(), 'daily', 'sync_github_repositories');
        }
    }

    public static function unschedule_sync() {
        $timestamp = wp_next_scheduled('sync_github_repositories');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'sync_github_repositories');
        }
    }

    public static function sync_repositories_to_db() {
        error_log('[DEBUG] Running sync_repositories_to_db...');
        $github_base_url = 'https://api.github.com';
        $github_headers = [
            'Authorization' => 'Bearer ' . get_option('the_repo_plugin_github_pat', ''),
            'User-Agent' => 'WordPress GitHub Plugin Search',
        ];
        $topics = ['wordpress-plugin'];
    
        $date_six_months_ago = date('Y-m-d', strtotime('-6 months'));
    
        foreach ($topics as $topic) {
            $page = 1;
            $per_page = 100;
            $has_more_results = true;
    
            while ($has_more_results) {
                $api_url = "$github_base_url/search/repositories?q=topic:$topic+has:releases+pushed:>$date_six_months_ago&per_page=$per_page&page=$page";
    
                $response = wp_remote_get($api_url, ['headers' => $github_headers]);
    
                if (is_wp_error($response)) {
                    error_log('[DEBUG] GitHub API Error: ' . $response->get_error_message());
                    break;
                }
    
                $response_body = wp_remote_retrieve_body($response);
    
                $data = json_decode($response_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('[DEBUG] JSON Decode Error: ' . json_last_error_msg());
                    break;
                }
    
                if (!empty($data['items'])) {
                    foreach ($data['items'] as $repo) {
                        $slug = self::derive_plugin_slug($repo);
    
                        $repo_data = [
                            'repo_id' => $repo['id'],
                            'full_name' => $repo['full_name'],
                            'html_url' => $repo['html_url'],
                            'description' => $repo['description'] ?? '',
                            'topics' => implode(',', $repo['topics'] ?? []),
                            'latest_release_date' => $repo['pushed_at'] ?? null,
                            'homepage' => $repo['homepage'] ?? '',
                            'slug' => $slug,
                        ];
    
                        // Save to database
                        RepositoryCache::save_repository($repo_data);
                    }
    
                    $page++;
                } else {
                    $has_more_results = false;
                }
            }
        }
    }
    
    /**
     * Derive the plugin slug based on the GitHub repository.
     *
     * @param array $repo Repository data from GitHub.
     * @return string Derived slug.
     */
    private static function derive_plugin_slug($repo) {
        // Derive slug from the repo name
        $slug = explode('/', $repo['full_name'])[1]; // Default to the repository name
    
        // Check if the repo has a `readme.txt` or main plugin file
        // You could extend this logic with an API call to check repo contents, if necessary
    
        // Final sanitization
        return sanitize_title($slug);
    }
    
    
}
