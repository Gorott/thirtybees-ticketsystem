<?php


function smarty_modifier_relativetime($time) {
    if (empty($time)) {
        return '';
    }

    $created = new DateTime($time);
    $now = new DateTime();
    $diff = $created->diff($now);

    if ($diff->invert) {
        if ($diff->d > 0) {
            return 'in' . $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return 'in' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return 'in' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        return 'soon';
    } else {
        if ($diff->y > 0 ) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' agp';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        return 'just now';
    }

}