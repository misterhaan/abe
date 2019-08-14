import BookmarkApi from "../api/bookmark.js";

export default {
	data() {
		return {
			bookmarks: []
		};
	},
	created() {
		this.Error = error => {
			this.$emit('error', error);
		};

		BookmarkApi.List().done(bookmarks => {
			this.bookmarks = bookmarks;
		}).fail(this.Error);
	},
	methods: {
		MoveDown(bookmark, index) {
			BookmarkApi.MoveDown(bookmark.id).done(() => {
				this.bookmarks[index] = this.bookmarks.splice(index + 1, 1, bookmark)[0];
			}).fail(this.Error);
		},
		MoveUp(bookmark, index) {
			BookmarkApi.MoveUp(bookmark.id).done(() => {
				this.bookmarks[index] = this.bookmarks.splice(index - 1, 1, bookmark)[0];
			}).fail(this.Error);
		},
		Delete(bookmark, index) {
			BookmarkApi.Delete(bookmark.id).done(() => {
				this.bookmarks.splice(index, 1);
			}).fail(this.Error);
		}
	},
	template: /*html*/ `
		<nav id=bookmarks v-if=bookmarks.length>
			<div>
				<header>Bookmarks</header>
				<div class=bookmark v-for="(bookmark, index) in bookmarks">
					<a :class=bookmark.page :href=bookmark.url>{{bookmark.name}}</a>
					<a class=down href="api/bookmark/moveDown" title="Move this bookmark down" @click.prevent="MoveDown(bookmark, index)" v-if="index < bookmarks.length - 1"></a>
					<a class=up href="api/bookmark/moveUp" title="Move this bookmark up" @click.prevent="MoveUp(bookmark, index)" v-if="index > 0"></a>
					<a class=delete href="api/bookmark/delete" title="Delete this bookmark" @click.prevent="Delete(bookmark, index)"></a>
				</div>
			</div>
		</nav>
	`
};
