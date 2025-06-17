<?php

if (!function_exists('highlightText')) {
    function highlightText($text, $query, $field = null, $currentField = null) {
        if (!$query || ($field && $field !== $currentField)) {
            return htmlspecialchars($text);
        }
        return preg_replace("/(" . preg_quote($query, '/') . ")/i", '<span class="highlight">$1</span>', htmlspecialchars($text));
    }
}