<?php

function has_releases($repo_url, $github_headers) {
    
    $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases';

    $response = wp_remote_get($api_url, ['headers' => $github_headers]);

    if (is_wp_error($response)) {
        error_log('GitHub API error for releases: ' . $response->get_error_message());
        return false;
    }

    $releases = json_decode(wp_remote_retrieve_body($response), true);

    // Return true if releases exist and are valid
    return !empty($releases) && is_array($releases);
}