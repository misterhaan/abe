import Views from "../views.js";

export default {
	props: [
		'isHome',
		'title',
		'actions'
	],
	template: /*html*/ `
		<header>
			<span class=back>
				<a v-if=!isHome id=toggleMenuPane href="." title="Go to main menu"><span>home</span></a>
			</span>
			<span class=actions>
				<a v-for="action in actions" :class=action.class :href=action.url @click.prevent=action.action :title=action.tooltip><span>{{action.text}}</span></a>
			</span>
			<h1>{{title}}</h1>
		</header>
`
}
