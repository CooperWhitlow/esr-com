<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_AppointmentsController
 */
class AB_AppointmentsController extends AB_Controller {

    public function index()
    {
        /** @var WP_Locale $wp_locale */
        global $wp_locale;

        $this->enqueueStyles( array(
            'backend' => array(
                'css/bookly.main-backend.css',
                'bootstrap/css/bootstrap.min.css',
                'css/daterangepicker.css',
                'css/bootstrap-select.min.css',
            )
        ) );

        $this->enqueueScripts( array(
            'backend' => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/angular-1.3.11.min.js',
                'js/angular-sanitize.min.js',
                'js/angular-ui-utils-0.2.1.min.js',
                'js/angular-ui-date-0.0.7.js',
                'js/moment.min.js',
                'js/moment-format-php.js' => array( 'ab-moment.min.js' ),
                'js/daterangepicker.js' => array( 'jquery', 'ab-moment-format-php.js' ),
                'js/bootstrap-select.min.js',
            ),
            'module' => array(
                'js/ng-app.js' => array( 'jquery', 'ab-angular-1.3.11.min.js', 'ab-angular-ui-utils-0.2.1.min.js', 'ab-angular-ui-date-0.0.7.js' ),
            )
        ) );

        wp_localize_script( 'ab-ng-app.js', 'BooklyL10n', array(
            'are_you_sure'  => __( 'Are you sure?', 'ab' ),
            'today'         => __( 'Today', 'ab' ),
            'yesterday'     => __( 'Yesterday', 'ab' ),
            'last_7'        => __( 'Last 7 Days', 'ab' ),
            'last_30'       => __( 'Last 30 Days', 'ab' ),
            'this_month'    => __( 'This Month', 'ab' ),
            'next_month'    => __( 'Next Month', 'ab' ),
            'custom_range'  => __( 'Custom Range', 'ab' ),
            'apply'         => __( 'Apply' ),
            'cancel'        => __( 'Cancel' ),
            'to'            => __( 'To', 'ab' ),
            'from'          => __( 'From', 'ab' ),
            'months'        => array_values( $wp_locale->month ),
            'days'          => array_values( $wp_locale->weekday_abbrev ),
            'start_of_week' => get_option( 'start_of_week' ),
            'formatPHP'     => get_option( 'date_format' ),
        ));

        $this->render( 'index' );
    }

    /**
     * Get list of appointments.
     */
    public function executeGetAppointments() {
        $wpdb = $this->getWpdb();

        $response = array(
            'status' => 'ok',
            'data'   => array(
                'appointments' => array(),
                'total'       => 0,
                'pages'       => 0,
                'active_page' => 0,
            )
        );

        $page   = intval( $this->getParameter( 'page' ) );
        $sort   = in_array( $this->getParameter( 'sort' ), array( 'staff_name', 'service_title', 'start_date', 'price' ) )
            ? $this->getParameter( 'sort' ) : 'start_date';
        $order  = in_array( $this->getParameter( 'order' ), array( 'asc', 'desc' ) ) ? $this->getParameter( 'order' ) : 'asc';

        $start_date = new DateTime( $this->getParameter( 'date_start' ) );
        $start_date = $start_date->format( 'Y-m-d H:i:s' );
        $end_date   = new DateTime( $this->getParameter( 'date_end' ) );
        $end_date   = $end_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' );

        $items_per_page = 20;
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `ab_appointment` WHERE start_date BETWEEN '{$start_date}' AND '{$end_date}'" );
        $pages = ceil( $total / $items_per_page );
        if ( $page < 1 || $page > $pages ) {
            $page = 1;
        }

        if ( $total ) {

            $query = "
                SELECT ca.id        AS id,
                       ca.number_of_persons AS number_of_persons,
                       ca.coupon_discount   AS coupon_discount,
                       st.full_name AS staff_name,
                       s.title      AS service_title,
                       a.start_date AS start_date,
                       s.duration   AS service_duration,
                       ss.price     AS price,
                       c.name       AS customer_name
                FROM ab_customer_appointment ca
                LEFT JOIN ab_appointment a    ON a.id = ca.appointment_id
                LEFT JOIN ab_service s        ON s.id = a.service_id
                LEFT JOIN ab_customer c       ON c.id = ca.customer_id
                LEFT JOIN ab_staff st         ON st.id = a.staff_id
                LEFT JOIN ab_staff_service ss ON ss.staff_id = st.id AND ss.service_id = s.id
                WHERE a.start_date BETWEEN '{$start_date}' AND '{$end_date}'
            ";

            $query .= " ORDER BY {$sort} {$order}";
            // LIMIT
            $start = ( $page - 1) * $items_per_page;
            $query .= " LIMIT {$start}, {$items_per_page}";

            $rows = $wpdb->get_results( $query, ARRAY_A );
            foreach ( $rows as &$row ) {
                if ( $row['coupon_discount'] ) {
                    $coupon = new AB_Coupon();
                    $coupon->set( 'discount', $row['coupon_discount'] );
                    $row['price'] = $coupon->apply( $row['price'] );
                }
                $row['price'] *= $row['number_of_persons'];
                $row['price']  = AB_CommonUtils::formatPrice( $row['price'] );
                $row['start_date'] = AB_DateTimeUtils::formatDateTime( $row['start_date'] );
                $row['service_duration'] = AB_Service::durationToString( $row['service_duration'] );
            }

            // Populate response.
            $response[ 'data' ][ 'appointments' ] = $rows;
            $response[ 'data' ][ 'total' ]        = $total;
            $response[ 'data' ][ 'pages' ]        = $pages;
            $response[ 'data' ][ 'active_page' ]  = $page;
        }

        echo json_encode( $response );
        exit ( 0 );
    }

    /**
     * Delete customer appointment.
     */
    public function executeDeleteCustomerAppointment()
    {
        $customer_appointment = new AB_CustomerAppointment();
        $customer_appointment->load( $this->getParameter( 'id' ) );

        $appointment = new AB_Appointment();
        $appointment->load( $customer_appointment->get( 'appointment_id' ) );

        $customer_appointment->delete();

        // Delete appointment, if there aren't customers
        $count = $this->getWpdb()->get_var( $this->getWpdb()->prepare(
            'SELECT COUNT(*) FROM `ab_customer_appointment` WHERE appointment_id = %d',
            $customer_appointment->get( 'appointment_id' )
        ) );

        if ( !$count ) {
            $appointment->delete();
        } else {
            $appointment->handleGoogleCalendar();
        }
    }

    /**
     * Export Appointment to CSV
     */
    public function executeExportToCSV() {
        $start_date = new DateTime( $this->getParameter( 'date_start' ) );
        $start_date = $start_date->format( 'Y-m-d H:i:s' );
        $end_date   = new DateTime( $this->getParameter( 'date_end' ) );
        $end_date   = $end_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' );
        $delimiter  = $this->getParameter( 'delimiter', ',' );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=Appointments.csv' );

        $header = array(
            __( 'Booking Time', 'ab' ),
            __( 'Staff Member', 'ab' ),
            __( 'Service', 'ab' ),
            __( 'Duration', 'ab' ),
            __( 'Price', 'ab' ),
            __( 'Customer', 'ab' ),
            __( 'Phone', 'ab' ),
            __( 'Email', 'ab' ),
        );

        $custom_fields = array();
        $fields_data = json_decode( get_option( 'ab_custom_fields' ) );
        foreach ($fields_data as $field_data) {
            $custom_fields[$field_data->id] = '';
            $header[] = $field_data->label;
        }

        $output = fopen( 'php://output', 'w' );
        fwrite($output, pack("CCC",0xef,0xbb,0xbf));
        fputcsv( $output, $header, $delimiter );

        $rows = $this->getWpdb()->get_results( "
        SELECT ca.id AS id,
               ca.number_of_persons AS number_of_persons,
               ca.coupon_discount AS coupon_discount,
               st.full_name AS staff_name,
               s.title AS service_title,
               a.start_date AS start_date,
               s.duration AS service_duration,
               c.name AS customer_name,
               c.phone AS customer_phone,
               c.email AS customer_email,
               ss.price AS price
        FROM ab_customer_appointment ca
        LEFT JOIN ab_appointment a ON a.id = ca.appointment_id
        LEFT JOIN ab_service s ON s.id = a.service_id
        LEFT JOIN ab_staff st ON st.id = a.staff_id
        LEFT JOIN ab_customer c ON c.id = ca.customer_id
        LEFT JOIN ab_staff_service ss ON ss.staff_id = st.id AND ss.service_id = s.id
        WHERE a.start_date between '{$start_date}' AND '{$end_date}'
        ORDER BY a.start_date DESC
        ", ARRAY_A );

        foreach( $rows as $row ) {

            if ( $row['coupon_discount'] ) {
                $coupon = new AB_Coupon();
                $coupon->set( 'discount', $row['coupon_discount'] );
                $row['price'] = $coupon->apply( $row['price'] );
            }
            $row['price'] *= $row['number_of_persons'];

            $row_data = array(
                $row['start_date'],
                $row['staff_name'],
                $row['service_title'],
                AB_Service::durationToString( $row['service_duration'] ),
                AB_CommonUtils::formatPrice( $row['price'] ),
                $row['customer_name'],
                $row['customer_phone'],
                $row['customer_email'],
            );

            $customer_appointment = new AB_CustomerAppointment();
            $customer_appointment->load($row['id']);
            foreach ($customer_appointment->getCustomFields() as $custom_field) {
                $custom_fields[$custom_field['id']] = $custom_field['value'];
            }

            fputcsv( $output, array_merge( $row_data, $custom_fields ), $delimiter );

            $custom_fields = array_map(function() { return ''; }, $custom_fields);
        }
        fclose( $output );

        exit();
    }

    /**
     * Override parent method to add 'wp_ajax_ab_' prefix
     * so current 'execute*' methods look nicer.
     */
    protected function registerWpActions( $prefix = '' ) {
        parent::registerWpActions( 'wp_ajax_ab_' );
    }
}