/**
 * Immutable get/set for dot-paths into the nested settings object.
 */
export function getPath( obj, path ) {
	return path.split( '.' ).reduce( ( o, k ) => ( o == null ? undefined : o[ k ] ), obj );
}

export function setPath( obj, path, value ) {
	const keys = path.split( '.' );
	const clone = Array.isArray( obj ) ? obj.slice() : { ...obj };
	let cur = clone;
	for ( let i = 0; i < keys.length - 1; i++ ) {
		const k = keys[ i ];
		const child = cur[ k ];
		cur[ k ] = child == null ? {} : Array.isArray( child ) ? child.slice() : { ...child };
		cur = cur[ k ];
	}
	cur[ keys[ keys.length - 1 ] ] = value;
	return clone;
}
