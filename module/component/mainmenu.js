import Views from "../views.js";

export default {
	data() {
		return {
			views: Views
		};
	},
	template: /*html*/ `
		<nav id=mainmenu>
			<a href="#${Views.Transactions.Name}">${Views.Transactions.Title}</a>
			<a href="#${Views.Spending.Name}">${Views.Spending.Title}</a>
			<a href="#${Views.Import.Name}">${Views.Import.Title}</a>
			<a href="#${Views.Saving.Name}">${Views.Saving.Title}</a>
			<a href="#${Views.Settings.Name}">${Views.Settings.Title}</a>
		</nav>
	`
};
