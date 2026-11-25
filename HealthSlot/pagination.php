<?php
// Shared pagination bar. Keeps the current filters in the page links.
function renderPagination($page, $pages, $total, $perPage) {
    if ($total === 0) { return; }

    $from = ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);

    $link = function ($p) {
        $q = $_GET;
        $q['page'] = $p;
        return '?' . http_build_query($q);
    };

    echo '<div class="pager">';
    echo '<span class="muted">Showing ' . $from . '-' . $to . ' of ' . $total . '</span>';

    if ($pages > 1) {
        echo '<div class="pager-links">';
        if ($page > 1) {
            echo '<a class="btn btn-outline" href="' . htmlspecialchars($link($page - 1)) . '">Previous</a>';
        }
        $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $cls = ($i === $page) ? 'btn' : 'btn btn-outline';
            echo '<a class="' . $cls . '" href="' . htmlspecialchars($link($i)) . '">' . $i . '</a>';
        }
        if ($page < $pages) {
            echo '<a class="btn btn-outline" href="' . htmlspecialchars($link($page + 1)) . '">Next</a>';
        }
        echo '</div>';
    }

    echo '</div>';
}
