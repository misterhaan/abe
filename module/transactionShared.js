export default {
	UncategorizedName: "(uncategorized)",
	HighlightString(str, search) {
		const div = document.createElement("div");
		div.textContent = str;
		const html = div.innerHTML;
		return search ? html.replace(new RegExp("(" + EscapeRegExp(search) + ")", "ig"), "<em>$1</em>") : html;
	},
};

function EscapeRegExp(str) {
	return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
}
