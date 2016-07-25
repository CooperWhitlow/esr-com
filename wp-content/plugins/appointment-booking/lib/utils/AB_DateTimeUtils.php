<?php

class AB_DateTimeUtils {
    private static $week_days = array(
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    );

    /**
     * Get week day by day number (0 = Sunday, 1 = Monday...)
     *
     * @param $number
     *
     * @return string
     */
    public static function getWeekDayByNumber( $number ) {
        return isset( self::$week_days[ $number ] ) ? self::$week_days[ $number ] : '';
    }

    /**
     * Format ISO date according to WP date format setting.
     *
     * @param string $iso_date
     * @return string
     */
    public static function formatDate( $iso_date ) {
        return date_i18n( get_option( 'date_format' ), strtotime( $iso_date ) );
    }

    /**
     * Format ISO time according to WP time format setting.
     *
     * @param string $iso_time
     * @return string
     */
    public static function formatTime( $iso_time ) {
        return date_i18n( get_option( 'time_format' ), strtotime( $iso_time ) );
    }

    /**
     * Format ISO datetime according to WP date and time format settings.
     *
     * @param string $iso_date_time
     * @return string
     */
    public static function formatDateTime( $iso_date_time ) {
        return self::formatDate( $iso_date_time ) . ' ' . self::formatTime( $iso_date_time );
    }

    /**
     * Apply time zone offset (in minutes) to the given ISO date and time
     * which is considered to be in WP time zone.
     *
     * @param $iso_date_time
     * @param $offset         Offset in minutes
     * @param string $format  Output format
     * @return bool|string
     */
    public static function applyTimeZoneOffset( $iso_date_time, $offset, $format = 'Y-m-d H:i:s' ) {
        $client_diff = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS + $offset * 60;

        return date( $format, strtotime( $iso_date_time ) - $client_diff );
    }
}