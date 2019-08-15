export default {
	created() {
		// TODO:  implement here instead of redirecting
		window.location.replace("categories.php");
	},
	template: /*html*/ `
		<section id=categorygroups>
			<p class=loading>Redirecting to categories...</p>
		</section>
	`
};
