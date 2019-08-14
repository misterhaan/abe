import Bookmarks from "./bookmarks.js";
import MainMenu from "./mainmenu.js";

export default {
	methods: {
		Error(error) {
			this.$emit('error', error);
		}
	},
	components: {
		bookmarks: Bookmarks,
		mainmenu: MainMenu
	},
	template: `
		<main role=main>
			<bookmarks @error="Error($event)"></bookmarks>
			<mainmenu @change-view="$emit('change-view', $event)"></mainmenu>
		</main>
	`
};
