<?php

function filter_repositories_by_release_date($repositories, $github_headers) {
    error_log("repos filtered by releaes date!");
    $results = [];
    $six_months_ago = strtotime('-6 months');

    foreach ($repositories as $repo) {
        if (isset($repo['topics']) && in_array('wordpress-plugin', $repo['topics'])) {
            $repo_url = $repo['html_url'];

            // Fetch latest release
            $latest_release = get_latest_release_data($repo_url, $github_headers);
            if ($latest_release && isset($latest_release['created_at'])) {
                $release_date = strtotime($latest_release['created_at']);
                if ($release_date >= $six_months_ago) {
                    $results[] = [
                        'full_name'   => $repo['full_name'],
                        'html_url'    => $repo['html_url'],
                        'description' => $repo['description'] ?: 'No description available.',
                        'homepage'    => !empty($repo['homepage']) ? esc_url($repo['homepage']) : null,
                    ];
                }
            }
        }
    }

    return $results;
}