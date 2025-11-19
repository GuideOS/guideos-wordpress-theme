const DATA_ELEMENT_ID = 'guideos-advent-data';

const parseBootstrapData = () => {
	const el = document.getElementById( DATA_ELEMENT_ID );
	if ( ! el ) {
		return {};
	}

	try {
		const data = JSON.parse( el.textContent || '{}' );
		el.parentNode?.removeChild( el );
		return data;
	} catch ( error ) {
		console.warn( 'GuideOS Advent Calendar: Unable to parse bootstrap data', error );
		return {};
	}
};

class AdventCalendar {
	constructor( root, config ) {
		this.root = root;
		this.config = config || {};
		this.instanceId = this.root?.dataset?.instance;
		this.doorButtons = Array.from( this.root.querySelectorAll( '.guideos-advent__door' ) );
		this.statusEl = this.root.querySelector( '.guideos-advent__status' );
		this.modal = {
			wrapper: this.root.querySelector( '.guideos-advent__modal' ),
			body: this.root.querySelector( '.guideos-advent__modal-body' ),
			close: this.root.querySelector( '.guideos-advent__modal-close' ),
			backdrop: this.root.querySelector( '.guideos-advent__modal-backdrop' ),
		};

		this.storageKey = `guideos-advent-${ this.instanceId }-doors`;
		this.openedDoors = new Set( this.readStorage() );
		this.isModalOpen = false;

		if ( ! this.instanceId || ! this.config?.ajaxUrl ) {
			return;
		}

		this.applyInitialState();
		this.bindEvents();
	}

	readStorage() {
		try {
			const raw = window.localStorage.getItem( this.storageKey );
			return raw ? JSON.parse( raw ) : [];
		} catch ( error ) {
			console.warn( 'GuideOS Advent Calendar: localStorage unavailable', error );
			return [];
		}
	}

	writeStorage() {
		try {
			window.localStorage.setItem( this.storageKey, JSON.stringify( Array.from( this.openedDoors ) ) );
		} catch ( error ) {
			console.warn( 'GuideOS Advent Calendar: Unable to persist opened doors', error );
		}
	}

	applyInitialState() {
		const unlockedUntil = this.config?.testMode ? 24 : this.config?.availableDay || 0;
		this.doorButtons.forEach( ( button ) => {
			const day = parseInt( button.dataset.day, 10 );
			if ( Number.isNaN( day ) ) {
				return;
			}

			if ( this.openedDoors.has( day ) ) {
				button.classList.add( 'is-open' );
				button.setAttribute( 'aria-pressed', 'true' );
			}

			if ( day <= unlockedUntil || this.config?.testMode ) {
				button.classList.add( 'is-unlocked' );
				button.dataset.locked = '0';
			}
		} );
	}

	bindEvents() {
		this.doorButtons.forEach( ( button ) => {
			button.addEventListener( 'click', () => this.handleDoorClick( button ) );
		} );

		if ( this.modal.close ) {
			this.modal.close.addEventListener( 'click', () => this.closeModal() );
		}

		if ( this.modal.backdrop ) {
			this.modal.backdrop.addEventListener( 'click', () => this.closeModal() );
		}

		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key && this.isModalOpen ) {
				this.closeModal();
			}
		} );
	}

	setStatus( message, type = 'neutral' ) {
		if ( ! this.statusEl ) {
			return;
		}
		this.statusEl.textContent = message || '';
		this.statusEl.dataset.status = type;
	}

	setDoorLoading( button, isLoading ) {
		if ( isLoading ) {
			button.classList.add( 'is-loading' );
			button.disabled = true;
		} else {
			button.classList.remove( 'is-loading' );
			button.disabled = false;
		}
	}

	async handleDoorClick( button ) {
		const day = parseInt( button.dataset.day, 10 );
		if ( Number.isNaN( day ) ) {
			return;
		}

		this.setDoorLoading( button, true );
		this.setStatus( '' );

		try {
			const response = await this.fetchDoor( day );
			if ( response?.door?.content ) {
				this.openedDoors.add( day );
				this.writeStorage();
				this.markDoorOpened( button );
				this.renderModal( response.door.content );
				this.openModal();
			}
		} catch ( error ) {
			this.setStatus( error.message || error, 'error' );
		} finally {
			this.setDoorLoading( button, false );
		}
	}

	markDoorOpened( button ) {
		button.classList.add( 'is-open' );
		button.dataset.locked = '0';
		button.setAttribute( 'aria-pressed', 'true' );
	}

	async fetchDoor( day ) {
		const formData = new URLSearchParams();
		formData.append( 'action', 'guideos_advent_open_door' );
		formData.append( 'instance', this.instanceId );
		formData.append( 'day', String( day ) );
		formData.append( 'nonce', this.config?.nonce || '' );

		const response = await fetch( this.config.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			credentials: 'same-origin',
			body: formData.toString(),
		} );

		const payload = await response.json();
		if ( ! payload?.success ) {
			const message = payload?.data?.message || payload?.data?.error || payload?.data?.message || 'Dieses Türchen ist noch verschlossen.';
			throw new Error( message );
		}

		return payload.data;
	}

	renderModal( contentHtml ) {
		if ( ! this.modal.body ) {
			return;
		}
		this.modal.body.innerHTML = contentHtml;
	}

	openModal() {
		if ( ! this.modal.wrapper ) {
			return;
		}
		this.modal.wrapper.hidden = false;
		this.modal.wrapper.classList.add( 'is-visible' );
		this.root.classList.add( 'has-open-modal' );
		this.isModalOpen = true;
	}

	closeModal() {
		if ( ! this.modal.wrapper ) {
			return;
		}
		this.modal.wrapper.classList.remove( 'is-visible' );
		this.root.classList.remove( 'has-open-modal' );
		this.modal.wrapper.hidden = true;
		this.isModalOpen = false;
	}
}

const bootstrapCalendars = () => {
	const configMap = parseBootstrapData();
	const calendars = document.querySelectorAll( '.guideos-advent' );
	calendars.forEach( ( calendar ) => {
		const instance = calendar?.dataset?.instance;
		if ( instance && configMap?.[ instance ] ) {
			new AdventCalendar( calendar, configMap[ instance ] );
		}
	} );
};

if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
	bootstrapCalendars();
} else {
	document.addEventListener( 'DOMContentLoaded', bootstrapCalendars );
}
