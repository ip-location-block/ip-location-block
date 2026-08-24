import { syncDraft } from './preciseDraft';
import { serializeRules } from './rules';

/**
 * The Simple-mode editor serializes `{ countries, precise: draft }` into the
 * stored string (via serializeRules — the same call SimpleBlocking makes) and
 * records it as "last written"; on the next render it calls
 * `syncDraft( draft, stored, lastWritten )`. These tests simulate that loop.
 */

// Mirror the component's write: serialize the draft's non-empty subset.
const write = ( countries, draft ) =>
	serializeRules( { countries, precise: draft } );

describe( 'syncDraft', () => {
	it( 'keeps a freshly-added empty row after the editor writes', () => {
		// User clicks "Add a precise rule": a blank row joins the draft.
		let draft = [ { country: 'US', level: 'State', value: '' } ];
		// The editor serializes (blank dropped) and records what it wrote.
		const written = write( [], draft ); // ''
		// Next render: stored === written, so the blank row must survive.
		draft = syncDraft( draft, written, written );
		expect( draft ).toHaveLength( 1 );
		expect( draft[ 0 ] ).toEqual( {
			country: 'US',
			level: 'State',
			value: '',
		} );
	} );

	it( 'does NOT delete a row when its value is typed then cleared', () => {
		// Row has a value...
		let draft = [ { country: 'US', level: 'State', value: 'Washington' } ];
		let written = write( [], draft ); // 'US:State:Washington'
		draft = syncDraft( draft, written, written );
		expect( draft ).toHaveLength( 1 );

		// ...then the user clears the Name field mid-edit.
		draft = [ { ...draft[ 0 ], value: '' } ];
		written = write( [], draft ); // '' (blank dropped)
		draft = syncDraft( draft, written, written );
		// The row is still there to keep typing into — not deleted.
		expect( draft ).toHaveLength( 1 );
		expect( draft[ 0 ].value ).toBe( '' );
	} );

	it( 'rebuilds the draft when settings change from outside (reset/preset/reload)', () => {
		const draft = [ { country: 'US', level: 'State', value: '' } ];
		const lastWritten = ''; // editor last wrote an empty precise set
		// An external save/reset lands a different stored string.
		const next = syncDraft( draft, 'CN,US:State:California', lastWritten );
		expect( next ).toEqual( [
			{ country: 'US', level: 'State', value: 'California' },
		] );
	} );

	it( 'still drops empties from the serialized string on save', () => {
		const draft = [
			{ country: 'US', level: 'State', value: 'California' },
			{ country: 'US', level: 'State', value: '' },
		];
		expect( write( [ 'US' ], draft ) ).toBe( 'US,US:State:California' );
	} );

	it( 'keeps the draft when a sibling control (countries) rewrites the same string', () => {
		// Draft holds an in-progress blank row; the user edits the country list,
		// which the editor also serializes and records as "last written".
		const draft = [ { country: 'US', level: 'State', value: '' } ];
		const written = write( [ 'CN', 'RU' ], draft ); // 'CN,RU'
		const next = syncDraft( draft, written, written );
		expect( next ).toBe( draft ); // same reference — untouched
	} );
} );
