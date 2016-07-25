<div id="ab_custom_fields_dialog" class="modal hide fade" tabindex=-1 role=dialog aria-labelledby=myModalLabel aria-hidden=true>
    <div class="dialog-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
            <h3 id="myModalLabel"><?php _e( 'Edit booking details', 'ab' ) ?></h3>
        </div>
        <div class="modal-body">
            <form class="form-horizontal" ng-hide=loading style="z-index: 1050">
                <fieldset>
                    <legend><?php _e( 'Participants', 'ab' ) ?></legend>
                    <div class="ab-row-fluid">
                        <div class="ab-formGroup ab-full">
                            <label for="ab-edit-number-of-persons" class="ab-formLabel"><?php _e( 'Number of persons', 'ab' ) ?></label>
                            <select id="ab-edit-number-of-persons"></select>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><?php _e( 'Custom Fields', 'ab' ) ?></legend>
                    <?php foreach ( $custom_fields as $custom_field ): ?>
                        <div class="ab-row-fluid">
                            <div class="ab-formGroup ab-full">
                                <label class="ab-formLabel"><?php echo $custom_field->label ?></label>
                                <div class="ab-formField" data-type="<?php echo $custom_field->type ?>" data-id="<?php echo $custom_field->id ?>">

                                    <?php if ( $custom_field->type == 'text-field' ): ?>
                                        <input type="text" class="ab-custom-field" />

                                    <?php elseif ( $custom_field->type == 'textarea' ): ?>
                                        <textarea rows="3" class="ab-custom-field"></textarea>

                                    <?php elseif ( $custom_field->type == 'checkboxes' ): ?>
                                        <?php foreach ( $custom_field->items as $item ): ?>
                                            <label><input class="ab-custom-field" type="checkbox" value="<?php echo esc_attr( $item ) ?>" /> <?php echo $item ?></label>
                                        <?php endforeach ?>

                                    <?php elseif ( $custom_field->type == 'radio-buttons' ): ?>
                                        <?php foreach ( $custom_field->items as $item ): ?>
                                            <label><input type="radio" name="<?php echo $custom_field->id ?>" class="ab-custom-field" value="<?php echo esc_attr( $item ) ?>" /> <?php echo $item ?></label>
                                        <?php endforeach ?>

                                    <?php elseif ( $custom_field->type == 'drop-down' ): ?>
                                        <select class="ab-custom-field">
                                            <option value=""></option>
                                            <?php foreach ( $custom_field->items as $item ): ?>
                                                <option value="<?php echo esc_attr( $item ) ?>"><?php echo $item ?></option>
                                            <?php endforeach ?>
                                        </select>

                                    <?php endif ?>

                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </fieldset>
            </form>
        </div>
        <div class="modal-footer">
            <div class="ab-modal-button">
                <input type="button" data-customer="" ng-click=saveCustomFields() class="btn btn-info ab-popup-save ab-update-button" value="<?php _e( 'Apply' , 'ab' ) ?>">
                <input type="button" class="ab-reset-form" data-dismiss=modal value="<?php _e( 'Cancel' , 'ab' ) ?>" aria-hidden=true>
            </div>
        </div>

    </div>
</div>