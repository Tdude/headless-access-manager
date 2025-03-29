<?php

/**
 * inc/rest-api.php
 */
add_action('rest_api_init', function () {
    register_rest_route('ham/v1', '/user', [
        'methods' => 'GET',
        'callback' => 'ham_get_current_user',
        'permission_callback' => '__return_true'
    ]);
});

function ham_get_current_user(WP_REST_Request $request)
{
    $user = wp_get_current_user();
    if (!$user->exists()) {
        return new WP_Error('not_logged_in', 'User not logged in', ['status' => 403]);
    }

    return [
        'id' => $user->ID,
        'name' => $user->display_name,
        'role' => $user->roles[0]
    ];
}
