<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * @var AB_UserBookingData $userData
 * @var string $info_text
 */

// Show Progress Tracker if enabled in settings
if ( get_option( 'ab_appearance_show_progress_tracker' ) == 1 ) {
    echo $progress_tracker;
}
?>

<div class="ab-first-step">
    <div class="ab-row-fluid">
        <div class="ab-bold ab-desc"><?php _e( $info_text, 'ab' ) ?></div>
    </div>
    <form>
        <div class="ab-mobile-step_1 ab-row-fluid">
            <div class="ab-formGroup ab-category ab-left">
                <label class="ab-formLabel"><?php _e( get_option( 'ab_appearance_text_label_category' ), 'ab' ) ?></label>
                <div class="ab-formField">
                    <select class="ab-formElement ab-select-mobile ab-select-category">
                      <option value=""><?php echo esc_html( __( get_option( 'ab_appearance_text_option_category' ), 'ab' ) ) ?></option>
                    </select>
                </div>
            </div>
            <div class="ab-formGroup ab-service ab-left">
                <label class="ab-formLabel"><?php _e( get_option( 'ab_appearance_text_label_service' ), 'ab' ) ?></label>
                <div class="ab-formField">
                    <select class="ab-formElement ab-select-mobile ab-select-service">
                        <option value=""><?php echo esc_html( __( get_option( 'ab_appearance_text_option_service' ), 'ab' ) ) ?></option>
                    </select>
                </div>
                <div class="ab-select-service-error ab-label-error" style="padding-top: 5px; display: none"><?php _e( '* Please select a service', 'ab' ) ?></div>
            </div>
            <div class="ab-formGroup ab-employee ab-left">
                <label class="ab-formLabel"><?php _e( get_option( 'ab_appearance_text_label_employee' ), 'ab' ) ?></label>
                <div class="ab-formField">
                    <select class="ab-formElement ab-select-mobile ab-select-employee">
                      <option value=""><?php echo esc_html( __( get_option( 'ab_appearance_text_option_employee' ), 'ab' ) ) ?></option>
                    </select>
                </div>
            </div>
            <div class="ab-formGroup ab-number-of-persons ab-left">
                <label class="ab-formLabel"><?php _e( get_option( 'ab_appearance_text_label_number_of_persons' ), 'ab') ?></label>
                <div class="ab-formField">
                    <select class="ab-formElement ab-select-mobile ab-select-number-of-persons">
                        <option value="1">1</option>
                    </select>
                </div>
            </div>

            <div class="ab-nav-steps ab-clear ab-row-fluid">
                <button class="ab-right ab-mobile-next-step ab-btn ab-none ladda-button" data-style="zoom-in" data-spinner-size="40">
                    <span class="ladda-label"><?php _e( 'Next', 'ab' ) ?></span>
                </button>
            </div>
        </div>
        <div class="ab-mobile-step_2">
            <div class="ab-row-fluid">
                <div class="ab-left ab-available-date ab-formGroup ab-lastGroup hide-crap" style="width: auto">
                    <label class="ab-nowrap ab-bold"><?php _e( get_option( 'ab_appearance_text_label_select_date' ), 'ab' ) ?></label>
                    <div class="ab-input-wrap ab-formField">
                        <span class="ab-date-wrap">
                           <input class="ab-date-from ab-formElement" type="text" value="<?php echo esc_attr( date_i18n( 'j F, Y', strtotime( $userData->get( 'date_from' ) ) ) ) ?>" />
                        </span>
                    </div>
                </div>
                <?php if ( !empty( $work_day_time_data['available_days'] ) ) : ?>
                    <div class="ab-left ab-available-days hide-crap">
                        <ul class="ab-week-days">
                            <?php foreach ( $work_day_time_data['available_days'] as $key => $day ) : ?>
                                <li>
                                    <div class="ab-bold"><?php echo $day ?></div>
                                    <label<?php if ( in_array( $key, $days_checked ) ): ?> class="active"<?php endif ?>>
                                        <input class="ab-week-day ab-week-day-<?php echo $key ?>" value="<?php echo $key ?>" <?php checked( in_array( $key, $days_checked ) ) ?> type="checkbox"/>
                                    </label>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( !empty( $work_day_time_data['time_range'] ) ) : ?>
                    <?php $time_list = $work_day_time_data['time_range'] ?>
                    <div class="ab-left ab-time-range hide-crap">
                        <div class="ab-left ab-time-from">
                            <label class="ab-bold"><?php _e( get_option( 'ab_appearance_text_label_start_from' ), 'ab' ) ?></label>
                            <div>
                                <select class="ab-select-time-from">
                                    <?php foreach ($time_list as $key => $time) : ?>
                                        <option value="<?php echo $key ?>"<?php if ( $userData->get( 'time_from' ) == $key ) echo ' selected="selected"' ?>><?php echo $time ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        </div>
                        <div class="ab-left ab-time-to">
                            <label class="ab-bold"><?php _e( get_option( 'ab_appearance_text_label_finish_by' ), 'ab' ) ?></label>
                            <div>
                                <select class="ab-select-time-to">
                                    <?php foreach ($time_list as $key => $time) : ?>
                                        <option value="<?php echo $key ?>"<?php if ( $userData->get( 'time_to' ) == $key ) echo ' selected="selected"' ?>><?php echo $time ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        </div>
                        <div class="ab-select-time-error ab-label-error ab-clear" style="text-align:right; padding-top: 5px; display: none"><?php _e( '* The start time must be less than the end time', 'ab' ) ?></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="ab-nav-steps ab-clear ab-row-fluid">
                <button class="ab-left ab-mobile-prev-step ab-btn ab-none ladda-button" data-style="zoom-in" data-spinner-size="40">
                    <span class="ladda-label"><?php _e( 'Back', 'ab' ) ?></span>
                </button>
                <button class="ab-right ab-next-step ab-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
                    <span class="ladda-label"><?php _e( 'Next', 'ab' ) ?></span>
                </button>
            </div>
        </div>
    </form>
</div>
