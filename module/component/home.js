import Bookmarks from "./bookmarks.js";
import MainMenu from "./mainmenu.js";
import ReportErrors from "../reportErrors.js";

export default {
	mixins: [ReportErrors],
	components: {
		bookmarks: Bookmarks,
		mainmenu: MainMenu
	},
	template: /*html*/ `
		<main role=main>
			<bookmarks @error="Error($event)"></bookmarks>
			<mainmenu></mainmenu>
		</main>
	`
};
