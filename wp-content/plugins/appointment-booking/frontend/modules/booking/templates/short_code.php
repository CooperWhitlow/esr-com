<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php if ( $print_assets ) include '_css.php' ?>
<div id="ab-booking-form-<?php echo $form_id ?>" class="ab-booking-form"></div>
<script type="text/javascript">
    (function (win, fn) {
        var done = false, top = true,
            doc = win.document,
            root = doc.documentElement,
            modern = doc.addEventListener,
            add = modern ? 'addEventListener' : 'attachEvent',
            rem = modern ? 'removeEventListener' : 'detachEvent',
            pre = modern ? '' : 'on',
            init = function(e) {
                if (e.type == 'readystatechange') if (doc.readyState != 'complete') return;
                (e.type == 'load' ? win : doc)[rem](pre + e.type, init, false);
                if (!done) { done = true; fn.call(win, e.type || e); }
            },
            poll = function() {
                try { root.doScroll('left'); } catch(e) { setTimeout(poll, 50); return; }
                init('poll');
            };
        if (doc.readyState == 'complete') fn.call(win, 'lazy');
        else {
            if (!modern) if (root.doScroll) {
                try { top = !win.frameElement; } catch(e) { }
                if (top) poll();
            }
            doc[add](pre + 'DOMContentLoaded', init, false);
            doc[add](pre + 'readystatechange', init, false);
            win[add](pre + 'load', init, false);
        }
    })(window, function() {
        window.bookly({
            is_finished    : <?php echo (int)$booking_finished  ?>,
            is_cancelled   : <?php echo (int)$booking_cancelled  ?>,
            ajaxurl        : <?php echo json_encode( admin_url('admin-ajax.php') . ( isset( $_REQUEST[ 'lang' ] ) ? '?lang=' . $_REQUEST[ 'lang' ] : '' ) ) ?>,
            attributes     : <?php echo $attributes ?>,
            form_id        : <?php echo json_encode( $form_id ) ?>,
            start_of_week  : <?php echo intval( get_option( 'start_of_week' ) ) ?>,
            date_min       : <?php echo json_encode( AB_BookingConfiguration::getDateMin() ) ?>,
            final_step_url : <?php echo json_encode( get_option('ab_settings_final_step_url') ) ?>,
            custom_fields  : <?php echo get_option( 'ab_custom_fields' ) ?>,
            day_one_column : <?php echo intval( get_option( 'ab_appearance_show_day_one_column' ) ) ?>,
            show_calendar  : <?php echo intval( get_option( 'ab_appearance_show_calendar' ) ) ?>,
            woocommerce    : <?php echo intval( get_option( 'ab_woocommerce' ) && get_option( 'ab_woocommerce_product' ) && class_exists( 'WooCommerce' ) )  ?>,
            woocommerce_cart_url : <?php echo json_encode( class_exists( 'WooCommerce' ) ? WC()->cart->get_cart_url() : '' ) ?>
        });
    });
</script>