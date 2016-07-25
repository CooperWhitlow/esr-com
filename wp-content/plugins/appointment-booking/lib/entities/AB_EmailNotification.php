<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_EmailNotification
 */
class AB_EmailNotification extends AB_Entity {

    protected static $table_name = 'ab_email_notification';

    protected static $schema = array(
        'id'                      => array( 'format' => '%d' ),
        'customer_appointment_id' => array( 'format' => '%d' ),
        'staff_id'                => array( 'format' => '%d' ),
        'type'                    => array( 'format' => '%s' ),
        'created'                 => array( 'format' => '%s' ),
    );
}