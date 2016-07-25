<?php if ( is_user_logged_in() ): ?>
    <div class="ab-customer-appointments">
        <h2><?php _e( 'Appointments', 'ab' ) ?></h2>
        <?php if ( !empty( $appointments ) ): ?>
            <?php if ( isset( $attr[ 'columns' ] ) && $columns = explode( ',', $attr[ 'columns' ] ) ): ?>
                <table class="ab-appointments-table">

                    <?php if ( isset( $attr[ 'show_column_titles' ] ) && $attr[ 'show_column_titles' ] ): ?>
                        <thead>
                            <tr>
                                <?php foreach ( $columns as $column ): ?>
                                    <th class="<?php echo 'ab-column-' . $column ?>"><?php _e( ucfirst( $column ), 'ab' ) ?></th>
                                <?php endforeach ?>
                            </tr>
                        </thead>
                    <?php endif ?>

                    <?php foreach ( $appointments as $a ): ?>
                    <tr>
                        <?php foreach ( $columns as $column ): ?>
                            <?php
                                switch ( $column ) {

                                    case 'date':
                                        ?><td class="ab-column-date"><?php echo AB_DateTimeUtils::formatDate( $a[ 'start_date' ] ) ?></td><?php
                                        break;

                                    case 'time':
                                        ?><td class="ab-column-time"><?php echo AB_DateTimeUtils::formatTime( $a[ 'start_date' ] ) ?></td><?php
                                        break;

                                    case 'price':
                                        ?><td class="ab-column-price"><?php echo AB_CommonUtils::formatPrice( $a[ 'price' ] ) ?></td><?php
                                        break;

                                    case 'cancel':
                                        ?><td class="ab-column-cancel">
                                            <?php if ( $a[ 'start_date' ] > current_time( 'mysql' ) ): ?>
                                                <a class="ab-btn orange" href="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) . '?action=ab_cancel_appointment&token=' . $a['token'] ) ?>">
                                                    <span class="ab_label"><?php _e( 'Cancel' ) ?></span>
                                                </a>
                                            <?php else: ?>
                                                <?php _e( 'Expired', 'ab' ) ?>
                                            <?php endif ?>
                                        </td><?php
                                        break;

                                    default:
                                        ?><td class="ab-column-<?php echo $column ?>"><?php echo $a[ $column ] ?></td><?php
                                }
                            ?>
                        <?php endforeach ?>
                    </tr>
                    <?php endforeach ?>

                </table>
            <?php endif ?>
        <?php else: ?>
            <p><?php echo __( 'No appointments found', 'ab' ) ?></p>
        <?php endif ?>
    </div>
<?php else: ?>
    <?php wp_login_form() ?>
<?php endif ?>