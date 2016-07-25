<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="ab-title"><?php _e( 'Appointments', 'ab' ); ?></div>

<div ng-app="appointments" ng-controller="appointmentsCtrl" style="min-width: 800px;" class="form-horizontal ng-cloak">

    <form class="form-inline" action="<?php echo admin_url( 'admin-ajax.php' ) ?>?action=ab_export_to_csv" method="post" style="margin: 0">
        <div id=reportrange class="pull-left ab-reportrange" style="margin-bottom: 10px">
            <i class="icon-calendar icon-large"></i>
            <span data-date="<?php echo date( 'F j, Y', strtotime( 'first day of' ) ) ?> - <?php echo date( 'F j, Y', strtotime( 'last day of' ) ) ?>"><?php echo date_i18n( get_option( 'date_format' ), strtotime( 'first day of' ) ) ?> - <?php echo date_i18n( get_option( 'date_format' ), strtotime( 'last day of' ) ) ?></span> <b style="margin-top: 8px;" class=caret></b>
        </div>
        <input type="hidden" name="date_start" ng-value="date_start" />
        <input type="hidden" name="date_end" ng-value="date_end" />
        <span class="help-inline"><?php _e( 'Delimiter' , 'ab' ) ?></span>
        <select name="delimiter" style="width: 125px;height: 30px">
            <option value=","><?php _e( 'Comma (,)', 'ab' ) ?></option>
            <option value=";"><?php _e( 'Semicolon (;)', 'ab' ) ?></option>
        </select>
        <button type="submit" class="btn btn-info"><?php _e('Export to CSV','ab') ?></button>
    </form>

    <table id="ab_appointments_list" class="table table-striped" cellspacing=0 cellpadding=0 border=0 style="clear: both;">
        <thead>
        <tr>
            <th style="width: 14%;" ng-class="css_class.start_date"><a href="" ng-click="reload({sort:'start_date'})"><?php _e( 'Booking Time', 'ab' ); ?></a></th>
            <th style="width: 14%;" ng-class="css_class.staff_name"><a href="" ng-click="reload({sort:'staff_name'})"><?php _e( 'Staff Member', 'ab' ); ?></a></th>
            <th style="width: 14%;" ng-class="css_class.customer_name"><a href="" ng-click="reload({sort:'customer_name'})"><?php _e( 'Customer Name', 'ab' ); ?></a></th>
            <th style="width: 14%;" ng-class="css_class.service_title"><a href="" ng-click="reload({sort:'service_title'})"><?php _e( 'Service', 'ab' ); ?></a></th>
            <th style="width: 14%;" ng-class="css_class.service_duration"><a href="" ng-click="reload({sort:'service_duration'})"><?php _e( 'Duration', 'ab' ); ?></a></th>
            <th style="width: 14%;" ng-class="css_class.price"><a href="" ng-click="reload({sort:'price'})"><?php _e( 'Price', 'ab' ); ?></a></th>
            <th style="width: 14%;" ng-class="css_class.price"></th>
        </tr>
        </thead>
        <tbody>
        <tr ng-repeat="appointment in dataSource.appointments">
            <td>{{appointment.start_date}}</td>
            <td>{{appointment.staff_name}}</td>
            <td>{{appointment.customer_name}}</td>
            <td>{{appointment.service_title}}</td>
            <td>{{appointment.service_duration}}</td>
            <td>{{appointment.price}}</td>
            <td><a href="javascript:void(0)" ng-click="deleteAppointment(appointment)" role="button" class="btn btn-danger" id="{{appointment.id}}" name="appointment_delete"><?php _e( 'Delete', 'ab' ) ?></a></td>
        </tr>
        <tr ng-hide="dataSource.appointments.length || loading"><td colspan=6><?php _e( 'No appointments', 'ab' ); ?></td></tr>
        </tbody>
    </table>

    <div class="btn-toolbar" ng-hide="dataSource.pages.length == 1">
        <div class="btn-group">
            <button ng-click="reload({page:page.number})" class="btn" ng-repeat="page in dataSource.pages" ng-switch on="page.active">
                <span ng-switch-when="true">{{page.number}}</span>
                <a href="" ng-switch-default>{{page.number}}</a>
            </button>
        </div>
    </div>

    <div ng-show="loading" class="loading-indicator">
        <img src="<?php echo plugins_url( 'backend/resources/images/ajax_loader_32x32.gif', AB_PATH . '/main.php' ) ?>" alt="" />
    </div>
</div>