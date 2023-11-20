<?php

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

class GooglePlacesDynamicTag extends Tag {

    public function get_name() {
        return 'google-places-data';
    }
    public function get_title() {
        return 'Google Places Data';
    }
    public function get_group() {
        return 'site';
    }
    public function get_categories() {
        return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::URL_CATEGORY
		];
    }
    protected function _register_controls() {
        $this->add_control(
            'place_data_type',
            [
                'label' => 'Data Type',
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'name' => 'Name',
                    'rating' => 'Rating',
                    'user_ratings_total' => 'Total User Ratings',
					'url' => 'URL',
					'weekday_text' => 'Weekday',
					'business_status' => 'Business Status',
					'formatted_address' => 'Formatted Address',
					'place_id' => 'Place ID'
                ],
                'default' => 'name'
            ]
        );

        $this->add_control(
            'place_id',
            [
                'label' => 'Place ID',
                'type' => Controls_Manager::TEXT
            ]
        );
    }

    public function render() {
        $api_key = get_option('google_places_api_key', '');

        $place_data_type = $this->get_settings('place_data_type');
        $place_id = $this->get_settings('place_id');

        // Check if the transient exists
        $transient_name = "google_place_$place_id";
        $place_details = get_transient($transient_name);

        // If no transient exists, fetch the data
        if (false === $place_details) {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id=$place_id&fields=name,place_id,rating,user_ratings_total,url,opening_hours,business_status,formatted_address&key=$api_key";
			$response = wp_remote_get($url);
            $data = wp_remote_retrieve_body($response);
            $place_details = json_decode($data, true);

			// Зберігання даних в транзієнті з вказаним терміном дії
        $cache_duration = get_option('google_places_cache_duration', 750); // 750 годин за замовчуванням
        $place_details['cached_timestamp'] = time();
		set_transient($transient_name, $place_details, $cache_duration * HOUR_IN_SECONDS);
        }
		
		// Check if it's a number and url category
		$isNumberCategory = in_array(\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY, $this->get_categories());
		$isURLCategory = in_array(\Elementor\Modules\DynamicTags\Module::URL_CATEGORY, $this->get_categories());

        // Continue the rest of your rendering logic
        switch ($place_data_type) {
            case 'name':
                echo (isset($place_details['result']['name']) ? $place_details['result']['name'] : '');
                break;
            case 'rating':
				if ($isNumberCategory) {
					echo isset($place_details['result']['rating']) ? $place_details['result']['rating'] : '0';
				} else {
					echo (isset($place_details['result']['rating']) ? $place_details['result']['rating'] : '');
				}
				break;
            case 'user_ratings_total':
                if(isset($place_details['result']) && array_key_exists('user_ratings_total', $place_details['result'])) {
                    echo $place_details['result']['user_ratings_total'];
                } else {
                    echo "";
                }
                break;
			case 'place_id':
                if(isset($place_details['result']) && array_key_exists('place_id', $place_details['result'])) {
                    echo $place_details['result']['place_id'];
                } else {
                    echo "";
                }
                break;
			case 'business_status':
				$status = isset($place_details['result']['business_status']) ? $place_details['result']['business_status'] : '';
				switch ($status) {
					case 'OPERATIONAL':
						echo "Open time";
						break;
					case 'CLOSED_TEMPORARILY':
						echo "Right now place is temporarily closed.<br>Please re-check in future";
						break;
					case 'CLOSED_PERMANENTLY':
						echo "Place is closed permanently";
						break;
					default:
						echo $status;
						break;
				}
				break;
			case 'url':
					if ($isURLCategory) {
						echo isset($place_details['result']['url']) ? $place_details['result']['url'] : '';
					} else {
						echo "URL: " . (isset($place_details['result']['url']) ? $place_details['result']['url'] : '');
					}
			break;
			case 'weekday_text':
				if (isset($place_details['result']['opening_hours']['weekday_text'])) {
					foreach ($place_details['result']['opening_hours']['weekday_text'] as $weekday) {
						echo $weekday . "<br>";
					}
				} else {
					echo "";
				}
				break;
			case 'formatted_address':
				echo (isset($place_details['result']['formatted_address']) ? $place_details['result']['formatted_address'] : '');
				break;
            default:
                echo "Error: Invalid data type selected.";
                break;
        }
		
    }
}

// Register the tag
add_action('elementor/dynamic_tags/register_tags', function($dynamic_tags) {
    $dynamic_tags->register_tag('GooglePlacesDynamicTag');
});
