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
			<a href="#${Views.Import.Name}">${Views.Import.Title}</a>
			<a href="#${Views.Saving.Name}">${Views.Saving.Title}</a>
			<a href="#${Views.Settings.Name}">${Views.Settings.Title}</a>
		</nav>
	`
};
