<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<div class="break-interval-wrapper" data-break_id="<?php echo $staff_schedule_item_break_id; ?>">
    <div class="ab-popup-wrapper hide-on-non-working-day">
        <a class="ab-popup-trigger break-interval" href="javascript:void(0)"><?php echo $formatted_interval; ?></a>
        <div class="ab-popup" style="display: none">
            <div class="ab-arrow"></div>
            <div class="error" style="display: none"></div>
            <div class="ab-content">
                <table cellspacing="0" cellpadding="0">
                    <tr>
                        <td><?php echo $break_start_choices; ?> <span class="hide-on-non-working-day"><?php _e( 'to', 'ab') ?></span> <?php echo $break_end_choices; ?></td>
                    </tr>
                    <tr>
                        <td>
                            <a class="btn btn-info ab-popup-save ab-save-break"><?php _e('Save break','ab'); ?></a>
                            <a class="ab-popup-close" href="#"><?php _e('Cancel', 'ab'); ?></a>
                        </td>
                    </tr>
                </table>
                <a class="ab-popup-close ab-popup-close-icon" href="javascript:void(0)"></a>
            </div>
        </div>
    </div>
    <img class="delete-break" src="<?php echo plugins_url( 'backend/resources/images/delete_cross.png', AB_PATH . '/main.php' ); ?>" />
</div>