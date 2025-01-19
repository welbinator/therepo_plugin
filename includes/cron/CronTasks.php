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
    
        // Calculate the date six months ago
        $date_six_months_ago = date('Y-m-d', strtotime('-6 months'));
    
        foreach ($topics as $topic) {
            // Use dynamic date in the query
            $api_url = "$github_base_url/search/repositories?q=topic:$topic+has:releases+pushed:>$date_six_months_ago&per_page=100";
            error_log('[DEBUG] GitHub API URL: ' . $api_url);
    
            $response = wp_remote_get($api_url, ['headers' => $github_headers]);
    
            if (is_wp_error($response)) {
                error_log('[DEBUG] GitHub API Error: ' . $response->get_error_message());
                continue;
            }
    
            $response_body = wp_remote_retrieve_body($response);
            error_log('[DEBUG] GitHub API Response Length: ' . strlen($response_body));
    
            $data = json_decode($response_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[DEBUG] JSON Decode Error: ' . json_last_error_msg());
                continue;
            }
    
            if (!empty($data['items'])) {
                foreach ($data['items'] as $repo) {
                    error_log('[DEBUG] Processing Repository: ' . $repo['full_name']);
    
                    $repo_data = [
                        'repo_id' => $repo['id'],
                        'full_name' => $repo['full_name'],
                        'html_url' => $repo['html_url'],
                        'description' => $repo['description'] ?? '',
                        'topics' => implode(',', $repo['topics'] ?? []),
                        'latest_release_date' => $repo['pushed_at'] ?? null,
                        'homepage' => $repo['homepage'] ?? '',
                    ];
    
                    // Log data before saving
                    error_log('[DEBUG] Repository Data to Save: ' . print_r($repo_data, true));
    
                    // Save to database
                    RepositoryCache::save_repository($repo_data);
                }
            } else {
                error_log('[DEBUG] No items found for topic: ' . $topic);
            }
        }
    }
    
    
    
}
