import Bookmarks from "./bookmarks.js";
import MainMenu from "./mainmenu.js";

export default {
	components: {
		bookmarks: Bookmarks,
		mainmenu: MainMenu
	},
	template: /*html*/ `
		<main role=main>
			<bookmarks></bookmarks>
			<mainmenu></mainmenu>
		</main>
	`
}
