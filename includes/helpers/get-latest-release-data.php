<?php 

function get_latest_release_data($repo_url , $github_headers) {
    $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases/latest';
    $response = wp_remote_get($api_url, ['headers' => $github_headers]);

    if (is_wp_error($response)) {
        error_log('GitHub API error for releases: ' . $response->get_error_message());
        return null;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($release_data['created_at'])) {
        // error_log('[DEBUG] Latest release date for ' . $repo_url . ': ' . $release_data['created_at']);
    } else {
        error_log('[DEBUG] No releases found for ' . $repo_url);
    }

    return $release_data;
}