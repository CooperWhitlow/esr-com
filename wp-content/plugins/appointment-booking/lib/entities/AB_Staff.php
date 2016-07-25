<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_Staff
 */
class AB_Staff extends AB_Entity
{
    protected static $table_name = 'ab_staff';

    protected static $schema = array(
        'id'                 => array( 'format' => '%d' ),
        'wp_user_id'         => array( 'format' => '%d' ),
        'full_name'          => array( 'format' => '%s' ),
        'email'              => array( 'format' => '%s' ),
        'avatar_path'        => array( 'format' => '%s' ),
        'avatar_url'         => array( 'format' => '%s' ),
        'phone'              => array( 'format' => '%s' ),
        'google_data'        => array( 'format' => '%s' ),
        'google_calendar_id' => array( 'format' => '%s' ),
        'position'           => array( 'format' => '%d', 'default' => 9999 ),
    );

    public function save()
    {
        $is_new = ! $this->get( 'id' );

        if ( $is_new && $this->get( 'wp_user_id' ) ) {
            $user = get_user_by( 'id', $this->get( 'wp_user_id' ) );
            if( $user ) {
                $this->set( 'email', $user->get( 'user_email' ) );
            }
        }

        parent::save();

        if ( $is_new ) {
            // Schedule items.
            $staff_id = $this->get( 'id' );
            $index    = 1;
            foreach ( array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ) as $week_day ) {
                $item = new AB_StaffScheduleItem();
                $item->set( 'staff_id', $staff_id );
                $item->set( 'day_index', $index ++ );
                $item->set( 'start_time', get_option( "ab_settings_{$week_day}_start" ) ?: null );
                $item->set( 'end_time', get_option( "ab_settings_{$week_day}_end" ) ?: null );
                $item->save();
            }

            // Create holidays for staff
            $this->wpdb->query( sprintf(
                'INSERT INTO `ab_holiday` (`parent_id`, `staff_id`, `holiday`, `repeat_event`, `title`)
                SELECT `id`, %d, `holiday`, `repeat_event`, `title` FROM `ab_holiday` WHERE `staff_id` IS NULL',
                $staff_id
            ) );
        }
    }

    public function getScheduleList()
    {
        if ( ! $this->isLoaded() ) {
            return array();
        }
        $list = $this->wpdb->get_results( $this->wpdb->prepare(
           'SELECT
              ssi.*,
              ssi.id AS "staff_schedule_item_id"
            FROM `ab_staff_schedule_item` ssi
            WHERE ssi.staff_id = %d
            ORDER BY ssi.day_index',
            $this->get( 'id' )
        ) );

        if ( ! empty( $list ) ) {
            $wp_week_start_day = get_option('start_of_week', 1);
            $list_start_day    = $list[0]->day_index - 1;

            // if wp week start day is higher than our
            // cut the list into 2 parts (before and after wp wp week start day)
            // move the second part of the list above the first one
            if ( $wp_week_start_day > $list_start_day ) {
                $list_start = array_slice( $list, 0, $wp_week_start_day );
                $list_end   = array_slice( $list, $wp_week_start_day );
                $list       = $list_end;

                foreach ( $list_start as $list_item ) {
                    $list[] = $list_item;
                }
            }
        }

        foreach ($list as $day) {
            $day->name = AB_DateTimeUtils::getWeekDayByNumber($day->day_index - 1);
        }

        return $list;
    }

    /**
     * Get staff appointments for period
     *
     * @param      $start_date
     * @param null $end_date
     *
     * @return array|mixed
     */
    public function getAppointments( $start_date, $end_date = null )
    {
        if ( ! $this->isLoaded() ) {
            return array();
        }
        $args = array(
            'SELECT
                  a.id,
                  a.start_date,
                  a.end_date,
                  service.title,
                  service.color,
                  staff.id AS "staff_id",
                  staff.full_name,
                  ss.capacity AS max_capacity,
                  SUM( ca.number_of_persons ) AS total_number_of_persons,
                  ca.customer_id
              FROM ab_appointment a
              LEFT JOIN ab_customer_appointment ca ON ca.appointment_id = a.id
              LEFT JOIN ab_service service ON a.service_id = service.id
              LEFT JOIN ab_staff staff ON a.staff_id = staff.id
              LEFT JOIN ab_staff_service ss ON ss.staff_id = a.staff_id AND ss.service_id = a.service_id
              WHERE staff.id = %d
              ',
            $this->get( 'id' )
        );

        if ( null !== $end_date ) {
            $args[0] .=' AND DATE(a.start_date) BETWEEN %s AND %s';
            $args[]   = $start_date;
            $args[]   = $end_date;
        } else {
            $args[0] .= ' AND DATE(a.start_date) = %s';
            $args[]   = $start_date;
        }

//        $args[0] .= ' GROUP BY a.id';
        $args[0] .= ' GROUP BY a.start_date';

        return $this->wpdb->get_results( call_user_func_array( array( $this->wpdb, 'prepare' ), $args ) );
    }

    /**
     * Get AB_StaffService entities associated with this staff member.
     *
     * @return array  Array of entities
     */
    public function getStaffServices()
    {
        $result = array();

        if ( $this->get( 'id' ) ) {
            $records = $this->wpdb->get_results( $this->wpdb->prepare(
                'SELECT `ss`.*,
                        `s`.`title`,
                        `s`.`duration`,
                        `s`.`price` AS `service_price`,
                        `s`.`color`,
                        `s`.`capacity` AS `service_capacity`
                FROM `ab_staff_service` `ss` LEFT JOIN `ab_service` `s` ON `s`.`id` = `ss`.`service_id`
                WHERE `ss`.`staff_id` = %d',
                $this->get( 'id' )
            ), ARRAY_A);

            foreach( $records as $data ) {
                $ss = new AB_StaffService();
                $ss->setData( $data );

                // Inject AB_Service entity.
                $ss->service        = new AB_Service();
                $data[ 'id' ]       = $data[ 'service_id' ];
                $data[ 'price' ]    = $data[ 'service_price' ];
                $data[ 'capacity' ] = $data[ 'service_capacity' ];
                $ss->service->setData( $data, true );

                $result[] = $ss;
            }
        }

        return $result;
    }

    public function delete()
    {
        parent::delete();
        if ( $this->get( 'avatar_path' ) ) {
            unlink( $this->get( 'avatar_path' ) );
        }
    }
}
