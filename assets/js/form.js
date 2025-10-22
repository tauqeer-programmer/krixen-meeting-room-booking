/**
 * Krixen Booking Form JavaScript
 * 
 * Modern ES6+ implementation with better state management and UX.
 * 
 * @package Krixen
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Booking Form Manager
	 */
	class KrixenBookingForm {
		constructor() {
			this.form = $('#krixen-booking-form');
			this.messageEl = this.form.find('.krixen-message');
			this.availabilityEl = $('#krixen-availability');
			this.roomStatusEl = $('#krixen-room-status');
			
			this.init();
		}

		/**
		 * Initialize the form
		 */
		init() {
			this.bindEvents();
			this.prefillDateIfNeeded();
			this.checkInitialAvailability();
		}

		/**
		 * Bind all event handlers
		 */
		bindEvents() {
			// Room card selection
			$('.krixen-book-room').on('click', (e) => {
				e.preventDefault();
				const roomId = $(e.currentTarget).data('room-id');
				this.selectRoom(roomId);
			});

			// Form submission
			this.form.on('submit', (e) => this.handleSubmit(e));

			// Room/Date change triggers
			$('select[name="room_id"], input[name="date"]').on('change', () => {
				this.refreshAvailability();
			});

			// Start time change
			$('select[name="start_time"]').on('change', () => {
				this.updateEndTime();
			});
		}

		/**
		 * Prefill today's date if empty
		 */
		prefillDateIfNeeded() {
			const dateInput = $('input[name="date"]');
			if (!dateInput.val()) {
				const today = new Date();
				const dateStr = today.toISOString().split('T')[0];
				dateInput.val(dateStr);
			}
		}

		/**
		 * Select a room and show the form
		 * 
		 * @param {number} roomId 
		 */
		async selectRoom(roomId) {
			const roomSelect = $('select[name="room_id"]');
			roomSelect.val(roomId);
			
			await this.refreshAvailability();
			this.form.show();
			
			// Smooth scroll to form
			$('html, body').animate({
				scrollTop: this.form.offset().top - 20
			}, 400);
		}

		/**
		 * Handle form submission
		 * 
		 * @param {Event} e 
		 */
		async handleSubmit(e) {
			e.preventDefault();
			
			this.messageEl.hide().removeClass('error success');
			
			const formData = this.form.serializeArray();
			formData.push(
				{ name: 'action', value: 'krixen_submit_booking' },
				{ name: 'nonce', value: KrixenBooking.nonce }
			);

			try {
				const response = await $.post(KrixenBooking.ajax_url, formData);
				
				if (response.success) {
					this.showSuccess(response.data);
					this.form[0].reset();
					this.prefillDateIfNeeded();
				} else {
					this.showError(response.data);
				}
			} catch (error) {
				this.showError('An error occurred. Please try again.');
			}
		}

		/**
		 * Build time slots dropdown
		 */
		async buildSlots() {
			const roomId = $('select[name="room_id"]').val();
			const date = $('input[name="date"]').val();
			
			if (!roomId || !date) {
				$('select[name="start_time"]').empty();
				return;
			}

			try {
				const response = await $.post(KrixenBooking.ajax_url, {
					action: 'get_krixen_time_slots',
					nonce: KrixenBooking.nonce,
					room_id: roomId,
					date: date,
					duration: 3
				});

				if (response.success) {
					this.renderTimeSlots(response.data);
				}
			} catch (error) {
				console.error('Error fetching time slots:', error);
			}
		}

		/**
		 * Render time slots in the dropdown and availability display
		 * 
		 * @param {Array} slots 
		 */
		renderTimeSlots(slots) {
			const startSelect = $('select[name="start_time"]').empty();
			this.availabilityEl.empty();

			if (!slots || slots.length === 0) {
				this.availabilityEl.append(
					'<div class="krixen-availability-error">No available time slots</div>'
				);
				return;
			}

			slots.forEach(slot => {
				// Add to dropdown
				const option = $('<option/>')
					.val(slot.start_24)
					.text(slot.label)
					.prop('disabled', !slot.available)
					.toggleClass('disabled', !slot.available);
				startSelect.append(option);

				// Add to availability display
				const badge = slot.available 
					? '<span class="badge-ok">Available</span>' 
					: '<span class="badge-no">Booked</span>';
				this.availabilityEl.append(
					`<div class="slot-row">${slot.label} ${badge}</div>`
				);
			});

			// Auto-select first available slot
			const firstAvailable = startSelect.find('option:not(:disabled)').first();
			if (firstAvailable.length) {
				startSelect.val(firstAvailable.val());
				this.updateEndTime();
			}
		}

		/**
		 * Update end time based on selected start time
		 */
		updateEndTime() {
			const start = $('select[name="start_time"]').val();
			const duration = 3; // Fixed 3 hours
			
			if (!start) {
				$('input[name="end_time"]').val('');
				return;
			}

			const [hours, minutes] = start.split(':').map(Number);
			const startDate = new Date();
			startDate.setHours(hours, minutes, 0, 0);
			
			const endDate = new Date(startDate);
			endDate.setHours(endDate.getHours() + duration);
			
			const endHours = endDate.getHours();
			const endMinutes = String(endDate.getMinutes()).padStart(2, '0');
			const ampm = endHours >= 12 ? 'PM' : 'AM';
			const displayHours = endHours % 12 || 12;
			
			$('input[name="end_time"]').val(
				`${String(displayHours).padStart(2, '0')}:${endMinutes} ${ampm}`
			);
		}

		/**
		 * Refresh availability display
		 */
		async refreshAvailability() {
			const roomId = $('select[name="room_id"]').val();
			const date = $('input[name="date"]').val();
			
			if (!roomId || !date) {
				this.availabilityEl.empty();
				this.roomStatusEl.hide();
				return;
			}

			try {
				const response = await $.post(KrixenBooking.ajax_url, {
					action: 'krixen_check_availability',
					nonce: KrixenBooking.nonce,
					room_id: roomId,
					date: date
				});

				if (response.success) {
					this.updateRoomStatus(response.data);
					await this.buildSlots();
				} else {
					this.availabilityEl.html(
						`<div class="krixen-availability-error">${response.data}</div>`
					);
				}
			} catch (error) {
				console.error('Error checking availability:', error);
			}
		}

		/**
		 * Update room status indicator
		 * 
		 * @param {Array} bookings 
		 */
		updateRoomStatus(bookings) {
			this.roomStatusEl.show();
			
			if (bookings.length === 0) {
				this.roomStatusEl
					.text('Room is available for the selected date.')
					.removeClass('warn')
					.addClass('ok');
			} else {
				this.roomStatusEl
					.text('Some times are booked. Please choose an available time below.')
					.removeClass('ok')
					.addClass('warn');
			}
		}

		/**
		 * Check initial availability if form is pre-filled
		 */
		checkInitialAvailability() {
			const roomId = $('select[name="room_id"]').val();
			const date = $('input[name="date"]').val();
			
			if (roomId && date) {
				this.refreshAvailability();
			}
		}

		/**
		 * Show success message
		 * 
		 * @param {string} message 
		 */
		showSuccess(message) {
			this.messageEl
				.addClass('success')
				.removeClass('error')
				.css('color', '#065f46')
				.text(message)
				.fadeIn();
		}

		/**
		 * Show error message
		 * 
		 * @param {string} message 
		 */
		showError(message) {
			this.messageEl
				.addClass('error')
				.removeClass('success')
				.css('color', '#991b1b')
				.text(message)
				.fadeIn();
		}
	}

	// Initialize when document is ready
	$(document).ready(() => {
		if ($('#krixen-booking-form').length) {
			new KrixenBookingForm();
		}
	});

})(jQuery);
