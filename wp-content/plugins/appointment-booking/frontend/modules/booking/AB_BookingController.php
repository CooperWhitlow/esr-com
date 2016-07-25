<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_BookingController
 */
class AB_BookingController extends AB_Controller
{

    protected function getPermissions()
    {
        return array(
          '_this' => 'anonymous',
        );
    }

    /**
     * Render Bookly shortcode.
     *
     * @param $attributes
     * @return string
     */
    public function renderShortCode( $attributes )
    {
        $this->print_assets = ! wp_script_is( 'bookly', 'done' );

        if ( $this->print_assets ) {
            ob_start();

            // The styles and scripts are registered in AB_Frontend.php
            wp_print_styles( 'ab-reset' );
            wp_print_styles( 'ab-picker-date' );
            wp_print_styles( 'ab-picker-classic-date' );
            wp_print_styles( 'ab-picker' );
            wp_print_styles( 'ab-ladda-themeless' );
            wp_print_styles( 'ab-ladda-min' );
            wp_print_styles( 'ab-main' );
            wp_print_styles( 'ab-columnizer' );

            wp_print_scripts( 'ab-spin' );
            wp_print_scripts( 'ab-ladda' );
            wp_print_scripts( 'ab-picker' );
            wp_print_scripts( 'ab-picker-date' );
            wp_print_scripts( 'ab-hammer' );
            // Android animation
            if ( stripos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'android' ) !== false ) {
                wp_print_scripts( 'ab-jquery-animate-enhanced');
            }
            wp_print_scripts( 'bookly' );

            $assets = ob_get_clean();
        }

        // Find bookings with any of paypal statuses
        $this->booking_finished = $this->booking_cancelled = false;
        $this->form_id = uniqid();
        if ( isset ( $_SESSION['bookly'] ) ) {
            foreach ( $_SESSION['bookly'] as $form_id => $data ) {
                if ( isset( $data['paypal'] ) ) {
                    if ( ! isset ( $data['paypal']['processed'] ) ) {
                        switch ( $data['paypal']['status'] ) {
                            case 'success':
                                $this->form_id = $form_id;
                                $this->booking_finished = true;
                                break;
                            case 'cancelled':
                            case 'error':
                                $this->form_id = $form_id;
                                $this->booking_cancelled = true;
                                break;
                        }
                        // Mark this form as processed for cases when there are more than 1 booking form on the page.
                        $_SESSION['bookly'][ $form_id ]['paypal']['processed'] = true;
                    }
                }
                else {
                    unset ( $_SESSION['bookly'][ $form_id ] );
                }
            }
        }

        $this->attributes = json_encode( array(
            'hide_categories'       => @$attributes[ 'hide_categories' ]        ?: ( @$attributes[ 'ch' ]  ?: false ),
            'category_id'           => @$attributes[ 'category_id' ]            ?: ( @$attributes[ 'cid' ] ?: false ),
            'hide_services'         => @$attributes[ 'hide_services' ]          ?: ( @$attributes[ 'hs' ]  ?: false ),
            'service_id'            => @$attributes[ 'service_id' ]             ?: ( @$attributes[ 'sid' ] ?: false ),
            'hide_staff_members'    => @$attributes[ 'hide_staff_members' ]     ?: ( @$attributes[ 'he' ]  ?: false ),
            'staff_member_id'       => @$attributes[ 'staff_member_id' ]        ?: ( @$attributes[ 'eid' ] ?: false ),
            'hide_date_and_time'    => @$attributes[ 'hide_date_and_time' ]     ?: ( @$attributes[ 'ha' ]  ?: false ),
            'show_number_of_persons'=> @$attributes[ 'show_number_of_persons' ] ?: false,
        ) );

        return $assets . $this->render( 'short_code', array(), false );
    }

    /**
     * Render first step.
     *
     * @return string JSON
     */
    public function executeRenderService()
    {
        $form_id = $this->getParameter( 'form_id' );

        $response = null;

        if ( $form_id ) {
            $configuration = new AB_BookingConfiguration();
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();

            if ( get_option( 'ab_settings_use_client_time_zone' ) ) {
                $time_zone_offset = $this->getParameter( 'time_zone_offset' );
                $configuration->setClientTimeZoneOffset( $time_zone_offset / 60 );
                $userData->saveData( array(
                    'time_zone_offset' => $time_zone_offset,
                    'date_from' => date( 'Y-m-d', current_time( 'timestamp' ) + AB_BookingConfiguration::getMinimumTimePriorBooking() - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS + $time_zone_offset * 60 ) )
                ) );
            }

            $this->work_day_time_data = $configuration->fetchAvailableWorkDaysAndTime();

            $this->_prepareProgressTracker( 1, $userData->getServicePrice() );
            $this->info_text = nl2br( get_option( 'ab_appearance_text_info_first_step' ) );
            // Prepare week days that need to be checked.
            $days_checked = $userData->get( 'days' );
            if ( empty( $days_checked ) ) {
                // Check all available days.
                $days_checked = array_keys( $this->work_day_time_data['available_days'] );
            }
            $response = array(
                'status'     => 'success',
                'html'       => $this->render( '1_service', array( 'userData' => $userData, 'days_checked' => $days_checked ), false ),
                'categories' => $configuration->getCategories(),
                'staff'      => $configuration->getStaff(),
                'services'   => $configuration->getServices(),
                'attributes' => $userData->get( 'service_id' )
                    ? array(
                        'service_id'        => $userData->get( 'service_id' ),
                        'staff_member_id'   => $userData->getStaffId(),
                        'number_of_persons' => $userData->get('number_of_persons'),
                    )
                    : null
            );
        }

        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit (0);
    }

    /**
     * Render second step.
     *
     * @return string JSON
     */
    public function executeRenderTime()
    {
        $form_id = $this->getParameter( 'form_id' );

        $response = null;

        if ( $form_id ) {
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();

            if ( $this->getParameter( 'selected_date' ) ) {
                $userData->saveData(array('date_from' => $this->getParameter( 'selected_date' )));
            }

            $availableTime = new AB_AvailableTime( $userData );
            $availableTime->load();

            $this->time = $availableTime->getTime();
            $this->_prepareProgressTracker( 2, $userData->getServicePrice() );
            $this->info_text = $this->_prepareInfoText( 2, $userData );

            // Set response.
            $response = array(
                'status'         => empty ( $this->time ) ? 'error' : 'success',
                'html'           => $this->render( '2_time', array('date_from' => $userData->get( 'date_from' ) ), false ),
                'has_more_slots' => $availableTime->hasMoreSlots(),
                'holidays'       => $availableTime->getHolidays(),
            );
        }
        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit (0);
    }

    /**
     * Render third step.
     *
     * @return string JSON
     */
    public function executeRenderDetails()
    {
        $form_id = $this->getParameter( 'form_id' );

        $response = null;

        if ( $form_id ) {
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();

            // Prepare custom fields data.
            $cf_data = array();
            $custom_fields = $userData->get( 'custom_fields' );
            if ( $custom_fields !== null ) {
                foreach ( json_decode( $custom_fields, true ) as $field ) {
                    $cf_data[ $field[ 'id' ] ] = $field[ 'value' ];
                }
            }

            $this->info_text = $this->_prepareInfoText( 3, $userData );
            $this->_prepareProgressTracker( 3, $userData->getServicePrice() );
            $response = array(
                'status' => 'success',
                'html'   => $this->render( '3_details', array(
                    'userData'      => $userData,
                    'custom_fields' => json_decode( get_option( 'ab_custom_fields' ) ),
                    'cf_data'       => $cf_data
                ), false )
            );
        }

        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit (0);
    }

    /**
     * Render fourth step.
     *
     * @return string JSON
     */
    public function executeRenderPayment()
    {
        $form_id = $this->getParameter( 'form_id' );

        $response = null;

        if ( $form_id ) {
            $payment_disabled = AB_BookingConfiguration::isPaymentDisabled();

            $userData = new AB_UserBookingData( $form_id );
            $userData->load();
            if ($userData->getServicePrice() <= 0) {
                $payment_disabled = true;
            }

            if ( $payment_disabled == false ) {
                $this->form_id = $form_id;
                $this->info_text = nl2br( get_option( 'ab_appearance_text_info_fourth_step' ) );
                $this->info_text_coupon = $this->_prepareInfoText(4, $userData);

                $service = $userData->getService();
                $price   = $userData->getFinalServicePrice();

                // create a paypal object
                $paypal = new PayPal();
                $product = new stdClass();
                $product->name  = $service->get( 'title' );
                $product->desc  = $service->getTitleWithDuration();
                $product->price = $price;
                $product->qty   = $userData->get('number_of_persons');
                $paypal->addProduct($product);

                // get the products information from the $_POST and create the Product objects
                $this->paypal = $paypal;
                $this->_prepareProgressTracker( 4, $price );

                // Set response.
                $response = array(
                    'status' => 'success',
                    'html'   => $this->render( '4_payment', array(
                        'userData'      => $userData,
                        'paypal_status' => $userData->extractPayPalStatus()
                    ), false )
                );
            }
        }

        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit (0);
    }

    /**
     * Render fifth step.
     *
     * @return string JSON
     */
    public function executeRenderComplete()
    {
        $state = array (
            'success' => nl2br( esc_html( get_option( 'ab_appearance_text_info_fifth_step' ) ) ),
            'error' =>  __( '<h3>The selected time is not available anymore. Please, choose another time slot.</h3>', 'ab' )
        );

        if ($form_id  = $this->getParameter( 'form_id' ) ) {
            $userData = new AB_UserBookingData($form_id);
            $userData->load();

            // Show Progress Tracker if enabled in settings
            if ( get_option( 'ab_appearance_show_progress_tracker' ) == 1 ) {
                $price = $userData->getServicePrice();

                $this->_prepareProgressTracker( 5, $price );
                echo json_encode ( array (
                    'state' => $state,
                    'step'  => $this->progress_tracker
                ) );
            }
            else {
                echo json_encode ( array ( 'state' => $state ) );
            }
        }

        exit ( 0 );
    }

    /**
     * Save booking data in session.
     */
    public function executeSessionSave()
    {
        $form_id = $this->getParameter( 'form_id' );
        $errors  = array();
        if ( $form_id ) {
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();
            $errors = $userData->validate( $this->getParameters() );
            if ( empty ( $errors ) ) {
                $userData->saveData( $this->getParameters() );
            }
        }

        header( 'Content-Type: application/json' );
        echo json_encode( $errors );
        exit;
    }

    /**
     * Save appointment (final action).
     */
    public function executeSaveAppointment()
    {
        $form_id = $this->getParameter( 'form_id' );
        $time_is_available = false;

        if ( $form_id ) {
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();

            if ( AB_BookingConfiguration::isPaymentDisabled() ||
                get_option( 'ab_settings_pay_locally' ) ||
                $userData->getFinalServicePrice() == 0
            ) {
                $availableTime = new AB_AvailableTime( $userData );
                // check if appointment's time is still available
                if ($availableTime->checkBookingTime()) {
                    $userData->save();
                    $time_is_available = true;
                }
            }
        }

        exit ( json_encode( array( 'state' => $time_is_available ) ) );
    }

    /**
     * render Progress Tracker for Backend Appearance
     */
    public function executeRenderProgressTracker( )
    {
        $booking_step = $this->getParameter( 'booking_step' );

        if ( $booking_step ) {
            $this->_prepareProgressTracker( $booking_step );

            echo json_encode( array(
                'html' => $this->progress_tracker
            ) );
        }
        exit;
    }

    public function executeRenderNextTime()
    {
        $form_id = $this->getParameter( 'form_id' );

        $response = null;

        if ( $form_id ) {
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();

            $availableTime = new AB_AvailableTime( $userData );
            $availableTime->setLastFetchedDay( $this->getParameter( 'start_date' ) );

            $availableTime->load();

            if ( count( $availableTime->getTime() ) ) { // check, if there are available time
                $html = '';
                foreach ( $availableTime->getTime() as $client_timestamp => $slot ) {
                    if ( $slot[ 'is_day' ] ) {
                        $button = sprintf(
                            '<button class="ab-available-day" value="%s">%s</button>',
                            esc_attr( date( 'Y-m-d', -$client_timestamp ) ),
                            date_i18n( 'D, M d', -$client_timestamp )
                        );
                    }
                    else {
                        $button = sprintf(
                            '<button data-date="%s" data-staff_id="%s" class="ab-available-hour ladda-button %s" value="%s" data-style="zoom-in" data-spinner-color="#333"><span class="ladda-label"><i class="ab-hour-icon"><span></span></i>%s</span></button>',
                            esc_attr( date( 'Y-m-d', $client_timestamp ) ),
                            $slot[ 'staff_id' ],
                            $slot['booked'] ? 'booked' : '',
                            esc_attr( date( 'Y-m-d H:i:s', $slot[ 'timestamp' ] ) ),
                            date_i18n( get_option('time_format'), $client_timestamp )
                        );
                    }
                    $html .= $button;
                }
                // Set response.
                $response = array(
                    'status'         => 'success',
                    'html'           => $html,
                    'has_more_slots' => $availableTime->hasMoreSlots() // show/hide the next button
                );
            }
            else {
                // Set response.
                $response = array(
                    'status' => 'error',
                    'html'   => sprintf(
                        '<h3>%s</h3>',
                        __( 'The selected time is not available anymore. Please, choose another time slot.', 'ab' )
                    )
                );
            }
        }

        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit (0);
    }

    /**
     * Cancel Appointment using token.
     */
    public function executeCancelAppointment()
    {
        $customer_appointment = new AB_CustomerAppointment();

        if ( $customer_appointment->loadBy( array( 'token' => $this->getParameter( 'token' ) ) ) ) {
            // Send email
            AB_NotificationSender::sendEmails( AB_NotificationSender::E_CANCELLED_APPOINTMENT, $customer_appointment );

            $customer_appointment->delete();

            $appointment = new AB_Appointment();
            $appointment->load( $customer_appointment->get( 'appointment_id' ) );

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

            if ( $this->url = get_option( 'ab_settings_cancel_page_url' ) ) {
                wp_redirect( $this->url );
                $this->render( 'cancel_appointment' );

                exit ( 0 );
            }
        }

        $this->url = home_url();
        if ( isset ( $_SERVER['HTTP_REFERER'] ) ) {
            if ( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) == parse_url( $this->url, PHP_URL_HOST ) ) {
                // Redirect back if user came from our site.
                $this->url = $_SERVER['HTTP_REFERER'];
            }
        }
        wp_redirect( $this->url );
        $this->render( 'cancel_appointment' );

        exit ( 0 );
    }

    /**
     * Apply coupon
     */
    public function executeApplyCoupon()
    {
        $form_id = $this->getParameter( 'form_id' );
        $coupon_code = $this->getParameter( 'coupon' );

        $response = null;

        if (get_option('ab_settings_coupons') and $form_id) {
            $userData = new AB_UserBookingData($form_id);
            $userData->load();

            $price = $userData->getServicePrice();

            if ($coupon_code === ''){
                $userData->saveData( array( 'coupon' => null ) );
                $response = array(
                    'status' => 'reset',
                    'text'   => $this->_prepareInfoText(4, $userData, $price)
                );
            }
            else {
                $coupon = new AB_Coupon();
                $coupon->loadBy( array(
                    'code' => $coupon_code,
                    'used' => 0,
                ) );

                if ( $coupon->isLoaded() ) {
                    $userData->saveData( array( 'coupon' => $coupon_code ) );
                    $price = $coupon->apply( $price );
                    $response = array(
                        'status'   => 'success',
                        'text'     => $this->_prepareInfoText(4, $userData, $price),
                        'discount' => $coupon->get( 'discount' )
                    );
                }
                else {
                    $userData->saveData( array( 'coupon' => null ) );
                    $response = array(
                        'status' => 'error',
                        'error'  => __('* This coupon code is invalid or has been used', 'ab'),
                        'text'   => $this->_prepareInfoText(4, $userData, $price)
                    );
                }
            }
        }

        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit (0);
    }

    /**
     * Render progress tracker into a variable.
     *
     * @param int $booking_step
     * @param int|bool $price
     */
    private function _prepareProgressTracker( $booking_step, $price = false )
    {
        $payment_disabled = (
            AB_BookingConfiguration::isPaymentDisabled()
            ||
            // If price is passed and it is zero then do not display payment step.
            $price !== false &&
            $price <= 0
        );

        $this->progress_tracker = $this->render( '_progress_tracker', array(
            'booking_step'     => $booking_step,
            'payment_disabled' => $payment_disabled
        ), false );
    }

    /**
     * Render info text into a variable.
     *
     * @param int $booking_step
     * @param AB_UserBookingData $userData
     * @param int $preset_price
     *
     * @return string
     */
    private function _prepareInfoText( $booking_step, $userData, $preset_price = null )
    {
        $service = $userData->getService();
        $category_name = $service->getCategoryName();
        $staff_name = $userData->getStaffName();
        $price = ($preset_price === null)? $userData->getServicePrice() : $preset_price;
        $number_of_persons = $userData->get('number_of_persons');

        // Convenient Time
        if ( $booking_step === 2 ) {
            $replacement = array(
                '[[STAFF_NAME]]'        => '<b>' . $staff_name . '</b>',
                '[[SERVICE_NAME]]'      => '<b>' . $service->get( 'title' ) . '</b>',
                '[[CATEGORY_NAME]]'     => '<b>' . $category_name . '</b>',
                '[[NUMBER_OF_PERSONS]]' => '<b>' . $number_of_persons . '</b>',
            );

            return str_replace( array_keys( $replacement ), array_values( $replacement ),
                nl2br( get_option( 'ab_appearance_text_info_second_step' ) )
            );
        }

        // Your Details
        if ( $booking_step === 3 ) {
            if ( get_option( 'ab_settings_use_client_time_zone' ) ) {
                $service_time = AB_DateTimeUtils::formatTime( AB_DateTimeUtils::applyTimeZoneOffset( $userData->get( 'appointment_datetime' ), $userData->get( 'time_zone_offset' ) ) );
            }
            else {
                $service_time = AB_DateTimeUtils::formatTime( $userData->get( 'appointment_datetime' ) );
            }
            $service_date = AB_DateTimeUtils::formatDate( $userData->get( 'appointment_datetime' ) );

            $replacement = array(
                '[[STAFF_NAME]]'        => '<b>' . $staff_name . '</b>',
                '[[SERVICE_NAME]]'      => '<b>' . $service->get( 'title' ) . '</b>',
                '[[CATEGORY_NAME]]'     => '<b>' . $category_name . '</b>',
                '[[SERVICE_TIME]]'      => '<b>' . $service_time . '</b>',
                '[[SERVICE_DATE]]'      => '<b>' . $service_date . '</b>',
                '[[SERVICE_PRICE]]'     => '<b>' . AB_CommonUtils::formatPrice( $price ) . '</b>',
                '[[TOTAL_PRICE]]'       => '<b>' . AB_CommonUtils::formatPrice( $price * $number_of_persons ) . '</b>',
                '[[NUMBER_OF_PERSONS]]' => '<b>' . $number_of_persons . '</b>',
            );

            return str_replace( array_keys( $replacement ), array_values( $replacement ),
                nl2br( get_option( 'ab_appearance_text_info_third_step' ) )
            );
        }

        // Coupon Text
        if ( $booking_step === 4 ) {
            $replacement = array(
                '[[SERVICE_PRICE]]'     => '<b>' . AB_CommonUtils::formatPrice( $price ) . '</b>',
                '[[TOTAL_PRICE]]'       => '<b>' . AB_CommonUtils::formatPrice( $price * $number_of_persons ) . '</b>',
                '[[NUMBER_OF_PERSONS]]' => '<b>' . $number_of_persons . '</b>',
            );

            return str_replace( array_keys( $replacement ), array_values( $replacement ),
                nl2br( get_option( 'ab_appearance_text_info_coupon' ) )
            );
        }

        return '';
    }

    /**
     * Override parent method to add 'wp_ajax_ab_' prefix
     * so current 'execute*' methods look nicer.
     */
    protected function registerWpActions( $prefix = '' )
    {
        parent::registerWpActions( 'wp_ajax_ab_' );
        parent::registerWpActions( 'wp_ajax_nopriv_ab_' );
    }
}