<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AB_AvailableTime
{
    /** @var DateInterval */
    private $one_day = null;

    /** @var AB_UserBookingData */
    private $userData;

    private $_staffIdsStr = '0';

    private $service_duration = 0;

    private $last_fetched_day = null;

    private $staffData = array();

    private $has_more_slots = false;

    /**
     * @var array
     */
    private $time = array();

    /**
     * Constructor.
     *
     * @param AB_UserBookingData $userData
     */
    public function __construct( AB_UserBookingData $userData )
    {
        $this->one_day = new DateInterval( 'P1D' );

        $this->userData = $userData;

        // Service duration.
        $this->service_duration = (int) $userData->getService()->get( 'duration' );

        // Prepare staff ids string for SQL queries.
        $this->_staffIdsStr = implode( ', ', array_merge(
            array_map( 'intval', $userData->get( 'staff_ids' ) ),
            array( 0 )
        ) );
    }

    public function load()
    {
        $slots               = 0; // number of handled slots
        $days                = 0; // number of handled days
        $show_day_per_column = AB_BookingConfiguration::showDayPerColumn();
        $show_calendar       = AB_BookingConfiguration::showCalendar();
        $time_slot_length    = AB_BookingConfiguration::getTimeSlotLength();
        $client_diff         = get_option( 'ab_settings_use_client_time_zone' )
            ? get_option( 'gmt_offset' ) * HOUR_IN_SECONDS + $this->userData->get( 'time_zone_offset' ) * 60
            : 0;

        /**
         * @var int $req_timestamp
         * @var DateTime $date
         * @var DateTime $max_date
         */
        list ( $req_timestamp, $date, $max_date ) = $this->_prepareDates();

        // Prepare staff data.
        $this->_prepareStaffData( $date );

        // The main loop.
        while (
            // get the 10 columns/request
            ( $show_calendar || ( $show_day_per_column && $days < 10 /* one day/column */) || ( !$show_day_per_column && $slots < 100 /* 10 slots/column * 10 columns */) )
            &&
            // don't exceed limit of days from settings
            $date < $max_date
        ) {
            $date = $this->_findAvailableDay( $date );

            if ( $date === false ) {
                break;
            }

            foreach ( $this->_findAvailableTime( $date ) as $frame ) {
                // Loop from start to end with time slot length step.
                for ( $time = $frame['start']; $time <= ( $frame['end'] - $this->service_duration ); $time += $time_slot_length ) {

                    $timestamp = $date->getTimestamp() + $time;
                    $client_timestamp = $timestamp - $client_diff;

                    if ( $client_timestamp < $req_timestamp ) {
                        // When we start 1 day before the requested date we may not need all found slots,
                        // we should skip those slots which do not fit the requested date in client's time zone.
                        continue;
                    }
                    if ( $show_calendar && $client_timestamp >= $req_timestamp + 86400 ) {
                        // When displaying calendar we stop the loop
                        // when client's date exceeds the requested date.
                        break 3;
                    }

                    // Resolve intersections.
                    if ( !isset ( $this->time[ $client_timestamp ] ) ) {

                        if ( $this->_addDate( $client_timestamp ) ) {
                            ++ $slots;
                            ++ $days;
                        }

                        $this->_addTime( $client_timestamp, $timestamp, $frame['staff_id'], isset ( $frame['booked'] ) );
                        ++ $slots;
                    }
                    else {
                        // Change staff member for this slot if the other one has higher price.
                        if ( $this->staffData[ $this->time[ $client_timestamp ]['staff_id'] ]['price'] < $this->staffData[ $frame['staff_id'] ]['price'] ) {
                            $this->time[ $client_timestamp ]['staff_id'] = $frame['staff_id'];
                        }
                    }

                    // Handle not full bookings (when number of bookings is less than capacity).
                    if ( isset ( $frame['not_full'] ) ) {
                        break;
                    }
                }
            }

            $date->add( $this->one_day );
        }

        // Detect if there are more slots.
        if ( !$show_calendar ) {
            while ( $date < $max_date ) {
                $date = $this->_findAvailableDay( $date );
                if ( $date === false ) {
                    break;
                }
                $available_time = $this->_findAvailableTime( $date );
                if ( !empty ( $available_time ) ) {
                    $this->has_more_slots = true;
                    break;
                }
                $date->add( $this->one_day );
            }
        }
    }

    /**
     * Determine requested timestamp and start and max date.
     *
     * @return array
     */
    private function _prepareDates()
    {
        if ( $this->last_fetched_day ) {
            $start_date = new DateTime( substr( $this->last_fetched_day, 0, 10 ) );
            $req_timestamp = $start_date->getTimestamp();
            // The last_fetched_day is always in WP time zone (see AB_BookingController::executeRenderNextTime()).
            // We increase it by 1 day to get the date to start with.
            $start_date->add( $this->one_day );
        }
        else {
            $start_date = new DateTime( $this->userData->get( 'date_from' ) );
            $req_timestamp = $start_date->getTimestamp();
            if ( get_option( 'ab_settings_use_client_time_zone' ) ) {
                // The userData::date_from is in client's time zone so we need to check the previous day too
                // because some available slots can be found in the previous day due to time zone offset.
                $start_date->sub( $this->one_day );
            }
        }
        $max_date = date_create(
            '@' . ( (int)current_time( 'timestamp' ) + AB_BookingConfiguration::getMaximumAvailableDaysForBooking() * 86400 )
        )->setTime( 0, 0 );

        return array( $req_timestamp, $start_date, $max_date );
    }

    /**
     * Add date to Time Table (step 2)
     *
     * @param int $client_timestamp
     * @return bool
     */
    private function _addDate( $client_timestamp )
    {
        $midnight = intval( $client_timestamp / 86400 ) * 86400;
        if ( !isset ( $this->time[ -$midnight ] ) ) {
            $this->time[ -$midnight ] = array(
                'is_day' => true,
            );

            return true;
        }

        return false;
    }

    /**
     * Add time to Time Table (step 2)
     *
     * @param int $client_timestamp
     * @param int $timestamp
     * @param int $staff_id
     * @param boolean $booked
     */
    private function _addTime( $client_timestamp, $timestamp, $staff_id, $booked = false )
    {
        $this->time[ $client_timestamp ] = array(
            'is_day'    => false,
            'timestamp' => $timestamp,
            'staff_id'  => $staff_id,
            'booked'    => $booked,
        );
    }

    /**
     * Find a day which is available for booking based on
     * user requested set of days.
     *
     * @access private
     * @param DateTime $date
     * @return DateTime
     */
    private function _findAvailableDay( DateTime $date )
    {
        $attempt = 0;
        // Find available day within requested days.
        $requested_days = $this->userData->get( 'days' );
        while ( !in_array( $date->format( 'w' ) + 1, $requested_days ) ) {
            $date->add( $this->one_day );
            if ( ++ $attempt >= 7 ) {
                return false;
            }
        }

        return $date;
    }

    /**
     * Find array of time slots available for booking
     * for given date.
     *
     * @access private
     * @param DateTime $date
     * @return array
     */
    private function _findAvailableTime( DateTime $date )
    {
        $result             = array();
        $time_slot_length   = AB_BookingConfiguration::getTimeSlotLength();
        $prior_time         = AB_BookingConfiguration::getMinimumTimePriorBooking();
        $show_blocked_slots = AB_BookingConfiguration::showBlockedTimeSlots();
        $current_timestamp  = (int)current_time( 'timestamp' ) + $prior_time;
        $current_date       = date_create( '@' . $current_timestamp )->setTime( 0, 0 );

        if ( $date < $current_date ) {
            return array();
        }

        $day_of_week = $date->format( 'w' ) + 1; // 1-7
        $start_time  = date( 'H:i:s', ceil( $current_timestamp / $time_slot_length ) * $time_slot_length );

        foreach ( $this->staffData as $staff_id => $staff ) {

            if ( $staff['capacity'] < $this->userData->get( 'number_of_persons' ) ) {
                continue;
            }

            if ( isset ( $staff['working_hours'][ $day_of_week ] ) && $this->isWorkingDay( $date, $staff_id ) ) {
                // Find intersection between working and requested hours
                //(excluding time slots in the past).
                $working_start_time = ( $date == $current_date && $start_time > $staff['working_hours'][ $day_of_week ]['start_time'] )
                    ? $start_time
                    : $staff['working_hours'][ $day_of_week ]['start_time'];

                $intersections = $this->_findIntersections(
                    $this->_timeToSecs( $working_start_time ),
                    $this->_timeToSecs( $staff['working_hours'][ $day_of_week ]['end_time'] ),
                    $this->_timeToSecs( $this->userData->get( 'time_from' ) ),
                    $this->_timeToSecs( $this->userData->get( 'time_to' ) )
                );

                foreach ( $intersections as $intersection ) {
                    if ( $intersection['end'] - $intersection['start'] >= $this->service_duration ) {
                        // Initialize time frames.
                        $frames = array( array(
                            'start'    => $intersection['start'],
                            'end'      => $intersection['end'],
                            'staff_id' => $staff_id
                        ) );
                        // Remove breaks from the time frames.
                        foreach ( $staff[ 'working_hours' ][ $day_of_week ]['breaks'] as $break ) {
                            $frames = $this->_removeTimePeriod(
                                $frames,
                                $this->_timeToSecs( $break['start'] ),
                                $this->_timeToSecs( $break['end'] )
                            );
                        }
                        // Remove bookings from the time frames.
                        foreach ( $staff['bookings'] as $booking ) {
                            // Work with bookings for the current day only.
                            if ( $date->format( 'Y-m-d' ) == substr( $booking['start_date'], 0, 10 ) ) {

                                $booking_start = $this->_timeToSecs( substr( $booking['start_date'], 11 ) );
                                $booking_end   = $this->_timeToSecs( substr( $booking['end_date'], 11 ) );

                                $frames = $this->_removeTimePeriod( $frames, $booking_start, $booking_end );

                                // Handle not full bookings (when number of bookings is less than capacity).
                                if (
                                    $booking['from_google'] == false &&
                                    $booking['service_id'] == $this->userData->get( 'service_id' ) &&
                                    $booking_start >= $intersection['start'] &&
                                    $staff['capacity'] - $booking['number_of_bookings'] >= $this->userData->get( 'number_of_persons' )
                                ) {
                                    if ( $show_blocked_slots ) {
                                        // When displaying blocked slots then show only the first slot as not full.
                                        $frames[] = array(
                                            'start'    => $booking_start,
                                            'end'      => $booking_start + $this->service_duration,
                                            'staff_id' => $staff_id,
                                            'not_full' => true
                                        );
                                        // The rest must be shown as blocked.
                                        $frames[] = array(
                                            'start'    => $booking_start + $this->service_duration,
                                            'end'      => $booking_end <= $intersection['end'] ? $booking_end : $intersection['end'],
                                            'staff_id' => $staff_id,
                                            'booked'   => true
                                        );
                                    } else {
                                        $frames[] = array(
                                            'start'    => $booking_start,
                                            'end'      => $booking_end,
                                            'staff_id' => $staff_id,
                                            'not_full' => true
                                        );
                                    }
                                }
                                // Handle fully booked slots when displaying blocked slots.
                                else if ( $show_blocked_slots ) {
                                    // Show removed slots as blocked.
                                    $frames[] = array(
                                        'start'    => $booking_start >= $intersection['start'] ? $booking_start : $intersection['start'],
                                        'end'      => $booking_end <= $intersection['end'] ? $booking_end : $intersection['end'],
                                        'staff_id' => $staff_id,
                                        'booked'   => true
                                    );
                                }
                            }
                        }
                        $result = array_merge( $result, $frames );
                    }
                }
            }
        }
        usort( $result, function ( $a, $b ) { return $a['start'] - $b['start']; } );

        return $result;
    }

    /**
     * Checks if the date is not a holiday for this employee
     *
     * @param DateTime $date
     * @param int $staff_id
     *
     * @return bool
     */
    private function isWorkingDay( DateTime $date, $staff_id )
    {
        $working_day = true;

        if ( $this->staffData[ $staff_id ]['holidays'] ) {
            foreach ( $this->staffData[ $staff_id ]['holidays'] as $holiday ) {
                $holidayDate = new DateTime( $holiday['holiday'] );
                if ( $holiday['repeat_event'] ) {
                    $working_day = $holidayDate->format( 'm-d' ) != $date->format( 'm-d' );
                } else {
                    $working_day = $holidayDate != $date;
                }
                if ( !$working_day ) {
                    break;
                }
            }
        }

        return $working_day;
    }

    /**
     * Find intersection between 2 time periods.
     *
     * @param mixed $p1_start
     * @param mixed $p1_end
     * @param mixed $p2_start
     * @param mixed $p2_end
     * @return array
     */
    private function _findIntersections( $p1_start, $p1_end, $p2_start, $p2_end )
    {
        $result = array();

        if ( $p2_start > $p2_end ) {
            $result[] = $this->_findIntersections($p1_start, $p1_end, 0, $p2_end);
            $result[] = $this->_findIntersections($p1_start, $p1_end, $p2_start, 86400);
        }
        else {
            if ( $p1_start <= $p2_start && $p1_end > $p2_start && $p1_end <= $p2_end ) {
                $result[] = array( 'start' => $p2_start, 'end' => $p1_end );
            } else if ( $p1_start <= $p2_start && $p1_end >= $p2_end ) {
                $result[] = array( 'start' => $p2_start, 'end' => $p2_end );
            } else if ( $p1_start >= $p2_start && $p1_start < $p2_end && $p1_end >= $p2_end ) {
                $result[] = array( 'start' => $p1_start, 'end' => $p2_end );
            } else if ( $p1_start >= $p2_start && $p1_end <= $p2_end ) {
                $result[] = array( 'start' => $p1_start, 'end' => $p1_end );
            }
        }

        return $result;
    }

    /**
     * Remove time period from the set of time frames.
     *
     * @param array $frames
     * @param mixed $p_start
     * @param mixed $p_end
     * @return array
     */
    private function _removeTimePeriod( array $frames, $p_start, $p_end )
    {
        $result = array();
        foreach ( $frames as $frame ) {
            $intersections = $this->_findIntersections(
                $frame['start'],
                $frame['end'],
                $p_start,
                $p_end
            );
            foreach ( $intersections as $intersection ) {
                if ( $intersection['start'] - $frame['start'] >= $this->service_duration ) {
                    $result[] = array(
                        'start'    => $frame['start'],
                        'end'      => $intersection['start'],
                        'staff_id' => $frame['staff_id']
                    );
                }
                if ( $frame['end'] - $intersection['end'] >= $this->service_duration ) {
                    $result[] = array(
                        'start'    => $intersection['end'],
                        'end'      => $frame['end'],
                        'staff_id' => $frame['staff_id']
                    );
                }
            }
            if ( empty ( $intersections ) ) {
                $result[] = $frame;
            }
        }

        return $result;
    }

    /**
     * Convert time in format H:i:s to seconds.
     *
     * @param $str
     * @return int
     */
    private function _timeToSecs( $str )
    {
        $result  = 0;
        $seconds = 3600;

        foreach ( explode( ':', $str ) as $part ) {
            $result += (int)$part * $seconds;
            $seconds /= 60;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getTime()
    {
        return $this->time;
    }

    public function setLastFetchedDay( $last_fetched_day )
    {
        $this->last_fetched_day = $last_fetched_day;
    }

    public function hasMoreSlots()
    {
        return $this->has_more_slots;
    }

    /**
     * Prepare data for staff.
     *
     * @param DateTime $start_date
     */
    private function _prepareStaffData( DateTime $start_date )
    {
        /** @var WPDB $wpdb */
        global $wpdb;

        $this->staffData = array();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT staff_id, price, capacity FROM `ab_staff_service` WHERE `staff_id` IN ({$this->_staffIdsStr}) AND `service_id` = %d",
            $this->userData->get( 'service_id' )
        ), ARRAY_A );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $this->staffData[ $row['staff_id'] ] = array(
                    'price'         => $row['price'],
                    'capacity'      => $row['capacity'],
                    'holidays'      => array(),
                    'bookings'      => array(),
                    'working_hours' => array(),
                );
            }
        }

        // Load holidays.
        $rows = $wpdb->get_results( "SELECT * FROM `ab_holiday` WHERE `staff_id` IN ({$this->_staffIdsStr})", ARRAY_A );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $this->staffData[ $row['staff_id'] ]['holidays'][] = $row;
            }
        }

        // Load working schedule.
        $rows = $wpdb->get_results( "
            SELECT `item`.*, `break`.`start_time` AS `break_start`, `break`.`end_time` AS `break_end`
                FROM `ab_staff_schedule_item` `item`
                LEFT JOIN `ab_schedule_item_break` `break` ON `item`.`id` = `break`.`staff_schedule_item_id`
            WHERE `item`.`staff_id` IN ({$this->_staffIdsStr}) AND `item`.`start_time` IS NOT NULL
        ", ARRAY_A );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( !isset ( $this->staffData[ $row['staff_id'] ]['working_hours'][ $row['day_index'] ] ) ) {
                    $this->staffData[ $row['staff_id'] ]['working_hours'][ $row['day_index'] ] = array(
                        'start_time' => $row['start_time'],
                        'end_time'   => $row['end_time'],
                        'breaks'     => array(),
                    );
                }
                if ( $row[ 'break_start' ] ) {
                    $this->staffData[ $row['staff_id'] ]['working_hours'][ $row['day_index'] ]['breaks'][] = array(
                        'start' => $row['break_start'],
                        'end'   => $row['break_end']
                    );
                }
            }
        }

        // Load bookings.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT `a`.*, SUM(`ca`.`number_of_persons`) AS `number_of_bookings`
                FROM `ab_customer_appointment` `ca`
                LEFT JOIN `ab_appointment` a ON `a`.`id` = `ca`.`appointment_id`
                LEFT JOIN `ab_staff_service` `ss` ON `ss`.`staff_id` = `a`.`staff_id` AND `ss`.`service_id` = `a`.`service_id`
             WHERE `a`.`staff_id` IN ({$this->_staffIdsStr}) AND `a`.`start_date` >= %s
             GROUP BY `a`.`start_date`, `a`.`staff_id`, `a`.`service_id`",
            $this->userData->get( 'date_from' ) ), ARRAY_A );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $row['from_google'] = false;
                $this->staffData[ $row['staff_id'] ]['bookings'][] = $row;
            }
        }

        // Handle Google Calendar events.
        if  ( get_option( 'ab_settings_google_two_way_sync' ) ) {
            $staff_members = AB_EntityManager::getInstance( 'AB_Staff' )->findBy( array( 'id' => array_merge(
                array_map( 'intval', $this->userData->get( 'staff_ids' ) ),
                array( 0 )
            ) ) );
            foreach ( $staff_members as $staff ) {
                $google = new AB_Google();
                if ( $google->loadByStaff( $staff ) ) {
                    $this->staffData[ $staff->get( 'id' ) ]['bookings'] = array_merge(
                        $this->staffData[ $staff->get( 'id' ) ]['bookings'],
                        $google->getCalendarEvents( $start_date ) ?: array()
                    );
                }
            }

        }
    }

    /**
     * @return array
     */
    public function getHolidays()
    {
        $holidays = array();

        foreach ( $this->staffData as $staff_id => $staff_data ) {
            $holidays[ $staff_id ] = $staff_data['holidays'];
        }

        return $holidays;
    }

    /**
     * Check if booking time is still available
     * Return TRUE if time is available
     *
     * @return bool
     */
    public function checkBookingTime()
    {
        /** @var WPDB $wpdb */
        global $wpdb;

        $booked_datetime = $this->userData->get( 'appointment_datetime' );

        $endDate = new DateTime( $booked_datetime );
        $endDate->modify( "+ {$this->userData->getService()->get( 'duration' )} sec" );

        $query = $wpdb->prepare(
            "SELECT `a`.*, `ss`.`capacity`, SUM(`ca`.`number_of_persons`) AS `total_number_of_persons`
                FROM `ab_customer_appointment` `ca`
                LEFT JOIN `ab_appointment`   `a`  ON `a`.`id` = `ca`.`appointment_id`
                LEFT JOIN `ab_staff_service` `ss` ON `ss`.`staff_id` = `a`.`staff_id` AND `ss`.`service_id` = `a`.`service_id`
                WHERE `a`.`staff_id` = %d
                GROUP BY `a`.`start_date` , `a`.`staff_id` , `a`.`service_id`
                HAVING
                      (`a`.`start_date` = %s AND `service_id` =  %d AND `total_number_of_persons` >= `capacity`) OR
                      (`a`.`start_date` = %s AND `service_id` <> %d) OR
                      (`a`.`start_date` > %s AND `a`.`end_date` <= %s) OR
                      (`a`.`start_date` < %s  AND `a`.`end_date` > %s) OR
                      (`a`.`start_date` < %s  AND `a`.`end_date` > %s)
                ",
            $this->userData->getStaffId(),
            $booked_datetime, $this->userData->get( 'service_id' ),
            $booked_datetime, $this->userData->get( 'service_id' ),
            $booked_datetime, $endDate->format( 'Y-m-d H:i:s' ),
            $endDate->format( 'Y-m-d H:i:s' ), $endDate->format( 'Y-m-d H:i:s' ),
            $booked_datetime, $booked_datetime
        );

        return !(bool)$wpdb->get_row($query);
    }
}