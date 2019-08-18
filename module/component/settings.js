import AllViews from "../views.js";
import ReportErrors from "../reportErrors.js";
import Accounts from "./accounts.js";
import Categories from "./categories.js";

const Views = AllViews.Settings.SubViews;

export default {
	props: [
		'view'
	],
	mixins: [ReportErrors],
	components: {
		accounts: Accounts,
		categories: Categories
	},
	template: /*html*/ `
		<main role=main>
			<div class=tabbed>
				<nav class=tabs>
					<span class=accounts v-if="view.Name == '${Views.Accounts.Name}'">${Views.Accounts.Title}</span>
					<a class=accounts v-if="view.Name != '${Views.Accounts.Name}'" href=#settings/accounts>${Views.Accounts.Title}</a>
					<span class=categories v-if="view.Name == '${Views.Categories.Name}'">${Views.Categories.Title}</span>
					<a class=categories v-if="view.Name != '${Views.Categories.Name}'" href="#settings/categories">${Views.Categories.Title}</a>
				</nav>
				<component :is=view.Name @error=Error($event) @add-action="$emit('add-action', $event)" ></component>
			</div>
		</main>
	`
};
