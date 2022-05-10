<?php

namespace SimplyTest\AppointmentUpdater\includes;

class Appointment_Updater_Helpers {

    private function __construct() {

    }

    /**
     * Filters and cleans the variations of a SINGLE PRODUCT in the following way:
     * 1. Removes inactive / unpublished variations
     * 2. Removes variations with no SKU date, e.g. individual appointments
     * 3. Normalizes sku_dates in the following format: DD.MM.YYYY
     * 4. Removes unnecessary information, that is, returns only the product id, product name, permalink, the variation SKU date,
     * and the reversed SKU Date (format: YYYY.MM.DD). This might be useful for fast comparing dates as strings.
     * @param array $product_variations The variations to be filtered / cleaned.
     * @param string $product_id The product id to which the variations belong to.
     * @param string $product_name The original product name, as it is no longer available in a variation response body.
     * @return array Array of filtered and cleaned associative array with the following keys: product_id, training_name, sku_date, reversed_sku_date, permalink.
     */ 
    public static function clean_product_variations( $product_variations, $product_id, $product_name ) {
        $_cleaned_product_variations = [];
        foreach( $product_variations as $variation ) {
            if ( $variation['status'] === 'publish' && !str_contains( $variation['sku'], 'Individuell' ) ) {
                $sku_dates = Appointment_Updater_Helpers::normalize_date ( $variation['sku'] );
                $_cleaned_product_variations[] = [
                    'product_id' => $product_id,
                    'training_name' => $product_name,
                    'sku_date' => $sku_dates[0],
                    'reversed_sku_date' => $sku_dates[1],
                    'permalink' => $variation['permalink'],
                ];
            }
        }
        return $_cleaned_product_variations;
    }

    /**
     * Normalizes a product variation date and returns it in the following format: DD.MM.YYYY - DD.MM.YYYY and
     * also in a reversed order: YYYY.MM.DD - YYYY.MM.DD
     * @param string $date The date to normalize
     * @return array Normalized date array [DD.MM.YYYY - DD.MM.YYYY, YYYY.MM.DD - YYYY.MM.DD]
     */
    private static function normalize_date( $date ) {
        $date = substr( $date, 0, strpos( $date, '(' ) ?: null );  // Remove portion of string after '(', if the original $date contains '('.
        $date = str_replace('–', '-', $date);  // Can contain one of these characters. Decide on '-'.
        $_dates = explode('-', $date);  // Now we can separate the dates on '-'.
        $normalized_dates = array();  // Helper array for the normalized dates.
        $reversed_normalized_dates = array();
        foreach ( $_dates as $_date ) {
            $_date = trim($_date);  // Remove any whitespace from left or right on the date.
            $_date = explode('.', $_date);  // Separate date into day, month, year.
            if ( strlen( $_date[2] ) == 2 )
                $_date[2] = '20' . $_date[2];  // Normalize year if the year is given as 22 instead of 2022. Will work until the year 2100.
            $reversed_normalized_dates[] = implode( '.', array_reverse( $_date ) );
            $normalized_dates[] = implode( '.', $_date );  // Rejoin date as DD.MM.YYYY
        }
        return array( implode( ' - ', $normalized_dates ), implode( ' - ', $reversed_normalized_dates ) );  // Join the dates to a single string (as the original date in the args).
    }

    /**
     * Sorts the variation dates in-place in ascending order. 
     * @param array $variations Array of array containing all variations. Each of its value is an associative array containing a variation
     * with the following keys: product_id, training_name, sku_date, reversed_sku_date, permalink.
     * @return bool true on success or false on failure.
     * */
    public static function sort_dates_ascending( &$variations) {
        return usort($variations, function ($_variation1, $_variation2) {
            // Take only the first part of the product date in format DD.MM.YYYY and remove whitespace.
            $_date1 = explode( '-', $_variation1['reversed_sku_date'] );
            $_date2 = explode( '-', $_variation2['reversed_sku_date'] );
            $_date1 = rtrim( $_date1[0] );
            $_date2 = rtrim( $_date2[0] );

            /*// Split date on '.', reverse the order of the array and rejoin with '.',
            // thus giving us a date in the format YYYY.MM.DD. With this format, we can use strcmp to just compare string dates.
            $_date1 = implode( '.', array_reverse( explode( '.', $_date1 ) ) );
            $_date2 = implode( '.', array_reverse( explode( '.', $_date2 ) ) );*/

            return strcmp( $_date1, $_date2 );
        });
    }

    /**
     * Removes duplicates from a 2D array at the given key.
     * @param array $array A multidimensional array.
     * @param string $key The key in the array where to check for duplicates.
     * @return array New array with duplicates removed.
     */
    public static function remove_duplicate_keys( $array, $key ) {
        $duplicates_removed = array();
        $value_array = array();
        foreach ( $array as $array_value ) {
            if ( ! in_array( $array_value[$key], $value_array, true ) ) {
                $value_array[] = $array_value[$key];
                $duplicates_removed[] = $array_value;
            }
        }
        return $duplicates_removed;
    }

    /**
     * Removes variations with dates before today's date. 
     * Array must be sorted in ascending order before.
     * @param array $variations Array containing the variations with sku_date
     * @return array Array with dates before today's date removed.
     */
    public static function remove_dates_before_current_date( $variations ) {
        $_offset = -1;  // The offset where to extract a slice of the array
        $current_date = date( "Y.m.d" );  // Formats today's date as YYYY.MM.DD
        $_arraySize = count ( $variations );
        for ( $i = 0; $i < $_arraySize; $i++ ) {
            $b_date = explode( '-', $variations[$i]['reversed_sku_date'] );  // The beginning date of an appointment
            $b_date = rtrim ( $b_date[0] );
            if ( $b_date < $current_date ) { $_offset = $i; }
            else { break; }  // If the array is sorted in ascending order, we can fast return here.
        }
        return array_slice( $variations, $_offset + 1 );
    }
}

?>