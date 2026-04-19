/**
 * Rich Text Editor Component - WYSIWYG
 */
import { useRef, useState, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

interface RichTextEditorProps {
	value: string;
	onChange: ( value: string ) => void;
	placeholder?: string;
}

interface ToolbarButton {
	command: string;
	icon: string;
	title: string;
	arg?: string;
	divider?: false;
}

interface ToolbarDivider {
	divider: true;
}

type ToolbarItem = ToolbarButton | ToolbarDivider;

const TOOLBAR_BUTTONS: ToolbarItem[] = [
	{ command: 'bold', icon: 'B', title: __( 'Fett (Ctrl+B)', 'bs-custom-mail' ) },
	{ command: 'italic', icon: 'I', title: __( 'Kursiv (Ctrl+I)', 'bs-custom-mail' ) },
	{ command: 'underline', icon: 'U', title: __( 'Unterstrichen (Ctrl+U)', 'bs-custom-mail' ) },
	{ command: 'strikeThrough', icon: 'S', title: __( 'Durchgestrichen', 'bs-custom-mail' ) },
	{ divider: true },
	{ command: 'formatBlock', arg: 'H2', icon: 'H2', title: __( 'Überschrift 2', 'bs-custom-mail' ) },
	{ command: 'formatBlock', arg: 'H3', icon: 'H3', title: __( 'Überschrift 3', 'bs-custom-mail' ) },
	{ command: 'formatBlock', arg: 'P', icon: '¶', title: __( 'Normaler Text', 'bs-custom-mail' ) },
	{ divider: true },
	{ command: 'insertUnorderedList', icon: '•', title: __( 'Aufzählung', 'bs-custom-mail' ) },
	{ command: 'insertOrderedList', icon: '1.', title: __( 'Nummerierung', 'bs-custom-mail' ) },
	{ divider: true },
	{ command: 'justifyLeft', icon: '⬅', title: __( 'Linksbündig', 'bs-custom-mail' ) },
	{ command: 'justifyCenter', icon: '⬌', title: __( 'Zentriert', 'bs-custom-mail' ) },
	{ command: 'justifyRight', icon: '➡', title: __( 'Rechtsbündig', 'bs-custom-mail' ) },
];

export function RichTextEditor( { value, onChange, placeholder }: RichTextEditorProps ) {
	const editorRef = useRef<HTMLDivElement | null>( null );
	const [ activeCommands, setActiveCommands ] = useState<string[]>( [] );

	const handleInput = useCallback( () => {
		if ( editorRef.current ) {
			onChange( editorRef.current.innerHTML );
		}
	}, [ onChange ] );

	const execCommand = useCallback( ( command: string, arg?: string ) => {
		document.execCommand( command, false, arg );
		handleInput();
		
		// Update active commands
		if ( activeCommands.includes( command ) ) {
			setActiveCommands( activeCommands.filter( ( c ) => c !== command ) );
		} else {
			setActiveCommands( [ ...activeCommands, command ] );
		}
		
		// Focus back to editor
		editorRef.current?.focus();
	}, [ activeCommands, handleInput ] );

	// Sync external value changes into the editor when it is not focused.
	// This handles the case where the template loads asynchronously after
	// the editor has already mounted (e.g. edit mode).
	useEffect( () => {
		const el = editorRef.current;
		if ( el && document.activeElement !== el && el.innerHTML !== value ) {
			el.innerHTML = value || '';
		}
	}, [ value ] );

	// Set initial content on first mount
	const setInitialContent = useCallback( ( el: HTMLDivElement | null ) => {
		if ( el && ! el.innerHTML && value ) {
			el.innerHTML = value;
		}
		editorRef.current = el;
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<div className="bs-rich-editor-wrapper">
			<div className="bs-editor-toolbar">
				{ TOOLBAR_BUTTONS.map( ( button, index ) => (
					'divider' in button ? (
						<span key={ index } className="bs-toolbar-divider" />
					) : (
						<button
							key={ `${ button.command }-${ button.arg || '' }` }
							title={ button.title }
							className={ activeCommands.includes( button.command ) ? 'is-active' : '' }
							onClick={ () => execCommand( button.command, button.arg ) }
							type="button"
						>
							{ button.icon }
						</button>
					)
				) ) }
			</div>
			<div
				ref={ setInitialContent }
				className="bs-rich-editor"
				contentEditable
				onInput={ handleInput }
				data-placeholder={ placeholder }
				style={ {
					padding: '16px',
					minHeight: '300px',
					outline: 'none',
					lineHeight: '1.6',
				} }
			/>
		</div>
	);
}

// Emoji picker component
const EMOJIS = [
	'📧', '📍', '📋', '📚', '🧭', '🎥', '🩺', '🚤', '🎁', '⚓', '📞', '✉️',
	'✅', '❌', '⚠️', '👉', '👍', '👋', '🎯', '🔔', '📅', '💳', '🎫', '💡',
	'📎', '📄', '📊', '🗓️', '⏰', '📱', '💻', '🌐', '📮', '🏠', '🚩', '📝',
];

interface EmojiPickerProps {
	onSelect: ( emoji: string ) => void;
}

export function EmojiPicker( { onSelect }: EmojiPickerProps ) {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<div className="bs-emoji-picker" style={ { position: 'relative' } }>
			<Button
				variant="secondary"
				onClick={ () => setIsOpen( ! isOpen ) }
				title={ __( 'Emoji einfügen', 'bs-custom-mail' ) }
			>
				😊
			</Button>
			{ isOpen && (
				<div 
					className="bs-emoji-popup"
					style={ {
						position: 'absolute',
						top: '100%',
						left: 0,
						marginTop: '8px',
						background: 'white',
						border: '1px solid var(--bs-color-border)',
						borderRadius: '12px',
						padding: '12px',
						boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1)',
						zIndex: 100,
						display: 'grid',
						gridTemplateColumns: 'repeat(8, 1fr)',
						gap: '8px',
					} }
				>
					{ EMOJIS.map( ( emoji ) => (
						<button
							key={ emoji }
							onClick={ () => {
								onSelect( emoji );
								setIsOpen( false );
							} }
							style={ {
								padding: '8px',
								border: 'none',
								background: 'transparent',
								cursor: 'pointer',
								fontSize: '20px',
								borderRadius: '6px',
							} }
							type="button"
						>
							{ emoji }
						</button>
					) ) }
				</div>
			) }
		</div>
	);
}
