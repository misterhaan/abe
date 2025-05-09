export default {
	created(el, bind) {
		bind.value
			? $(el).slideDown()
			: $(el).hide();
	},
	updated(el, bind) {
		bind.value
			? $(el).slideDown()
			: $(el).slideUp();
	}
};
