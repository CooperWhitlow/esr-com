(function($) {
    window.bookly = function(options) {
        var $container  = $('#ab-booking-form-' + options.form_id);
        var today       = new Date();
        var Options     = $.extend(options, {
            skip_first_step : (
                options.attributes.hide_categories &&
                options.attributes.category_id &&
                options.attributes.hide_services &&
                options.attributes.service_id &&
                options.attributes.hide_staff_members &&
                !options.attributes.show_number_of_persons &&
                options.attributes.hide_date_and_time
            ),
            skip_date_time : options.attributes.hide_date_and_time,
            skip_service   : options.attributes.hide_categories
                && options.attributes.category_id
                && options.attributes.hide_services
                && options.attributes.service_id
                && options.attributes.hide_staff_members
                && !options.attributes.show_number_of_persons
        });

        // initialize
        if (Options.is_finished) {
            fifthStep();
        } else {
            firstStep();
        }

        //
        function firstStep() {

            if (Options.is_cancelled) {
                fourthStep();

            } else if (Options.is_finished) {
                fifthStep();

            } else {
                $.ajax({
                    url         : Options.ajaxurl,
                    data        : { action: 'ab_render_service', form_id: Options.form_id, time_zone_offset: today.getTimezoneOffset() },
                    dataType    : 'json',
                    xhrFields   : { withCredentials: true },
                    crossDomain : 'withCredentials' in new XMLHttpRequest(),
                    success     : function (response) {
                        if (response.status == 'success') {
                            $container.html(response.html);

                            var $select_category  = $('.ab-select-category', $container),
                                $select_service   = $('.ab-select-service', $container),
                                $select_nop       = $('.ab-select-number-of-persons', $container),
                                $select_staff     = $('.ab-select-employee', $container),
                                $date_from        = $('.ab-date-from', $container),
                                $week_day         = $('.ab-week-day', $container),
                                $select_time_from = $('.ab-select-time-from', $container),
                                $select_time_to   = $('.ab-select-time-to', $container),
                                $service_error    = $('.ab-select-service-error', $container),
                                $time_error       = $('.ab-select-time-error', $container),
                                $next_step        = $('.ab-next-step', $container),
                                $mobile_next_step = $('.ab-mobile-next-step', $container),
                                $mobile_prev_step = $('.ab-mobile-prev-step', $container),
                                categories        = response.categories,
                                services          = response.services,
                                staff             = response.staff
                            ;

                            // Overwrite attributes if necessary.
                            if (response.attributes) {
                                if (!Options.attributes.hide_categories && Options.attributes.service_id != response.attributes.service_id) {
                                    Options.attributes.category_id = null;
                                }
                                Options.attributes.service_id = response.attributes.service_id;
                                if (!Options.attributes.hide_staff_members) {
                                    Options.attributes.staff_member_id = response.attributes.staff_member_id;
                                }
                                Options.attributes.number_of_persons = response.attributes.number_of_persons;
                            }

                            $date_from.pickadate({
                                min             : Options.date_min || true,
                                clear           : false,
                                close           : false,
                                today           : BooklyL10n.today,
                                weekdaysShort   : BooklyL10n.days,
                                monthsFull      : BooklyL10n.months,
                                labelMonthNext  : BooklyL10n.nextMonth,
                                labelMonthPrev  : BooklyL10n.prevMonth,
                                firstDay        : Options.start_of_week,
                                onSet           : function(timestamp) {
                                    if ($.isNumeric(timestamp.select)) {
                                        // Checks appropriate day of the week
                                        var date = new Date(timestamp.select);
                                        $('.ab-week-day[value="' + (date.getDay() + 1) + '"]:not(:checked)', $container).attr('checked', true).trigger('change');
                                    }
                                }
                            });

                            function setSelectNumberOfPersons() {
                                var service_id = $select_service.val();
                                if (service_id) {
                                    var staff_id = $select_staff.val();
                                    var number_of_persons = $select_nop.val();
                                    var max_capacity = staff_id ? staff[staff_id].services[service_id].max_capacity : services[service_id].max_capacity;
                                    $select_nop.empty();
                                    for (var i = 1; i <= max_capacity; ++ i) {
                                        $select_nop.append('<option value="' + i +'">' + i + '</option>');
                                    }
                                    if (number_of_persons <= max_capacity) {
                                        $select_nop.val(number_of_persons);
                                    }
                                }
                                else {
                                    $select_nop.empty().append('<option value="1">1</option>');
                                }
                            }

                            // fill the selects
                            setSelect($select_category, categories);
                            setSelect($select_service, services);
                            setSelect($select_staff, staff);

                            // Category select change
                            $select_category.on('change', function() {
                                var category_id = this.value;

                                // filter the services and staff
                                // if service or staff is selected, leave it selected
                                if (category_id) {
                                    setSelect($select_service, categories[category_id].services);
                                    setSelect($select_staff, categories[category_id].staff, true);
                                // show all services and staff
                                // if service or staff is selected, reset it
                                } else {
                                    setSelect($select_service, services);
                                    setSelect($select_staff, staff);
                                }
                            });

                            // Service select change
                            $select_service.on('change', function() {
                                var service_id = this.value;

                                // select the category
                                // filter the staffs by service
                                // show staff with price
                                // if staff selected, leave it selected
                                // if staff not selected, select all
                                if (service_id) {
                                    $select_category.val(services[service_id].category_id);
                                    setSelect($select_staff, services[service_id].staff, true);
                                // filter staff by category
                                } else {
                                    var category_id = $select_category.val();
                                    if (category_id) {
                                        setSelect($select_staff, categories[category_id].staff, true);
                                    } else {
                                        setSelect($select_staff, staff, true);
                                    }

                                }
                                setSelectNumberOfPersons();
                            });

                            // Staff select change
                            $select_staff.on('change', function() {
                                var staff_id = this.value;
                                var category_id = $select_category.val();

                                // filter services by staff and category
                                // if service selected, leave it
                                if (staff_id) {
                                    var services_a = {};
                                    if (category_id) {
                                        $.each(staff[staff_id].services, function(index, st) {
                                            if (services[st.id].category_id == category_id) {
                                                services_a[st.id] = st;
                                            }
                                        });
                                    } else {
                                        services_a = staff[staff_id].services;
                                    }
                                    setSelect($select_service, services_a, true);
                                // filter services by category
                                } else {
                                    if (category_id) {
                                        setSelect($select_service, categories[category_id].services, true);
                                    } else {
                                        setSelect($select_service, services, true);
                                    }
                                }
                                setSelectNumberOfPersons();
                            });

                            // Category
                            if (Options.attributes.category_id) {
                                $select_category.val(Options.attributes.category_id).trigger('change');
                            }
                            // Services
                            if (Options.attributes.service_id) {
                                $select_service.val(Options.attributes.service_id).trigger('change');
                            }
                            // Employee
                            if (Options.attributes.staff_member_id) {
                                $select_staff.val(Options.attributes.staff_member_id).trigger('change');
                            }
                            // Number of persons
                            if (Options.attributes.number_of_persons) {
                                $select_nop.val(Options.attributes.number_of_persons);
                            }

                            hideByAttributes();

                            // change the week days
                            $week_day.on('change', function () {
                                var $this = $(this);
                                if ($this.is(':checked')) {
                                    $this.parent().not("[class*='active']").addClass('active');
                                } else {
                                    $this.parent().removeClass('active');
                                }
                            });

                            // time from
                            $select_time_from.on('change', function () {
                                var start_time       = $(this).val(),
                                    end_time         = $select_time_to.val(),
                                    $last_time_entry = $('option:last', $select_time_from);

                                $select_time_to.empty();

                                // case when we click on the not last time entry
                                if ($select_time_from[0].selectedIndex < $last_time_entry.index()) {
                                    // clone and append all next "time_from" time entries to "time_to" list
                                    $('option', this).each(function () {
                                        if ($(this).val() > start_time) {
                                            $select_time_to.append($(this).clone());
                                        }
                                    });
                                // case when we click on the last time entry
                                } else {
                                    $select_time_to.append($last_time_entry.clone()).val($last_time_entry.val());
                                }

                                var first_value =  $('option:first', $select_time_to).val();
                                $select_time_to.val(end_time >= first_value ? end_time : first_value);
                            });

                            var firstStepValidator = function(button_type) {
                                var valid           = true,
                                    $select_wrap    = $select_service.parent(),
                                    $time_wrap_from = $select_time_from.parent(),
                                    $time_wrap_to   = $select_time_to.parent(),
                                    $scroll_to      = null;

                                $service_error.hide();
                                $time_error.hide();
                                $select_wrap.removeClass('ab-error');
                                $time_wrap_from.removeClass('ab-error');
                                $time_wrap_to.removeClass('ab-error');

                                // service validation
                                if (!$select_service.val()) {
                                    valid = false;
                                    $select_wrap.addClass('ab-error');
                                    $service_error.show();
                                    $scroll_to = $select_wrap;
                                }

                                // date validation
                                $date_from.css('borderColor', $date_from.val() ? '' : 'red');
                                if (!$date_from.val()) {
                                    valid = false;
                                    if ($scroll_to === null) {
                                        $scroll_to = $date_from;
                                    }
                                }

                                // time validation
                                if (button_type !== 'mobile' && $select_time_from.val() == $select_time_to.val()) {
                                    valid = false;
                                    $time_wrap_from.addClass('ab-error');
                                    $time_wrap_to.addClass('ab-error');
                                    $time_error.show();
                                    if ($scroll_to === null) {
                                        $scroll_to = $time_wrap_from;
                                    }
                                }

                                // week days
                                if (!$('.ab-week-day:checked', $container).length) {
                                    valid = false;
                                    if ($scroll_to === null) {
                                        $scroll_to = $week_day;
                                    }
                                }

                                if ($scroll_to !== null) {
                                    scrollTo($scroll_to);
                                }

                                return valid;
                            };

                            // "Next" click
                            $next_step.on('click', function (e) {
                                e.preventDefault();

                                if (firstStepValidator('simple')) {

                                    var ladda = Ladda.create(this);
                                    ladda.start();

                                    // Prepare staff ids.
                                    var staff_ids = [];
                                    if ($select_staff.val()) {
                                        staff_ids.push($select_staff.val());
                                    }
                                    else {
                                        $select_staff.find('option').each(function() {
                                            if (this.value) {
                                                staff_ids.push(this.value);
                                            }
                                        });
                                    }
                                    // Prepare days.
                                    var days = [];
                                    $('.ab-week-days .active input.ab-week-day', $container).each(function() {
                                        days.push(this.value);
                                    });

                                    $.ajax({
                                        url  : Options.ajaxurl,
                                        data : {
                                            action            : 'ab_session_save',
                                            form_id           : Options.form_id,
                                            service_id        : $select_service.val(),
                                            number_of_persons : $select_nop.val(),
                                            staff_ids         : staff_ids,
                                            date_from         : $date_from.pickadate('picker').get('select', 'yyyy-mm-dd'),
                                            days              : days,
                                            time_from         : $select_time_from.val(),
                                            time_to           : $select_time_to.val()
                                        },
                                        dataType : 'json',
                                        xhrFields : { withCredentials: true },
                                        crossDomain : 'withCredentials' in new XMLHttpRequest(),
                                        success : function (response) {
                                            secondStep();
                                        }
                                    });
                                }
                            });

                            //
                            $mobile_next_step.on('click', function () {
                                if (firstStepValidator('mobile')) {
                                    if (Options.skip_date_time) {
                                        var ladda = Ladda.create(this);
                                        ladda.start();
                                        $next_step.trigger('click');
                                    } else {
                                        $('.ab-mobile-step_1', $container).hide();
                                        $('.ab-mobile-step_2', $container).css('display', 'block');
                                        if (Options.skip_service) {
                                            $mobile_prev_step.remove();
                                        }
                                        scrollTo($container);
                                    }
                                }

                                return false;
                            });

                            //
                            $mobile_prev_step.on('click', function () {
                                $('.ab-mobile-step_1', $container).show();
                                $('.ab-mobile-step_2', $container).hide();

                                if ($select_service.val()) {
                                    $('.ab-select-service', $container).parent().removeClass('ab-error');
                                }
                                return false;
                            });

                            if (Options.skip_first_step) {
                                $next_step.trigger('click');
                            } else if (Options.skip_service) {
                                $mobile_next_step.trigger('click');
                            }
                        }
                    } // ajax success
                }); // ajax
            }
        }

        //
        function secondStep(time_is_busy, selected_date) {

            $.ajax({
                url         : Options.ajaxurl,
                data :      { action: 'ab_render_time', form_id: Options.form_id, selected_date: selected_date },
                dataType    : 'json',
                xhrFields   : { withCredentials: true },
                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                success     : function (response) {
                    if (response.status == 'no-data') {
                        // The session doesn't contain data.
                        firstStep();
                        return;
                    }

                    $container.html(response.html);
                    scrollTo($container);

                    var $back_button = $('.ab-to-first-step', $container);

                    $back_button.on('click', function(e) {
                        e.preventDefault();
                        var ladda = Ladda.create(this);
                        ladda.start();
                        firstStep();
                    });

                    if (Options.skip_first_step) {
                        $back_button.hide();
                    }

                    // Init calendar.
                    if (Options.show_calendar) {
                        var holidays = [];
                        $.each(response.holidays, function(key){
                            var holiday_date = response.holidays[key];
                            $.each(holiday_date, function(key,holiday){
                                var holiday_count = holiday_date[key]['holiday'],
                                    slice_str = holiday_count.substr(0, 10),
                                    replace_str = slice_str.replace(/-/g, ","),
                                    arr_date = replace_str.split(',');

                                arr_date[1]--;
                                holidays.push(arr_date);
                            });
                        });
                        $('.ab-date-from-timeslots', $container).pickadate({
                            min           : Options.date_min || true,
                            weekdaysShort : BooklyL10n.days,
                            monthsFull    : BooklyL10n.months,
                            firstDay      : Options.start_of_week,
                            clear         : false,
                            close         : false,
                            today         : false,
                            disable       : holidays,
                            closeOnSelect : false,
                            klass : {
                                picker: 'picker picker--opened picker--focused'
                            },
                            onSet: function(e) {
                                if(e.select){
                                    secondStep(false, $('.ab-date-from-timeslots', $container).pickadate('picker').get('select', 'yyyy-mm-dd'));
                                    $('.ab-time-screen,.ab-not-time-screen', $container).addClass('ab-spin-overlay');
                                    var opts = {
                                        lines: 11, // The number of lines to draw
                                        length: 11, // The length of each line
                                        width: 4, // The line thickness
                                        radius: 5, // The radius of the inner circle
                                        corners: 1, // Corner roundness (0..1)
                                        rotate: 0, // The rotation offset
                                        direction: 1, // 1: clockwise, -1: counterclockwise
                                        color: '#000', // #rgb or #rrggbb or array of colors
                                        speed: 1, // Rounds per second
                                        trail: 60, // Afterglow percentage
                                        shadow: false, // Whether to render a shadow
                                        hwaccel: false, // Whether to use hardware acceleration
                                        className: 'spinner', // The CSS class to assign to the spinner
                                        zIndex: 2e9, // The z-index (defaults to 2000000000)
                                        top: '0', // Top position relative to parent
                                        left: '0' // Left position relative to parent
                                    };

                                    var spinner = new Spinner(opts).spin($('.ab-spin', $container).get(0));
                                }
                            },
                            onClose: function() {
                                this.open();
                            }
                        });
                    }

                     if (response.status == 'success') {
                        if (time_is_busy) {
                            $container.prepend(time_is_busy);
                        }

                        var $next_button    = $('.ab-time-next', $container),
                            $prev_button    = $('.ab-time-prev', $container),
                            $list           = $('.ab-second-step', $container),
                            $columnizer_wrap = $('.ab-columnizer-wrap', $list),
                            $columnizer     = $('.ab-columnizer', $columnizer_wrap),
                            $column,
                            $screen,
                            $current_screen,
                            $button,
                            screen_index    = 0,
                            $screens,
                            item_height     = 35,
                            column_width    = 127,
                            columns         = 0,
                            $current_booking_form = $('#ab-booking-form-' + options.form_id),
                            screen_width    = $current_booking_form.width(),
                            window_height   = $(window).height(),
                            columns_per_screen = parseInt(screen_width / column_width, 10),
                            has_more_slots  = response.has_more_slots || false
                        ;

                        if (Options.show_calendar && $(window).width() >= 650) {
                            columns_per_screen = parseInt((screen_width - 310) / column_width, 10);
                        }

                        if (window_height < 4 * item_height) {
                            window_height = 4 * item_height;
                        }
                        else if (window_height > 8 * item_height) {
                            window_height = 10 * item_height;
                        }

                        var items_per_column = parseInt(window_height / item_height, 10);
                        $columnizer_wrap.css({ height: (items_per_column * item_height + 25) });


                        function createColumns() {
                            var $buttons =  $('> button', $columnizer);
                            var max_length = $buttons.length > items_per_column && has_more_slots ? items_per_column : 0;

                            while ($buttons.length > max_length) {
                                $column = $('<div class="ab-column" />');

                                var items_in_column = items_per_column;
                                if (columns % columns_per_screen == 0 && !$buttons.eq(0).hasClass('ab-available-day')) {
                                    // If this is the first column of a screen and the first slot in this column is not day
                                    // then put 1 slot less in this column because createScreens adds 1 more
                                    // slot to such columns.
                                    -- items_in_column;
                                }

                                for (var i = 0; i < items_in_column; ++ i) {
                                    if (i + 1 == items_in_column && $buttons.eq(0).hasClass('ab-available-day')) {
                                        // Skip the last slot if it is day.
                                        break;
                                    }
                                    $button = $($buttons.splice(0, 1));
                                    if (i == 0) {
                                        $button.addClass('ab-first-child');
                                    } else if (i + 1 == items_in_column) {
                                        $button.addClass('ab-last-child');
                                    }
                                    $column.append($button);
                                }
                                $columnizer.append($column);
                                ++ columns;
                            }
                        }


                        function createOneColumnsDay() {
                            var $buttons        = $('> button', $columnizer);
                            var max_height      = 0;
                            var column_height   = 0;

                            while ($buttons.length > 0) {

                                // create column
                                if ($buttons.eq(0).hasClass('ab-available-day')) {
                                    column_height = 1;
                                    $column = $('<div class="ab-column" />');
                                    $button = $($buttons.splice(0, 1));
                                    $button.addClass('ab-first-child');
                                    $column.append($button);

                                    // add slots in column
                                } else {
                                    column_height++;
                                    $button = $($buttons.splice(0, 1));
                                    // if is last in column
                                    if (!$buttons.length || $buttons.eq(0).hasClass('ab-available-day')) {

                                        $button.addClass('ab-last-child');
                                        $column.append($button);

                                        $columnizer.append($column);
                                        columns++;

                                    } else {
                                        $column.append($button);
                                    }
                                }
                                // calculate max height of columns
                                if (column_height > max_height) {
                                    max_height = column_height;
                                }
                            }
                            $columnizer_wrap.css({ height: (max_height * (item_height + 2.5)) });
                        }

                        function createScreens() {
                            var $columns = $('> .ab-column', $columnizer);

                            if ($container.width() < 2 * column_width) {
                                screen_width = 2 * column_width;
                            }

                            if ($columns.length < columns_per_screen) {
                                columns_per_screen = $columns.length;
                            }

                            while ($columns.length && $columns.length >= (has_more_slots ? columns_per_screen : 0)) {
                                if(Options.show_calendar){
                                    $screen = $('<div class="ab-time-screen"><div class="ab-spin" /></div>');
                                } else {
                                    $screen = $('<div class="ab-time-screen"/>');
                                }
                                for (var i = 0; i < columns_per_screen; i++) {
                                    $column = $($columns.splice(0, 1));
                                    if (i == 0) {
                                        $column.addClass('ab-first-column');
                                        var $first_button_in_first_column = $column.filter('.ab-first-column')
                                            .find('.ab-first-child');
                                        // in first column first button is time
                                        if (!$first_button_in_first_column.hasClass('ab-available-day')) {
                                            var curr_date = $first_button_in_first_column.data('date'),
                                                $curr_date = $('button.ab-available-day[value="' + curr_date + '"]:last', $container);
                                            // copy dateslot to first column
                                            $column.prepend($curr_date.clone());
                                        }
                                    }
                                    $screen.append($column);
                                }
                                $columnizer.append($screen);
                            }
                            $screens = $('.ab-time-screen', $columnizer);
                        }

                        function onTimeSelectionHandler(e, el) {
                            e.preventDefault();
                            var data = {
                                    action: 'ab_session_save',
                                    appointment_datetime: el.val(),
                                    staff_ids: [el.data('staff_id')],
                                    form_id: options.form_id
                                },
                                ladda = Ladda.create(el[0]);

                            ladda.start();
                            $.ajax({
                                type : 'POST',
                                url  : options.ajaxurl,
                                data : data,
                                dataType : 'json',
                                xhrFields : { withCredentials: true },
                                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                                success : function (response) {
                                    thirdStep();
                                }
                            });
                        }

                        $next_button.on('click', function (e) {
                            var last_date;
                            $prev_button.show();

                            if ($screens.eq(screen_index + 1).length) {
                                $columnizer.animate(
                                    { left: '-=' + $current_screen.width() },
                                    { duration: 800 }
                                );
                                $current_screen = $screens.eq(++screen_index);

                                if (screen_index + 1 == $screens.length && !has_more_slots) {
                                    $next_button.hide();
                                }

                            // Do ajax request when there are more slots.
                            } else if (has_more_slots) {
                                $button = $('> button:last', $columnizer);
                                if ($button.length == 0) {
                                    $button = $('.ab-column:hidden:last > button:last', $columnizer);
                                    if ($button.length == 0) {
                                        $button = $('.ab-column:last > button:last', $columnizer);
                                    }
                                }
                                last_date = $button.val();

                                // Render Next Time
                                var data = {
                                        action: 'ab_render_next_time',
                                        form_id: options.form_id,
                                        start_date: last_date
                                    },
                                    ladda = Ladda.create(document.querySelector('.ab-time-next'));

                                ladda.start();
                                $.ajax({
                                    type : 'POST',
                                    url  : options.ajaxurl,
                                    data : data,
                                    dataType : 'json',
                                    xhrFields : { withCredentials: true },
                                    crossDomain : 'withCredentials' in new XMLHttpRequest(),
                                    success : function (response) {
                                        if (response.status == 'error') { // no available time
                                            $next_button.hide();
                                        }
                                        else if (response.status == 'success') { // if there are available time
                                            has_more_slots = response.has_more_slots;
                                            var $html = $(response.html);
                                            // The first slot is always a day slot.
                                            // Check if such day slot already exists (this can happen
                                            // because of time zone offset) and then remove the first slot.
                                            var $first_day = $html.eq(0);
                                            if ($('button.ab-available-day[value="' + $first_day.attr('value') + '"]', $container).length) {
                                                $html = $html.not(':first');
                                            }
                                            $columnizer.append($html);
                                            if (Options.day_one_column == 1) {
                                                createOneColumnsDay();
                                            } else {
                                                createColumns();
                                            }
                                            createScreens();
                                            $next_button.trigger('click');
                                            $('button.ab-available-hour', $container).off('click').on('click', function (e) {
                                                e.preventDefault();
                                                onTimeSelectionHandler(e, $(this));
                                            });
                                        }
                                        ladda.stop();
                                    }
                                });
                            }
                        });

                        $prev_button.on('click', function () {
                            $next_button.show();
                            $current_screen = $screens.eq(--screen_index);
                            $columnizer.animate(
                                { left: '+=' + $current_screen.width() },
                                { duration: 800 }
                            );
                            if (screen_index === 0) {
                                $prev_button.hide();
                            }
                        });

                        $('button.ab-available-hour', $container).off('click').on('click', function (e) {
                            e.preventDefault();
                            onTimeSelectionHandler(e, $(this));
                        });

                        if (Options.day_one_column == 1) {
                            createOneColumnsDay();
                        } else {
                            createColumns();
                        }
                        createScreens();
                        $current_screen = $screens.eq(0);

                        if (!has_more_slots && $screens.length == 1) {
                            $next_button.remove();
                        }

                        // fixing styles
                        $list.css({
                            'width': function() {
                                if (Options.show_calendar && $(window).width() >= 650) {
                                    return parseInt(($current_booking_form.width() - 310) / column_width, 10) * column_width;
                                } else {
                                    return parseInt($current_booking_form.width() / column_width, 10) * column_width;
                                }
                            },
                            'max-width': '2850px'
                        });

                        var hammertime = $list.hammer({ swipe_velocity: 0.1 });

                        hammertime.on('swipeleft', function() {
                            $next_button.trigger('click');
                        });

                        hammertime.on('swiperight', function() {
                            if ($prev_button.is(':visible')) {
                                $prev_button.trigger('click');
                            }
                        });
                    }
                }
            });
        }

        //
        function thirdStep() {
            $.ajax({
                url         : Options.ajaxurl,
                data        : { action: 'ab_render_details', form_id: Options.form_id },
                dataType    : 'json',
                xhrFields   : { withCredentials: true },
                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                success     : function (response) {
                    if (response.status == 'success') {
                        $container.html(response.html);
                        scrollTo($container);

                        // Init
                        var $button_next    = $('.ab-to-fourth-step', $container),
                            $back_button    = $('.ab-to-second-step', $container),
                            $phone_field    = $('.ab-user-phone', $container),
                            $email_field    = $('.ab-user-email', $container),
                            $name_field     = $('.ab-full-name', $container),
                            $phone_error    = $('.ab-user-phone-error', $container),
                            $email_error    = $('.ab-user-email-error', $container),
                            $name_error     = $('.ab-full-name-error', $container),
                            $errors         = $('.ab-user-phone-error, .ab-user-email-error, .ab-full-name-error, div.ab-custom-field-error', $container),
                            $fields         = $('.ab-user-phone, .ab-user-email, .ab-full-name, .ab-custom-field', $container)
                        ;

                        $button_next.on('click', function(e) {
                            e.preventDefault();
                            var custom_fields_data = [],
                                checkbox_values
                            ;

                            $.each(Options.custom_fields, function(i, field) {
                                switch (field.type) {
                                    case 'text-field':
                                        custom_fields_data.push({
                                            id      : field.id,
                                            value   : $('input[name="ab-custom-field-' + field.id + '"]', $container).val()
                                        });
                                        break;
                                    case 'textarea':
                                        custom_fields_data.push({
                                            id      : field.id,
                                            value   : $('textarea[name="ab-custom-field-' + field.id + '"]', $container).val()
                                        });
                                        break;
                                    case 'checkboxes':
                                        if ($('input[name="ab-custom-field-' + field.id + '"][type=checkbox]:checked', $container).length) {
                                            checkbox_values = [];
                                            $('input[name="ab-custom-field-' + field.id + '"][type=checkbox]:checked', $container).each(function () {
                                                checkbox_values.push($(this).val());
                                            });
                                            custom_fields_data.push({
                                                id      : field.id,
                                                value   : checkbox_values
                                            });
                                        }
                                        break;
                                    case 'radio-buttons':
                                        if ($('input[name="ab-custom-field-' + field.id + '"][type=radio]:checked', $container).length) {
                                            custom_fields_data.push({
                                                id      : field.id,
                                                value   : $('input[name="ab-custom-field-' + field.id + '"][type=radio]:checked', $container).val()
                                            });
                                        }
                                        break;
                                    case 'drop-down':
                                        custom_fields_data.push({
                                            id      : field.id,
                                            value   : $('select[name="ab-custom-field-' + field.id + '"] > option:selected', $container).val()
                                        });
                                        break;
                                }
                            });

                            var data = {
                                    action          : 'ab_session_save',
                                    form_id         : Options.form_id,
                                    name            : $name_field.val(),
                                    phone           : $phone_field.val(),
                                    email           : $email_field.val(),
                                    custom_fields   : JSON.stringify(custom_fields_data)
                                },
                                ladda = Ladda.create(this);

                            ladda.start();
                            $.ajax({
                                type        : 'POST',
                                url         : Options.ajaxurl,
                                data        : data,
                                dataType    : 'json',
                                xhrFields   : { withCredentials: true },
                                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                                success     : function (response) {
                                    // Error messages
                                    $errors.empty();
                                    $fields.removeClass('ab-details-error');

                                    if (response.length == 0) {
                                        if (Options.woocommerce) {
                                            wooStep();
                                        } else {
                                            fourthStep();
                                        }
                                    } else {
                                        ladda.stop();
                                        var $scroll_to = null;
                                        if (response.name) {
                                            $name_error.html(response.name);
                                            $name_field.addClass('ab-details-error');
                                            $scroll_to = $name_field;
                                        }
                                        if (response.phone) {
                                            $phone_error.html(response.phone);
                                            $phone_field.addClass('ab-details-error');
                                            if ($scroll_to === null) {
                                                $scroll_to = $phone_field;
                                            }
                                        }
                                        if (response.email) {
                                            $email_error.html(response.email);
                                            $email_field.addClass('ab-details-error');
                                            if ($scroll_to === null) {
                                                $scroll_to = $email_field;
                                            }
                                        }
                                        if (response.custom_fields) {
                                            $.each(response.custom_fields, function(key, value) {
                                                $('.' + key + '-error', $container).html(value);
                                                $('[name=' + key + ']', $container).addClass('ab-details-error');
                                                if ($scroll_to === null) {
                                                    $scroll_to = $('[name=' + key + ']', $container);
                                                }
                                            });
                                        }
                                        if ($scroll_to !== null) {
                                            scrollTo($scroll_to);
                                        }
                                    }
                                }
                            });
                        });

                        $back_button.on('click', function (e) {
                            e.preventDefault();
                            var ladda = Ladda.create(this);
                            ladda.start();
                            secondStep();
                        });
                    }
                }
            });
        }

        //
        function wooStep(){
            var data = {
                action  : 'ab_add_to_woocommerce_cart',
                form_id : Options.form_id
            };
            $.ajax({
                type        : 'POST',
                url         : Options.ajaxurl,
                data        : data,
                dataType    : 'json',
                xhrFields   : { withCredentials: true },
                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                success     : function (response) {
                    // The session doesn't contain data or payment is disabled in Admin Settings
                    if (response.status == 'no-data') {
                        save();
                    } else if (response.status == 'success') {
                        window.location.href = Options.woocommerce_cart_url;
                    }
                }
            });
        }

        //
        function fourthStep() {
            $.ajax({
                url        : Options.ajaxurl,
                data       : {action: 'ab_render_payment', form_id: Options.form_id},
                dataType   : 'json',
                xhrFields  : {withCredentials: true},
                crossDomain: 'withCredentials' in new XMLHttpRequest(),
                success    : function (response) {

                    // The session doesn't contain data or payment is disabled in Admin Settings
                    if (response.status == 'no-data') {
                        save();

                    } else {
                        $container.html(response.html);
                        scrollTo($container);

                        if (Options.is_cancelled) {
                            Options.is_cancelled = false;
                        }

                        var $local_pay = $('.ab-local-payment', $container),
                            $paypal_pay = $('.ab-paypal-payment', $container),
                            $authorizenet_pay = $('.ab-authorizenet-payment', $container),
                            $stripe_pay = $('.ab-stripe-payment', $container),
                            $local_pay_button = $('.ab-local-pay-button', $container),
                            $coupon_pay_button = $('.ab-coupon-payment-button', $container),
                            $paypal_pay_button = $('.ab-paypal-payment-button', $container),
                            $card_payment_button = $('.ab-card-payment-button', $container),
                            $back_button = $('.ab-to-third-step', $container),
                            $apply_coupon_button = $('.apply-coupon', $container),
                            $coupon_input = $('input.ab-user-coupon', $container),
                            $coupon_error = $('.ab-coupon-error', $container),
                            $coupon_info_text = $('.ab-info-text-coupon', $container),
                            $ab_payment_nav = $('.ab-payment-nav', $container),
                            $buttons = $('.ab-paypal-payment-button,.ab-card-payment-button,form.ab-authorizenet,form.ab-stripe,.ab-local-pay-button', $container)
                            ;

                        $local_pay.on('click', function () {
                            $buttons.hide();
                            $local_pay_button.show();
                        });

                        $paypal_pay.on('click', function () {
                            $buttons.hide();
                            $paypal_pay_button.show();
                        });

                        $authorizenet_pay.on('click', function () {
                            $buttons.hide();
                            $card_payment_button.show();
                            $('form.ab-authorizenet', $container).show();
                        });

                        $stripe_pay.on('click', function () {
                            $buttons.hide();
                            $card_payment_button.show();
                            $('form.ab-stripe', $container).show();
                        });

                        $apply_coupon_button.on('click', function (e) {
                            var ladda = Ladda.create(this);

                            ladda.start();
                            $coupon_error.text('');
                            $coupon_input.removeClass('ab-details-error');

                            var data = {
                                action : 'ab_apply_coupon',
                                form_id: Options.form_id,
                                coupon : $coupon_input.val()
                            };

                            $.ajax({
                                type       : 'POST',
                                url        : Options.ajaxurl,
                                data       : data,
                                dataType   : 'json',
                                xhrFields  : {withCredentials: true},
                                crossDomain: 'withCredentials' in new XMLHttpRequest(),
                                success    : function (response) {
                                    if (response.status == 'success') {
                                        $coupon_info_text.html(response.text);
                                        $coupon_input.replaceWith(data.coupon);
                                        $apply_coupon_button.replaceWith('✓');
                                        if (response.discount == 100) {
                                            $ab_payment_nav.hide();
                                            $buttons.hide();
                                            $coupon_pay_button.show('fast', function () {
                                                $('.ab-coupon-free', $container).attr('checked', 'checked').val(data.coupon);
                                            });
                                        }
                                    }
                                    else if (response.status == 'error') {
                                        $coupon_error.html(response.error);
                                        $coupon_input.addClass('ab-details-error');
                                        $coupon_info_text.html(response.text);
                                        scrollTo($coupon_error);
                                    }
                                    ladda.stop();
                                },
                                error      : function () {
                                    ladda.stop();
                                }
                            });
                        });

                        if ($coupon_input.val()) {
                            $apply_coupon_button.click();
                        }

                        $('.ab-final-step', $container).on('click', function (e) {
                            var ladda = Ladda.create(this);

                            if ($('.ab-local-payment', $container).is(':checked') || $(this).hasClass('ab-coupon-payment')) { // handle only if was selected local payment !
                                e.preventDefault();
                                ladda.start();
                                save();

                            } else if ($('.ab-authorizenet-payment', $container).is(':checked') || $('.ab-stripe-payment', $container).is(':checked')) { // handle only if was selected AuthorizeNet payment !
                                var authorize = $('.ab-authorizenet-payment', $container).is(':checked');
                                var card_action = authorize ? 'ab_authorize_net_aim' : 'ab_stripe';
                                var card_form = authorize ? 'ab-authorizenet' : 'ab-stripe';

                                e.preventDefault();
                                ladda.start();

                                var data = {
                                    action        : card_action,
                                    ab_card_number: $('.' + card_form + ' input[name="ab_card_number"]', $container).val(),
                                    ab_card_code  : $('.' + card_form + ' input[name="ab_card_code"]', $container).val(),
                                    ab_card_month : $('.' + card_form + ' select[name="ab_card_month"]', $container).val(),
                                    ab_card_year  : $('.' + card_form + ' select[name="ab_card_year"]', $container).val(),
                                    form_id       : Options.form_id
                                };

                                $.ajax({
                                    type       : 'POST',
                                    url        : Options.ajaxurl,
                                    data       : data,
                                    xhrFields  : {withCredentials: true},
                                    crossDomain: 'withCredentials' in new XMLHttpRequest(),
                                    success    : function (response) {
                                        var _response;
                                        try {
                                            _response = JSON.parse(response);
                                        } catch (e) {
                                        }
                                        if (typeof _response === 'object') {
                                            var $response = $.parseJSON(response);

                                            if ($response.error) {
                                                ladda.stop();
                                                $('.' + card_form + ' .ab-card-error', $container).text($response.error);
                                            } else {
                                                Options.is_available = !!$response.state;
                                                fifthStep();
                                            }
                                        } else {
                                            ladda.stop();
                                        }
                                    }
                                });
                            } else if ($('.ab-paypal-payment', $container).is(':checked')) {
                                ladda.start();
                                $(this).closest('form').submit();
                            }
                        });

                        $back_button.on('click', function (e) {
                            e.preventDefault();
                            var ladda = Ladda.create(this);
                            ladda.start();

                            thirdStep();
                        });
                    }
                }
            });
        }

        //
        function fifthStep() {
            $.ajax({
                url         : Options.ajaxurl,
                data        : { action : 'ab_render_complete', form_id : Options.form_id },
                xhrFields   : { withCredentials: true },
                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                success     : function (response) {
                    if (response.length != 0) {
                        var $response = $.parseJSON(response);

                        if (Options.is_available || Options.is_finished) {
                            if (Options.final_step_url) {
                                document.location.href = Options.final_step_url;
                            } else {
                                $response.step
                                    ? $container.html($response.step + $response.state.success)
                                    : $container.html($response.state.success)
                                ;
                                scrollTo($container);
                            }

                            Options.is_finished = false;
                        } else {
                            secondStep($response.state.error);
                        }
                    }
                }
            });
        }

        // =========== helpers ===================

        function hideByAttributes() {
            if (Options.skip_first_step) {
                $('.ab-first-step', $container).hide();
            }
            if (Options.attributes.hide_categories && Options.attributes.category_id) {
                $('.ab-category', $container).hide();
            }
            if (Options.attributes.hide_services && Options.attributes.service_id) {
                $('.ab-service', $container).hide();
            }
            if (Options.attributes.hide_staff_members) {
                $('.ab-employee', $container).hide();
            }
            if (Options.attributes.hide_date_and_time) {
                $('.ab-available-date', $container).parent().hide();
            }
            if (!Options.attributes.show_number_of_persons) {
                $('.ab-number-of-persons', $container).hide();
            }
            if (Options.attributes.show_number_of_persons &&
                !Options.attributes.hide_staff_members &&
                !Options.attributes.hide_services &&
                !Options.attributes.hide_categories) {
                $('.ab-mobile-step_1', $container).addClass('ab-four-cols');
            }
        }

        // insert data into select
        function setSelect($select, data, leave_selected) {
            var selected = $select.val();
            var reset    = true;
            // reset select
            $('option:not([value=""])', $select).remove();
            // and fill the new data
            var docFragment = document.createDocumentFragment();

            function valuesToArray(obj) {
                return Object.keys(obj).map(function (key) { return obj[key]; });
            }

            function compare(a, b) {
                if (parseInt(a.position) < parseInt(b.position))
                    return -1;
                if (parseInt(a.position) > parseInt(b.position))
                    return 1;
                return 0;
            }

            // sort select by position
            data = valuesToArray(data).sort(compare);

            $.each(data, function(id, object) {
                id = object.id;

                if (selected === id && leave_selected) {
                    reset = false;
                }
                var option = document.createElement('option');
                option.value = id;
                option.text = object.name;
                docFragment.appendChild(option);
            });
            $select.append(docFragment);
            // set default value of select
            $select.val(reset ? '' : selected);
        }

        //
        function save() {
            $.ajax({
                type        : 'POST',
                url         : Options.ajaxurl,
                xhrFields   : { withCredentials: true },
                crossDomain : 'withCredentials' in new XMLHttpRequest(),
                data        : { action  : 'ab_save_appointment', form_id : Options.form_id }
            }).done(function(response) {
                var $response = $.parseJSON(response);
                Options.is_available = !!$response.state;
                fifthStep();
            });
        }

        /**
         * Scroll to element if it is not visible.
         *
         * @param $elem
         */
        function scrollTo( $elem ) {
            var elemTop   = $elem.offset().top;
            var scrollTop = $(window).scrollTop();
            if (elemTop < $(window).scrollTop() || elemTop > scrollTop + window.innerHeight) {
                $('html,body').animate({ scrollTop: (elemTop - 24) }, 500);
            }
        }
    }
})(jQuery);
