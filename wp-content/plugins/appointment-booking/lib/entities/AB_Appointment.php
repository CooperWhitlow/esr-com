<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_Appointment
 */
class AB_Appointment extends AB_Entity {

    protected static $table_name = 'ab_appointment';

    protected static $schema = array(
        'id'              => array( 'format' => '%d' ),
        'staff_id'        => array( 'format' => '%d' ),
        'service_id'      => array( 'format' => '%d' ),
        'start_date'      => array( 'format' => '%s' ),
        'end_date'        => array( 'format' => '%s' ),
        'google_event_id' => array( 'format' => '%s' ),
    );

    /**
     * Get color of service
     *
     * @param string $default
     * @return string
     */
    public function getColor( $default = '#DDDDDD' ) {
        if ( ! $this->isLoaded() ) {
            return $default;
        }

        $service = new AB_Service();

        if ( $service->load( $this->get( 'service_id' ) ) ) {
            return $service->get( 'color' );
        }

        return $default;
    }

    /**
     * Get AB_CustomerAppointment entities associated with this appointment.
     *
     * @return AB_CustomerAppointment[]   Array of entities
     */
    public function getCustomerAppointments() {
        $result = array();

        if ( $this->get( 'id' ) ) {
            $records = $this->wpdb->get_results( $this->wpdb->prepare(
                'SELECT `ca`.*,
                        `c`.`name`,
                        `c`.`phone`,
                        `c`.`email`
                FROM `ab_customer_appointment` `ca` LEFT JOIN `ab_customer` `c` ON `c`.`id` = `ca`.`customer_id`
                WHERE `ca`.`appointment_id` = %d',
                $this->get( 'id' )
            ), ARRAY_A);

            foreach( $records as $data ) {
                $ca = new AB_CustomerAppointment();
                $ca->setData( $data );

                // Inject AB_Customer entity.
                $ca->customer = new AB_Customer();
                $data[ 'id' ] = $data[ 'customer_id' ];
                $ca->customer->setData( $data, true );

                $result[] = $ca;
            }
        }

        return $result;
    }

    /**
     * Set array of customers associated with this appointment.
     *
     * @param array $data  Array of customer IDs, custom_fields and number_of_persons
     */
    public function setCustomers( array $data ) {
        // Prepare array of customers.
        $customers = array();
        foreach ( $data as $customer ) {
            $customers[ $customer[ 'id' ] ] = $customer;
        }

        // Retrieve customer IDs currently associated with this appointment.
        $current_ids = array_map( function( $ca ) { return $ca->customer->get( 'id' ); }, $this->getCustomerAppointments() );

        // Remove redundant customers.
        $customer_appointment = new AB_CustomerAppointment();
        foreach ( array_diff( $current_ids, array_keys( $customers ) ) as $id ) {
            if ( $customer_appointment->loadBy( array(
                'appointment_id' => $this->get( 'id' ),
                'customer_id'    => $id
            ) ) ) {
                $customer_appointment->delete();
            }
        }

        // Add new customers.
        foreach ( array_diff( array_keys( $customers ), $current_ids ) as $id ) {
            $customer_appointment = new AB_CustomerAppointment();
            $customer_appointment->set( 'appointment_id', $this->get('id') );
            $customer_appointment->set( 'customer_id', $id );
            $customer_appointment->set( 'custom_fields', json_encode( $customers[ $id ][ 'custom_fields' ] ) );
            $customer_appointment->set( 'number_of_persons', $customers[ $id ][ 'number_of_persons' ] );
            $customer_appointment->save();
        }

        // Update existing customers.
        foreach ( array_intersect( $current_ids, array_keys( $customers ) ) as $id ) {
            $customer_appointment = new AB_CustomerAppointment();
            $customer_appointment->loadBy( array(
                'appointment_id' => $this->get('id'),
                'customer_id'    => $id
            ) );
            $customer_appointment->set( 'custom_fields', json_encode( $customers[ $id ][ 'custom_fields' ] ) );
            $customer_appointment->set( 'number_of_persons', $customers[ $id ][ 'number_of_persons' ] );
            $customer_appointment->save();
        }
    }

    /**
     * Save appointment to database
     *(and delete event in Google Calendar if staff changes).
     *
     * @return false|int
     */
    public function save() {
        // Google Calendar.
        if ( $this->isLoaded() && $this->hasGoogleCalendarEvent() ) {
            $modified = $this->getModified();
            if ( array_key_exists( 'staff_id', $modified ) ) {
                // Delete event from the Google Calendar of the old staff if the staff was changed.
                $staff_id = $this->get( 'staff_id' );
                $this->set( 'staff_id', $modified[ 'staff_id' ] );
                $this->deleteGoogleCalendarEvent();
                $this->set( 'staff_id', $staff_id );
                $this->set( 'google_event_id', null );
            }
        }

        return parent::save();
    }

    /**
     * Delete entity from database
     *(and delete event in Google Calendar if it exists).
     *
     * @return bool|false|int
     */
    public function delete(){
        $result = parent::delete();
        if ( $result && $this->hasGoogleCalendarEvent() ) {
            $this->deleteGoogleCalendarEvent();
        }

        return $result;
    }

    /**
     * Create or update event in Google Calendar.
     *
     * @return bool
     */
    public function handleGoogleCalendar() {
        if ( $this->hasGoogleCalendarEvent() ) {
            return $this->updateGoogleCalendarEvent();
        }
        else {
            $google_event_id = $this->createGoogleCalendarEvent();
            if ( $google_event_id ) {
                $this->set( 'google_event_id', $google_event_id );
                return (bool)$this->save();
            }
        }

        return false;
    }

    /**
     * Check whether this appointment has an associated event in Google Calendar.
     *
     * @return bool
     */
    public function hasGoogleCalendarEvent() {
        return $this->get( 'google_event_id' ) != '';
    }

    /**
     * Create a new event in Google Calendar and associate it to this appointment.
     *
     * @return string|false
     */
    public function createGoogleCalendarEvent() {
        $google = new AB_Google();
        if ( $google->loadByStaffId( $this->get( 'staff_id' ) ) ) {
            // Create new event in Google Calendar.
            return $google->createEvent( $this );

        }

        return false;
    }

    public function updateGoogleCalendarEvent() {
        $google = new AB_Google();
        if ( $google->loadByStaffId( $this->get( 'staff_id' ) ) ) {
            // Update existing event in Google Calendar.
            return $google->updateEvent( $this );
        }

        return false;
    }

    /**
     * Delete event from Google Calendar associated to this appointment.
     *
     * @return bool
     */
    public function deleteGoogleCalendarEvent() {
        $google = new AB_Google();
        if ( $google->loadByStaffId( $this->get( 'staff_id' ) ) ) {
            // Delete existing event in Google Calendar.
            return $google->delete( $this->get( 'google_event_id' ) );
        }

        return false;
    }
}
