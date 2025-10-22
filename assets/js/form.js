// Vanilla JS rewrite for modern behavior
(function(){
  function qs(sel, ctx){ return (ctx||document).querySelector(sel); }
  function qsa(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
  function on(el, ev, fn){ el.addEventListener(ev, fn); }
  function post(url, data){
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(data).toString()
    }).then(r=>r.json());
  }

  var form = qs('#krixen-booking-form');
  var msg  = form ? form.querySelector('.krixen-message') : null;
  var startSel = form ? form.querySelector('select[name="start_time"]') : null;

  function setTodayIfEmpty(){
    var dateEl = form.querySelector('input[name="date"]');
    if(!dateEl.value){
      var t=new Date(); var yyyy=t.getFullYear(); var mm=('0'+(t.getMonth()+1)).slice(-2); var dd=('0'+t.getDate()).slice(-2);
      dateEl.value = yyyy+'-'+mm+'-'+dd;
    }
  }

  function updateEndTime(){
    var start = startSel.value;
    var duration = 3; // fixed 3 hours
    var endEl = form.querySelector('input[name="end_time"]');
    if(!start){ endEl.value=''; return; }
    var parts = start.split(':');
    var d = new Date(); d.setHours(parseInt(parts[0],10)); d.setMinutes(parseInt(parts[1],10));
    d.setHours(d.getHours()+duration);
    var hh = d.getHours(); var mm = ('0'+d.getMinutes()).slice(-2);
    var ampm = hh >= 12 ? 'PM' : 'AM';
    var hr12 = hh % 12; if(hr12===0) hr12 = 12;
    endEl.value = (('0'+hr12).slice(-2))+':'+mm+' '+ampm;
  }

  function buildSlots(){
    var roomId = form.querySelector('select[name="room_id"]').value;
    var date   = form.querySelector('input[name="date"]').value;
    if(!roomId || !date){ startSel.innerHTML=''; return; }
    post(KrixenBooking.ajax_url, {
      action: 'get_krixen_time_slots',
      nonce: KrixenBooking.nonce,
      room_id: roomId,
      date: date,
      duration: 3
    }).then(function(resp){
      startSel.innerHTML = '';
      var availEl = qs('#krixen-availability');
      availEl.innerHTML = '';
      if(resp && resp.success){
        resp.data.forEach(function(slot){
          var opt = document.createElement('option');
          opt.value = slot.start_24; opt.textContent = slot.label; opt.disabled = !slot.available;
          if(!slot.available){ opt.classList.add('disabled'); }
          startSel.appendChild(opt);
          var badge = slot.available? '<span class="badge-ok">Available</span>' : '<span class="badge-no">Booked</span>';
          var div = document.createElement('div');
          div.className = 'slot-row';
          div.innerHTML = slot.label+' '+badge;
          availEl.appendChild(div);
        });
        var first = startSel.querySelector('option:not([disabled])');
        if(first){ startSel.value = first.value; updateEndTime(); }
      }
    });
  }

  function refreshAvailability(cb){
    var roomId = form.querySelector('select[name="room_id"]').value;
    var date   = form.querySelector('input[name="date"]').value;
    var el = qs('#krixen-availability');
    el.innerHTML = '';
    if(!roomId || !date){ if(typeof cb==='function') cb(false); return; }
    post(KrixenBooking.ajax_url, {
      action: 'krixen_check_availability',
      nonce: KrixenBooking.nonce,
      room_id: roomId,
      date: date
    }).then(function(resp){
      if(resp && resp.success){
        if(resp.data.length === 0){
          el.insertAdjacentHTML('beforeend','<div class="krixen-availability-free">All day available</div>');
          if(typeof cb==='function') cb(false);
        } else {
          el.insertAdjacentHTML('beforeend','<div class="krixen-availability-title">Booked slots:</div>');
          resp.data.forEach(function(row){
            var div = document.createElement('div');
            div.className = 'krixen-availability-slot';
            div.textContent = row.start_time+' - '+row.end_time;
            el.appendChild(div);
          });
          if(typeof cb==='function') cb(true);
        }
        buildSlots();
      } else {
        el.insertAdjacentHTML('beforeend','<div class="krixen-availability-error">'+(resp && resp.data ? resp.data : 'Error')+'</div>');
        if(typeof cb==='function') cb(true);
      }
    });
  }

  function initCardButtons(){
    qsa('.krixen-book-room').forEach(function(btn){
      on(btn, 'click', function(){
        var roomId = btn.getAttribute('data-room-id');
        form.style.display = 'block';
        var roomSelect = form.querySelector('select[name="room_id"]');
        roomSelect.value = roomId;
        setTodayIfEmpty();
        refreshAvailability(function(hasBookings){
          var status = qs('#krixen-room-status');
          status.style.display = 'block';
          if(hasBookings){
            status.textContent = 'Some times are booked. Please choose an available time below.';
            status.classList.remove('ok'); status.classList.add('warn');
          } else {
            status.textContent = 'Room is available for the selected date.';
            status.classList.remove('warn'); status.classList.add('ok');
          }
          window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 40, behavior: 'smooth' });
        });
      });
    });
  }

  function bindForm(){
    on(form, 'submit', function(e){
      e.preventDefault();
      msg.style.display='none'; msg.classList.remove('error','success');
      var fd = new FormData(form);
      fd.append('action','krixen_submit_booking');
      fd.append('nonce', KrixenBooking.nonce);
      var btn = form.querySelector('button[type="submit"]');
      var old = btn.textContent; btn.disabled=true; btn.textContent='Booking...';
      post(KrixenBooking.ajax_url, Object.fromEntries(fd)).then(function(response){
        if(response && response.success){
          msg.classList.add('success'); msg.style.color='green'; msg.textContent = response.data; msg.style.display='block';
          form.reset();
          qs('#krixen-availability').innerHTML='';
        } else {
          msg.classList.add('error'); msg.style.color='red'; msg.textContent = (response && response.data) ? response.data : 'Error'; msg.style.display='block';
        }
      }).finally(function(){
        btn.disabled=false; btn.textContent=old;
      });
    });

    on(startSel, 'change', updateEndTime);
    on(form.querySelector('select[name="room_id"]'), 'change', function(){ refreshAvailability(); });
    on(form.querySelector('input[name="date"]'), 'change', function(){ refreshAvailability(); });
  }

  document.addEventListener('DOMContentLoaded', function(){
    if(!form) return;
    initCardButtons();
    bindForm();
    refreshAvailability();
  });
})();