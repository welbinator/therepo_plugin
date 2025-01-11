<?php

namespace TheRepoPlugin\RenderForm;

class GitHubSearchUI {

    function render_form() {
        
        ?>
        <div id="github-plugin-search">
            <form id="github-search-form">
                <input type="text" id="github-search-query" placeholder="Search for WordPress plugins..." />
                <button type="submit">Search</button>
            </form>
            <div id="github-search-results" class="grid"></div>
            <div id="github-search-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
