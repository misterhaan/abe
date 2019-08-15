import Views from "../views.js";
// TODO: hrefs to #viewname

export default {
	data() {
		return {
			views: Views
		};
	},
	template: /*html*/ `
		<nav id=mainmenu>
			<a href="transactions.php">Transactions</a>
			<a href="spending.php">Spending</a>
			<a href="import.php">Import</a>
			<a href="#${Views.Saving.Name}">${Views.Saving.Title}</a>
			<a href="categories.php">Settings</a>
		</nav>
	`
};
