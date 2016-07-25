<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<?php
    // Show Progress Tracker if enabled in settings
    if ( get_option( 'ab_appearance_show_progress_tracker' ) == 1 ) {
        echo $progress_tracker;
    }
?>

<?php if ( !empty ( $time ) ): ?>
<div class="ab-teaser ab-row-fluid">
    <div class="ab-desc"><?php _e( $info_text, 'ab' ) ?></div>
</div>
<?php if (get_option( 'ab_appearance_show_calendar' )): ?>
  <div style="clear: both"></div>
  <style>.picker__holder{top: 0;left: 0;}</style>
  <div class="ab-input-wrap ab-slot-calendar">
      <span class="ab-date-wrap">
         <input style="display: none" class="ab-date-from-timeslots ab-formElement" type="text" value="<?php echo esc_attr( date_i18n( 'j F, Y', strtotime( $date_from ) ) ); ?>" />
      </span>
  </div>
<?php endif; ?>
<div class="ab-second-step">
  <div class="ab-columnizer-wrap">
      <div class="ab-columnizer">
          <?php foreach ( $time as $client_timestamp => $slot ) : ?>
              <?php if ( $slot[ 'is_day' ] ): ?>
                  <button class="ab-available-day" value="<?php echo esc_attr( date( 'Y-m-d', -$client_timestamp ) ) ?>"><?php echo date_i18n( 'D, M d', -$client_timestamp ) ?></button>
              <?php else: ?>
                  <button <?php disabled( $slot[ 'booked' ], true ) ?>
                      data-staff_id="<?php echo esc_attr( $slot[ 'staff_id' ] ) ?>"
                      data-date="<?php echo esc_attr( date( 'Y-m-d', $client_timestamp ) ) ?>"
                      class="ab-available-hour ladda-button<?php if ( $slot[ 'booked' ] ) echo ' booked' ?>"
                      value="<?php echo esc_attr( date( 'Y-m-d H:i:s', $slot[ 'timestamp' ] ) ) ?>"
                      data-style="zoom-in"
                      data-spinner-color="#333"
                      data-spinner-size="40"
                      >
                      <span class="ladda-label"><i class="ab-hour-icon"><span></span></i><?php echo date_i18n( get_option('time_format'), $client_timestamp ) ?></span>
                  </button>
              <?php endif ?>
          <?php endforeach ?>
      </div>
  </div>
</div>

<div class="ab-row-fluid ab-nav-steps ab-clear">
    <button class="ab-time-next ab-btn ab-right ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label">&gt;</span>
    </button>
    <button class="ab-time-prev ab-btn ab-right ladda-button" data-style="zoom-in" style="display: none" data-spinner-size="40">
        <span class="ladda-label">&lt;</span>
    </button>
    <button class="ab-left ab-to-first-step ab-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php _e( 'Back', 'ab' ) ?></span>
    </button>
</div>

<?php else: ?>
  <div class="ab-teaser ab-row-fluid">
      <div class="ab-desc"><?php _e( $info_text, 'ab' ) ?></div>
  </div>
<?php if (get_option( 'ab_appearance_show_calendar' )): ?>
  <div style="clear: both"></div>
  <style>.picker__holder{top: 0;left: 0;}</style>
  <div class="ab-input-wrap ab-slot-calendar">
      <span class="ab-date-wrap">
          <input style="display: none" class="ab-date-from-timeslots ab-formElement" type="text" value="<?php echo esc_attr( date_i18n( 'j F, Y', strtotime( $date_from ) ) ); ?>" />
      </span>
  </div>
<?php endif; ?>
<div class="ab-not-time-screen <?php echo get_option( 'ab_appearance_show_calendar' ) == 1 ? '' : 'ab-not-calendar' ?>">
    <?php _e( 'No time is available for selected criteria.', 'ab' ) ?>
</div>
<div class="ab-row-fluid ab-nav-steps ab-clear">
    <button class="ab-left ab-to-first-step ab-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php _e( 'Back', 'ab' ) ?></span>
    </button>
</div>
<?php endif ?>
