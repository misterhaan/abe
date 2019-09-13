import Views from "../views.js";
import ReportErrors from "../reportErrors.js";
import SlideVisible from "../slideVisible.js";
import BookmarkApi from "../api/bookmark.js";

export default {
	props: [
		"view",
		"subView",
		"params",
		"actions",
		"titlePrefix"
	],
	data() {
		return {
			showAddBookmark: false,
			bookmarkName: ""
		}
	},
	computed: {
		isHome() {
			return this.view == Views.Home;
		},
		bookmarkUrl() {
			return this.baseUrl + "!" + this.bookmarkParams;
		},
		baseUrl() {
			let url = this.view.Name;
			if(this.subView)
				url += "/" + this.subView;
			return url;
		},
		bookmarkParams() {
			if(this.view.BookmarkParams && this.params) {
				const params = [];
				for(const param in this.params)
					if(this.view.BookmarkParams.includes(param))
						params.push(encodeURIComponent(param) + "=" + encodeURIComponent(this.params[param]).replace(/%2C/gi, ","));
				return params.join("/");
			}
			return "";
		}
	},
	mixins: [
		ReportErrors
	],
	methods: {
		ToggleBookmark() {
			this.showAddBookmark = !this.showAddBookmark;
		},
		AddBookmark() {
			if(this.bookmarkName && this.bookmarkUrl) {
				BookmarkApi.Add(this.bookmarkUrl, this.bookmarkName).fail(this.Error);
				this.ToggleBookmark();
			} else
				this.Error(new Error("Bookmark title is required."));
		}
	},
	directives: {
		slideVisible: SlideVisible
	},
	template: /*html*/ `
		<header>
			<span class=back>
				<a v-if=!isHome href="." title="Go to main menu"><span>home</span></a>
			</span>
			<span class=actions>
				<a class=bookmark title="Add a bookmark to this location on the main menu" href=#showBookmark v-if=bookmarkParams @click.prevent=ToggleBookmark><span>Bookmark</span></a>
				<a v-for="action in actions" :class=action.class :href=action.url @click.prevent=action.action :title=action.tooltip><span>{{action.text}}</span></a>
			</span>
			<h1>{{titlePrefix}} {{view.Title}}</h1>
			<div id=newBookmark v-slideVisible=showAddBookmark @keydown.esc=ToggleBookmark>
				<label>
					<span>Title:</span>
					<input required maxlength=60 v-model=bookmarkName>
				</label>
				<label>
					<span>Page:</span>
					<input readonly maxlength=146 :value="'#' + bookmarkUrl">
				</label>
				<div class=calltoaction>
					<button id=saveBookmark @click.prevent=AddBookmark>Save</button><a href=#cancelBookmark title="Close the bookmark window" @click.prevent=ToggleBookmark>Cancel</a>
				</div>
			</div>
		</header>
`
}
