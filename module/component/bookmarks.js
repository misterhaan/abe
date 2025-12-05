import BookmarkApi from "../api/bookmark.js";

export default {
	data() {
		return {
			bookmarks: []
		};
	},
	async created() {
		this.bookmarks = await BookmarkApi.List();
	},
	methods: {
		async MoveDown(bookmark, index) {
			await BookmarkApi.MoveDown(bookmark.ID);
			this.bookmarks[index] = this.bookmarks.splice(index + 1, 1, bookmark)[0];
		},
		async MoveUp(bookmark, index) {
			await BookmarkApi.MoveUp(bookmark.ID);
			this.bookmarks[index] = this.bookmarks.splice(index - 1, 1, bookmark)[0];
		},
		async Delete(bookmark, index) {
			await BookmarkApi.Delete(bookmark.ID)
			this.bookmarks.splice(index, 1);
		}
	},
	template: /*html*/ `
		<nav id=bookmarks v-if=bookmarks.length>
			<div>
				<header>Bookmarks</header>
				<div class=bookmark v-for="(bookmark, index) in bookmarks">
					<a :class=bookmark.Page :href=bookmark.URL>{{bookmark.Name}}</a>
					<a class=down href="api/bookmark/moveDown" title="Move this bookmark down" @click.prevent="MoveDown(bookmark, index)" v-if="index < bookmarks.length - 1"></a>
					<a class=up href="api/bookmark/moveUp" title="Move this bookmark up" @click.prevent="MoveUp(bookmark, index)" v-if="index > 0"></a>
					<a class=delete href="api/bookmark/delete" title="Delete this bookmark" @click.prevent="Delete(bookmark, index)"></a>
				</div>
			</div>
		</nav>
	`
};
