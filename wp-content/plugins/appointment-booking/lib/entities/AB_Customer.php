<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_Customer
 */
class AB_Customer extends AB_Entity
{
    protected static $table_name = 'ab_customer';

    protected static $schema = array(
        'id'         => array( 'format' => '%d' ),
        'wp_user_id' => array( 'format' => '%d' ),
        'name'       => array( 'format' => '%s', 'default' => '' ),
        'phone'      => array( 'format' => '%s', 'default' => '' ),
        'email'      => array( 'format' => '%s', 'default' => '' ),
        'notes'      => array( 'format' => '%s', 'default' => '' ),
    );

    /**
     * Delete customer and associated WP user if requested.
     *
     * @param bool $with_wp_user
     * @return false|int
     */
    public function delete( $with_wp_user )
    {
        if ( $with_wp_user && $this->get( 'wp_user_id' ) ) {
            wp_delete_user( $this->get( 'wp_user_id' ) );
        }

        return parent::delete();
    }

    /**
     * Get array with appointments data for customer profile.
     *
     * @return array
     */
    public function getAppointmentsForProfile()
    {
        $records = array();

        if ( $this->get( 'id' ) ) {
            $result = $this->wpdb->get_results( $this->wpdb->prepare(
                'SELECT c.name               category,
                        sv.title             service,
                        s.full_name          staff,
                        a.start_date         start_date,
                        ss.price             price,
                        ca.number_of_persons number_of_persons,
                        ca.coupon_discount   coupon_discount,
                        ca.time_zone_offset  time_zone_offset,
                        ca.token             token
                 FROM ab_appointment a
                 LEFT JOIN ab_staff s ON s.id = a.staff_id
                 LEFT JOIN ab_service sv ON sv.id = a.service_id
                 LEFT JOIN ab_category c ON c.id = sv.category_id
                 LEFT JOIN ab_staff_service ss ON ss.staff_id = a.staff_id AND ss.service_id = a.service_id
                 INNER JOIN ab_customer_appointment ca ON ca.appointment_id = a.id AND ca.customer_id = %d',
                $this->get( 'id' )
            ), ARRAY_A);

            if ( $result ) {
                foreach ( $result as $row ) {
                    if ( $row['time_zone_offset'] !== null ) {
                        $row['start_date'] = AB_DateTimeUtils::applyTimeZoneOffset( $row[ 'start_date' ], $row[ 'time_zone_offset' ] );
                    }
                    if ( $row['coupon_discount'] ) {
                        $coupon = new AB_Coupon();
                        $coupon->set( 'discount', $row['coupon_discount'] );
                        $row['price'] = $coupon->apply( $row['price'] );
                    }
                    $row['price'] *= $row['number_of_persons'];

                    unset ( $row['time_zone_offset'], $row['coupon_discount'], $row['number_of_persons'] );

                    $records[] = $row;
                }
            }
        }

        return $records;
    }

    /**
     * Associate WP user with customer.
     *
     * @param null $user_id
     */
    public function setWPUser( $user_id = null )
    {
        if ( $user_id === null ) {
            $user_id = $this->_createWPUser();
        }

        if ( $user_id ) {
            $this->set( 'wp_user_id', $user_id );
        }
    }

    /**
     * Create new WP user and send email notification.
     *
     * @return bool|int
     */
    private function _createWPUser()
    {
        // Generate unique username.
        $i        = 1;
        $base     = $this->get( 'name' ) != '' ? sanitize_user( $this->get( 'name' ) ) : 'client';
        $username = $base;
        while ( username_exists( $username ) ) {
            $username = $base . $i;
            ++ $i;
        }
        // Generate password.
        $password = wp_generate_password( 6, true );
        // Create user.
        $user_id = wp_create_user( $username , $password, $this->get( 'email' ) );
        if ( ! $user_id instanceof WP_Error ) {
            // Set the role
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Send email notification.
            AB_NotificationSender::sendEmailForNewUser( $this, $username, $password );

            return $user_id;
        }

        return false;
    }
}