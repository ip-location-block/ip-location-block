import { syncUaDraft } from './uaDraft';
import { serializeUaList, parseUaList } from './uaRules';

const row = ( ua, action, qualType, qualValue = '', negate = false ) => ( {
	ua,
	action,
	qualType,
	qualValue,
	negate,
} );

describe( 'syncUaDraft', () => {
	it( 'keeps a freshly-added incomplete row after the editor writes', () => {
		// User clicks "Add rule": a blank row joins the draft.
		let draft = [ row( '', 'block', 'any' ) ];
		const written = serializeUaList( draft ); // '' (dropped)
		draft = syncUaDraft( draft, written, written );
		expect( draft ).toHaveLength( 1 );
		expect( draft[ 0 ].ua ).toBe( '' );
	} );

	it( 'keeps a value-required row while its value is being typed', () => {
		let draft = [ row( 'GPTBot', 'block', 'country', '' ) ];
		const written = serializeUaList( draft ); // '' (blank value dropped)
		draft = syncUaDraft( draft, written, written );
		expect( draft ).toHaveLength( 1 );
		expect( draft[ 0 ].qualType ).toBe( 'country' );
	} );

	it( 'rebuilds from storage when the string changes from outside', () => {
		const draft = [ row( '', 'block', 'any' ) ];
		const next = syncUaDraft( draft, 'GPTBot#*,Googlebot:HOST', '' );
		expect( next ).toEqual( parseUaList( 'GPTBot#*,Googlebot:HOST' ) );
	} );

	it( 'returns the same draft reference on a self-originated rewrite', () => {
		const draft = [ row( 'GPTBot', 'block', 'any' ) ];
		const written = serializeUaList( draft ); // 'GPTBot#*'
		expect( syncUaDraft( draft, written, written ) ).toBe( draft );
	} );
} );
