<?php
define('SHORTINIT', true);

require_once( __DIR__ . '/../../../../../wp-load.php' );
require_once( __DIR__ . '/../../../../../' . WPINC . '/formatting.php' );
require_once( __DIR__ . '/../../../../../' . WPINC . '/general-template.php' );
require_once( __DIR__ . '/../../../../../' . WPINC . '/pluggable.php' );
require_once( __DIR__ . '/../../../../../' . WPINC . '/link-template.php' );
require_once( __DIR__ . '/AB_CommonUtils.php' );
require_once( __DIR__ . '/AB_DateTimeUtils.php' );
require_once( __DIR__ . '/../AB_NotificationCodes.php' );
require_once( __DIR__ . '/../AB_NotificationSender.php' );
require_once( __DIR__ . '/../AB_Entity.php' );
require_once( __DIR__ . '/../entities/AB_Appointment.php' );
require_once( __DIR__ . '/../entities/AB_Category.php' );
require_once( __DIR__ . '/../entities/AB_Coupon.php' );
require_once( __DIR__ . '/../entities/AB_Customer.php' );
require_once( __DIR__ . '/../entities/AB_CustomerAppointment.php' );
require_once( __DIR__ . '/../entities/AB_EmailNotification.php' );
require_once( __DIR__ . '/../entities/AB_Notification.php' );
require_once( __DIR__ . '/../entities/AB_Service.php' );
require_once( __DIR__ . '/../entities/AB_Staff.php' );
require_once( __DIR__ . '/../entities/AB_StaffService.php' );

/**
 * Class Notifications
 */
class Notifications {
    private $mysql_now; // format: YYYY-MM-DD HH:MM:SS

    /**
     * @var array
     */
    private static $notifications_types = array(
        'event_next_day'   => 'SELECT * FROM ab_notifications WHERE slug = "event_next_day" AND active = 1',
        'evening_after'    => 'SELECT * FROM ab_notifications WHERE slug = "evening_after" AND active = 1',
        'evening_next_day' => 'SELECT * FROM ab_notifications WHERE slug = "evening_next_day" AND active = 1'
    );

    /**
     * @var array
     */
    private static $appointments_types = array(
        'event_next_day' =>
            'SELECT a.*, c.*, s.*, st.full_name AS staff_name, st.email AS staff_email, st.phone AS staff_phone, st.avatar_url AS staff_photo, ca.customer_id as customer_id, ss.price AS sprice
            FROM ab_customer_appointment ca
            LEFT JOIN ab_appointment a ON a.id = ca.appointment_id
            LEFT JOIN ab_customer c ON c.id = ca.customer_id
            LEFT JOIN ab_service s ON s.id = a.service_id
            LEFT JOIN ab_staff st ON st.id = a.staff_id
            LEFT JOIN ab_staff_service ss ON ss.staff_id = a.staff_id AND ss.service_id = a.service_id
            WHERE DATE(DATE_ADD("{{NOW}}", INTERVAL 1 DAY)) = DATE(a.start_date)
            AND NOT EXISTS (SELECT id FROM ab_email_notification aen WHERE DATE(aen.created) = DATE("{{NOW}}") AND aen.type = "staff_next_day_agenda" AND aen.staff_id = a.staff_id)',
        'evening_after' =>
            'SELECT `a`.*, `ca`.* FROM `ab_customer_appointment` `ca` LEFT JOIN `ab_appointment` `a` ON `a`.`id` = `ca`.`appointment_id`
            WHERE DATE("{{NOW}}") = DATE(`a`.`start_date`)
            AND NOT EXISTS (
              SELECT `id` FROM `ab_email_notification` `aen`
                WHERE DATE(`aen`.`created`) = DATE("{{NOW}}") AND `aen`.`type` = "client_follow_up_email" AND `aen`.`customer_appointment_id` = `ca`.`id`
            )',
        'evening_next_day' =>
            'SELECT `ca`.`id` FROM `ab_customer_appointment` `ca` LEFT JOIN `ab_appointment` `a` ON `a`.`id` = `ca`.`appointment_id`
            WHERE DATE(DATE_ADD("{{NOW}}", INTERVAL 1 DAY)) = DATE(`a`.`start_date`)
            AND NOT EXISTS (
              SELECT * FROM `ab_email_notification` `aen`
                WHERE DATE(`aen`.`created`) = DATE("{{NOW}}") AND `aen`.`type` = "client_reminder" AND `aen`.`customer_appointment_id` = `ca`.`id`
            )'
    );

    /**
     * @param array $notifications
     * @param $type
     */
    public function processNotifications( $notifications, $type ) {
        /** @var $wpdb wpdb */
        global $wpdb;

        $date = new DateTime();
        switch ( $type ) {
            case 'event_next_day':
                if ( $date->format( 'H' ) >= 18 ) {
                    $rows = $wpdb->get_results(str_replace('{{NOW}}', $this->mysql_now, self::$appointments_types[ 'event_next_day' ]));

                    if ( $rows ) {
                        $staff_schedules = array();
                        $staff_emails = array();
                        foreach ( $rows as $row ) {
                            $staff_schedules[$row->staff_id][] = $row;
                            $staff_emails[$row->staff_id] = $row->staff_email;
                        }

                        foreach ( $staff_schedules as $staff_id => $collection ) {
                            $schedule = '<table>';
                            foreach ( $collection as $object ) {
                                $startDate = new DateTime($object->start_date);
                                $endDate = new DateTime($object->end_date);
                                $schedule .= '<tr>';
                                $schedule .= sprintf( '<td>%s<td>',
                                    ($startDate->format( 'H:i' ) . '-' . $endDate->format( 'H:i' ) ) );
                                $schedule .= sprintf( '<td>%s<td>', $object->title );
                                $schedule .= sprintf( '<td>%s<td>', $object->name );
                                $schedule .= '</tr>';
                            }
                            $schedule .= '</table>';

                            $replacement = new AB_NotificationCodes();
                            $replacement->set('next_day_agenda', $schedule);
                            $replacement->set('appointment_datetime', $row->start_date);
                            $message = $replacement->replace($notifications->message);
                            $subject = $replacement->replace($notifications->subject);

                            // send mail & create emailNotification
                            if ( wp_mail( $staff_emails[$staff_id], $subject, wpautop( $message ), AB_CommonUtils::getEmailHeaderFrom() ) ) {
                                $email_notification = new AB_EmailNotification();
                                $email_notification->set('staff_id', $staff_id);
                                $email_notification->set('type', 'staff_next_day_agenda');
                                $email_notification->set('created', $date->format( 'Y-m-d H:i:s' ));
                                $email_notification->save();
                            }
                        }
                    }
                }
                break;
            case 'evening_after':
                if ( $date->format( 'H' ) >= 21 ) {
                    $rows = $wpdb->get_results(
                        str_replace('{{NOW}}', $this->mysql_now, self::$appointments_types[ 'evening_after' ]),
                        ARRAY_A
                    );

                    if ( $rows ) {
                        $notification = new AB_Notification();
                        $notification->loadBy( array( 'slug' => 'evening_after' ) );
                        foreach ( $rows as $row ) {
                            $customer_appointment = new AB_CustomerAppointment();
                            $customer_appointment->load( $row['id'] );

                            if ( AB_NotificationSender::sendCronEmails( AB_NotificationSender::C_FOLLOW_UP_EMAIL, $notification, $customer_appointment ) ) {
                                $email_notification = new AB_EmailNotification();
                                $email_notification->set( 'customer_appointment_id', $customer_appointment->get( 'id' ) );
                                $email_notification->set( 'type', 'client_follow_up_email' );
                                $email_notification->set( 'created', $date->format( 'Y-m-d H:i:s' ) );
                                $email_notification->save();
                            }
                        }
                    }
                }
                break;
            case 'evening_next_day':
                if ( $date->format( 'H' ) >= 18 ) {
                    $rows = $wpdb->get_results(
                        str_replace('{{NOW}}', $this->mysql_now, self::$appointments_types[ 'evening_next_day' ]),
                        ARRAY_A
                    );

                    if ( $rows ) {
                        $notification = new AB_Notification();
                        $notification->loadBy( array( 'slug' => 'evening_next_day' ) );
                        foreach ( $rows as $row ) {
                            $customer_appointment = new AB_CustomerAppointment();
                            $customer_appointment->load( $row['id'] );

                            if ( AB_NotificationSender::sendCronEmails( AB_NotificationSender::C_NEXT_DAY_APPOINTMENT, $notification, $customer_appointment ) ) {
                                $email_notification = new AB_EmailNotification();
                                $email_notification->set( 'customer_appointment_id', $customer_appointment->get( 'id' ) );
                                $email_notification->set( 'type', 'client_reminder' );
                                $email_notification->set( 'created', $date->format( 'Y-m-d H:i:s' ) );
                                $email_notification->save();
                            }
                        }
                    }
                }
                break;
        }
    }

    /**
     * Constructor
     */
    public function __construct() {
        /** @var $wpdb wpdb */
        global $wpdb;

        date_default_timezone_set( AB_CommonUtils::getTimezoneString() );

        wp_load_translations_early();

        $now = new DateTime();
        $this->mysql_now = $now->format('Y-m-d H:i:s');

        // run each notification
        foreach ( self::$notifications_types as $type => $query ) {
            $notifications = $wpdb->get_row( $query );

            if ( $notifications ) {
                $this->processNotifications( $notifications, $type );
            }
        }
    }

}

$notifications = new Notifications();