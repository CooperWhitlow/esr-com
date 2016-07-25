<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( add_query_arg( 'type', '_purchase_code' ) ) ?>" class="ab-settings-form" id="purchase_code">
    <?php if ( isset ( $message_pc ) ) : ?>
        <div style="margin: 0px!important;" class="updated below-h2">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <p><?php echo $message_pc ?></p>
        </div>
    <?php endif ?>

    <table class="form-horizontal">
        <tr>
            <td colspan="3">
                <fieldset class="ab-instruction">
                    <legend><?php _e( 'Instructions', 'ab' ) ?></legend>
                    <div><?php _e( 'Upon providing the purchase code you will have access to free updates of Bookly. Updates may contain functionality improvements and important security fixes. For more information on where to find your purchase code see this <a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-can-I-find-my-Purchase-Code-" target="_blank">page</a>.', 'ab' ) ?></div>
                </fieldset>
            </td>
        </tr>
        <tr>
            <td><label for="ab_envato_purchase_code"><?php _e( 'Purchase Code', 'ab' ) ?></label></td>
            <td>
                <input class="purchase-code" type="text" size="255" id=ab_envato_purchase_code name="ab_envato_purchase_code" value="<?php echo get_option( 'ab_envato_purchase_code' ) ?>" />
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <input type="submit" value="<?php _e( 'Save', 'ab' ) ?>" class="btn btn-info ab-update-button" />
                <button class="ab-reset-form" type="reset"><?php _e( ' Reset ', 'ab' ) ?></button>
            </td>
        </tr>
    </table>
</form>