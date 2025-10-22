jQuery(document).ready(function($){
    function todayYmd(){
        var t = new Date();
        var yyyy = t.getFullYear();
        var mm = ('0'+(t.getMonth()+1)).slice(-2);
        var dd = ('0'+t.getDate()).slice(-2);
        return yyyy+'-'+mm+'-'+dd;
    }

    // New UX: clicking "Book This Room" moves the form under the card
    $(document).on('click', '.krixen-book-room', function(){
        var btn   = $(this);
        var card  = btn.closest('.krixen-room-card');
        var form  = $('#krixen-booking-form');
        var roomId = btn.data('room-id') || card.data('room-id');
        var cap    = parseInt(btn.data('capacity') || 0, 10);
        var roomName = card.find('.krixen-room-name').text() || btn.data('room-name') || '';

        // Set selected room in form
        $('select[name="room_id"]').val(roomId).trigger('change');

        // Ensure date has at least today
        var dateEl = $('input[name="date"]');
        if(!dateEl.val()){ dateEl.val(todayYmd()); }

        // Show contextual status
        var status = $('#krixen-room-status');
        status.show().text('Checking availability for '+roomName+(cap?(' (Capacity: '+cap+')'):'')+'...').removeClass('ok warn');

        // Move and reveal the form under the selected card
        var insertAndShow = function(){
            form.insertAfter(card);
            form.stop(true, true).hide().slideDown(200);
        };
        if(form.is(':visible')){
            form.stop(true, true).slideUp(150, insertAndShow);
        } else {
            insertAndShow();
        }

        // Refresh availability and populate slots
        refreshAvailability(function(hasBookings){
            if(hasBookings){
                status.text('Some times are booked. Please choose an available time below.').removeClass('ok').addClass('warn');
            } else {
                status.text('Room is available for the selected date.').removeClass('warn').addClass('ok');
            }
        });
    });
    $('#krixen-booking-form').on('submit',function(e){
        e.preventDefault();
        var form = $(this);
        var msg  = form.find('.krixen-message');
        var submitBtn = form.find('button[type="submit"]');
        msg.hide().removeClass('error success');
        var data = form.serializeArray();
        data.push({name:'action',value:'krixen_submit_booking'});
        data.push({name:'nonce',value:KrixenBooking.nonce});
        submitBtn.prop('disabled', true).addClass('loading');
        $.post(KrixenBooking.ajax_url,data,function(response){
            if(response.success){
                msg.addClass('success').text(response.data).css('color','green').show();
                // Keep room and date; rebuild slots so UI reflects the new booking
                var keepRoom = $('select[name="room_id"]').val();
                var keepDate = $('input[name="date"]').val();
                form[0].reset();
                $('select[name="room_id"]').val(keepRoom);
                $('input[name="date"]').val(keepDate);
                refreshAvailability();
            }else{
                msg.addClass('error').text(response.data).css('color','red').show();
            }
        }).always(function(){
            submitBtn.prop('disabled', false).removeClass('loading');
        });
    });

    // Removed attendees field; no capacity updater needed

    function buildSlots(){
        var roomId = $('select[name="room_id"]').val();
        var date   = $('input[name="date"]').val();
        if(!roomId || !date){ $('select[name="start_time"]').empty(); return; }
        $.post(KrixenBooking.ajax_url,{
            action:'get_krixen_time_slots',
            nonce: KrixenBooking.nonce,
            room_id: roomId,
            date: date,
            duration: 3
        }, function(resp){
            var startSel = $('select[name="start_time"]').empty();
            var availEl = $('#krixen-availability').empty();
            if(resp.success){
                resp.data.forEach(function(slot){
                    var opt = $('<option/>').val(slot.start_24).text(slot.label).prop('disabled', !slot.available).toggleClass('disabled', !slot.available);
                    startSel.append(opt);
                    var badge = slot.available? '<span class="badge-ok" aria-label="Available">Available</span>' : '<span class="badge-no" aria-label="Booked">Booked</span>';
                    availEl.append('<div class="slot-row" role="listitem" aria-live="off">'+slot.label+' '+badge+'</div>');
                });
                // set end time based on first valid
                var selected = startSel.find('option:not(:disabled)').first();
                if(selected.length){ startSel.val(selected.val()); updateEndTime(); }
            }
        });
    }

    function updateEndTime(){
        var start = $('select[name="start_time"]').val();
        var duration = 3; // fixed 3 hours
        if(!start){ $('input[name="end_time"]').val(''); return; }
        var parts = start.split(':');
        var d = new Date(); d.setHours(parseInt(parts[0],10)); d.setMinutes(parseInt(parts[1],10));
        d.setHours(d.getHours()+duration);
        var hh = d.getHours(); var mm = ('0'+d.getMinutes()).slice(-2);
        var ampm = hh >= 12 ? 'PM' : 'AM';
        var hr12 = hh % 12; if(hr12===0) hr12 = 12;
        $('input[name="end_time"]').val((('0'+hr12).slice(-2))+':'+mm+' '+ampm);
    }

    function refreshAvailability(cb){
        var roomId = $('select[name="room_id"]').val();
        var date   = $('input[name="date"]').val();
        if(!roomId || !date){
            $('#krixen-availability').empty();
            if(typeof cb === 'function') cb(false);
            return;
        }
        $.post(KrixenBooking.ajax_url,{
            action:'krixen_check_availability',
            nonce: KrixenBooking.nonce,
            room_id: roomId,
            date: date
        },function(resp){
            var el = $('#krixen-availability');
            el.empty();
            if(resp.success){
                if(resp.data.length === 0){
                    el.append('<div class="krixen-availability-free">All day available</div>');
                    if(typeof cb === 'function') cb(false);
                } else {
                    el.append('<div class="krixen-availability-title">Booked slots:</div>');
                    resp.data.forEach(function(row){
                        el.append('<div class="krixen-availability-slot">'+row.start_time+' - '+row.end_time+'</div>');
                    });
                    if(typeof cb === 'function') cb(true);
                }
                buildSlots();
            } else {
                el.append('<div class="krixen-availability-error">'+resp.data+'</div>');
                if(typeof cb === 'function') cb(true);
            }
        });
    }

    $('select[name="room_id"], input[name="date"]').on('change', refreshAvailability);
    $('select[name="start_time"]').on('change', updateEndTime);
    // Initialize once if values prefilled (Elementor preview)
    refreshAvailability();
});