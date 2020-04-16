export default {
	methods: {
		Error(error) {
			this.$emit("error", error);
		}
	}
}
