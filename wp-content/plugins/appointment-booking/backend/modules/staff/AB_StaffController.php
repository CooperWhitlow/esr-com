<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_StaffController
 *
 * @property $form
 * @property $collection
 * @property $services
 * @property $staff_id
 * @property AB_Staff $staff
 */
class AB_StaffController extends AB_Controller {

    protected function getPermissions()
    {
        return get_option( 'ab_settings_allow_staff_members_edit_profile' ) ? array( '_this' => 'user' ) : array();
    }

    public function index()
    {
        /** @var WP_Locale $wp_locale */
        global $wp_locale;

        $this->enqueueStyles( array(
            'backend' => array(
                'css/bookly.main-backend.css',
                'bootstrap/css/bootstrap.min.css',
                'css/jCal.css',
            ),
            'module' => array(
                'css/staff.css'
            )
        ) );

        $this->enqueueScripts( array(
            'backend' => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/ab_popup.js' => array( 'jquery' ),
                'js/jCal.js' => array( 'jquery' ),
            ),
            'module' => array(
                'js/staff.js' => array( 'jquery-ui-sortable', 'jquery' ),
            )
        ) );

        wp_localize_script( 'ab-jCal.js', 'BooklyL10n',  array(
            'we_are_not_working' => __( 'We are not working on this day', 'ab' ),
            'repeat'             => __( 'Repeat every year', 'ab' ),
            'months'             => array_values( $wp_locale->month ),
            'days'               => array_values( $wp_locale->weekday_abbrev )
        ) );

        $this->form = new AB_StaffMemberNewForm();

        $em = AB_EntityManager::getInstance( 'AB_Staff' );
        $this->staff_members = is_super_admin()
            ? $em->findAll( array( 'position' => 'asc' ) )
            : $em->findBy( array( 'wp_user_id' => get_current_user_id() ) );

        if ( ! isset ( $this->active_staff_id ) ) {
            if ( $this->hasParameter( 'staff_id' ) ) {
                $this->active_staff_id = $this->getParameter( 'staff_id' );
            } else {
                $this->active_staff_id = ! empty ( $this->staff_members ) ? $this->staff_members[0]->get( 'id' ) : 0;
            }
        }

        // Check if this request is the request after google auth, set the token-data to the staff
        if ( $this->hasParameter( 'code' ) ) {
            $google = new AB_Google();
            $success_auth = $google->authCodeHandler( $this->getParameter( 'code' ) );

            if ( $success_auth ) {
                $staff_id = base64_decode( strtr( $this->getParameter( 'state' ), '-_,', '+/=' ) );
                $staff = new AB_Staff();
                $staff->load( $staff_id );
                $staff->set( 'google_data', $google->getAccessToken() );
                $staff->save();

                echo '<script>location.href="' . AB_Google::generateRedirectURI() . '&staff_id=' . $staff_id . '";</script>';

                exit (0);
            }
            else {
                $_SESSION['google_auth_error'] = json_encode($google->getErrors());
            }
        }

        if ( $this->hasParameter( 'google_logout' ) ) {
            $google = new AB_Google();
            $this->active_staff_id = $google->logoutByStaffId( $this->getParameter( 'google_logout' ) );
        }

        $this->render( 'list' );
    }

    public function executeCreateStaff()
    {
        $this->form = new AB_StaffMemberNewForm();
        $this->form->bind( $this->getPostParameters() );

        $staff = $this->form->save();
        if ( $staff ) {
            $this->render( 'list_item', array( 'staff' => $staff ) );
        }
        exit;
    }

    public function executeUpdateStaffPosition()
    {
        $staff_sorts = $this->getParameter( 'position' );
        foreach ( $staff_sorts as $position => $staff_id ) {
            $staff_sort = new AB_Staff();
            $staff_sort->load($staff_id);
            $staff_sort->set( 'position', $position );
            $staff_sort->save();
        }
    }

    public function executeStaffServices()
    {
        $this->form = new AB_StaffServicesForm();
        $this->form->load( $this->getParameter( 'id' ) );
        $this->staff_id = $this->getParameter( 'id' );
        $this->render( 'services' );
        exit;
    }

    public function executeStaffSchedule()
    {
        $staff = new AB_Staff();
        $staff->load( $this->getParameter( 'id' ) );
        $this->schedule_list = $staff->getScheduleList();
        $this->staff_id      = $this->getParameter( 'id' );
        $this->render( 'schedule' );
        exit;
    }

    public function executeStaffScheduleUpdate()
    {
        $this->form = new AB_StaffScheduleForm();
        $this->form->bind( $this->getPostParameters() );
        $this->form->save();
        exit;
    }

    /**
     *
     * @throws Exception
     */
    public function executeResetBreaks()
    {
        $breaks = $this->getParameter( 'breaks' );

        // remove all breaks for staff member
        $break = new AB_ScheduleItemBreak();
        $break->removeBreaksByStaffId( $breaks[ 'staff_id' ] );
        $html_breaks = array();

        // restore previous breaks
        if (isset($breaks['breaks']) && is_array($breaks['breaks'])) {
            foreach ($breaks['breaks'] as $day) {
                $schedule_item_break = new AB_ScheduleItemBreak();
                $schedule_item_break->setData($day);
                $schedule_item_break->save();
            }
        }

        $staff = new AB_Staff();
        $staff->load( $breaks['staff_id'] );

        // make array with breaks (html) for each day
        foreach ( $staff->getScheduleList() as $list_item ) {
            $html_breaks[$list_item->id] = $this->render( '_breaks', array(
                'day_is_not_available'     => null === $list_item->start_time,
                'list_item'                => $list_item,
                'time_format'              => get_option( 'time_format' ),
                'start_time_default_value' => AB_StaffScheduleItem::WORKING_START_TIME,
                'end_time_default_value'   => AB_StaffScheduleItem::WORKING_END_TIME,
            ), false );
        }

        echo json_encode($html_breaks);
        exit();
    }

    public function executeStaffScheduleHandleBreak()
    {
        $start_time    = $this->getParameter( 'start_time' );
        $end_time      = $this->getParameter( 'end_time' );
        $working_start = $this->getParameter( 'working_start' );
        $working_end   = $this->getParameter( 'working_end' );

        if ( strtotime( date( 'Y-m-d ' . $start_time ) ) >= strtotime( date( 'Y-m-d ' . $end_time ) ) ) {
            echo json_encode( array(
                'success'   => false,
                'error_msg' => __( 'The start time must be less than the end one', 'ab' ),
            ) );
            exit;
        }

        $staffScheduleItem = new AB_StaffScheduleItem();
        $staffScheduleItem->load( $this->getParameter( 'staff_schedule_item_id' ) );

        $break_id = $this->getParameter( 'break_id', 0 );

        $in_working_time = $working_start <= $start_time && $start_time <= $working_end
            && $working_start <= $end_time && $end_time <= $working_end;
        if ( !$in_working_time || ! $staffScheduleItem->isBreakIntervalAvailable( $start_time, $end_time, $break_id ) ) {
            echo json_encode( array(
                'success'   => false,
                'error_msg' => __( 'The requested interval is not available', 'ab' ),
            ) );
            exit;
        }

        $formatted_interval_start = AB_DateTimeUtils::formatTime( $start_time );
        $formatted_interval_end   = AB_DateTimeUtils::formatTime( $end_time );
        $formatted_interval       = $formatted_interval_start . ' - ' . $formatted_interval_end;

        if ( $break_id ) {
            $break = new AB_ScheduleItemBreak();
            $break->load( $break_id );
            $break->set( 'start_time', $start_time );
            $break->set( 'end_time', $end_time );
            $break->save();

            echo json_encode( array(
                'success'      => true,
                'new_interval' => $formatted_interval,
            ) );
        } else {
            $this->form = new AB_StaffScheduleItemBreakForm();
            $this->form->bind( $this->getPostParameters() );

            $staffScheduleItemBreak = $this->form->save();
            if ( $staffScheduleItemBreak ) {
                $breakStart = new AB_TimeChoiceWidget( array( 'use_empty' => false ) );
                $break_start_choices = $breakStart->render(
                    '',
                    $start_time,
                    array(
                        'class'              => 'break-start',
                        'data-default_value' => AB_StaffScheduleItem::WORKING_START_TIME
                    )
                );
                $breakEnd = new AB_TimeChoiceWidget( array( 'use_empty' => false ) );
                $break_end_choices = $breakEnd->render(
                    '',
                    $end_time,
                    array(
                        'class'              => 'break-end',
                        'data-default_value' => date( 'H:i:s', strtotime( AB_StaffScheduleItem::WORKING_START_TIME . ' + 1 hour' ) )
                    )
                );
                echo json_encode(array(
                    'success'      => true,
                    'item_content' => $this->render('_break', array(
                        'staff_schedule_item_break_id'  => $staffScheduleItemBreak->get( 'id' ),
                        'formatted_interval'            => $formatted_interval,
                        'break_start_choices'           => $break_start_choices,
                        'break_end_choices'             => $break_end_choices,
                    ), false),
                ) );
            } else {
                echo json_encode( array(
                    'success'   => false,
                    'error_msg' => __( 'Error adding the break interval', 'ab'),
                ) );
            }
        }

        exit;
    }

    public function executeDeleteStaffScheduleBreak()
    {
        $break = new AB_ScheduleItemBreak();
        $break->load( $this->getParameter( 'id' ) );
        $break->delete();
        exit;
    }

    public function executeStaffServicesUpdate()
    {
        $this->form = new AB_StaffServicesForm();
        $this->form->bind( $this->getPostParameters() );
        $this->form->save();
        exit;
    }

    public function executeEditStaff()
    {
        $this->form = new AB_StaffMemberEditForm();
        $this->staff = new AB_Staff();
        $this->staff->load( $this->getParameter( 'id' ) );
        $staff_errors = array();

        if ( isset( $_SESSION['was_update'] ) ) {
            unset($_SESSION['was_update']);
            $this->update = true;
        }
        else if( isset ( $_SESSION['google_calendar_error'] ) ) {
            $staff_errors[] = __('Calendar ID is not valid.', 'ab') . ' (' . $_SESSION['google_calendar_error'] . ')';
            unset($_SESSION['google_calendar_error']);
        }

        if (isset($_SESSION['google_auth_error'])){
            foreach (json_decode($_SESSION['google_auth_error']) as $error){
                $staff_errors[] = $error;
            }
            unset($_SESSION['google_auth_error']);
        }

        if ( $this->staff->get( 'google_data' ) == '' ) {
            if ( get_option( 'ab_settings_google_client_id' ) == '' ) {
                $this->authUrl = false;
            }
            else {
                $google = new AB_Google();
                $this->authUrl = $google->createAuthUrl( $this->getParameter( 'id' ) );
            }
        }

        $this->render('edit', array(
            'staff_errors' => $staff_errors
        ));
        exit;
    }

    /**
     * Update staff from POST request.
     * @see AB_Backend.php
     */
    public function updateStaff()
    {
        if ( ! is_super_admin() ) {
            // Check permissions to prevent one staff member from updating profile of another staff member.
            do {
                if ( get_option( 'ab_settings_allow_staff_members_edit_profile' ) ) {
                    $staff = new AB_Staff();
                    $staff->load( $this->getParameter( 'id' ) );
                    if ( $staff->get( 'wp_user_id' ) == get_current_user_id() ) {
                        unset ( $_POST['wp_user_id'] );
                        break;
                    }
                }
                do_action( 'admin_page_access_denied' );
                wp_die( __( 'Bookly: You do not have sufficient permissions to access this page.', 'ab' ) );
            } while ( 0 );
        }

        $form = new AB_StaffMemberEditForm();
        $form->bind( $this->getPostParameters(), $_FILES );
        $result = $form->save();

        // Set staff id to load the form for.
        $this->active_staff_id = $this->getParameter( 'id' );

        if ( $result === false && array_key_exists('google_calendar', $form->getErrors() ) ) {
            $errors = $form->getErrors();
            $_SESSION['google_calendar_error'] = $errors['google_calendar'];
        } else {
            $_SESSION['was_update'] = true;
        }
    }

    public function executeDeleteStaff()
    {
        $staff = new AB_Staff();
        $staff->load( $this->getParameter( 'id' ) );
        $staff->delete();
        $form = new AB_StaffMemberForm();
        header('Content-Type: application/json');
        echo json_encode($form->getUsersForStaff());
        exit;
    }

    public function executeDeleteStaffAvatar()
    {
        $staff = new AB_Staff();
        $staff->load( $this->getParameter( 'id' ) );
        unlink( $staff->get( 'avatar_path' ) );
        $staff->set( 'avatar_url', '' );
        $staff->set( 'avatar_path', '' );
        $staff->save();
        exit;
    }

    public function executeStaffHolidays()
    {
        $this->id = $this->getParameter( 'id', 0 );
        $this->holidays = $this->getHolidays( $this->id );
        $this->render('holidays');
        exit;
    }

    public function executeStaffHolidaysUpdate()
    {
        $id         = $this->getParameter( 'id' );
        $holiday    = $this->getParameter( 'holiday' ) == 'true';
        $repeat     = $this->getParameter( 'repeat' ) == 'true';
        $day        = $this->getParameter( 'day', false );
        $staff_id   = $this->getParameter( 'staff_id' );

        if ( $staff_id ) {
            // update or delete the event
            if ( $id ) {
                if ( $holiday ) {
                    $this->getWpdb()->update( 'ab_holiday', array( 'repeat_event' => intval( $repeat ) ), array( 'id' => $id ), array( '%d' ) );
                } else {
                    $this->getWpdb()->delete( 'ab_holiday', array( 'id' => $id ), array( '%d' ) );
                }
                // add the new event
            } else if ( $holiday && $day ) {
                $day = new DateTime($day);
                $this->getWpdb()->insert( 'ab_holiday', array( 'holiday' => date( 'Y-m-d H:i:s', $day->format( 'U' ) ), 'repeat_event' => intval( $repeat ), 'staff_id' => $staff_id ), array( '%s', '%d', '%d' ) );
            }

            // and return refreshed events
            echo $this->getHolidays($staff_id);
        }
        exit;
    }



    // Protected methods.

    protected function getHolidays( $id )
    {
        $collection = $this->getWpdb()->get_results( $this->getWpdb()->prepare( "SELECT * FROM ab_holiday WHERE staff_id = %d",  $id ) );
        $holidays = array();
        if ( count( $collection ) ) {
            foreach ( $collection as $holiday ) {
                $holidays[$holiday->id] = array(
                    'm'     => intval(date('m', strtotime($holiday->holiday))),
                    'd'     => intval(date('d', strtotime($holiday->holiday))),
                    'title' => $holiday->title,
                );
                // if not repeated holiday, add the year
                if ( ! $holiday->repeat_event ) {
                    $holidays[$holiday->id]['y'] = intval(date('Y', strtotime($holiday->holiday)));
                }
            }
        }

        return json_encode( (object) $holidays );
    }

    /**
     * Extend parent method to control access on staff member level.
     *
     * @param string $action
     * @return bool
     */
    protected function hasAccess( $action )
    {
        if ( parent::hasAccess( $action ) ) {
            if ( ! is_super_admin() ) {
                $staff = new AB_Staff();

                switch ( $action ) {
                    case 'executeEditStaff':
                    case 'executeDeleteStaffAvatar':
                    case 'executeStaffServices':
                    case 'executeStaffSchedule':
                    case 'executeStaffHolidays':
                        $staff->load( $this->getParameter( 'id' ) );
                        break;
                    case 'executeStaffServicesUpdate':
                    case 'executeStaffHolidaysUpdate':
                        $staff->load( $this->getParameter( 'staff_id' ) );
                        break;
                    case 'executeStaffScheduleHandleBreak':
                        $staffScheduleItem = new AB_StaffScheduleItem();
                        $staffScheduleItem->load( $this->getParameter( 'staff_schedule_item_id' ) );
                        $staff->load( $staffScheduleItem->get( 'staff_id' ) );
                        break;
                    case 'executeDeleteStaffScheduleBreak':
                        $break = new AB_ScheduleItemBreak();
                        $break->load( $this->getParameter( 'id' ) );
                        $staffScheduleItem = new AB_StaffScheduleItem();
                        $staffScheduleItem->load( $break->get( 'staff_schedule_item_id' ) );
                        $staff->load( $staffScheduleItem->get( 'staff_id' ) );
                        break;
                    case 'executeStaffScheduleUpdate':
                        if ( $this->hasParameter( 'days' ) ) {
                            foreach ( $this->getParameter( 'days' ) as $id => $day_index ) {
                                $staffScheduleItem = new AB_StaffScheduleItem();
                                $staffScheduleItem->load( $id );
                                $staff = new AB_Staff();
                                $staff->load( $staffScheduleItem->get('staff_id') );
                                if ( $staff->get( 'wp_user_id' ) != get_current_user_id() ) {
                                    return false;
                                }
                            }
                        }
                        break;
                    default:
                        return false;
                }

                return $staff->get( 'wp_user_id' ) == get_current_user_id();
            }

            return true;
        }

        return false;
    }

    /**
     * Override parent method to add 'wp_ajax_ab_' prefix
     * so current 'execute*' methods look nicer.
     */
    protected function registerWpActions( $prefix = '' )
    {
        parent::registerWpActions( 'wp_ajax_ab_' );
    }
}