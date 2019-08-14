import Views from "../views.js";

export default {
	props: [
		'isHome',
		'title'
	],
	created() {
		this.homeView = Views.Home;
	},
	template: `
		<header>
			<span class=back>
				<a v-if=!isHome id=toggleMenuPane href="#" title="Go to main menu" @click="$emit('change-view', homeView)"><span>home</span></a>
			</span>
			<span class=actions>
			</span>
			<h1>{{title}}</h1>
		</header>
`
}
