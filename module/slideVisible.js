import "../external/jquery-3.4.1.min.js";

export default {
	bind(el, bind) {
		bind.value
			? $(el).slideDown()
			: $(el).hide();
	},
	update(el, bind) {
		bind.value
			? $(el).slideDown()
			: $(el).slideUp();
	}
};
