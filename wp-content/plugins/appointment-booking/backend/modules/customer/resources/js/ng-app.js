;(function() {
    var module = angular.module('customers', ['ui.utils', 'ui.date', 'newCustomerDialog', 'ngSanitize']);

    module.factory('dataSource', function($q, $rootScope) {
        var ds = {
            customers : [],
            total     : 0,
            pages     : [],
            form      : {
                new_customer : {
                    name       : null,
                    wp_user_id : null,
                    phone      : null,
                    email      : null,
                    notes      : null
                }
            },
            loadData  : function(params) {
                var deferred = $q.defer();
                jQuery.ajax({
                    url  : ajaxurl,
                    type : 'POST',
                    data : jQuery.extend({ action : 'ab_get_customers' }, params),
                    dataType : 'json',
                    success : function(response) {
                        if (response.status === 'ok') {
                            ds.customers = response.data.customers;
                            ds.total     = response.data.total;
                            ds.pages     = [];
                            for (var i = 0; i < response.data.pages; ++ i) {
                                ds.pages.push({
                                    number : i + 1,
                                    active : response.data.active_page == i + 1
                                });
                            }
                            for (var i = 0; i < ds.customers.length; ++ i) {
                                for (var j = 0; j < BooklyL10n.wp_users.length; ++ j) {
                                    if( ds.customers[i].wp_user_id == BooklyL10n.wp_users[j].ID ){
                                        ds.customers[i].wp_user = BooklyL10n.wp_users[j];
                                        break;
                                    }
                                }
                            }
                        }
                        $rootScope.$apply(deferred.resolve);
                    },
                    error : function() {
                        ds.customers = [];
                        ds.total     = 0;
                        $rootScope.$apply(deferred.resolve);
                    }
                });
                return deferred.promise;
            }
        };
        ds.wp_users = BooklyL10n.wp_users;
        return ds;
    });

    module.controller('customersCtrl', function($scope, dataSource) {
        // Set up initial data.
        var params = {
            page   : 1,
            sort   : 'name',
            order  : 'asc',
            filter : ''
        };
        $scope.loading   = true;
        $scope.css_class = {
            name               : 'asc',
            wp_user            : '',
            phone              : '',
            email              : '',
            notes              : '',
            last_appointment   : '',
            total_appointments : '',
            payments           : ''
        };
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
                        name               : '',
                        wp_user            : '',
                        phone              : '',
                        email              : '',
                        notes              : '',
                        last_appointment   : '',
                        total_appointments : '',
                        payments           : ''
                    };
                    $scope.css_class[opt.sort] = params.order;
                }
                jQuery.extend(params, opt);
            }
            dataSource.loadData(params).then(function() {
                $scope.loading = false;
            });
        };

        var filter_delay = null;
        $scope.$watch('filter', function() {
            if (filter_delay !== null) {
                clearTimeout(filter_delay);
            }
            filter_delay = setTimeout(function() {
                filter_delay = null;
                $scope.$apply(function($scope) {
                    $scope.reload({filter: $scope.filter});
                });
            }, 400);
        });

        /**
         * Edit customer.
         *
         * @param object customer
         * @param object params
         */
        $scope.saveCustomer = function(customer, params) {
            customer.edit_name    = false;
            customer.edit_wp_user = false;
            customer.edit_phone   = false;
            customer.edit_email   = false;
            customer.edit_notes   = false;
            customer.errors       = {};

            $scope.loading = true;
            jQuery.ajax({
                url  : ajaxurl,
                type : 'POST',
                data : {
                    action     : 'ab_save_customer',
                    id         : customer.id,
                    wp_user_id : customer.wp_user ? customer.wp_user.ID : null,
                    name       : customer.name,
                    phone      : customer.phone,
                    email      : customer.email,
                    notes      : customer.notes
                },
                dataType : 'json',
                success  : function(response) {
                    $scope.$apply(function($scope) {
                        if (response.status === 'error') {
                            jQuery.each(response.errors, function(field, errors) {
                                customer.errors[field]    = {};
                                customer['edit_' + field] = true;
                                jQuery.each(errors, function(key, error) {
                                    customer.errors[field][error] = true;
                                });
                            });
                        }
                        $scope.loading = false;
                    });
                },
                error : function(response) {
                    $scope.$apply(function($scope) {
                        $scope.loading = false;
                    });
                }
            });
        };

        /**
         * Callback for creating new customer.
         *
         * @param object customer
         */
        $scope.createCustomer = function(customer) {
            dataSource.customers.push(customer);
            $scope.reload(params.page);
        };

        /**
         * Delete customer.
         *
         * @param customer
         */
        $scope.deleteCustomer = function(customer) {
            if (delete_customer_choice === null) {
                $modal.data('customer_id', customer.id).modal('show');
            } else {
                deleteCustomer(customer.id, delete_customer_choice);
            }
        };

        /**
         * Popup for deleting customer.
         */
        var delete_customer_choice = null;
        var deleteCustomer = function(customer_id, with_wp_user) {
            $scope.loading = true;
            jQuery.ajax({
                url  : ajaxurl,
                type : 'POST',
                data : {
                    action       : 'ab_delete_customer',
                    id           : customer_id,
                    with_wp_user : with_wp_user ? 1 : 0
                },
                dataType : 'json',
                success  : function(response) {
                    $scope.$apply(function($scope) {
                        $scope.reload();
                    });
                }
            });
        };
        var $modal = jQuery('#ab-customer-delete');
        $modal
            .on('click', '.ab-yes', function () {
                $modal.modal('hide');
                if ( jQuery('#ab-remember-my-choice').prop('checked') ) {
                    delete_customer_choice = true;
                }
                deleteCustomer($modal.data('customer_id'), true);

            })
            .on('click', '.ab-no', function () {
                if ( jQuery('#ab-remember-my-choice').prop('checked') ) {
                    delete_customer_choice = false;
                }
                deleteCustomer($modal.data('customer_id'), false);
            });

    });

    /**
     * Directive for setting focus to element.
     */
    module.directive('focusMe', function($timeout) {
        return {
            link: function(scope, element, attrs) {
                scope.$watch(attrs.focusMe, function(value) {
                    if (value) {
                        $timeout(function() {
                            element[0].focus();
                        });
                    }
                });
            }
        };
    });

    module.filter('nl2br', function() {
        return function(input) {
            return ('' + input).split('\n').join('<br>');
        };
    });

})();