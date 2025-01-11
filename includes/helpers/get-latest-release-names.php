<?php

function get_latest_release_zip_name($repo_url, $github_headers) {
    error_log("latest release zip name got!");
    $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases/latest';

    $response = wp_remote_get($api_url, ['headers' => $github_headers]);

    if (is_wp_error($response)) {
        error_log('[DEBUG] Failed to fetch latest release for ' . $repo_url . ': ' . $response->get_error_message());
        return null;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($release_data['assets'][0]['name'])) {
        $zip_name = strtolower(pathinfo($release_data['assets'][0]['name'], PATHINFO_FILENAME));
        error_log('[DEBUG] Latest release ZIP name for ' . $repo_url . ': ' . $zip_name);
        return $zip_name;
    }

    error_log('[DEBUG] No ZIP name found for latest release of ' . $repo_url);
    return null;
}
