<?php
// settings.php

// Add a new menu under Settings
add_action('admin_menu', 'google_places_settings_menu');

function google_places_settings_menu() {
    add_options_page('Google Places for Elementor', 'Google Places for Elementor', 'manage_options', 'google-places-for-elementor', 'google_places_settings_page');
}

// Function to validate the API key by making an actual request
function validate_google_places_api_key($api_key, $saved_api_key) {
    // Якщо $api_key не передано, використовуйте $saved_api_key
    $api_key = empty($api_key) ? $saved_api_key : $api_key;
    
    // Test place_id for Google's headquarters
    $test_place_id = "ChIJj61dQgK6j4AR4GeTYWZsKWw";
    $response = wp_remote_get("https://maps.googleapis.com/maps/api/place/details/json?place_id={$test_place_id}&key={$api_key}");
    if (is_wp_error($response)) {
        return [
            'valid' => false,
            'message' => 'Failed to connect to Google API.'
        ];
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['status'])) {
        switch ($data['status']) {
            case 'OK':
                return [
                    'valid' => true,
                    'message' => 'API Key is valid!'
                ];
            case 'REQUEST_DENIED':
                return [
                    'valid' => false,
                    'message' => 'Request denied. Check your API key and permissions.'
                ];
            case 'INVALID_REQUEST':
                return [
                    'valid' => false,
                    'message' => 'Invalid request. Something went wrong with the test request.'
                ];
            default:
                return [
                    'valid' => false,
                    'message' => 'Unknown error occurred. Please check your API key and try again.'
                ];
        }
    }
    return [
        'valid' => false,
        'message' => 'Unexpected error occurred.'
    ];
}

function get_google_places_transients() {
    global $wpdb;
    $transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_google_place_%'");
    $data = [];
    $saved_cache_duration = get_option('google_places_cache_duration', 750) * HOUR_IN_SECONDS;
    foreach ($transients as $transient) {
        $place_details = get_transient(str_replace('_transient_', '', $transient));
        if ($place_details && isset($place_details['result'])) {
            $result = $place_details['result'];

            // Отримуємо business_status та конвертуємо його у потрібний текст
            $business_status = isset($result['business_status']) ? $result['business_status'] : 'N/A';
            switch($business_status) {
                case 'OPERATIONAL':
                    $business_status_text = 'Place is open';
                    break;
                case 'CLOSED_TEMPORARILY':
                    $business_status_text = 'Right now place is temporarily closed. Please re-check in future';
                    break;
                case 'CLOSED_PERMANENTLY':
                    $business_status_text = 'Place is closed permanently';
                    break;
                default:
                    $business_status_text = $business_status;
            }

            $data[] = [
                'place_id' => isset($result['place_id']) ? $result['place_id'] : 'N/A',
                'name' => isset($result['name']) ? $result['name'] : 'N/A',
                'rating' => isset($result['rating']) ? $result['rating'] : 'N/A',
                'user_ratings_total' => isset($result['user_ratings_total']) ? $result['user_ratings_total'] : 'N/A',
                'url' => isset($result['url']) ? $result['url'] : 'N/A',
                'business_status' => $business_status_text, // додано
				'formatted_address' => isset($result['formatted_address']) ? $result['formatted_address'] : 'N/A',
                'expiration_date' => isset($place_details['cached_timestamp']) ? date('Y-m-d H:i:s', $place_details['cached_timestamp'] + $saved_cache_duration) : 'N/A'
            ];
        }
    }
    return $data;
}

function clear_google_places_cache() {
    global $wpdb;
    
    $transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_google_place_%'");
    
    $count = 0;
    foreach ($transients as $transient) {
        if (delete_transient(str_replace('_transient_', '', $transient))) {
            $count++;
        }
    }
	return $count;
}

function google_places_settings_page() {
    $saved_api_key = get_option('google_places_api_key', '');
	
		// Перевірте, чи має користувач достатньо прав
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
	
    if (isset($_POST['clear_cache'])) {
		$deleted_count = clear_google_places_cache();
		echo "<div class='updated'><p>Cleared cache for {$deleted_count} places!</p></div>";

        error_log("Clear Cache button pressed.");

        // Тут має бути виклик функції, яка видаляє кеш
        clear_google_places_cache();
    }

    // Спочатку перевірте nonce перед обробкою форми
    if (isset($_POST['save_changes']) || isset($_POST['check_api_key'])) {
        // Перевірте nonce
        if (!isset($_POST['google_places_nonce']) || !wp_verify_nonce($_POST['google_places_nonce'], 'google_places_nonce_action')) {
            die('Invalid nonce. Try again.');
        }

		if (isset($_POST['check_api_key'])) {
			$api_key_to_check = sanitize_text_field($_POST['google_places_api_key']);
			$validation_result = validate_google_places_api_key($api_key_to_check, $saved_api_key);
			if (!$validation_result['valid']) {
				echo "<div class='error'><p>" . esc_html($validation_result['message']) . "</p></div>";
			} else {
				echo "<div class='updated'><p>" . esc_html($validation_result['message']) . "</p></div>";
			}
		}

        if(isset($_POST['save_changes'])) {
            $api_key = sanitize_text_field($_POST['google_places_api_key']);
            update_option('google_places_api_key', $api_key);
            echo "<div class='updated'><p>API Key saved!</p></div>";

            // Збереження часу кешування
            $cache_duration = intval($_POST['cache_duration']);
            update_option('google_places_cache_duration', $cache_duration);
            echo "<div class='updated'><p>Time cache saved!</p></div>";
        }
    }
	
	
	    if (isset($_POST['delete_selected'])) {
        // Тут має бути виклик функції, яка видаляє вибрані рядки з кешу
        $selected_place_ids = $_POST['selected_place_ids'];
        foreach ($selected_place_ids as $place_id) {
            delete_transient('google_place_' . $place_id);
        }
        echo "<div class='updated'><p>Видалено вибрані рядки!</p></div>";
    }
	
	
    $saved_cache_duration = get_option('google_places_cache_duration', 750); // 750 годин за замовчуванням

    ?>
    <div class="wrap">
        <h2>Google Places for Elementor</h2>
        <form method="post">
		<?php wp_nonce_field('google_places_nonce_action', 'google_places_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google Places API Key</th>
                    <td>
                        <input type="text" name="google_places_api_key" value="<?php echo esc_attr($saved_api_key); ?>" />
                        <input type="submit" name="check_api_key" class="button" value="Перевірити ключ" />
                    </td>
                </tr>
            </table>
			<table class="form-table">
                <tr valign="top">
                    <th scope="row">Час кешування (у годинах)</th>
                    <td>
                        <input type="number" name="cache_duration" value="<?php echo esc_attr($saved_cache_duration); ?>" />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_changes" class="button-primary" value="Save Changes" />
				<input type="submit" name="clear_cache" class="button" value="Clear Cache" />	
            </p>
        </form>
		
<?php		// Отримання кешованих даних про Place ID
$places_data = get_google_places_transients();

echo "<h3>Cached Google Places Data</h3>";
echo "<form method='post'>";
echo "<table class='widefat fixed' cellspacing='0'>";
echo "<thead>
        <tr>
            <th width='20px'><input type='checkbox' id='select_all'></th>
			<th>Place Id</th>
			<th>Business Status</th>
            <th>Назва</th>
            <th>Рейтинг</th>
            <th>Кількість відгуків</th>
			<th>formatted_address</th>
            <th>URL</th>
            <th>Дата до якої зберігається кеш</th>
        </tr>
      </thead>
      <tbody>";

foreach ($places_data as $place) {
    echo "<tr>";
    echo "<td><input type='checkbox' name='selected_place_ids[]' value='" . esc_attr($place['place_id']) . "'></td>";
    echo "<td>" . esc_html($place['place_id']) . "</td>";
    echo "<td>" . esc_html($place['business_status']) . "</td>";
    echo "<td>" . esc_html($place['name']) . "</td>";
    echo "<td>" . esc_html($place['rating']) . "</td>";
    echo "<td>" . esc_html($place['user_ratings_total']) . "</td>";
	echo "<td>" . esc_html($place['formatted_address']) . "</td>";
    echo "<td><a href='" . esc_url($place['url']) . "' target='_blank'>Link</a></td>";
    echo "<td>" . esc_html($place['expiration_date']) . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";
echo "<p class='submit'>";
echo "<input type='submit' name='delete_selected' class='button' value='Видалити вибране'>";
echo "</p>";
echo "</form>";

    // Add JavaScript for handling select all checkboxes
    echo "<script>
        document.getElementById('select_all').addEventListener('click', function(e) {
            var checkboxes = document.querySelectorAll('input[type=\"checkbox\"]');
            for (var checkbox of checkboxes) {
                checkbox.checked = e.target.checked;
            }
        });
    </script>";

?>
    </div>
    <?php
}
?>
