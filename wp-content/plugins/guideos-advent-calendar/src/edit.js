import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	PanelColorSettings,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	URLInput,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	Notice,
} from '@wordpress/components';
import { useEffect, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const CONTENT_TYPES = [
	{ label: __( 'Bild', 'guideos-advent' ), value: 'image' },
	{ label: __( 'Download-Link', 'guideos-advent' ), value: 'download' },
	{ label: __( 'Externer Link', 'guideos-advent' ), value: 'link' },
	{ label: __( 'YouTube-Video', 'guideos-advent' ), value: 'video' },
];

const createDefaultDoors = () =>
	Array.from( { length: 24 }, ( _, index ) => {
		const day = index + 1;
		return {
			day,
			title: sprintf( __( 'Tür %d', 'guideos-advent' ), day ),
			type: day === 24 ? 'download' : 'image',
			description: '',
			imageUrl: '',
			imageUrlFull: '',
			imageId: 0,
			downloadLabel:
				day === 24
					? __( 'GuideOS ISO herunterladen', 'guideos-advent' )
					: __( 'Download starten', 'guideos-advent' ),
			linkUrl: '',
			linkLabel: __( 'Mehr erfahren', 'guideos-advent' ),
			videoUrl: '',
		};
	} );

const mergeDoors = ( savedDoors = [] ) => {
	const defaults = createDefaultDoors();
	let changed = false;
	const merged = defaults.map( ( door ) => {
		const existing = savedDoors.find( ( saved ) => Number( saved.day ) === door.day );
		if ( ! existing ) {
			changed = true;
			return door;
		}
		const nextDoor = { ...door, ...existing, day: door.day };
		if ( JSON.stringify( nextDoor ) !== JSON.stringify( existing ) ) {
			changed = true;
		}
		return nextDoor;
	} );
	return { merged, changed };
};

const DoorFields = ( { door, onChange } ) => {
	const set = ( key, value ) => onChange( { ...door, [ key ]: value } );
	
	// Load image URL if only imageId is present
	const media = useSelect(
		( select ) => {
			if ( door.imageId && ! door.imageUrl ) {
				return select( coreStore ).getMedia( door.imageId, { context: 'view' } );
			}
			return null;
		},
		[ door.imageId, door.imageUrl ]
	);

	// Sync imageUrl when media is loaded
	useEffect( () => {
		if ( media && media.source_url && ! door.imageUrl ) {
			const fullUrl = media.media_details?.sizes?.full?.source_url || media.source_url;
			set( 'imageUrl', media.source_url );
			set( 'imageUrlFull', fullUrl );
		}
	}, [ media, door.imageUrl, set ] );

	return (
		<div className="guideos-advent-door-fields">
			<SelectControl
				label={ __( 'Inhaltstyp', 'guideos-advent' ) }
				value={ door.type }
				onChange={ ( value ) => set( 'type', value ) }
				options={ CONTENT_TYPES }
			/>
			<TextControl
				label={ __( 'Tür-Titel', 'guideos-advent' ) }
				value={ door.title }
				onChange={ ( value ) => set( 'title', value ) }
			/>
			<TextareaControl
				label={ __( 'Beschreibung', 'guideos-advent' ) }
				value={ door.description }
				onChange={ ( value ) => set( 'description', value ) }
				rows={ 3 }
			/>
			{ door.type === 'image' && (
				<BaseControl label={ __( 'Bild', 'guideos-advent' ) }>
					<div className="guideos-advent-door-media">
						{ door.imageUrl && (
							<div style={{ marginBottom: '10px' }}>
								<img
									src={ door.imageUrl }
									alt={ door.title || __( 'Vorschau', 'guideos-advent' ) }
									style={{ maxWidth: '100%', height: 'auto', display: 'block' }}
								/>
							</div>
						) }
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) => {
									// Use 'full' size for original, unscaled image
									const fullUrl = media?.sizes?.full?.url || media?.url || '';
									set( 'imageUrl', media?.url || '' );
									set( 'imageUrlFull', fullUrl );
									set( 'imageId', media?.id || 0 );
								} }
								value={ door.imageId }
								render={ ( { open } ) => (
									<Button variant="secondary" onClick={ open }>
										{ door.imageUrl
											? __( 'Bild ersetzen', 'guideos-advent' )
											: __( 'Bild wählen', 'guideos-advent' ) }
									</Button>
								) }
							/>
						</MediaUploadCheck>
						{ door.imageUrl && (
							<Button
								variant="link"
								isDestructive
								onClick={ () => {
									set( 'imageUrl', '' );
									set( 'imageId', 0 );
								} }
								style={{ marginLeft: '10px' }}
							>
								{ __( 'Bild entfernen', 'guideos-advent' ) }
							</Button>
						) }
					</div>
				</BaseControl>
			) }
			{ door.type === 'download' && (
				<>
					<BaseControl label={ __( 'Download-URL', 'guideos-advent' ) }>
						<URLInput
							value={ door.linkUrl }
							onChange={ ( value ) => set( 'linkUrl', value ) }
						/>
					</BaseControl>
					<TextControl
						label={ __( 'Button-Label', 'guideos-advent' ) }
						value={ door.downloadLabel }
						onChange={ ( value ) => set( 'downloadLabel', value ) }
					/>
				</>
			) }
			{ door.type === 'link' && (
				<>
					<BaseControl label={ __( 'Link-URL', 'guideos-advent' ) }>
						<URLInput
							value={ door.linkUrl }
							onChange={ ( value ) => set( 'linkUrl', value ) }
						/>
					</BaseControl>
					<TextControl
						label={ __( 'Link-Label', 'guideos-advent' ) }
						value={ door.linkLabel }
						onChange={ ( value ) => set( 'linkLabel', value ) }
					/>
				</>
			) }
			{ door.type === 'video' && (
				<TextControl
					label={ __( 'YouTube-URL', 'guideos-advent' ) }
					value={ door.videoUrl }
					onChange={ ( value ) => set( 'videoUrl', value ) }
				/>
			) }
		</div>
	);
};

export default function Edit( { attributes, setAttributes } ) {
	const {
		headline,
		subline,
		backgroundColor,
		accentColor,
		textColor,
		instanceId,
		doors,
	} = attributes;

	const { merged: normalizedDoors, changed } = useMemo(
		() => mergeDoors( doors ),
		[ doors ]
	);

	useEffect( () => {
		if ( ! instanceId ) {
			setAttributes( {
				instanceId: `guideos-advent-${ Date.now().toString( 36 ) }-${
					Math.random().toString( 36 ).slice( 2, 8 )
				}`,
			} );
		}
	}, [ instanceId, setAttributes ] );

	useEffect( () => {
		if ( changed ) {
			setAttributes( { doors: normalizedDoors } );
		}
	}, [ changed, normalizedDoors, setAttributes ] );

	const doorList = doors && doors.length ? doors : normalizedDoors;

	const updateDoor = ( updatedDoor ) => {
		const nextDoors = doorList.map( ( door ) =>
			door.day === updatedDoor.day ? updatedDoor : door
		);
		setAttributes( { doors: nextDoors } );
	};

	const blockProps = useBlockProps( {
		className: 'guideos-advent-editor-preview',
		style: {
			'--guideos-advent-bg': backgroundColor,
			'--guideos-advent-accent': accentColor,
			'--guideos-advent-text': textColor,
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Allgemein', 'guideos-advent' ) } initialOpen>
					<TextControl
						label={ __( 'Überschrift', 'guideos-advent' ) }
						value={ headline }
						onChange={ ( value ) => setAttributes( { headline: value } ) }
					/>
					<TextControl
						label={ __( 'Subline', 'guideos-advent' ) }
						value={ subline }
						onChange={ ( value ) => setAttributes( { subline: value } ) }
					/>
					<Notice status="info" isDismissible={ false }>
						{ __(
							'Die ID wird automatisch vergeben, damit die Türen serverseitig erkannt werden.',
							'guideos-advent'
						) }
					</Notice>
					<TextControl
						label={ __( 'Instanz-ID', 'guideos-advent' ) }
						value={ instanceId }
						onChange={ ( value ) => setAttributes( { instanceId: value } ) }
						help={ __(
							'Nur ändern, wenn mehrere Kalender in einem Beitrag genutzt werden.',
							'guideos-advent'
						) }
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Farben', 'guideos-advent' ) }
					colorSettings={ [
						{
							label: __( 'Hintergrund', 'guideos-advent' ),
							onChange: ( value ) => setAttributes( { backgroundColor: value } ),
							value: backgroundColor,
						},
						{
							label: __( 'Akzent', 'guideos-advent' ),
							onChange: ( value ) => setAttributes( { accentColor: value } ),
							value: accentColor,
						},
						{
							label: __( 'Text', 'guideos-advent' ),
							onChange: ( value ) => setAttributes( { textColor: value } ),
							value: textColor,
						},
					] }
				/>
				<PanelBody title={ __( 'Tür-Inhalte', 'guideos-advent' ) } initialOpen={ false }>
					{ doorList.map( ( door ) => (
						<PanelBody
							key={ door.day }
							title={ sprintf( __( 'Tür %d', 'guideos-advent' ), door.day ) }
							initialOpen={ door.day === 1 }
						>
							<DoorFields
								door={ door }
								onChange={ ( updated ) => updateDoor( updated ) }
							/>
						</PanelBody>
					) ) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="h2"
					className="guideos-advent__headline"
					value={ headline }
					onChange={ ( value ) => setAttributes( { headline: value } ) }
					placeholder={ __( 'Überschrift hinzufügen…', 'guideos-advent' ) }
					allowedFormats={ [] }
				/>
				<RichText
					tagName="p"
					className="guideos-advent__subline"
					value={ subline }
					onChange={ ( value ) => setAttributes( { subline: value } ) }
					placeholder={ __( 'Subline hinzufügen…', 'guideos-advent' ) }
					allowedFormats={ [] }
				/>
				<div className="guideos-advent__grid" aria-live="polite">
					{ doorList.map( ( door ) => (
						<button
							type="button"
							key={ door.day }
							className="guideos-advent__door"
						>
							<span className="guideos-advent__door-number">{ door.day }</span>
							<span className="guideos-advent__door-title">
								{ door.title ||
									sprintf( __( 'Tür %d', 'guideos-advent' ), door.day ) }
							</span>
						</button>
					) ) }
				</div>
			</div>
		</>
	);
}
