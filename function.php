<?php
/*
Plugin Name: Custom Code By Owais Sheikh
Description: Custom Code For Customise Any Functionality By Owais Sheikh
Version: 1.0
Author: Owais Sheikh
*/

function custom_woocommerce_login_errors($user, $username, $password) {
    // Check if the username/email is empty
    if (empty($username)) {
        $error = new WP_Error();
        $error->add('empty_username', 'Invalid Username/email or password');
        return $error;
    }

    // Determine if the input is an email address or a username
    if (is_email($username)) {
        // If it's an email, get the user by email
        $user = get_user_by('email', $username);
    } else {
        // Otherwise, get the user by username
        $user = get_user_by('login', $username);
    }

    // Check if the user exists
    if (!$user) {
        $error = new WP_Error();
        $error->add('invalid_username', 'Invalid Username/email or password');
        return $error;
    }

    // Check if the password is empty
    if (empty($password)) {
        $error = new WP_Error();
        $error->add('empty_password', 'Invalid Username/email or password');
        return $error;
    }

    // Check if the password is correct
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        $error = new WP_Error();
        $error->add('incorrect_password', 'Invalid Username/email or password');
        return $error;
    }

    // If no errors, return the user object
    return $user;
}

// Hook into the WordPress authentication filter
add_filter('authenticate', 'custom_woocommerce_login_errors', 20, 3);






//Regitration


// Function to customize WooCommerce registration errors
function custom_woocommerce_registration_errors($errors, $username, $email) {
    // Customize username exists error
    if (isset($errors->errors['registration-error-username-exists'])) {
        $errors->errors['registration-error-username-exists'][0] = 'Username is already in use.';
    }

    // Customize email exists error
    if (isset($errors->errors['registration-error-email-exists'])) {
        $errors->errors['registration-error-email-exists'][0] = 'Email address is already in use.';
    }

    return $errors;
}
add_filter('woocommerce_registration_errors', 'custom_woocommerce_registration_errors', 10, 3);

// Ensure custom error messages are displayed correctly
function custom_woocommerce_registration_error_messages() {
    if (wc_notice_count('error')) {
        $notices = wc_get_notices('error');
        wc_clear_notices();

        foreach ($notices as $notice) {
            if (is_array($notice) && isset($notice['notice'])) {
                if (strpos($notice['notice'], 'An account is already registered with that username.') !== false) {
                    wc_add_notice('Username is already in use.', 'error');
                } elseif (strpos($notice['notice'], 'Email address is already in use.') !== false) {
                    wc_add_notice('Email address is already in use.', 'error');
                } else {
                    wc_add_notice($notice['notice'], 'error');
                }
            }
        }
    }
}
add_action('woocommerce_before_customer_login_form', 'custom_woocommerce_registration_error_messages');
add_action('woocommerce_before_checkout_form', 'custom_woocommerce_registration_error_messages');







//---------------API Reset Username and Password-----------------------------

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/reset_password', array(
        'methods' => 'POST',
        'callback' => 'handle_password_reset',
        'permission_callback' => '__return_true',
    ));
});

function handle_password_reset(WP_REST_Request $request) {
    // Get parameters
    $user_identifier = $request->get_param('user_identifier');
    $current_password = $request->get_param('current_password');
    $new_password = $request->get_param('new_password');

    // Log received parameters for debugging
    error_log('user_identifier: ' . $user_identifier);
    error_log('current_password: ' . $current_password);
    error_log('new_password: ' . $new_password);
    
    // Validate parameters
    if (empty($user_identifier) || empty($current_password) || empty($new_password)) {
        return new WP_Error('missing_parameters', 'Please provide all required parameters.', array('status' => 400));
    }
    
    // Get user by ID or Email
    if (is_email($user_identifier)) {
        error_log('Searching user by email: ' . $user_identifier);
        $user = get_user_by('email', $user_identifier);
    } else {
        error_log('Searching user by ID: ' . $user_identifier);
        $user = get_user_by('id', $user_identifier);
    }

    // Log the result of user search
    if ($user) {
        error_log('User found: ' . print_r($user, true));
    } else {
        error_log('User not found for identifier: ' . $user_identifier);
    }
    
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.', array('status' => 404));
    }
    
    // Verify current password
    if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
        return new WP_Error('incorrect_password', 'Current password is incorrect.', array('status' => 401));
    }
    
    // Update password
    wp_set_password($new_password, $user->ID);
    
    return new WP_REST_Response(array(
        'message' => 'Password successfully updated.',
    ), 200);
}




// Register custom REST API endpoints
add_action('rest_api_init', function () {
    //error_log('Registering custom REST API endpoints');
    register_rest_route('custom/v1', '/cart/add', array(
        'methods' => 'POST',
        'callback' => 'add_to_cart',
        'permission_callback' => 'validate_user'
    ));
    register_rest_route('custom/v1', '/cart/update', array(
        'methods' => 'POST',
        'callback' => 'update_cart_item',
        'permission_callback' => 'validate_user'
    ));
    register_rest_route('custom/v1', '/cart/list', array(
        'methods' => 'GET',
        'callback' => 'list_cart_items',
        'permission_callback' => 'validate_user'
    ));
    register_rest_route('custom/v1', '/cart/delete', array(
        'methods' => 'POST',
        'callback' => 'delete_cart_item',
        'permission_callback' => 'validate_user'
    ));
    register_rest_route('custom/v1', '/test', array(
        'methods' => 'GET',
        'callback' => function() {
            return new WP_REST_Response(['message' => 'Test endpoint reached'], 200);
        }
    ));
    //error_log('Custom REST API endpoints registered');
});

// add_action('rest_api_init', function() {
//     error_log(print_r(rest_get_server()->get_routes(), true));
// });

function validate_user() {
    return is_user_logged_in();
}

function initialize_woocommerce_session() {
    if (!class_exists('WooCommerce')) {
        error_log('WooCommerce class not found');
        return false;
    }

    if (WC()->session === null) {
        $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
        WC()->session = new $session_class();
        WC()->session->init();
        error_log('WooCommerce session initialized');
    }

    if (WC()->customer === null) {
        WC()->customer = new WC_Customer(get_current_user_id());
        error_log('WooCommerce customer initialized');
    }

    if (WC()->cart === null) {
        WC()->cart = new WC_Cart();
        error_log('WooCommerce cart initialized');
    }

    WC()->session->set_customer_session_cookie(true);
    WC()->session->save_data();
    return true;
}

function add_to_cart(WP_REST_Request $request) {
    if (!initialize_woocommerce_session()) {
        return new WP_REST_Response(['message' => 'WooCommerce not properly initialized'], 500);
    }

    $product_id = intval($request->get_param('product_id'));
    $quantity = intval($request->get_param('quantity'));

    if ($product_id && $quantity) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_REST_Response(['message' => 'Invalid product ID'], 400);
        }

        $result = WC()->cart->add_to_cart($product_id, $quantity);
        if ($result) {
            WC()->session->set('cart', WC()->cart->get_cart());
            WC()->session->save_data();
            error_log('Product added to cart: ' . print_r(WC()->cart->get_cart(), true));
            return new WP_REST_Response(['message' => 'Product added to cart', 'cart_item_key' => $result], 200);
        } else {
            return new WP_REST_Response(['message' => 'Failed to add product to cart'], 400);
        }
    } else {
        return new WP_REST_Response(['message' => 'Invalid product ID or quantity'], 400);
    }
}

function update_cart_item(WP_REST_Request $request) {
    if (!initialize_woocommerce_session()) {
        return new WP_REST_Response(['message' => 'WooCommerce not properly initialized'], 500);
    }

    $cart_item_key = sanitize_text_field($request->get_param('cart_item_key'));
    $quantity = intval($request->get_param('quantity'));

    if ($cart_item_key && $quantity) {
        $cart = WC()->cart->get_cart();
        if (isset($cart[$cart_item_key])) {
            $result = WC()->cart->set_quantity($cart_item_key, $quantity);
            if ($result) {
                WC()->session->set('cart', WC()->cart->get_cart());
                WC()->session->save_data();
                error_log('Cart item updated: ' . print_r(WC()->cart->get_cart(), true));
                return new WP_REST_Response(['message' => 'Cart item updated'], 200);
            } else {
                return new WP_REST_Response(['message' => 'Failed to update cart item'], 400);
            }
        } else {
            error_log('Cart item key not found: ' . $cart_item_key);
            return new WP_REST_Response(['message' => 'Cart item key not found'], 404);
        }
    } else {
        return new WP_REST_Response(['message' => 'Invalid cart item key or quantity'], 400);
    }
}

function list_cart_items() {
    if (!initialize_woocommerce_session()) {
        return new WP_REST_Response(['message' => 'WooCommerce not properly initialized'], 500);
    }

    WC()->session->set_customer_session_cookie(true);
    WC()->session->save_data();

    $cart = WC()->cart->get_cart();
    $items = [];
    foreach ($cart as $cart_item_key => $cart_item) {
        $items[] = [
            'product_id' => $cart_item['product_id'],
            'quantity' => $cart_item['quantity'],
            'cart_item_key' => $cart_item_key,
            'user_id' => get_current_user_id()
        ];
    }
    error_log('List cart items: ' . print_r($items, true));
    return new WP_REST_Response($items, 200);
}

function delete_cart_item(WP_REST_Request $request) {
    if (!initialize_woocommerce_session()) {
        return new WP_REST_Response(['message' => 'WooCommerce not properly initialized'], 500);
    }

    $cart_item_key = sanitize_text_field($request->get_param('cart_item_key'));

    if ($cart_item_key) {
        $cart = WC()->cart->get_cart();
        error_log('Attempting to delete cart item key: ' . $cart_item_key);
        if (isset($cart[$cart_item_key])) {
            $result = WC()->cart->remove_cart_item($cart_item_key);
            if ($result) {
                WC()->session->set('cart', WC()->cart->get_cart());
                WC()->session->save_data();
                error_log('Cart item deleted: ' . print_r(WC()->cart->get_cart(), true));
                return new WP_REST_Response(['message' => 'Cart item deleted', 'result' => $result], 200);
            } else {
                error_log('Failed to delete cart item: ' . $cart_item_key);
                return new WP_REST_Response(['message' => 'Failed to delete cart item'], 400);
            }
        } else {
            error_log('Cart item key not found for deletion: ' . $cart_item_key);
            return new WP_REST_Response(['message' => 'Cart item key not found'], 404);
        }
    } else {
        return new WP_REST_Response(['message' => 'Invalid cart item key'], 400);
    }
}
