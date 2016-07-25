<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php
$staffScheduleItem = new AB_StaffScheduleItem();
$staffScheduleItem->load($list_item->staff_schedule_item_id);

$breaks_list = $staffScheduleItem->getBreaksList();
$display     = count( $breaks_list ) ? 'inline-block' : 'none;';
?>
<table class="breaks-list hide-on-non-working-day" cellspacing="0" cellpadding="0"<?php if ( $day_is_not_available ) : ?> style="display: none"<?php endif; ?>>
    <tr>
        <td class="breaks-list-label">
            <span style="display: <?php echo $display ?>">
                <?php _e('breaks:','ab') ?>
            </span>
        </td>
        <td class="breaks-list-content">
            <?php foreach ( $breaks_list as $break_interval ) : ?>
                <?php
                $formatted_interval_start = date( $time_format, strtotime( $break_interval->start_time ) );
                $formatted_interval_end   = date( $time_format, strtotime( $break_interval->end_time ) );
                $formatted_interval       = $formatted_interval_start . ' - ' . $formatted_interval_end;
                if (isset($default_breaks)) {
                    $default_breaks['breaks'][] = array(
                        'start_time'            => $break_interval->start_time,
                        'end_time'              => $break_interval->end_time,
                        'staff_schedule_item_id'=> $break_interval->staff_schedule_item_id
                    );
                }

                $breakStart = new AB_TimeChoiceWidget( array( 'use_empty' => false ) );
                $break_start_choices = $breakStart->render(
                    '',
                    $break_interval->start_time,
                    array(
                        'class'              => 'break-start',
                        'data-default_value' => $start_time_default_value
                    )
                );
                $breakEnd = new AB_TimeChoiceWidget( array( 'use_empty' => false ) );
                $break_end_choices = $breakEnd->render(
                    '',
                    $break_interval->end_time,
                    array(
                        'class'              => 'break-end',
                        'data-default_value' => $end_time_default_value
                    )
                );

                $this->render('_break', array(
                    'staff_schedule_item_break_id'  => $break_interval->id,
                    'formatted_interval'            => $formatted_interval,
                    'break_start_choices'           => $break_start_choices,
                    'break_end_choices'             => $break_end_choices,
                ));
                ?>
            <?php endforeach; ?>
        </td>
    </tr>
</table>