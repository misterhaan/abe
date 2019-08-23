export default {
	UncategorizedName: "(uncategorized)",
	BlurDelay: 100,
	HighlightString(str, search) {
		var html = $("<div/>").text(str).html();
		return search ? html.replace(new RegExp("(" + EscapeRegExp(search) + ")", "ig"), "<em>$1</em>") : html;
	},
};

function EscapeRegExp(str) {
	return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
}
