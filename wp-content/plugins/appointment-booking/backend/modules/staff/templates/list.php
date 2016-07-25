<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="ab-title">
<?php if ( is_super_admin() ) : ?>
    <?php _e( 'Staff Members', 'ab' ) ?> (<span id="ab-list-item-number"><?php echo count( $staff_members ) ?></span>)
<?php else: ?>
    <?php _e( 'Profile', 'ab' ) ?>
<?php endif ?>
</div>
<div id="ab-staff" class="ab-left-bar ab-static-width"<?php if ( ! is_super_admin() ): ?> style="display: none" <?php endif ?>>
    <ul id="ab-staff-list">
        <?php foreach ( $staff_members as $staff ) : ?>
            <li class="ab-staff-member" id="ab-list-staff-<?php echo $staff->get( 'id' ) ?>" data-staff-id="<?php echo $staff->get( 'id' ) ?>"<?php if ( $active_staff_id == $staff->get( 'id' ) ): ?> data-active="true"<?php endif ?>>
                <span class="ab-handle">
                    <i class="ab-inner-handle icon-move"></i>
                </span>
                <?php if ( $staff->get( 'avatar_url' ) ): ?>
                    <img class="left ab-avatar" src="<?php echo esc_url( $staff->get( 'avatar_url' ) ) ?>" />
                <?php else: ?>
                    <img class="left ab-avatar" src="<?php echo esc_url( plugins_url( 'backend/resources/images/default-avatar.png', AB_PATH . '/main.php' ) ) ?>" />
                <?php endif ?>
                <div class="ab-text-align"><?php echo esc_html( $staff->get( 'full_name' ) ) ?></div>
            </li>
        <?php endforeach ?>
    </ul>
    <?php include 'new.php' ?>

</div>

<div id="ab-edit-staff-member"<?php if ( is_super_admin() ): ?>class="ab-right-content ab-full-width"<?php endif ?>></div>
<div id="ab-staff-popover-ext" style="display: none">
    <p><?php _e('If this staff member requires separate login to access personal calendar, a regular WP user needs to be created for this purpose.', 'ab') ?></p>
    <p><?php _e('User with "Administrator" role will have access to calendars and settings of all staff members, user with some other role will have access only to personal calendar and settings.', 'ab') ?></p>
    <p><?php _e('If you will leave this field blank, this staff member will not be able to access personal calendar using WP backend.', 'ab') ?></p>
</div>
<div id="ab-staff-google-popover-ext" style="display: none">
    <p><?php _e('Synchronize the data of the staff member bookings with Google Calendar.', 'ab') ?></p>
</div>
<div id="ab-staff-calendar-id-popover-ext" style="display: none">
    <p><?php _e('The Calendar ID can be found by clicking on "Calendar settings" next to the calendar you wish to display. The Calendar ID is then shown beside "Calendar Address".', 'ab') ?></p>
    <p><?php _e( '<b>Leave this field empty</b> to work with the default calendar.', 'ab' ) ?></p>
</div>
