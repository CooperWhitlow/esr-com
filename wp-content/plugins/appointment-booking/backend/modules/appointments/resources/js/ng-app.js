;(function() {

    var module = angular.module('appointments', ['ui.utils', 'ui.date', 'ngSanitize']);

    module.factory('dataSource', function($q, $rootScope) {
        var ds = {
            appointments : [],
            total     : 0,
            pages     : [],
            loadData  : function(params) {
                var deferred = $q.defer();
                jQuery.ajax({
                    url  : ajaxurl,
                    type : 'POST',
                    data : jQuery.extend({ action : 'ab_get_appointments' }, params),
                    dataType : 'json',
                    success : function(response) {
                        if (response.status === 'ok') {
                            ds.appointments = response.data.appointments;
                            ds.total     = response.data.total;
                            ds.pages     = [];
                            for (var i = 0; i < response.data.pages; ++ i) {
                                ds.pages.push({
                                    number : i + 1,
                                    active : response.data.active_page == i + 1
                                });
                            }
                        }
                        $rootScope.$apply(deferred.resolve);
                    },
                    error : function() {
                        ds.appointments = [];
                        ds.total     = 0;
                        $rootScope.$apply(deferred.resolve);
                    }
                });
                return deferred.promise;
            }
        };

        return ds;
    });

    module.controller('appointmentsCtrl', function($scope, dataSource) {
        // Set up initial data.
        var params = {
            page       : 1,
            sort       : 'start_date',
            order      : 'desc',
            date_start : '',
            date_end   : ''
        };
        $scope.loading   = true;
        $scope.css_class = {
            staff_name      : '',
            customer_name   : '',
            service_title   : '',
            start_date      : 'desc',
            service_duration: '',
            price           : ''
        };

        var format = 'YYYY-MM-DD';
        $scope.date_start = moment().startOf('month').format(format);
        $scope.date_end   = moment().endOf('month').format(format);

        // Set up data source (data will be loaded in reload function).
        $scope.dataSource = dataSource;

        $scope.reload = function( opt ) {
            $scope.loading = true;
            if (opt !== undefined) {
                if (opt.sort !== undefined) {
                    if (params.sort === opt.sort) {
                        // Toggle order when sorting by the same field.
                        params.order = params.order === 'asc' ? 'desc' : 'asc';
                    } else {
                        params.order = 'asc';
                    }
                    $scope.css_class = {
                        staff_name      : '',
                        customer_name   : '',
                        service_title   : '',
                        start_date      : '',
                        service_duration: '',
                        price           : ''
                    };
                    $scope.css_class[opt.sort] = params.order;
                }
                jQuery.extend(params, opt);
            }
            params.date_start = $scope.date_start;
            params.date_end   = $scope.date_end;
            dataSource.loadData(params).then(function() {
                $scope.loading = false;
            });
        };

        $scope.reload();

        /**
         * Delete customer appointment.
         *
         * @param appointment
         */
        $scope.deleteAppointment = function(appointment) {
            if (confirm(BooklyL10n['are_you_sure'])) {
                $scope.loading = true;
                jQuery.ajax({
                    url  : ajaxurl,
                    type : 'POST',
                    data : {
                        action : 'ab_delete_customer_appointment',
                        id     : appointment.id
                    },
                    dataType : 'json',
                    success  : function(response) {
                        $scope.$apply(function($scope) {
                            $scope.reload();
                        });
                    }
                });
            }
        };

        // Init date range picker.
        var picker_ranges = {};
        picker_ranges[BooklyL10n.today]      = [moment(), moment()];
        picker_ranges[BooklyL10n.yesterday]  = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
        picker_ranges[BooklyL10n.last_7]     = [moment().subtract(6, 'days'), moment()];
        picker_ranges[BooklyL10n.last_30]    = [moment().subtract(29, 'days'), moment()];
        picker_ranges[BooklyL10n.this_month] = [moment().startOf('month'), moment().endOf('month')];
        picker_ranges[BooklyL10n.next_month] = [moment().add(1, 'month').startOf('month'), moment().add(1, 'month').endOf('month')];

        jQuery('#reportrange').daterangepicker(
            {
                startDate: moment().startOf('month'),
                endDate: moment().endOf('month'),
                ranges: picker_ranges,
                locale: {
                    applyLabel: BooklyL10n.apply,
                    cancelLabel: BooklyL10n.cancel,
                    fromLabel: BooklyL10n.from,
                    toLabel: BooklyL10n.to,
                    customRangeLabel: BooklyL10n.custom_range,
                    daysOfWeek: BooklyL10n.days,
                    monthNames: BooklyL10n.months,
                    firstDay: parseInt(BooklyL10n.start_of_week)
                }
            },
            function(start, end) {
                jQuery('#reportrange span').html(start.formatPHP(BooklyL10n.formatPHP) + ' - ' + end.formatPHP(BooklyL10n.formatPHP));
                $scope.$apply(function($scope){
                    $scope.date_start = start.format(format);
                    $scope.date_end   = end.format(format);
                    $scope.reload();
                });
            }
        );
    });
})();