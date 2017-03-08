/**
 * Parse the location hash into a parameters object if it's prefixed with #!.
 * Parameters are separated by a forward slash, and names and values are
 * separated by an equals sign.  Values may contain an equals sign but not a
 * forward slash.
 * @returns object Hash object.
 */
function ParseHash() {
	var info = {};
	var hash = window.location.hash;
	if(hash.substr(0, 2) == "#!") {
		hash = hash.substr(2).split("/");
		for(var v = 0; v < hash.length; v++) {
			var pair = hash[v].split("=");
			if(pair.length > 1)
				info[pair.shift()] = pair.join("=");
		}
	}
	return info;
}
