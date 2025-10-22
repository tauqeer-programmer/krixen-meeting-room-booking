jQuery(document).ready(function($){
    // Tailwind-based UI integration from the provided design
    var bookingSection = $('#bookingSection');
    var bookingHeader  = $('#bookingHeader');
    var closeBooking   = $('#closeBookingBtn');
    var bookingForm    = $('#bookingForm');
    var toast          = $('#toast');
    var roomIdInput    = $('#room_id');
    var dateInput      = $('#bookingDate');
    var startInput     = $('#startTime');
    var endInput       = $('#endTime');
    var timelineDate   = $('#timelineDate');
    var timelineSlots  = $('#timeline-slots');
    var formError      = $('#formError');
    var bookBtn        = $('#bookNowBtn');

    function todayYmd(){
        var t = new Date();
        var yyyy = t.getFullYear();
        var mm = ('0'+(t.getMonth()+1)).slice(-2);
        var dd = ('0'+t.getDate()).slice(-2);
        return yyyy+'-'+mm+'-'+dd;
    }

    function ampmLabel(hh, mm){
        var h = hh % 12; if(h===0) h = 12;
        var ampm = hh >= 12 ? 'PM' : 'AM';
        return (h<10?(''+h):''+h)+':'+('0'+mm).slice(-2)+' '+ampm;
    }

    function setMinDate(){
        var t = todayYmd();
        dateInput.attr('min', t);
        if(!dateInput.val()) dateInput.val(t);
        timelineDate.text(new Date(dateInput.val()).toLocaleString('en-US', {day:'numeric', month:'long'}));
    }

    function toggleBookingSection(open){
        if(open){
            bookingSection.attr('aria-hidden','false');
            bookingSection.css('maxHeight', bookingSection.get(0).scrollHeight + 'px');
        } else {
            bookingSection.attr('aria-hidden','true');
            bookingSection.css('maxHeight', '0px');
        }
    }

    function refreshAvailability(cb){
        var rid = roomIdInput.val();
        var date = dateInput.val();
        if(!rid || !date){ timelineSlots.empty(); if(cb) cb(false); return; }
        $.post(KrixenBooking.ajax_url, {action:'krixen_check_availability', nonce:KrixenBooking.nonce, room_id:rid, date:date}, function(resp){
            // Build human-friendly booked list and also populate start time options from time slots endpoint
            buildTimeSlots(rid, date, cb);
        });
    }

    function buildTimeSlots(rid, date, cb){
        $.post(KrixenBooking.ajax_url, {action:'get_krixen_time_slots', nonce:KrixenBooking.nonce, room_id:rid, date:date, duration:3}, function(resp){
            timelineSlots.empty();
            startInput.val('');
            endInput.val('');
            var hasBookings = false;
            if(resp.success){
                var any = false;
                resp.data.forEach(function(slot){
                    any = true;
                    var card = $('<div/>').addClass('p-2.5 rounded-lg border text-center transition-all');
                    var border = slot.available ? 'border-gray-200 hover:border-orange-400 hover:shadow-sm' : 'border-red-200';
                    var bg = slot.available ? 'bg-white' : 'bg-red-50';
                    card.addClass(border+' '+bg);
                    var dot = $('<div/>').addClass('w-2 h-2 rounded-full mr-1.5 '+(slot.available?'bg-green-500':'bg-red-500'));
                    var status = $('<span/>').addClass('text-xs font-medium '+(slot.available?'text-green-800':'text-red-800')).text(slot.available?'Available':'Booked');
                    card.append($('<p/>').addClass('font-medium text-gray-800').css('font-size','0.8rem').text(slot.label));
                    var row = $('<div/>').addClass('flex items-center justify-center mt-1.5').append(dot).append(status);
                    card.append(row);
                    if(slot.available){
                        card.css('cursor','pointer').attr('tabindex',0).on('click keypress', function(e){
                            if(e.type==='click' || e.key==='Enter' || e.key===' '){
                                startInput.val(slot.start_24);
                                endInput.val(slot.end_24);
                                validateForm();
                            }
                        });
                    } else { hasBookings = true; }
                    timelineSlots.append(card);
                });
                if(!any){
                    timelineSlots.append('<div class="text-center text-gray-500 py-10 col-span-full"><p>No available slots.</p></div>');
                }
            }
            if(cb) cb(hasBookings);
        });
    }

    function validateForm(showShake){
        formError.text('');
        var valid = true;
        var start = startInput.val();
        var end   = endInput.val();
        var date  = dateInput.val();
        if(!$('#full_name').val() || !$('#email').val() || !date || !start || !end){ valid = false; }
        if(start && end && end <= start){ formError.text('End time must be after start time.'); valid = false; }
        bookBtn.prop('disabled', !valid);
        if(!valid && showShake){ formError.addClass('shake'); setTimeout(function(){ formError.removeClass('shake'); }, 500); }
        return valid;
    }

    // Room selection: open section, set room, load availability
    $(document).on('click', '.room-card, .room-card .select-room-btn', function(e){
        var card = $(this).closest('.room-card');
        var rid = card.data('room-id');
        var name = card.data('room-name');
        roomIdInput.val(rid);
        bookingHeader.text('Book '+name);
        $('.room-card').removeClass('ring-2 ring-orange-500');
        card.addClass('ring-2 ring-orange-500');
        setMinDate();
        toggleBookingSection(true);
        $('html, body').animate({scrollTop: bookingSection.offset().top - 60}, 300);
        refreshAvailability();
    });

    // Close booking
    closeBooking.on('click', function(){ toggleBookingSection(false); $('.room-card').removeClass('ring-2 ring-orange-500'); bookingForm.get(0).reset(); setMinDate(); validateForm(); });

    // Date change triggers availability
    dateInput.on('change', function(){ timelineDate.text(new Date($(this).val()).toLocaleString('en-US',{day:'numeric',month:'long'})); refreshAvailability(); validateForm(); });

    // Form submission via AJAX
    bookingForm.on('submit', function(e){
        e.preventDefault();
        if(!validateForm(true)) return;
        var btnText = bookBtn.find('.btn-text');
        var btnLoader = bookBtn.find('.btn-loader');
        btnText.addClass('hidden'); btnLoader.removeClass('hidden'); bookBtn.prop('disabled', true);
        var data = bookingForm.serializeArray();
        data.push({name:'action', value:'krixen_submit_booking'});
        data.push({name:'nonce', value: KrixenBooking.nonce});
        $.post(KrixenBooking.ajax_url, data, function(resp){
            if(resp && resp.success){
                toast.removeClass('translate-x-[120%]');
                setTimeout(function(){ toast.addClass('translate-x-[120%]'); }, 3000);
                // Refresh availability to reflect the new booking
                refreshAvailability();
            } else {
                formError.text(resp && resp.data ? resp.data : 'Error while booking.');
            }
        }).always(function(){ btnText.removeClass('hidden'); btnLoader.addClass('hidden'); bookBtn.prop('disabled', false); });
    });

    // Initial setup
    setMinDate();
    validateForm();
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

    // Old handlers removed; new design uses inputs and timeline tiles
});