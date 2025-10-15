jQuery(document).ready(function($){
    // Tabs: select room and check availability
    $('.krixen-room-tab').on('click', function(){
        $('.krixen-room-tab').removeClass('active');
        $(this).addClass('active');
        var roomId = $(this).data('room-id');
        var cap    = $(this).data('capacity');
        $('select[name="room_id"]').val(roomId).trigger('change');
        var date   = $('input[name="date"]').val();
        if(!date){
            // Prefill today if empty
            var today = new Date();
            var yyyy = today.getFullYear();
            var mm = ('0'+(today.getMonth()+1)).slice(-2);
            var dd = ('0'+today.getDate()).slice(-2);
            $('input[name="date"]').val(yyyy+'-'+mm+'-'+dd);
        }
        refreshAvailability(function(hasBookings){
            $('#krixen-room-status').show();
            if(hasBookings){
                $('#krixen-room-status').text('Some times are booked. Please choose an available time below.').removeClass('ok').addClass('warn');
            } else {
                $('#krixen-room-status').text('Room is available for the selected date.').removeClass('warn').addClass('ok');
            }
            $('#krixen-booking-form').show();
        });
    });
    $('#krixen-booking-form').on('submit',function(e){
        e.preventDefault();
        var form = $(this);
        var msg  = form.find('.krixen-message');
        msg.hide().removeClass('error success');
        var data = form.serializeArray();
        data.push({name:'action',value:'krixen_submit_booking'});
        data.push({name:'nonce',value:KrixenBooking.nonce});
        $.post(KrixenBooking.ajax_url,data,function(response){
            if(response.success){
                msg.addClass('success').text(response.data).css('color','green').show();
                form[0].reset();
            }else{
                msg.addClass('error').text(response.data).css('color','red').show();
            }
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
                    var badge = slot.available? '<span class="badge-ok">Available</span>' : '<span class="badge-no">Booked</span>';
                    availEl.append('<div class="slot-row">'+slot.label+' '+badge+'</div>');
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