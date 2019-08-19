export default {
	methods: {
		FilterAmountKeys(event) {
			if(event.key.length == 1)
				switch(event.key) {
					case "-":
					case ".":
					case "0":
					case "1":
					case "2":
					case "3":
					case "4":
					case "5":
					case "6":
					case "7":
					case "8":
					case "9":
						return true;;
					default:
						event.preventDefault();
						return false;
				}
			return true;
		}
	}
};
