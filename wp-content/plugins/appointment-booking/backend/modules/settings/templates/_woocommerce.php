<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( add_query_arg( 'type', '_woocommerce' ) ) ?>" class="ab-settings-form" id="woocommerce">

    <table class="form-horizontal">
        <?php if ( 1||! $woocommerce_active ): ?>
            <tr>
                <td colspan="2">
                    <fieldset class="ab-instruction">
                        <legend><?php _e( 'Instructions', 'ab' ) ?></legend>
                        <div>
                            <div style="margin-bottom: 10px">
                                <?php _e( 'You need to install and activate WooCommerce plugin before using the options below.<br/><br/>Once the plugin is activated do the following steps:', 'ab' ) ?>
                            </div>
                            <ol>
                                <li><?php _e( 'Create a product in WooCommerce that can be placed in cart.', 'ab' ) ?></li>
                                <li><?php _e( 'In the form below enable WooCommerce option.', 'ab' ) ?></li>
                                <li><?php _e( 'Select the product that you created at step 1 in the drop down list of products.', 'ab' ) ?></li>
                                <li><?php _e( 'If needed, edit item data which will be displayed in the cart.', 'ab' ) ?></li>
                            </ol>
                            <div style="margin-top: 10px">
                                <?php _e( 'Note that once you have enabled WooCommerce option in Bookly the built-in payment methods will no longer work. All your customers will be redirected to WooCommerce cart instead of standard payment step.', 'ab' ) ?>
                            </div>
                        </div>
                    </fieldset>
                </td>
            </tr>
        <?php endif ?>
        <tr>
            <td colspan="2"><div class="ab-payments-title">WooCommerce</div></td>
        </tr>
        <tr>
            <td colspan="2">
                <select name="ab_woocommerce" style="width: 200px;">
                    <?php foreach ( array( __( 'Disabled', 'ab' ) => '0', __( 'Enabled', 'ab' ) => '1' ) as $text => $mode ): ?>
                        <option value="<?php echo $mode ?>" <?php selected( get_option( 'ab_woocommerce' ), $mode ); ?> ><?php echo $text ?></option>
                    <?php endforeach ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Booking product', 'ab' ) ?></td>
            <td>
                <select name="ab_woocommerce_product" >
                    <?php foreach($candidates as $item) { ?>
                        <option value="<?php echo $item['id'] ?>" <?php selected( get_option( 'ab_woocommerce_product' ), $item['id'] ); ?>>
                            <?php echo $item['name'] ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="2"><div class="ab-payments-title"><?php _e( 'Cart item data','ab' ) ?></td>
        </tr>
        <tr>
            <td colspan="2"><input type="text" name="ab_woocommerce_cart_info_name" value="<?php echo esc_attr( get_option( 'ab_woocommerce_cart_info_name' ) ) ?>" placeholder="<?php echo esc_attr( __( 'Enter a name', 'ab' ) ) ?>" /></td>
        </tr>
        <tr>
            <td colspan="2">

                <textarea rows="8" name="ab_woocommerce_cart_info_value" style="width: 100%" placeholder="<?php _e( 'Enter a value','ab' ) ?>"><?php echo esc_textarea( get_option( 'ab_woocommerce_cart_info_value' ) ) ?></textarea>
            </td>
        </tr>
        <tr><td>[[APPOINTMENT_DATE]]</td><td><?php _e('date of appointment', 'ab') ?></td></tr>
        <tr><td>[[APPOINTMENT_TIME]]</td><td><?php _e('time of appointment', 'ab') ?></td></tr>
        <tr><td>[[CATEGORY_NAME]]</td><td><?php _e('name of category', 'ab') ?></td></tr>
        <tr><td>[[SERVICE_NAME]]</td><td><?php _e('name of service', 'ab') ?></td></tr>
        <tr><td>[[SERVICE_PRICE]]</td><td><?php _e('price of service', 'ab') ?></td></tr>
        <tr><td>[[STAFF_NAME]]</td><td><?php _e('name of staff', 'ab') ?></td></tr>
        <tr>
            <td colspan="2">
                <input type="submit" value="<?php _e( 'Save', 'ab' ) ?>" class="btn btn-info ab-update-button" />
                <button class="ab-reset-form" type="reset"><?php _e( ' Reset ', 'ab' ) ?></button>
            </td>
        </tr>
    </table>
</form>