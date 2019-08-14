import Views from "../views.js";
// TODO: hrefs to #viewname and hook up events

export default {
	data() {
		return {
			views: Views
		};
	},
	template: `
		<nav id=mainmenu>
			<a href="transactions.php">Transactions</a>
			<a href="spending.php">Spending</a>
			<a href="import.php">Import</a>
			<a href="saving.php">Saving</a>
			<a href="categories.php">Settings</a>
		</nav>
	`
};
